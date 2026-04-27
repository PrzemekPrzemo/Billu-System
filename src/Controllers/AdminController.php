<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Language;
use App\Core\Session;
use App\Models\Client;
use App\Models\InvoiceBatch;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\User;
use App\Models\Office;
use App\Models\AuditLog;
use App\Services\DemoSeederService;
use App\Models\Report;
use App\Services\ImportService;
use App\Services\ExportService;
use App\Services\PdfService;
use App\Services\MailService;
use App\Services\BulkImportService;
use App\Services\GusApiService;
use App\Services\CeidgApiService;
use App\Services\CompanyLookupService;
use App\Services\KsefApiService;
use App\Services\KsefCertificateService;
use App\Services\KsefLogger;
use App\Services\JpkV3Service;
use App\Services\SecurityScanService;
use App\Models\ClientCostCenter;
use App\Models\KsefConfig;
use App\Models\KsefOperationLog;
use App\Models\Notification;
use App\Models\ClientSmtpConfig;
use App\Models\EmailTemplate;
use App\Models\IssuedInvoice;
use App\Core\Pagination;
use App\Models\Module;

class AdminController extends Controller
{
    public function __construct()
    {
        Auth::requireAdmin();
    }

    // ── Dashboard ──────────────────────────────────

    public function dashboard(): void
    {
        $clients = Client::findAll(false, true);
        $offices = Office::findAll(false, true);
        $batches = InvoiceBatch::findAll(true);
        $recentLogs = AuditLog::getRecent(20);
        $monthlyStats = Invoice::getMonthlyStats(6, true);
        $statusTotals = Invoice::getStatusTotals(true);
        $invoicesThisMonth = Invoice::countThisMonth(true);

        $this->render('admin/dashboard', [
            'clients'           => $clients,
            'offices'           => $offices,
            'batches'           => $batches,
            'recentLogs'        => $recentLogs,
            'clientCount'       => count($clients),
            'officeCount'       => count($offices),
            'monthlyStats'      => $monthlyStats,
            'statusTotals'      => $statusTotals,
            'invoicesThisMonth' => $invoicesThisMonth,
        ]);
    }

    // ── Offices ────────────────────────────────────

    public function offices(): void
    {
        $offices = Office::findAll();
        $this->render('admin/offices', ['offices' => $offices]);
    }

    public function officeCreateForm(): void
    {
        $this->render('admin/office_form', ['office' => null]);
    }

    public function officeCreate(): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/admin/offices'); return; }

        $nip = preg_replace('/[^0-9]/', '', $_POST['nip'] ?? '');
        if (empty($nip) || strlen($nip) !== 10) {
            Session::flash('error', 'invalid_nip');
            $this->redirect('/admin/offices/create');
            return;
        }
        if (Office::findByNip($nip)) {
            Session::flash('error', 'nip_exists');
            $this->redirect('/admin/offices/create');
            return;
        }

        $email = trim($_POST['email'] ?? '');
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'invalid_email');
            $this->redirect('/admin/offices/create');
            return;
        }
        if (Office::findByEmail($email)) {
            Session::flash('error', 'email_exists');
            $this->redirect('/admin/offices/create');
            return;
        }

        $password = $_POST['password'] ?? '';
        $pwdErrors = Auth::validatePasswordStrength($password);
        if (!empty($pwdErrors)) {
            Session::flash('error', $pwdErrors[0]);
            $this->redirect('/admin/offices/create');
            return;
        }

        $newOfficeId = Office::create([
            'nip'                 => $nip,
            'name'                => $this->sanitize($_POST['name'] ?? ''),
            'address'             => $this->sanitize($_POST['address'] ?? ''),
            'email'               => $email,
            'phone'               => $this->sanitize($_POST['phone'] ?? ''),
            'representative_name' => $this->sanitize($_POST['representative_name'] ?? ''),
            'password_hash'       => Auth::hashPassword($password),
        ]);

        // Initialize all modules as enabled for the new office
        Module::initOfficeModules($newOfficeId, Auth::currentUserId());

        AuditLog::log('admin', Auth::currentUserId(), 'office_created', "NIP: {$nip}", 'office', $newOfficeId);
        Session::flash('success', 'office_created');
        $this->redirect('/admin/offices');
    }

    public function officeEditForm(string $id): void
    {
        $office = Office::findById((int) $id);
        if (!$office) { $this->redirect('/admin/offices'); return; }
        $smtpConfig = \App\Models\OfficeSmtpConfig::findByOfficeId((int) $id);
        $modules = Module::getOfficeModuleMatrix((int) $id);
        $this->render('admin/office_form', [
            'office' => $office,
            'smtpConfig' => $smtpConfig,
            'modules' => $modules,
        ]);
    }

    public function officeUpdate(string $id): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/admin/offices'); return; }

        $data = [
            'name'                      => $this->sanitize($_POST['name'] ?? ''),
            'address'                   => $this->sanitize($_POST['address'] ?? ''),
            'email'                     => $this->sanitize($_POST['email'] ?? ''),
            'phone'                     => $this->sanitize($_POST['phone'] ?? ''),
            'representative_name'       => $this->sanitize($_POST['representative_name'] ?? ''),
            'is_active'                 => isset($_POST['is_active']) ? 1 : 0,
            'language'                  => $_POST['language'] ?? 'pl',
            'verification_deadline_day' => !empty($_POST['verification_deadline_day']) ? (int)$_POST['verification_deadline_day'] : null,
            'auto_accept_on_deadline'   => isset($_POST['auto_accept_override']) ? ((int)$_POST['auto_accept_on_deadline']) : null,
            'notification_days_before'  => !empty($_POST['notification_days_before']) ? (int)$_POST['notification_days_before'] : null,
            'max_employees'             => !empty($_POST['max_employees']) ? (int)$_POST['max_employees'] : null,
            'max_clients'               => !empty($_POST['max_clients']) ? (int)$_POST['max_clients'] : null,
            'mobile_app_enabled'        => !empty($_POST['mobile_app_enabled']) ? 1 : 0,
        ];

        $password = $_POST['password'] ?? '';
        if (!empty($password)) {
            $pwdErrors = Auth::validatePasswordStrength($password);
            if (!empty($pwdErrors)) {
                Session::flash('error', $pwdErrors[0]);
                $this->redirect("/admin/offices/{$id}/edit");
                return;
            }
            $data['password_hash'] = Auth::hashPassword($password);
            $data['password_changed_at'] = date('Y-m-d H:i:s');
        }

        // Check if office is being deactivated — cascade to clients
        $oldOffice = Office::findById((int) $id);
        $deactivatingOffice = ($oldOffice && $oldOffice['is_active'] && $data['is_active'] === 0);

        Office::update((int) $id, $data, Office::adminAllowedFields());

        if ($deactivatingOffice) {
            $deactivatedCount = Office::deactivateClients((int) $id);
            AuditLog::log('admin', Auth::currentUserId(), 'office_deactivated',
                "Office ID: {$id} deactivated, {$deactivatedCount} client(s) auto-deactivated", 'office', (int)$id);
            Session::flash('success', 'office_deactivated');
            Session::flash('info_extra', Language::get('office_deactivated_clients_count', ['count' => $deactivatedCount]));
        } else {
            AuditLog::log('admin', Auth::currentUserId(), 'office_updated', "Office ID: {$id}", 'office', (int)$id);
            Session::flash('success', 'office_updated');
        }

        // Inline modules panel — only apply when the form actually submitted them
        // (the dedicated /admin/offices/{id}/modules page also still works).
        if (array_key_exists('modules_submitted', $_POST)) {
            $auto = $this->applyOfficeModules((int) $id, $_POST['modules'] ?? []);
            if ($auto) {
                AuditLog::log('admin', Auth::currentUserId(), 'office_modules_updated',
                    'Modules updated inline for office ID ' . $id . ' | Auto-cascade: ' . implode(', ', array_unique($auto)),
                    'office', (int)$id);
            }
        }

        // Save per-office SMTP config
        $smtpEnabled = !empty($_POST['smtp_enabled']);
        if ($smtpEnabled) {
            $smtpData = [
                'is_enabled'      => 1,
                'smtp_host'       => $this->sanitize($_POST['smtp_host'] ?? ''),
                'smtp_port'       => (int) ($_POST['smtp_port'] ?? 587),
                'smtp_encryption' => in_array($_POST['smtp_encryption'] ?? '', ['none', 'tls', 'ssl']) ? $_POST['smtp_encryption'] : 'tls',
                'smtp_user'       => $this->sanitize($_POST['smtp_user'] ?? ''),
                'from_email'      => $this->sanitize($_POST['smtp_from_email'] ?? ''),
                'from_name'       => $this->sanitize($_POST['smtp_from_name'] ?? ''),
            ];
            $smtpPass = $_POST['smtp_pass'] ?? '';
            if (!empty($smtpPass)) {
                $smtpData['smtp_pass_encrypted'] = base64_encode($smtpPass);
            }
            \App\Models\OfficeSmtpConfig::upsert((int) $id, $smtpData);
        } else {
            $existing = \App\Models\OfficeSmtpConfig::findByOfficeId((int) $id);
            if ($existing) {
                \App\Models\OfficeSmtpConfig::upsert((int) $id, ['is_enabled' => 0]);
            }
        }

        $this->redirect('/admin/offices');
    }

    public function officeToggleActive(string $id): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/admin/offices'); return; }

        $office = Office::findById((int) $id);
        if (!$office) { $this->redirect('/admin/offices'); return; }

        $newStatus = $office['is_active'] ? 0 : 1;
        Office::update((int) $id, ['is_active' => $newStatus]);

        if ($newStatus === 0) {
            $deactivatedCount = Office::deactivateClients((int) $id);
            AuditLog::log('admin', Auth::currentUserId(), 'office_deactivated',
                "Office ID: {$id} deactivated, {$deactivatedCount} client(s) auto-deactivated", 'office', (int)$id);
            Session::flash('success', 'office_deactivated');
        } else {
            AuditLog::log('admin', Auth::currentUserId(), 'office_activated',
                "Office ID: {$id} activated", 'office', (int)$id);
            Session::flash('success', 'office_activated');
        }

        $this->redirect('/admin/offices');
    }

    public function officeResetPassword(string $id): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/admin/offices'); return; }

        $office = Office::findById((int) $id);
        if (!$office) { $this->redirect('/admin/offices'); return; }

        $newPassword = $_POST['new_password'] ?? '';
        $pwdErrors = Auth::validatePasswordStrength($newPassword);
        if (!empty($pwdErrors)) {
            Session::flash('error', $pwdErrors[0]);
            $this->redirect("/admin/offices/{$id}/edit");
            return;
        }

        Office::updatePassword((int) $id, Auth::hashPassword($newPassword));

        AuditLog::log('admin', Auth::currentUserId(), 'office_password_reset',
            "Password reset for office ID: {$id} ({$office['name']})", 'office', (int)$id);
        Session::flash('success', 'password_reset_by_admin');
        $this->redirect("/admin/offices/{$id}/edit");
    }

    // ── Office Modules ─────────────────────────────

    public function officeModules(string $id): void
    {
        $office = Office::findById((int) $id);
        if (!$office) { $this->redirect('/admin/offices'); return; }

        $modules = Module::getOfficeModuleMatrix((int) $id);
        $this->render('admin/office_modules', [
            'office' => $office,
            'modules' => $modules,
        ]);
    }

    public function officeModulesUpdate(string $id): void
    {
        if (!$this->validateCsrf()) { $this->redirect("/admin/offices/{$id}/modules"); return; }

        $office = Office::findById((int) $id);
        if (!$office) { $this->redirect('/admin/offices'); return; }

        $autoActions = $this->applyOfficeModules((int) $id, $_POST['modules'] ?? []);

        $logMsg = 'Modules updated for office: ' . $office['name'] . ' (ID: ' . $id . ')';
        if ($autoActions) {
            $logMsg .= ' | Auto-cascade: ' . implode(', ', array_unique($autoActions));
        }
        AuditLog::log('admin', Auth::currentUserId(), 'office_modules_updated', $logMsg, 'office', (int) $id);
        Session::flash('success', 'modules_saved');
        $this->redirect("/admin/offices/{$id}/modules");
    }

    /**
     * Apply a list of enabled module slugs to an office. Reused by the dedicated
     * modules page AND by the inline modules panel in the office edit form.
     * Returns array of human-readable cascade actions ("+slug" / "-slug") for
     * audit logging.
     *
     * @param array<int,string> $enabledSlugs
     * @return array<int,string>
     */
    private function applyOfficeModules(int $officeId, array $enabledSlugs): array
    {
        $allModules = Module::findAll(true);
        $autoActions = [];

        foreach ($allModules as $mod) {
            $shouldEnable = in_array($mod['slug'], $enabledSlugs, true);
            if (!empty($mod['is_system'])) {
                $shouldEnable = true;
            }
            $currentlyEnabled = Module::isEnabledForOffice($officeId, $mod['slug']);

            if ($shouldEnable && !$currentlyEnabled) {
                $auto = Module::enableWithDependencies($officeId, (int) $mod['id'], Auth::currentUserId());
                if ($auto) {
                    $autoActions = array_merge($autoActions, array_map(fn($s) => "+{$s}", $auto));
                }
            } elseif (!$shouldEnable && $currentlyEnabled) {
                $auto = Module::disableWithDependents($officeId, (int) $mod['id'], Auth::currentUserId());
                if ($auto) {
                    $autoActions = array_merge($autoActions, array_map(fn($s) => "-{$s}", $auto));
                }
            } else {
                Module::setOfficeModule($officeId, (int) $mod['id'], $shouldEnable, Auth::currentUserId());
            }
        }
        return $autoActions;
    }

    // ── Client Module Management ─────────────────────

    public function clientModules(string $id): void
    {
        $client = Client::findById((int) $id);
        if (!$client) { $this->redirect('/admin/clients'); return; }

        $officeId = (int) ($client['office_id'] ?? 0);
        $modules = Module::getClientModuleMatrix((int) $id, $officeId);
        $this->render('admin/client_modules', [
            'client' => $client,
            'modules' => $modules,
        ]);
    }

    public function clientModulesUpdate(string $id): void
    {
        if (!$this->validateCsrf()) { $this->redirect("/admin/clients/{$id}/modules"); return; }

        $client = Client::findById((int) $id);
        if (!$client) { $this->redirect('/admin/clients'); return; }

        $enabledSlugs = $_POST['modules'] ?? [];
        $allModules = Module::findAll(true);

        foreach ($allModules as $mod) {
            $enabled = in_array($mod['slug'], $enabledSlugs, true);
            if (!empty($mod['is_system'])) {
                $enabled = true;
            }
            Module::setClientModule((int) $id, (int) $mod['id'], $enabled, Auth::currentUserId());
        }

        AuditLog::log('admin', Auth::currentUserId(), 'client_modules_updated',
            'Modules updated for client: ' . ($client['company_name'] ?? $client['name'] ?? '') . ' (ID: ' . $id . ')', 'client', (int) $id);
        Session::flash('success', 'modules_saved');
        $this->redirect("/admin/clients/{$id}/modules");
    }

    // ── Module Bundles Management ───────────────────────

    public function moduleBundles(): void
    {
        $bundles = Module::getBundles(false);
        $offices = Office::findAll();
        $dependencyMap = Module::getDependencyMap();

        $this->render('admin/module_bundles', [
            'bundles' => $bundles,
            'offices' => $offices,
            'dependencyMap' => $dependencyMap,
        ]);
    }

    public function moduleBundleAssign(): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/admin/module-bundles'); return; }

        $officeId = (int)($_POST['office_id'] ?? 0);
        $bundleId = (int)($_POST['bundle_id'] ?? 0);

        $office = Office::findById($officeId);
        $bundle = Module::findBundleById($bundleId);
        if (!$office || !$bundle) {
            Session::flash('error', 'invalid_bundle_or_office');
            $this->redirect('/admin/module-bundles');
            return;
        }

        Module::applyBundle($officeId, $bundleId, Auth::currentUserId());

        AuditLog::log('admin', Auth::currentUserId(), 'bundle_assigned',
            'Bundle "' . $bundle['name'] . '" assigned to office: ' . $office['name'] . ' (ID: ' . $officeId . ')',
            'office', $officeId);

        Session::flash('success', 'bundle_assigned');
        $this->redirect('/admin/module-bundles');
    }

    public function testOfficeSmtp(string $id): void
    {
        if (!$this->validateCsrf()) { $this->json(['error' => 'CSRF'], 403); return; }

        $host = $this->sanitize($_POST['smtp_host'] ?? '');
        $port = (int) ($_POST['smtp_port'] ?? 587);
        $encryption = $_POST['smtp_encryption'] ?? 'tls';
        $user = $this->sanitize($_POST['smtp_user'] ?? '');
        $pass = $_POST['smtp_pass'] ?? '';
        $fromEmail = $this->sanitize($_POST['smtp_from_email'] ?? '');
        $fromName = $this->sanitize($_POST['smtp_from_name'] ?? '');

        if (empty($host) || empty($fromEmail)) {
            $this->json(['success' => false, 'error' => 'Podaj host i email nadawcy']);
            return;
        }

        // If no password provided, try existing config
        if (empty($pass)) {
            $existing = \App\Models\OfficeSmtpConfig::findByOfficeId((int) $id);
            if ($existing && !empty($existing['smtp_pass_encrypted'])) {
                $pass = base64_decode($existing['smtp_pass_encrypted']);
            }
        }

        try {
            $result = \App\Services\MailService::testSmtpConnection($host, $port, $encryption, $user, $pass, $fromEmail, $fromName);
            $this->json($result);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    // ── Clients ────────────────────────────────────

    public function clients(): void
    {
        $filters = [
            'search' => trim($_GET['search'] ?? ''),
            'office_id' => $_GET['office_id'] ?? '',
            'status' => $_GET['status'] ?? '',
        ];
        $sort = $_GET['sort'] ?? 'company_name';
        if (!in_array($sort, ['company_name', 'nip', 'office_name', 'last_login_at', 'is_active', 'created_at'], true)) $sort = 'company_name';
        $dir = $_GET['dir'] ?? 'asc';
        if (!in_array($dir, ['asc', 'desc'], true)) $dir = 'asc';
        $groupMode = ($_GET['group'] ?? '') === 'office';

        $total = Client::countFiltered($filters);
        $pagination = Pagination::fromRequest($total, 25);
        $clients = Client::findAllFiltered($filters, $sort, $dir, $pagination->offset, $pagination->perPage);
        $offices = Office::findAll(true);

        $grouped = [];
        if ($groupMode) {
            foreach ($clients as $c) {
                $key = $c['office_name'] ?? '__none__';
                $grouped[$key][] = $c;
            }
        }

        $this->render('admin/clients', [
            'clients' => $clients,
            'offices' => $offices,
            'pagination' => $pagination,
            'filters' => $filters,
            'sort' => $sort,
            'dir' => $dir,
            'groupMode' => $groupMode,
            'grouped' => $grouped,
        ]);
    }

    public function clientCreateForm(): void
    {
        $offices = Office::findAll(true);
        $this->render('admin/client_form', ['client' => null, 'offices' => $offices, 'costCenters' => []]);
    }

    public function clientCreate(): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/admin/clients'); return; }

        $nip = preg_replace('/[^0-9]/', '', $_POST['nip'] ?? '');
        if (empty($nip) || strlen($nip) !== 10) {
            Session::flash('error', 'invalid_nip');
            $this->redirect('/admin/clients/create');
            return;
        }
        if (Client::findByNip($nip)) {
            Session::flash('error', 'nip_exists');
            $this->redirect('/admin/clients/create');
            return;
        }

        $password = $_POST['password'] ?? '';
        $pwdErrors = Auth::validatePasswordStrength($password);
        if (!empty($pwdErrors)) {
            Session::flash('error', $pwdErrors[0]);
            $this->redirect('/admin/clients/create');
            return;
        }

        $officeId = !empty($_POST['office_id']) ? (int) $_POST['office_id'] : null;

        // Check max_clients limit
        if ($officeId) {
            $office = Office::findById($officeId);
            if ($office && !empty($office['max_clients'])) {
                $currentCount = Client::countByOffice($officeId);
                if ($currentCount >= (int) $office['max_clients']) {
                    Session::flash('error', 'Osiągnięto limit klientów dla tego biura (' . $office['max_clients'] . ')');
                    $this->redirect('/admin/clients/create');
                    return;
                }
            }
        }

        $newClientId = Client::create([
            'nip'                 => $nip,
            'company_name'        => $this->sanitize($_POST['company_name'] ?? ''),
            'representative_name' => $this->sanitize($_POST['representative_name'] ?? ''),
            'address'             => $this->sanitize($_POST['address'] ?? ''),
            'email'               => $this->sanitize($_POST['email'] ?? ''),
            'report_email'        => $this->sanitize($_POST['report_email'] ?? ''),
            'phone'               => $this->sanitize($_POST['phone'] ?? ''),
            'regon'               => $this->sanitize($_POST['regon'] ?? ''),
            'password_hash'       => Auth::hashPassword($password),
            'office_id'           => $officeId,
            'ksef_enabled'        => !empty($_POST['ksef_enabled']) ? 1 : 0,
        ]);

        // Handle cost centers
        if (!empty($_POST['has_cost_centers'])) {
            Client::update($newClientId, ['has_cost_centers' => 1]);
            $ccNames = $_POST['cost_center_names'] ?? [];
            ClientCostCenter::syncForClient($newClientId, $ccNames);
        }

        // Handle KSeF config v3.0 (skip if table not yet migrated)
        try {
            $ksefEnv = $_POST['ksef_environment'] ?? 'test';
            if (!in_array($ksefEnv, ['test', 'demo', 'production'])) $ksefEnv = 'test';

            if (!empty($_POST['ksef_enabled'])) {
                $configData = [
                    'auth_method' => 'none',
                    'is_active' => 1,
                    'ksef_environment' => $ksefEnv,
                    'ksef_context_nip' => $nip,
                    'configured_by_type' => 'admin',
                    'configured_by_id' => Auth::currentUserId(),
                ];
                KsefConfig::upsert($newClientId, $configData);
            }
        } catch (\Exception $e) {
            error_log("KsefConfig create skipped for client {$newClientId}: " . $e->getMessage());
        }

        AuditLog::log('admin', Auth::currentUserId(), 'client_created', "NIP: {$nip}", 'client');
        Session::flash('success', 'client_created');
        $this->redirect('/admin/clients');
    }

    public function clientEditForm(string $id): void
    {
        $client = Client::findById((int) $id);
        if (!$client) { $this->redirect('/admin/clients'); return; }
        $offices = Office::findAll(true);
        $costCenters = ClientCostCenter::findByClient((int) $id);
        $smtpConfig = ClientSmtpConfig::findByClientId((int) $id);
        $this->render('admin/client_form', [
            'client' => $client,
            'offices' => $offices,
            'costCenters' => $costCenters,
            'smtpConfig' => $smtpConfig,
        ]);
    }

    public function clientUpdate(string $id): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/admin/clients'); return; }

        $oldClient = Client::findById((int) $id);
        $data = [
            'company_name'        => $this->sanitize($_POST['company_name'] ?? ''),
            'representative_name' => $this->sanitize($_POST['representative_name'] ?? ''),
            'address'             => $this->sanitize($_POST['address'] ?? ''),
            'email'               => $this->sanitize($_POST['email'] ?? ''),
            'report_email'        => $this->sanitize($_POST['report_email'] ?? ''),
            'phone'               => $this->sanitize($_POST['phone'] ?? ''),
            'regon'               => $this->sanitize($_POST['regon'] ?? ''),
            'is_active'           => isset($_POST['is_active']) ? 1 : 0,
            'language'            => $_POST['language'] ?? 'pl',
            'office_id'           => !empty($_POST['office_id']) ? (int) $_POST['office_id'] : null,
        ];

        $data['has_cost_centers'] = !empty($_POST['has_cost_centers']) ? 1 : 0;
        $data['ksef_enabled'] = !empty($_POST['ksef_enabled']) ? 1 : 0;
        $data['ip_whitelist'] = trim($_POST['ip_whitelist'] ?? '') ?: null;
        $data['can_send_invoices'] = !empty($_POST['can_send_invoices']) ? 1 : 0;
        $data['mobile_app_enabled'] = !empty($_POST['mobile_app_enabled']) ? 1 : 0;

        // Revoke all active mobile sessions immediately when access is disabled
        if (!$data['mobile_app_enabled']) {
            try {
                \App\Api\Auth\JwtService::revokeAllForClient((int) $id);
            } catch (\Throwable) {
                // api_tokens table may not exist yet — safe to skip
            }
        }

        $password = $_POST['password'] ?? '';
        if (!empty($password)) {
            $pwdErrors = Auth::validatePasswordStrength($password);
            if (!empty($pwdErrors)) {
                Session::flash('error', $pwdErrors[0]);
                $this->redirect("/admin/clients/{$id}/edit");
                return;
            }
            $data['password_hash'] = Auth::hashPassword($password);
            $data['password_changed_at'] = date('Y-m-d H:i:s');
        }

        Client::update((int) $id, $data, Client::adminAllowedFields());

        if ($data['has_cost_centers']) {
            $ccNames = $_POST['cost_center_names'] ?? [];
            ClientCostCenter::syncForClient((int) $id, $ccNames);
        }

        // Update KSeF config v3.0 (skip if table not yet migrated)
        try {
            $ksefEnv = $_POST['ksef_environment'] ?? 'test';
            if (!in_array($ksefEnv, ['test', 'demo', 'production'])) $ksefEnv = 'test';

            $configData = [
                'is_active' => $data['ksef_enabled'],
                'ksef_environment' => $ksefEnv,
                'ksef_context_nip' => $oldClient['nip'],
                'configured_by_type' => 'admin',
                'configured_by_id' => Auth::currentUserId(),
            ];

            KsefConfig::upsert((int)$id, $configData);
        } catch (\Exception $e) {
            // client_ksef_configs table may not exist if v3.0 migration not applied
            error_log("KsefConfig update skipped for client {$id}: " . $e->getMessage());
        }

        // Save client SMTP config
        try {
            $smtpEnabled = !empty($_POST['client_smtp_enabled']) ? 1 : 0;
            $smtpData = [
                'is_enabled'         => $smtpEnabled,
                'smtp_host'          => trim($_POST['client_smtp_host'] ?? ''),
                'smtp_port'          => (int) ($_POST['client_smtp_port'] ?? 587),
                'smtp_encryption'    => $_POST['client_smtp_encryption'] ?? 'tls',
                'smtp_user'          => trim($_POST['client_smtp_user'] ?? ''),
                'from_email'         => trim($_POST['client_smtp_from_email'] ?? ''),
                'from_name'          => trim($_POST['client_smtp_from_name'] ?? ''),
            ];
            $smtpPass = $_POST['client_smtp_pass'] ?? '';
            if (!empty($smtpPass)) {
                $smtpData['smtp_pass_encrypted'] = base64_encode($smtpPass);
            }
            ClientSmtpConfig::upsert((int) $id, $smtpData);
        } catch (\Exception $e) {
            error_log("ClientSmtpConfig save error for client {$id}: " . $e->getMessage());
        }

        AuditLog::log('admin', Auth::currentUserId(), 'client_updated', "Client ID: {$id}", 'client', (int)$id, $oldClient, $data);
        Session::flash('success', 'client_updated');
        $this->redirect('/admin/clients');
    }

    public function clientDelete(string $id): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/admin/clients'); return; }

        $client = Client::findById((int) $id);
        if (!$client) {
            $this->redirect('/admin/clients');
            return;
        }

        $clientName = $client['company_name'];
        $clientNip = $client['nip'];

        Client::delete((int) $id);

        AuditLog::log('admin', Auth::currentUserId(), 'client_deleted',
            "Deleted client: {$clientName} (NIP: {$clientNip}, ID: {$id})", 'client', (int)$id);
        Session::flash('success', 'client_deleted');
        $this->redirect('/admin/clients');
    }

    public function clientToggleActive(string $id): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/admin/clients'); return; }

        $client = Client::findById((int) $id);
        if (!$client) { $this->redirect('/admin/clients'); return; }

        $newStatus = $client['is_active'] ? 0 : 1;
        Client::update((int) $id, ['is_active' => $newStatus]);

        $action = $newStatus ? 'client_activated' : 'client_deactivated';
        AuditLog::log('admin', Auth::currentUserId(), $action,
            "Client ID: {$id} ({$client['company_name']})", 'client', (int)$id);
        Session::flash('success', $action);
        $this->redirect('/admin/clients');
    }

    public function clientResetPassword(string $id): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/admin/clients'); return; }

        $client = Client::findById((int) $id);
        if (!$client) { $this->redirect('/admin/clients'); return; }

        $newPassword = $_POST['new_password'] ?? '';
        $pwdErrors = Auth::validatePasswordStrength($newPassword);
        if (!empty($pwdErrors)) {
            Session::flash('error', $pwdErrors[0]);
            $this->redirect("/admin/clients/{$id}/edit");
            return;
        }

        Client::updatePassword((int) $id, Auth::hashPassword($newPassword));

        AuditLog::log('admin', Auth::currentUserId(), 'client_password_reset',
            "Password reset for client ID: {$id} ({$client['company_name']})", 'client', (int)$id);
        Session::flash('success', 'password_reset_by_admin');
        $this->redirect("/admin/clients/{$id}/edit");
    }

    public function clientCostCenters(string $id): void
    {
        $client = Client::findById((int) $id);
        if (!$client) {
            $this->redirect('/admin/clients');
            return;
        }

        $costCenters = ClientCostCenter::findByClient((int) $id);

        $this->render('admin/client_cost_centers', [
            'client'       => $client,
            'costCenters'  => $costCenters,
        ]);
    }

    public function clientCostCentersUpdate(string $id): void
    {
        if (!$this->validateCsrf()) {
            $this->redirect("/admin/clients/{$id}/cost-centers");
            return;
        }

        $client = Client::findById((int) $id);
        if (!$client) {
            $this->redirect('/admin/clients');
            return;
        }

        $hasCostCenters = isset($_POST['has_cost_centers']) ? 1 : 0;
        $costCenterNames = $_POST['cost_center_names'] ?? [];

        // Filter out empty names
        $costCenterNames = array_filter(array_map('trim', $costCenterNames));

        // Update client cost centers status
        Client::update((int) $id, ['has_cost_centers' => $hasCostCenters]);

        // Sync cost centers
        if ($hasCostCenters && !empty($costCenterNames)) {
            ClientCostCenter::syncForClient((int) $id, $costCenterNames);
        } else {
            ClientCostCenter::deleteByClient((int) $id);
        }

        AuditLog::log('admin', Auth::currentUserId(), 'client_cost_centers_updated', "Client ID: {$id}", 'client', (int) $id);
        Session::flash('success', 'cost_centers_updated');
        $this->redirect("/admin/clients/{$id}/cost-centers");
    }

    // ── Bulk Import Clients ────────────────────────

    public function bulkImportForm(): void
    {
        $offices = Office::findAll(true);
        $this->render('admin/bulk_import', ['offices' => $offices]);
    }

    public function bulkImport(): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/admin/clients/bulk-import'); return; }

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'import_missing_data');
            $this->redirect('/admin/clients/bulk-import');
            return;
        }

        if ($_FILES['file']['size'] > 10 * 1024 * 1024) {
            Session::flash('error', 'file_too_large');
            $this->redirect('/admin/clients/bulk-import');
            return;
        }

        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['txt', 'csv'])) {
            Session::flash('error', 'import_invalid_format');
            $this->redirect('/admin/clients/bulk-import');
            return;
        }

        $uploadPath = __DIR__ . '/../../storage/imports/' . uniqid('bulk_') . '.' . $ext;
        move_uploaded_file($_FILES['file']['tmp_name'], $uploadPath);

        $officeId = !empty($_POST['office_id']) ? (int) $_POST['office_id'] : null;
        $result = BulkImportService::importFromText($uploadPath, $officeId);

        AuditLog::log('admin', Auth::currentUserId(), 'bulk_client_import', "Imported: {$result['success']}/{$result['total']}", 'client');

        Session::set('bulk_import_result', $result);
        Session::flash('success', $result['success'] > 0 ? 'import_success' : 'import_no_success');
        $this->redirect('/admin/clients/bulk-import');
    }

    // ── GUS API ────────────────────────────────────

    public function gusDiagnostic(): void
    {
        $nip = preg_replace('/[^0-9]/', '', $_GET['nip'] ?? '');
        if (empty($nip)) {
            $nip = '5252866457';
        }

        $gus = new GusApiService();
        $result = $gus->diagnose($nip);

        $this->render('admin/gus_diagnostic', [
            'steps' => $result['steps'],
            'logContent' => $result['log'],
            'nip' => $nip,
        ]);
    }

    public function ceidgDiagnostic(): void
    {
        $nip = preg_replace('/[^0-9]/', '', $_GET['nip'] ?? '');
        if (empty($nip)) {
            $nip = '5252866457';
        }

        $ceidg = new CeidgApiService();
        $result = $ceidg->diagnose($nip);

        $this->render('admin/ceidg_diagnostic', [
            'steps' => $result['steps'],
            'logContent' => $result['log'],
            'nip' => $nip,
        ]);
    }

    public function gusLookup(): void
    {
        $nip = preg_replace('/[^0-9]/', '', $_GET['nip'] ?? '');

        try {
            $data = CompanyLookupService::findByNip($nip);

            if (!$data) {
                $this->json(['error' => 'Nie znaleziono podmiotu o podanym NIP w rejestrze GUS ani CEIDG.'], 404);
                return;
            }

            $data['formatted_address'] = GusApiService::formatAddress($data);
            // Also provide Nazwa for JS compatibility
            $data['Nazwa'] = $data['company_name'];
            $data['Regon'] = $data['regon'];
            AuditLog::log('admin', Auth::currentUserId(), 'gus_lookup', "NIP: {$nip} (źródło: {$data['source']})", 'client');
            $this->json($data);
        } catch (\RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    // ── VIES Check ────────────────────────────────

    public function viesCheck(): void
    {
        $nip = preg_replace('/[^0-9]/', '', $_GET['nip'] ?? '');
        $country = $_GET['country'] ?? 'PL';

        if (strlen($nip) < 5) {
            $this->json(['error' => 'Invalid VAT number'], 400);
            return;
        }

        $result = \App\Services\ViesService::checkVat($country, $nip);
        AuditLog::log('admin', Auth::currentUserId(), 'vies_check', "NIP: {$country}{$nip}", 'client');
        $this->json($result);
    }

    // ── KSeF Import ────────────────────────────────

    public function ksefImport(): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/admin/import'); return; }

        $clientId = $this->sanitizeInt($_POST['client_id'] ?? 0);
        $month = $this->sanitizeInt($_POST['month'] ?? date('n'));
        $year = $this->sanitizeInt($_POST['year'] ?? date('Y'));

        $client = Client::findById($clientId);
        if (!$client) {
            Session::flash('error', 'client_not_found');
            $this->redirect('/admin/import');
            return;
        }

        $ksef = KsefApiService::forClient($client);
        if (!$ksef->isConfigured()) {
            Session::flash('error', 'ksef_not_configured');
            $this->redirect('/admin/import');
            return;
        }

        $jobId = self::launchKsefImportJob($clientId, $month, $year, Auth::currentUserId(), 'admin', $client['office_id']);
        Session::set('ksef_import_job_id', $jobId);
        $this->redirect('/admin/import');
    }

    public function ksefImportStatus(): void
    {
        $jobId = $_GET['job_id'] ?? '';
        $result = self::checkKsefImportStatus($jobId);
        if ($result === null) {
            $this->json(['error' => 'Job not found'], 404);
            return;
        }
        $this->json($result);
    }

    // ── Import (File) ──────────────────────────────

    public function importForm(): void
    {
        $clients = Client::findAll(true);
        $ksef = new KsefApiService();
        $importTemplates = \App\Models\ImportTemplate::findAll();
        $this->render('admin/import', [
            'clients' => $clients,
            'ksefConfigured' => $ksef->isConfigured(),
            'importTemplates' => $importTemplates,
        ]);
    }

    public function importTemplateSave(): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/admin/import'); return; }

        $name = trim($_POST['template_name'] ?? '');
        $mapping = $_POST['column_mapping'] ?? '{}';
        $separator = $_POST['separator'] ?? ';';
        $encoding = $_POST['encoding'] ?? 'UTF-8';
        $skipRows = (int) ($_POST['skip_rows'] ?? 1);

        if ($name === '') {
            Session::flash('error', 'import_template_name_required');
            $this->redirect('/admin/import');
            return;
        }

        \App\Models\ImportTemplate::create([
            'name' => $name,
            'column_mapping' => $mapping,
            'separator' => $separator,
            'encoding' => $encoding,
            'skip_rows' => $skipRows,
        ]);

        Session::flash('success', 'import_template_saved');
        $this->redirect('/admin/import');
    }

    public function importTemplateDelete(string $id): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/admin/import'); return; }
        \App\Models\ImportTemplate::delete((int) $id);
        Session::flash('success', 'import_template_deleted');
        $this->redirect('/admin/import');
    }

    public function importTemplate(): void
    {
        $path = ImportService::generateImportTemplate();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="szablon_import_faktur.xlsx"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function import(): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/admin/import'); return; }

        $clientId = $this->sanitizeInt($_POST['client_id'] ?? 0);
        $month = $this->sanitizeInt($_POST['month'] ?? date('n'));
        $year = $this->sanitizeInt($_POST['year'] ?? date('Y'));

        if (!$clientId || !isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'import_missing_data');
            $this->redirect('/admin/import');
            return;
        }

        $file = $_FILES['file'];
        if ($file['size'] > 10 * 1024 * 1024) {
            Session::flash('error', 'file_too_large');
            $this->redirect('/admin/import');
            return;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xls', 'xlsx', 'txt', 'csv'])) {
            Session::flash('error', 'import_invalid_format');
            $this->redirect('/admin/import');
            return;
        }

        $uploadPath = __DIR__ . '/../../storage/imports/' . uniqid('import_') . '.' . $ext;
        move_uploaded_file($file['tmp_name'], $uploadPath);

        $client = Client::findById($clientId);
        $adminId = Auth::currentUserId();

        if (in_array($ext, ['xls', 'xlsx'])) {
            $result = ImportService::importFromExcel($uploadPath, $clientId, $adminId, $month, $year, 'admin', $client['office_id'] ?? null);
        } else {
            $result = ImportService::importFromText($uploadPath, $clientId, $adminId, $month, $year, 'admin', $client['office_id'] ?? null);
        }

        AuditLog::log('admin', $adminId, 'invoices_imported', json_encode($result), 'batch');

        if ($result['success'] > 0) Session::flash('success', 'import_success');
        if (!empty($result['errors'])) Session::flash('error', 'import_partial_errors');

        Session::set('import_result', $result);
        $this->redirect('/admin/import');
    }

    // ── Batches ────────────────────────────────────

    public function batches(): void
    {
        $total = InvoiceBatch::countAll();
        $pagination = Pagination::fromRequest($total, 25);
        $batches = InvoiceBatch::findAllPaginated($pagination->offset, $pagination->perPage);
        $this->render('admin/batches', ['batches' => $batches, 'pagination' => $pagination]);
    }

    public function batchDetail(string $id): void
    {
        $batch = InvoiceBatch::findById((int) $id);
        if (!$batch) { $this->redirect('/admin/batches'); return; }

        $filterStatus = $_GET['status'] ?? null;
        $filterSearch = $_GET['search'] ?? null;

        if ($filterStatus || $filterSearch) {
            $invoices = Invoice::findByBatchFiltered((int) $id, $filterStatus ?: null, $filterSearch ?: null);
        } else {
            $invoices = Invoice::findByBatch((int) $id);
        }
        $stats = Invoice::countByBatchAndStatus((int) $id);

        $commentCounts = \App\Models\InvoiceComment::countByBatch((int) $id);

        $this->render('admin/batch_detail', [
            'batch' => $batch,
            'invoices' => $invoices,
            'stats' => $stats,
            'commentCounts' => $commentCounts,
            'filters' => ['status' => $filterStatus, 'search' => $filterSearch],
        ]);
    }

    public function finalizeBatch(string $id): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/admin/batches'); return; }

        $batch = InvoiceBatch::findById((int) $id);
        if (!$batch || $batch['is_finalized']) { $this->redirect('/admin/batches'); return; }

        Invoice::autoAcceptPending((int) $id);
        InvoiceBatch::finalize((int) $id);

        $client = Client::findById($batch['client_id']);
        $periodLabel = sprintf('%02d/%04d', $batch['period_month'], $batch['period_year']);
        $attachmentPaths = [];
        $isKsef = ($batch['source'] === 'ksef_api');
        $reportFormat = $isKsef ? 'jpk_xml' : 'excel';

        // Handle cost centers if enabled
        if ($client['has_cost_centers']) {
            $costCenters = ClientCostCenter::findByClient($batch['client_id'], true);
            foreach ($costCenters as $cc) {
                $acceptedInvoices = Invoice::getAcceptedByBatchAndCostCenter((int) $id, (int)$cc['id']);
                if (!empty($acceptedInvoices)) {
                    $pdfPath = PdfService::generateCostCenterPdf((int) $id, $cc['name'], $acceptedInvoices);
                    $attachmentPaths[] = $pdfPath;
                    $reportData = [
                        'client_id' => $batch['client_id'], 'batch_id' => (int) $id,
                        'report_type' => 'accepted', 'pdf_path' => $pdfPath,
                        'cost_center_name' => $cc['name'], 'report_format' => $reportFormat,
                    ];

                    if ($isKsef) {
                        $xmlPath = JpkV3Service::generateCostCenterJpk((int) $id, $cc['name'], $acceptedInvoices);
                        $attachmentPaths[] = $xmlPath;
                        $reportData['xml_path'] = $xmlPath;
                    } else {
                        $xlsPath = ExportService::generateCostCenterXls((int) $id, $cc['name'], $acceptedInvoices);
                        $attachmentPaths[] = $xlsPath;
                        $reportData['xls_path'] = $xlsPath;
                    }

                    Report::create($reportData);
                }
            }
        } else {
            $pdfPath = PdfService::generateAcceptedPdf((int) $id);
            $attachmentPaths[] = $pdfPath;
            $reportData = [
                'client_id' => $batch['client_id'], 'batch_id' => (int) $id,
                'report_type' => 'accepted', 'pdf_path' => $pdfPath,
                'report_format' => $reportFormat,
            ];

            if ($isKsef) {
                $xmlPath = JpkV3Service::generateAcceptedJpk((int) $id);
                $attachmentPaths[] = $xmlPath;
                $reportData['xml_path'] = $xmlPath;
            } else {
                $xlsPath = ExportService::generateAcceptedXls((int) $id);
                $attachmentPaths[] = $xlsPath;
                $reportData['xls_path'] = $xlsPath;
            }

            Report::create($reportData);
        }

        // Generate rejected invoices report (always Excel + PDF)
        $rejectedXls = ExportService::generateRejectedXls((int) $id);
        $rejectedPdf = PdfService::generateRejectedPdf((int) $id);
        $attachmentPaths[] = $rejectedXls;
        $attachmentPaths[] = $rejectedPdf;

        // Send email with all attachments
        MailService::sendReportMultiple($client['report_email'], $client['company_name'], $client['nip'], $periodLabel, $attachmentPaths);
        AuditLog::log('admin', Auth::currentUserId(), 'batch_finalized', "Batch ID: {$id}", 'batch', (int)$id);
        Session::flash('success', 'batch_finalized');
        $this->redirect("/admin/batches/{$id}");
    }

    // ── Reports ────────────────────────────────────

    public function downloadReport(string $id): void
    {
        $report = Report::findById((int) $id);
        if (!$report) { $this->redirect('/admin/batches'); return; }

        $type = $_GET['type'] ?? 'pdf';

        if ($type === 'xml') {
            $path = $report['xml_path'] ?? null;
            $contentType = 'application/xml';
        } elseif ($type === 'xls') {
            $path = $report['xls_path'] ?? null;
            $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        } else {
            $path = $report['pdf_path'] ?? null;
            $contentType = 'application/pdf';
        }

        if ($path && file_exists($path)) {
            header('Content-Type: ' . $contentType);
            header('Content-Disposition: attachment; filename="' . basename($path) . '"');
            header('Content-Length: ' . filesize($path));
            readfile($path);
            exit;
        }

        Session::flash('error', 'report_not_found');
        $this->redirect('/admin/batches');
    }

    // ── Impersonation ──────────────────────────────

    public function impersonate(string $type, string $id): void
    {
        if (Auth::impersonate($type, (int) $id)) {
            if ($type === 'client') {
                $this->redirect('/client');
            } elseif ($type === 'office') {
                $this->redirect('/office');
            }
        } else {
            Session::flash('error', 'impersonation_failed');
            $this->redirect('/admin');
        }
    }

    public function stopImpersonation(): void
    {
        Auth::stopImpersonation();
        $this->redirect('/admin');
    }

    // ── Audit Log ──────────────────────────────────

    public function auditLog(): void
    {
        $action = $_GET['action'] ?? null;
        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo = $_GET['date_to'] ?? null;

        $total = AuditLog::searchCount($action, $dateFrom, $dateTo);
        $pagination = Pagination::fromRequest($total, 50);
        $logs = AuditLog::searchPaginated($action, $dateFrom, $dateTo, $pagination->offset, $pagination->perPage);
        $loginHistory = AuditLog::getLoginHistory(100);

        $this->render('admin/audit_log', [
            'logs' => $logs,
            'loginHistory' => $loginHistory,
            'filters' => ['action' => $action, 'date_from' => $dateFrom, 'date_to' => $dateTo],
            'pagination' => $pagination,
        ]);
    }

    // ── KSeF Logs ──────────────────────────────────

    public function ksefLogs(): void
    {
        $sessions = KsefLogger::listSessions(50);
        $selectedSession = $_GET['session'] ?? null;
        $logContent = null;

        if ($selectedSession) {
            $logContent = KsefLogger::readSession($selectedSession);
        }

        $this->render('admin/ksef_logs', [
            'sessions' => $sessions,
            'selectedSession' => $selectedSession,
            'logContent' => $logContent,
        ]);
    }

    public function ksefTest(): void
    {
        $clientId = $this->sanitizeInt($_GET['client_id'] ?? 0);
        $client = $clientId ? Client::findById($clientId) : null;
        $clients = Client::findAll(true);

        $diagnostics = [];

        // 1. Configuration check
        $ksef = $client ? KsefApiService::forClient($client) : new KsefApiService();
        $diagnostics['configured'] = $ksef->isConfigured();
        $diagnostics['environment'] = $ksef->getEnvironment();
        $diagnostics['api_url'] = $ksef->getApiUrl();
        $diagnostics['nip'] = $client['nip'] ?? Setting::get('ksef_nip', '');
        $ksefConfig = $client ? KsefConfig::findByClientId((int)$client['id']) : null;
        $diagnostics['has_certificate'] = !empty($ksefConfig['cert_fingerprint'] ?? '') || !empty($ksefConfig['cert_ksef_pem'] ?? '');
        $diagnostics['auth_method'] = $ksefConfig['auth_method'] ?? 'none';

        // 2. PHP extensions
        $diagnostics['php_version'] = PHP_VERSION;
        $diagnostics['openssl'] = extension_loaded('openssl');
        $diagnostics['openssl_version'] = OPENSSL_VERSION_TEXT ?? 'N/A';
        $diagnostics['curl'] = extension_loaded('curl');
        $diagnostics['curl_version'] = curl_version()['version'] ?? 'N/A';
        $diagnostics['curl_ssl'] = curl_version()['ssl_version'] ?? 'N/A';

        // 3. Test connectivity
        $diagnostics['connectivity'] = null;
        $diagnostics['challenge'] = null;
        $diagnostics['auth'] = null;
        $diagnostics['errors'] = [];

        if ($ksef->isConfigured()) {
            $ksef->enableLogging();

            // Test basic connectivity
            try {
                $testUrl = $ksef->getApiUrl() . '/api/v2/security/public-key-certificates';
                $ch = curl_init($testUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_HTTPHEADER => ['Accept: application/json'],
                ]);
                $resp = curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $cerr = curl_error($ch);
                $cinfo = curl_getinfo($ch);
                curl_close($ch);

                $diagnostics['connectivity'] = [
                    'url' => $testUrl,
                    'http_code' => $code,
                    'curl_error' => $cerr ?: null,
                    'connect_time' => round($cinfo['connect_time'] ?? 0, 3),
                    'total_time' => round($cinfo['total_time'] ?? 0, 3),
                    'ssl_verify' => $cinfo['ssl_verify_result'] ?? null,
                    'ip' => $cinfo['primary_ip'] ?? null,
                    'response_preview' => substr($resp ?: '', 0, 500),
                ];
            } catch (\Throwable $e) {
                $diagnostics['errors'][] = 'Connectivity: ' . $e->getMessage();
            }

            // Test challenge
            try {
                $testResult = $ksef->testConnection();
                $diagnostics['challenge'] = $testResult;
            } catch (\Throwable $e) {
                $diagnostics['errors'][] = 'Challenge: ' . $e->getMessage();
            }

            // Test full auth
            if ($_GET['full_test'] ?? false) {
                try {
                    $authOk = $ksef->authenticate();
                    $diagnostics['auth'] = ['success' => $authOk];
                } catch (\Throwable $e) {
                    $diagnostics['auth'] = ['success' => false, 'error' => $e->getMessage()];
                    $diagnostics['errors'][] = 'Auth: ' . $e->getMessage();
                }
            }

            $diagnostics['log_session'] = $ksef->getLogger() ? $ksef->getLogger()->getSessionId() : null;
        }

        $this->render('admin/ksef_test', [
            'diagnostics' => $diagnostics,
            'clients' => $clients,
            'selectedClient' => $clientId,
            'client' => $client,
        ]);
    }

    // ── KSeF Operations Log ───────────────────────────

    public function ksefOperations(): void
    {
        $clientId = $this->sanitizeInt($_GET['client_id'] ?? 0);
        $operations = $clientId
            ? KsefOperationLog::findByClient($clientId, 100)
            : KsefOperationLog::findRecent(100);

        $clients = Client::findAll(true);
        $configs = [];
        foreach ($clients as $c) {
            $cfg = KsefConfig::findByClientId((int)$c['id']);
            if ($cfg) $configs[$c['id']] = $cfg;
        }

        $this->render('admin/ksef_operations', [
            'operations' => $operations,
            'clients' => $clients,
            'configs' => $configs,
            'selectedClient' => $clientId,
        ]);
    }

    // ── Settings ───────────────────────────────────

    public function settings(): void
    {
        $rows = Setting::getAll();
        $values = [];
        foreach ($rows as $row) {
            $values[$row['setting_key']] = $row['setting_value'];
        }

        // Mobile API session stats
        $activeSessions = 0;
        try {
            $db = \App\Core\Database::getInstance();
            $row = $db->fetchOne(
                "SELECT COUNT(*) AS cnt FROM api_tokens WHERE revoked_at IS NULL AND expires_at > NOW()"
            );
            $activeSessions = (int) ($row['cnt'] ?? 0);
        } catch (\Throwable) {
            // api_tokens may not exist yet
        }

        $this->render('admin/settings', ['values' => $values, 'activeSessions' => $activeSessions]);
    }

    public function settingsUpdate(): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/admin/settings'); return; }

        $allowed = [
            'verification_deadline_day', 'auto_accept_on_deadline', 'password_expiry_days',
            'company_name', 'company_email', 'notification_days_before',
            'session_timeout_minutes', 'max_sessions_per_user',
            'gus_api_key', 'gus_api_url', 'gus_api_env',
            'ceidg_api_token', 'ceidg_api_url', 'ceidg_api_env',
            'whitelist_api_url', 'whitelist_check_enabled',
            'ksef_api_url', 'ksef_api_env', 'ksef_nip', 'ksef_auto_import_day',
            'system_name', 'system_description', 'primary_color', 'secondary_color', 'accent_color',
            'privacy_policy_enabled', 'privacy_policy_text',
            '2fa_enabled', '2fa_required', '2fa_required_admin', '2fa_required_client', '2fa_required_office',
            'support_contact_name', 'support_contact_email', 'support_contact_phone',
            'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_user', 'smtp_from_email', 'smtp_from_name',
            'mobile_api_enabled',
        ];

        // mobile_api_enabled is a checkbox — if unchecked it won't be in POST, so default to '0'
        if (!isset($_POST['mobile_api_enabled'])) {
            Setting::set('mobile_api_enabled', '0');
        }

        foreach ($allowed as $key) {
            if (isset($_POST[$key])) {
                Setting::set($key, $_POST[$key]);
            }
        }

        // SMTP password — only save if not empty (don't overwrite with blank)
        $smtpPass = $_POST['smtp_pass'] ?? '';
        if (!empty($smtpPass)) {
            Setting::set('smtp_pass', $smtpPass);
        }

        // Logo uploads (max 5MB, images only)
        $logoFields = [
            'logo'       => ['setting' => 'logo_path',       'filename' => 'logo'],
            'logo_dark'  => ['setting' => 'logo_path_dark',  'filename' => 'logo_dark'],
            'logo_login' => ['setting' => 'logo_path_login', 'filename' => 'logo_login'],
        ];
        $allowedLogoExt = ['png', 'jpg', 'jpeg', 'webp', 'svg'];
        foreach ($logoFields as $fieldName => $config) {
            if (isset($_FILES[$fieldName]) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK && $_FILES[$fieldName]['size'] <= 5 * 1024 * 1024) {
                $ext = strtolower(pathinfo($_FILES[$fieldName]['name'], PATHINFO_EXTENSION));
                if (in_array($ext, $allowedLogoExt, true)) {
                    $logoPath = '/assets/uploads/' . $config['filename'] . '.' . $ext;
                    $fullPath = __DIR__ . '/../../public' . $logoPath;
                    @mkdir(dirname($fullPath), 0755, true);
                    move_uploaded_file($_FILES[$fieldName]['tmp_name'], $fullPath);
                    Setting::set($config['setting'], $logoPath);
                }
            }
        }

        AuditLog::log('admin', Auth::currentUserId(), 'settings_updated', 'System settings updated');
        Session::flash('success', 'settings_updated');
        $this->redirect('/admin/settings');
    }

    public function testSmtp(): void
    {
        if (!$this->validateCsrf()) { $this->json(['error' => 'CSRF'], 403); return; }

        $host = $this->sanitize($_POST['smtp_host'] ?? '');
        $port = (int) ($_POST['smtp_port'] ?? 587);
        $encryption = $_POST['smtp_encryption'] ?? 'tls';
        $user = $this->sanitize($_POST['smtp_user'] ?? '');
        $pass = $_POST['smtp_pass'] ?? '';
        $fromEmail = $this->sanitize($_POST['smtp_from_email'] ?? '');
        $fromName = $this->sanitize($_POST['smtp_from_name'] ?? '');

        if (empty($host) || empty($fromEmail)) {
            $this->json(['success' => false, 'error' => 'Podaj host i email nadawcy']);
            return;
        }

        // If no password provided, use saved password
        if (empty($pass)) {
            $pass = Setting::get('smtp_pass', '');
        }

        try {
            $result = \App\Services\MailService::testSmtpConnection($host, $port, $encryption, $user, $pass, $fromEmail, $fromName);
            $this->json($result);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    // ── Mobile API Management ──────────────────────

    public function apiSessions(): void
    {
        $sessions = [];
        try {
            $db = \App\Core\Database::getInstance();
            $sessions = $db->fetchAll(
                "SELECT t.id, t.device_name, t.ip_address, t.created_at, t.expires_at,
                        c.id AS client_id, c.company_name, c.nip
                 FROM api_tokens t
                 JOIN clients c ON c.id = t.client_id
                 WHERE t.revoked_at IS NULL AND t.expires_at > NOW()
                 ORDER BY t.created_at DESC
                 LIMIT 200"
            );
        } catch (\Throwable) {
            // api_tokens may not exist yet
        }
        $this->render('admin/api_sessions', ['sessions' => $sessions]);
    }

    public function apiRevokeSession(string $id): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/admin/api/sessions'); return; }

        try {
            $db = \App\Core\Database::getInstance();
            $db->execute("UPDATE api_tokens SET revoked_at = NOW() WHERE id = ?", [(int) $id]);
            AuditLog::log('admin', Auth::currentUserId(), 'api_session_revoked', "Token ID: {$id}");
            Session::flash('success', 'session_revoked');
        } catch (\Throwable) {
            Session::flash('error', 'generic_error');
        }
        $this->redirect('/admin/api/sessions');
    }

    public function apiRevokeAllSessions(): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/admin/api/sessions'); return; }

        $clientId = !empty($_POST['client_id']) ? (int) $_POST['client_id'] : null;

        try {
            if ($clientId) {
                \App\Api\Auth\JwtService::revokeAllForClient($clientId);
                AuditLog::log('admin', Auth::currentUserId(), 'api_sessions_revoked_client',
                    "All tokens revoked for client ID: {$clientId}");
            } else {
                $db = \App\Core\Database::getInstance();
                $db->execute("UPDATE api_tokens SET revoked_at = NOW() WHERE revoked_at IS NULL");
                AuditLog::log('admin', Auth::currentUserId(), 'api_sessions_revoked_all',
                    'All mobile API sessions revoked');
            }
            Session::flash('success', 'sessions_revoked');
        } catch (\Throwable) {
            Session::flash('error', 'generic_error');
        }
        $this->redirect('/admin/api/sessions');
    }

    public function clientToggleMobile(string $id): void
    {
        if (!$this->validateCsrf()) { $this->json(['error' => 'CSRF'], 403); return; }

        $client = Client::findById((int) $id);
        if (!$client) { $this->json(['error' => 'not_found'], 404); return; }

        $newVal = (int) $client['mobile_app_enabled'] ? 0 : 1;
        Client::update((int) $id, ['mobile_app_enabled' => $newVal]);

        if (!$newVal) {
            try {
                \App\Api\Auth\JwtService::revokeAllForClient((int) $id);
            } catch (\Throwable) { }
        }

        AuditLog::log('admin', Auth::currentUserId(), 'client_mobile_toggled',
            "Client ID: {$id} mobile_app_enabled set to {$newVal}");

        $this->json(['ok' => true, 'mobile_app_enabled' => $newVal]);
    }

    public function officeBulkToggleMobile(string $id): void
    {
        if (!$this->validateCsrf()) { $this->redirect("/admin/offices/{$id}/edit"); return; }

        $val = (int) ($_POST['mobile_val'] ?? 1);
        $val = $val ? 1 : 0; // sanitize to 0 or 1

        $clients = Office::getClients((int) $id);
        $count = 0;

        foreach ($clients as $c) {
            Client::update((int) $c['id'], ['mobile_app_enabled' => $val]);
            if (!$val) {
                try {
                    \App\Api\Auth\JwtService::revokeAllForClient((int) $c['id']);
                } catch (\Throwable) { }
            }
            $count++;
        }

        AuditLog::log('admin', Auth::currentUserId(), 'office_bulk_mobile_toggled',
            "Office ID: {$id} — mobile_app_enabled set to {$val} for {$count} client(s)");

        Session::flash('success', 'bulk_mobile_updated');
        $this->redirect("/admin/offices/{$id}/edit");
    }

    // ── Notifications ──────────────────────────────

    public function notifications(): void
    {
        $userId = Auth::currentUserId();
        if ($_GET['format'] ?? '' === 'json') {
            $notifications = Notification::getUnread('admin', $userId);
            $this->json(['notifications' => $notifications, 'count' => count($notifications)]);
            return;
        }
        $notifications = Notification::getAll('admin', $userId, 50);
        $this->render('admin/notifications', ['notifications' => $notifications]);
    }

    public function notificationsMarkRead(): void
    {
        $userId = Auth::currentUserId();
        $id = $this->sanitizeInt($_POST['id'] ?? 0);
        if ($id > 0) {
            Notification::markAsRead($id, 'admin', $userId);
        } else {
            Notification::markAllAsRead('admin', $userId);
        }
        if ($_POST['ajax'] ?? false) {
            $this->json(['ok' => true]);
            return;
        }
        $this->redirect('/admin/notifications');
    }

    // ── Audit Log Export ──────────────────────────────

    public function auditLogExport(): void
    {
        $action = $_GET['action'] ?? null;
        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo = $_GET['date_to'] ?? null;

        $logs = AuditLog::search($action, $dateFrom, $dateTo, 10000);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="audit_log_' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        // BOM for Excel UTF-8
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Data', 'Typ użytkownika', 'ID użytkownika', 'Akcja', 'Szczegóły', 'IP', 'Typ encji', 'ID encji'], ';');

        foreach ($logs as $log) {
            fputcsv($out, [
                $log['created_at'],
                $log['user_type'],
                $log['user_id'],
                $log['action'],
                $log['details'] ?? '',
                $log['ip_address'] ?? '',
                $log['entity_type'] ?? '',
                $log['entity_id'] ?? '',
            ], ';');
        }
        fclose($out);
        exit;
    }

    // ── Aggregate Reports ─────────────────────────────

    public function aggregateReport(): void
    {
        $clients = Client::findAll(true);
        $this->render('admin/aggregate_report', ['clients' => $clients, 'results' => null]);
    }

    public function aggregateReportGenerate(): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/admin/reports/aggregate'); return; }

        $clientIds = $_POST['client_ids'] ?? [];
        $dateFrom = $_POST['date_from'] ?? '';
        $dateTo = $_POST['date_to'] ?? '';

        $results = [];
        $totalAccepted = 0;
        $totalRejected = 0;
        $totalPending = 0;
        $totalGross = 0;

        foreach ($clientIds as $cid) {
            $client = Client::findById((int)$cid);
            if (!$client) continue;

            $invoices = Invoice::findByClient((int)$cid);
            $filtered = array_filter($invoices, function ($inv) use ($dateFrom, $dateTo) {
                if ($dateFrom && $inv['issue_date'] < $dateFrom) return false;
                if ($dateTo && $inv['issue_date'] > $dateTo) return false;
                return true;
            });

            $accepted = count(array_filter($filtered, fn($i) => $i['status'] === 'accepted'));
            $rejected = count(array_filter($filtered, fn($i) => $i['status'] === 'rejected'));
            $pending = count(array_filter($filtered, fn($i) => $i['status'] === 'pending'));
            $gross = array_sum(array_column($filtered, 'gross_amount'));

            $results[] = [
                'client' => $client,
                'total' => count($filtered),
                'accepted' => $accepted,
                'rejected' => $rejected,
                'pending' => $pending,
                'gross' => $gross,
            ];

            $totalAccepted += $accepted;
            $totalRejected += $rejected;
            $totalPending += $pending;
            $totalGross += $gross;
        }

        $totals = [
            'accepted' => $totalAccepted,
            'rejected' => $totalRejected,
            'pending' => $totalPending,
            'gross' => $totalGross,
        ];

        // PDF download
        if (($_POST['format'] ?? '') === 'pdf') {
            $path = PdfService::generateAggregateReportPdf($results, $totals, $dateFrom, $dateTo);
            if ($path && file_exists($path)) {
                $this->downloadFile($path);
                return;
            }
        }

        $clients = Client::findAll(true);
        $this->render('admin/aggregate_report', [
            'clients' => $clients,
            'results' => $results,
            'totals' => $totals,
            'filters' => ['client_ids' => $clientIds, 'date_from' => $dateFrom, 'date_to' => $dateTo],
        ]);
    }

    // ── Period Comparison ────────────────────────────

    public function periodComparison(): void
    {
        $clients = Client::findAll(true);
        $clientId = (int) ($_GET['client_id'] ?? 0);
        $months = (int) ($_GET['months'] ?? 12);
        if ($months < 2) $months = 12;
        if ($months > 36) $months = 36;

        $data = [];
        $selectedClient = null;
        if ($clientId) {
            $selectedClient = Client::findById($clientId);
            $data = Invoice::getMonthlyComparison($clientId, $months);
        }

        $this->render('admin/period_comparison', [
            'clients' => $clients,
            'data' => $data,
            'selectedClient' => $selectedClient,
            'filters' => ['client_id' => $clientId, 'months' => $months],
        ]);
    }

    // ── Supplier Analysis ────────────────────────────

    public function supplierAnalysis(): void
    {
        $clients = Client::findAll(true);
        $clientId = (int) ($_GET['client_id'] ?? 0);
        $dateFrom = $_GET['date_from'] ?? date('Y-m-01', strtotime('-12 months'));
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');

        $suppliers = [];
        $selectedClient = null;
        $trends = [];

        if ($clientId) {
            $selectedClient = Client::findById($clientId);
            $suppliers = Invoice::getSupplierAnalysis($clientId, $dateFrom, $dateTo);

            // Compute avg across all suppliers to detect anomalies
            $globalAvg = 0;
            if (!empty($suppliers)) {
                $totalGross = array_sum(array_column($suppliers, 'total_gross'));
                $totalCount = array_sum(array_column($suppliers, 'invoice_count'));
                $globalAvg = $totalCount > 0 ? $totalGross / $totalCount : 0;
            }

            // Get 6-month trends for top suppliers and flag anomalies
            foreach ($suppliers as &$s) {
                $s['trend'] = Invoice::getSupplierMonthlyTrend($clientId, $s['seller_nip'], 6);
                $s['anomaly'] = ((float) $s['max_gross'] > 2 * (float) $s['avg_gross'] && (float) $s['avg_gross'] > 0);
            }
            unset($s);
        }

        $this->render('admin/supplier_analysis', [
            'clients' => $clients,
            'suppliers' => $suppliers,
            'selectedClient' => $selectedClient,
            'filters' => ['client_id' => $clientId, 'date_from' => $dateFrom, 'date_to' => $dateTo],
        ]);
    }

    // ── Scheduled Exports ────────────────────────────

    public function scheduledExports(): void
    {
        $exports = \App\Models\ScheduledExport::findAll();
        $clients = Client::findAll(true);

        $this->render('admin/scheduled_exports', [
            'exports' => $exports,
            'clients' => $clients,
        ]);
    }

    public function scheduledExportCreate(): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/admin/scheduled-exports'); return; }

        $clientId = (int) ($_POST['client_id'] ?? 0);
        $format = $_POST['format'] ?? '';
        $frequency = $_POST['frequency'] ?? 'monthly';
        $dayOfMonth = (int) ($_POST['day_of_month'] ?? 5);
        $email = trim($_POST['email'] ?? '');
        $includeRejected = isset($_POST['include_rejected']) ? 1 : 0;

        $allowedFormats = ['excel', 'pdf', 'jpk_fa', 'jpk_vat7', 'comarch_optima', 'sage', 'enova'];
        $allowedFrequencies = ['monthly', 'weekly'];

        if (!$clientId || !$format || !$email) {
            $_SESSION['error'] = Language::get('fill_required_fields');
            header('Location: /admin/scheduled-exports');
            return;
        }

        if (!in_array($format, $allowedFormats, true)) {
            $_SESSION['error'] = Language::get('fill_required_fields');
            header('Location: /admin/scheduled-exports');
            return;
        }

        if (!in_array($frequency, $allowedFrequencies, true)) {
            $frequency = 'monthly';
        }

        if ($dayOfMonth < 1 || $dayOfMonth > 28) {
            $dayOfMonth = 5;
        }

        $nextRun = \App\Models\ScheduledExport::calculateNextRun($frequency, $dayOfMonth);

        \App\Models\ScheduledExport::create([
            'client_id'       => $clientId,
            'format'          => $format,
            'frequency'       => $frequency,
            'day_of_month'    => $dayOfMonth,
            'email'           => $email,
            'include_rejected'=> $includeRejected,
            'is_active'       => 1,
            'next_run_at'     => $nextRun,
            'created_by_type' => 'admin',
            'created_by_id'   => $_SESSION['user_id'] ?? 0,
        ]);

        $_SESSION['success'] = Language::get('scheduled_export_created');
        header('Location: /admin/scheduled-exports');
    }

    public function scheduledExportDelete(int $id): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/admin/scheduled-exports'); return; }
        \App\Models\ScheduledExport::delete($id);
        $_SESSION['success'] = Language::get('scheduled_export_deleted');
        header('Location: /admin/scheduled-exports');
    }

    public function scheduledExportToggle(int $id): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/admin/scheduled-exports'); return; }
        \App\Models\ScheduledExport::toggle($id);
        $_SESSION['success'] = Language::get('scheduled_export_toggled');
        header('Location: /admin/scheduled-exports');
    }

    public function scheduledExportRunNow(int $id): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/admin/scheduled-exports'); return; }
        $success = \App\Services\ScheduledExportService::runNow($id);
        if ($success) {
            $_SESSION['success'] = Language::get('scheduled_export_run_success');
        } else {
            $_SESSION['error'] = Language::get('scheduled_export_run_error');
        }
        header('Location: /admin/scheduled-exports');
    }

    // ── Webhooks ──────────────────────────────────

    public function webhooks(): void
    {
        $webhooks = \App\Models\Webhook::findAll();
        $clients = Client::findAll(true);
        $this->render('admin/webhooks', ['webhooks' => $webhooks, 'clients' => $clients]);
    }

    public function webhookCreate(): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/admin/webhooks'); return; }

        $url = filter_var(trim($_POST['url'] ?? ''), FILTER_VALIDATE_URL);
        if (!$url) {
            Session::flash('error', 'invalid_url');
            $this->redirect('/admin/webhooks');
            return;
        }

        \App\Models\Webhook::create([
            'client_id' => !empty($_POST['client_id']) ? (int)$_POST['client_id'] : null,
            'url' => $url,
            'secret' => bin2hex(random_bytes(32)),
            'events' => $this->sanitize($_POST['events'] ?? 'all'),
        ]);

        AuditLog::log('admin', Auth::currentUserId(), 'webhook_created', 'Webhook: ' . $url);
        Session::flash('success', 'webhook_created');
        $this->redirect('/admin/webhooks');
    }

    public function webhookDelete(string $id): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/admin/webhooks'); return; }
        \App\Models\Webhook::delete((int)$id);
        AuditLog::log('admin', Auth::currentUserId(), 'webhook_deleted', 'Webhook ID: ' . $id);
        Session::flash('success', 'webhook_deleted');
        $this->redirect('/admin/webhooks');
    }

    public function webhookToggle(string $id): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/admin/webhooks'); return; }
        \App\Models\Webhook::toggle((int)$id);
        $this->redirect('/admin/webhooks');
    }

    public function security(): void
    {
        Auth::requireAdmin();
        $userId = Session::get('user_id');
        $user = User::findById($userId);
        $this->render('admin/security', [
            'twoFactorEnabled' => !empty($user['two_factor_enabled']),
            'twoFactorAllowed' => Auth::is2faEnabled(),
        ]);
    }

    // ── ERP Export ──────────────────────────────────

    public function erpExportForm(): void
    {
        $clients = \App\Models\Client::findAll();
        $batches = InvoiceBatch::findAll();
        $templates = \App\Models\ExportTemplate::findAll();

        $this->render('admin/erp_export', [
            'clients' => $clients,
            'batches' => $batches,
            'templates' => $templates,
        ]);
    }

    public function erpExport(): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/admin/erp-export'); return; }

        $batchId = (int) ($_POST['batch_id'] ?? 0);
        $format = $_POST['format'] ?? '';
        $onlyAccepted = ($_POST['only_accepted'] ?? '1') === '1';

        if (!$batchId || !$format) {
            Session::flash('error', 'erp_export_error');
            $this->redirect('/admin/erp-export');
            return;
        }

        $path = $this->generateErpExport($batchId, $format, $onlyAccepted);

        if (!$path || !file_exists($path)) {
            Session::flash('error', 'erp_no_invoices');
            $this->redirect('/admin/erp-export');
            return;
        }

        $this->downloadFile($path);
    }

    private function generateErpExport(int $batchId, string $format, bool $onlyAccepted): string
    {
        return match ($format) {
            'comarch_optima' => \App\Services\ErpExportService::exportComarchOptima($batchId, $onlyAccepted),
            'sage' => \App\Services\ErpExportService::exportSage($batchId, $onlyAccepted),
            'enova' => \App\Services\ErpExportService::exportEnova($batchId, $onlyAccepted),
            'insert_gt' => \App\Services\ErpExportService::exportInsertGt($batchId, $onlyAccepted),
            'rewizor' => \App\Services\ErpExportService::exportRewizor($batchId, $onlyAccepted),
            'wfirma' => \App\Services\ErpExportService::exportWfirma($batchId, $onlyAccepted),
            'universal_csv' => \App\Services\ErpExportService::exportUniversalCsv($batchId, $onlyAccepted),
            'jpk_vat7' => \App\Services\JpkVat7Service::generate($batchId, $onlyAccepted),
            'jpk_fa' => \App\Services\JpkFaService::generate($batchId, $onlyAccepted),
            default => '',
        };
    }

    private function downloadFile(string $path): void
    {
        $filename = basename($path);
        $ext = pathinfo($filename, PATHINFO_EXTENSION);

        $mimeTypes = [
            'csv' => 'text/csv',
            'xml' => 'application/xml',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        // Flush any output buffers to prevent BOM/whitespace corruption
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: ' . ($mimeTypes[$ext] ?? 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    // ── Invoice Comments ────────────────────────────

    public function invoiceComments(): void
    {
        $invoiceId = (int) ($_GET['id'] ?? 0);
        $invoice = Invoice::findById($invoiceId);

        if (!$invoice) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'not_found']);
            exit;
        }

        $comments = \App\Models\InvoiceComment::findByInvoice($invoiceId);
        foreach ($comments as &$c) {
            $c['user_name'] = \App\Models\InvoiceComment::getUserName($c['user_type'], (int) $c['user_id']);
        }

        header('Content-Type: application/json');
        echo json_encode($comments);
        exit;
    }

    public function addComment(): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/admin/batches'); return; }

        $invoiceId = (int) ($_POST['invoice_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        $invoice = Invoice::findById($invoiceId);

        if (!$invoice || $message === '') {
            Session::flash('error', 'comment_empty');
            $this->redirect($_SERVER['HTTP_REFERER'] ?? '/admin/batches');
            return;
        }

        $userId = Session::get('user_id');
        \App\Models\InvoiceComment::create($invoiceId, 'admin', $userId, $message);

        // Create notification for client
        Notification::create(
            'client',
            (int) $invoice['client_id'],
            'comment_new',
            'Nowy komentarz do faktury ' . ($invoice['invoice_number'] ?? '#' . $invoiceId)
        );

        Session::flash('success', 'comment_added');
        $batchId = $invoice['batch_id'];
        $this->redirect("/admin/batches/{$batchId}");
    }

    // ── Demo Management ──────────────────────────

    public function demoManagement(): void
    {
        $db = \App\Core\Database::getInstance();
        $demoOffices = $db->fetchAll("SELECT id, nip, name, email FROM offices WHERE is_demo = 1");
        $demoClients = $db->fetchAll("SELECT id, nip, company_name, email FROM clients WHERE is_demo = 1");
        $demoEmployees = $db->fetchAll(
            "SELECT e.id, e.name, e.email FROM office_employees e
             JOIN offices o ON e.office_id = o.id WHERE o.is_demo = 1"
        );

        // Count demo data
        $clientIds = array_column($demoClients, 'id');
        $dataCounts = [];
        if (!empty($clientIds)) {
            $ph = implode(',', array_fill(0, count($clientIds), '?'));
            $tables = [
                'invoices' => 'client_id',
                'issued_invoices' => 'client_id',
                'messages' => 'client_id',
                'client_tasks' => 'client_id',
                'tax_payments' => 'client_id',
                'contractors' => 'client_id',
            ];
            foreach ($tables as $table => $col) {
                try {
                    $row = $db->fetchOne("SELECT COUNT(*) as cnt FROM {$table} WHERE {$col} IN ({$ph})", $clientIds);
                    $dataCounts[$table] = (int) ($row['cnt'] ?? 0);
                } catch (\Throwable $e) {
                    $dataCounts[$table] = 0;
                }
            }
        }

        $lastReset = $db->fetchOne(
            "SELECT created_at FROM audit_log WHERE action = 'demo_reset' ORDER BY id DESC LIMIT 1"
        );

        $this->render('admin/demo', [
            'demoOffices'   => $demoOffices,
            'demoClients'   => $demoClients,
            'demoEmployees' => $demoEmployees,
            'dataCounts'    => $dataCounts,
            'lastReset'     => $lastReset['created_at'] ?? null,
        ]);
    }

    public function demoReset(): void
    {
        $this->validateCsrf();
        $credentials = DemoSeederService::resetDemo();
        Session::set('demo_credentials', $credentials);
        Session::flash('success', 'demo_reset_success');
        $this->redirect('/admin/demo');
    }

    public function demoPasswordReset(): void
    {
        $this->validateCsrf();
        $newPassword = trim($_POST['new_password'] ?? '');
        if (strlen($newPassword) < 8) {
            Session::flash('error', 'demo_password_too_short');
            $this->redirect('/admin/demo');
            return;
        }

        DemoSeederService::resetDemoPasswords($newPassword);
        Session::flash('success', 'demo_passwords_changed');
        Session::set('demo_password_display', $newPassword);
        $this->redirect('/admin/demo');
    }

    // ── Client SMTP Test ──────────────────────────────

    public function testClientSmtp(string $id): void
    {
        $config = ClientSmtpConfig::findByClientId((int) $id);
        if (!$config) {
            $this->json(['success' => false, 'error' => 'Brak konfiguracji SMTP']);
            return;
        }
        $pass = !empty($config['smtp_pass_encrypted'])
            ? base64_decode($config['smtp_pass_encrypted'])
            : '';
        $result = MailService::testSmtpConnection(
            $config['smtp_host'], (int) $config['smtp_port'],
            $config['smtp_encryption'], $config['smtp_user'],
            $pass, $config['from_email'], $config['from_name'] ?? ''
        );
        $this->json($result);
    }

    // ── Invoice sending toggle per office ─────────────

    public function enableInvoiceSendingForOffice(string $id): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/admin/offices'); return; }
        \App\Core\Database::getInstance()->query(
            "UPDATE clients SET can_send_invoices = 1 WHERE office_id = ?",
            [(int) $id]
        );
        AuditLog::log('admin', Auth::currentUserId(), 'invoice_sending_enabled', "Office ID: {$id}");
        Session::flash('success', 'invoice_sending_enabled_for_office');
        $this->redirect('/admin/offices');
    }

    public function disableInvoiceSendingForOffice(string $id): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/admin/offices'); return; }
        \App\Core\Database::getInstance()->query(
            "UPDATE clients SET can_send_invoices = 0 WHERE office_id = ?",
            [(int) $id]
        );
        AuditLog::log('admin', Auth::currentUserId(), 'invoice_sending_disabled', "Office ID: {$id}");
        Session::flash('success', 'invoice_sending_disabled_for_office');
        $this->redirect('/admin/offices');
    }

    // ── Email Templates ───────────────────────────────

    public function emailTemplates(): void
    {
        $templates = EmailTemplate::findAll();
        $this->render('admin/email_templates', ['templates' => $templates]);
    }

    public function emailTemplateEdit(string $key): void
    {
        $template = EmailTemplate::findByKey($key);
        if (!$template) { $this->redirect('/admin/email-templates'); return; }
        $this->render('admin/email_template_form', ['emailTemplate' => $template]);
    }

    public function emailTemplateUpdate(string $key): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/admin/email-templates'); return; }

        $template = EmailTemplate::findByKey($key);
        if (!$template) { $this->redirect('/admin/email-templates'); return; }

        EmailTemplate::update($key, [
            'subject_pl' => $_POST['subject_pl'] ?? $template['subject_pl'],
            'body_pl'    => $_POST['body_pl'] ?? $template['body_pl'],
            'subject_en' => $_POST['subject_en'] ?? $template['subject_en'],
            'body_en'    => $_POST['body_en'] ?? $template['body_en'],
        ]);

        AuditLog::log('admin', Auth::currentUserId(), 'email_template_updated', "Template: {$key}");
        Session::flash('success', 'email_template_updated');
        $this->redirect('/admin/email-templates');
    }

    // ── Security Scan ────────────────────────────────

    public function securityScan(): void
    {
        $results = null;
        $summary = null;
        $lastScan = AuditLog::findLast('security_scan_executed');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->validateCsrf()) { $this->redirect('/admin/security-scan'); return; }

            $results = SecurityScanService::runAll();
            $summary = SecurityScanService::getSummary($results);

            AuditLog::log('admin', Auth::currentUserId(), 'security_scan_executed',
                json_encode($summary, JSON_UNESCAPED_UNICODE));
            $lastScan = ['created_at' => date('Y-m-d H:i:s')];
        }

        $this->render('admin/security_scan', [
            'results' => $results,
            'summary' => $summary,
            'lastScan' => $lastScan,
        ]);
    }

    public function securityScanIgnore(): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/admin/security-scan'); return; }

        $checkName = $_POST['check_name'] ?? '';
        $action = $_POST['action'] ?? 'ignore';

        if ($checkName) {
            if ($action === 'unignore') {
                SecurityScanService::unignoreCheck($checkName);
            } else {
                SecurityScanService::ignoreCheck($checkName);
            }
        }

        $this->redirect('/admin/security-scan');
    }

    // ── Duplicates Report (F2) ────────────────────────────

    public function duplicatesReport(): void
    {
        $selectedStatus = $_GET['status'] ?? null;
        if ($selectedStatus === '') $selectedStatus = null;

        $candidates = \App\Models\DuplicateCandidate::findAllGlobal($selectedStatus);
        $scanResult = Session::getFlash('scan_result');

        $this->render('admin/duplicates_report', [
            'candidates' => $candidates,
            'selectedStatus' => $selectedStatus,
            'scanResult' => $scanResult,
        ]);
    }

    public function duplicatesScan(): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/admin/duplicates'); return; }

        $result = \App\Services\DuplicateDetectionService::batchScanAll();
        Session::flash('scan_result', $result);
        $this->redirect('/admin/duplicates');
    }

    public function duplicateReview(int $id): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/admin/duplicates'); return; }
        $status = $_POST['status'] ?? '';

        if (!in_array($status, ['dismissed', 'confirmed'])) {
            $this->redirect('/admin/duplicates');
            return;
        }

        $candidate = \App\Models\DuplicateCandidate::findById($id);
        if ($candidate) {
            \App\Models\DuplicateCandidate::updateStatus($id, $status, 'admin', (int) Session::get('user_id'));
        }

        $this->redirect('/admin/duplicates');
    }

    // ── Analytics ────────────────────────────────────────────────────────

    public function analytics(): void
    {
        // Login activity (last 30 days)
        $dailyActivity = AuditLog::getDailyActivityStats(30);

        // Invoice monthly stats (reuse existing method)
        $monthlyStats = Invoice::getMonthlyStats(6, true);

        // Office ranking
        $officeRanking = Office::getRankingStats(10);

        // KSeF usage stats
        $ksefActiveClients = KsefConfig::findAllActive();
        $ksefActiveCount = count($ksefActiveClients);
        $totalClients = Client::countAll();

        // New: System growth
        $clientGrowth = Client::getMonthlyGrowth(12);
        $officeGrowth = Office::getMonthlyGrowth(12);

        // New: Client activity breakdown
        $activityBreakdown = Client::getActivityBreakdown();

        // New: Invoice volume (sales + purchases)
        $purchaseMonthly = Invoice::getMonthlyCountAll(6);
        $salesMonthly = IssuedInvoice::getMonthlyCountAll(6);

        // New: KSeF health
        $ksefHealth = KsefOperationLog::getMonthlyHealth(6);

        // New: Feature adoption
        $db = \App\Core\Database::getInstance();
        $featureAdoption = [
            'sales' => (int) ($db->fetchOne("SELECT COUNT(DISTINCT client_id) as cnt FROM issued_invoices")['cnt'] ?? 0),
            'ksef_import' => $ksefActiveCount,
            'bank_export' => (int) ($db->fetchOne("SELECT COUNT(DISTINCT client_id) as cnt FROM invoices WHERE is_paid = 2")['cnt'] ?? 0),
            'total' => $totalClients,
        ];

        // New: Rejection rate per office
        $rejectionByOffice = Invoice::getRejectionRateByOffice(15);

        $this->render('admin/analytics', [
            'dailyActivity'     => $dailyActivity,
            'monthlyStats'      => $monthlyStats,
            'officeRanking'     => $officeRanking,
            'ksefActiveCount'   => $ksefActiveCount,
            'totalClients'      => $totalClients,
            'clientGrowth'      => $clientGrowth,
            'officeGrowth'      => $officeGrowth,
            'activityBreakdown' => $activityBreakdown,
            'purchaseMonthly'   => $purchaseMonthly,
            'salesMonthly'      => $salesMonthly,
            'ksefHealth'        => $ksefHealth,
            'featureAdoption'   => $featureAdoption,
            'rejectionByOffice' => $rejectionByOffice,
        ]);
    }
}
