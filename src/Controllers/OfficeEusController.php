<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\ModuleAccess;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\EusConfig;
use App\Models\EusDocument;
use App\Models\OfficeEmployee;
use App\Services\EusApiService;
use App\Services\EusCertificateService;
use App\Services\EusProfilZaufanyService;

/**
 * Office UI for the e-Urząd Skarbowy module.
 *
 * Every method enforces:
 *   1. Auth::requireOfficeOrEmployee() (constructor)
 *   2. ModuleAccess::requireModule('eus') — abort 403 / redirect when
 *      master admin disabled the module for this office
 *   3. requireClientForOffice($clientId) tenant gate (also enforces
 *      the office_employee assignment filter — same as HR endpoints)
 *
 * NO direct findById() calls — every read goes through the office-
 * scoped accessors (Client::findById + office_id check, or
 * EusConfig::findByClientForOffice).
 */
class OfficeEusController extends Controller
{
    public function __construct()
    {
        Auth::requireOfficeOrEmployee();
        ModuleAccess::requireModule('eus');
    }

    // ─── Tenant gate (mirrors OfficeController) ──────────

    /**
     * Returns the client row when both:
     *   - it belongs to the current office
     *   - for office_employee, it's in the assignment filter
     * Redirects + returns null on mismatch.
     */
    private function requireClientForOffice(int $clientId, string $redirectUrl = '/office/eus'): ?array
    {
        $client = Client::findById($clientId);
        $officeId = (int) Session::get('office_id');

        if (!$client || (int) ($client['office_id'] ?? 0) !== $officeId) {
            $this->redirect($redirectUrl);
            return null;
        }

        if (Auth::isEmployee()) {
            $employeeId = (int) Session::get('employee_id');
            $assigned = OfficeEmployee::getAssignedClientIds($employeeId);
            if (!in_array($clientId, $assigned, true)) {
                $this->redirect($redirectUrl);
                return null;
            }
        }
        return $client;
    }

    // ─── Endpoints ──────────────────────────────────────

    /**
     * GET /office/eus — list of clients with e-US status (UPL-1 traffic
     * light, cert expiry, last poll). Office_employees see only their
     * assigned clients.
     */
    public function index(): void
    {
        $officeId = (int) Session::get('office_id');
        $configs  = EusConfig::findAllForOffice($officeId);

        if (Auth::isEmployee()) {
            $employeeId = (int) Session::get('employee_id');
            $assigned = OfficeEmployee::getAssignedClientIds($employeeId);
            $configs = array_values(array_filter(
                $configs,
                fn($c) => in_array((int) $c['client_id'], $assigned, true)
            ));
        }

        // Clients of this office WITHOUT any e-US config — shown as
        // "skonfiguruj" rows so office admin can opt them in.
        $clients = Client::findByOffice($officeId, true);
        if (Auth::isEmployee()) {
            $employeeId = (int) Session::get('employee_id');
            $assigned = OfficeEmployee::getAssignedClientIds($employeeId);
            $clients = array_values(array_filter(
                $clients,
                fn($c) => in_array((int) $c['id'], $assigned, true)
            ));
        }
        $configuredClientIds = array_column($configs, 'client_id');
        $unconfigured = array_values(array_filter(
            $clients,
            fn($c) => !in_array((int) $c['id'], $configuredClientIds, true)
        ));

        $this->render('office/eus_index', [
            'configs'      => $configs,
            'unconfigured' => $unconfigured,
        ]);
    }

    /**
     * GET /office/eus/{clientId}/configure — render the configuration
     * form. Auth method, environment, UPL-1 metadata, scope checkboxes.
     */
    public function configureForm(string $clientId): void
    {
        $cid = (int) $clientId;
        $client = $this->requireClientForOffice($cid);
        if ($client === null) return;

        $config = EusConfig::findByClient($cid);
        $pzService = new EusProfilZaufanyService();

        $this->render('office/eus_configure', [
            'client'        => $client,
            'config'        => $config,
            'pz_available'  => $pzService->isAvailable(),
        ]);
    }

    /**
     * POST /office/eus/{clientId}/configure — save toggles + UPL-1.
     * Cert / passphrase / PZ artefact go through dedicated endpoints
     * (uploadCert, pzCallback) — NEVER via this form so $_POST cannot
     * smuggle a privilege field.
     */
    public function configureSave(string $clientId): void
    {
        if (!$this->validateCsrf()) {
            $this->redirect('/office/eus');
            return;
        }
        $cid = (int) $clientId;
        $client = $this->requireClientForOffice($cid);
        if ($client === null) return;

        $officeId = (int) Session::get('office_id');

        // Hand-pick only fields in EusConfig::FILLABLE — the model
        // also re-filters, but doing it here makes the audit log
        // entry record exactly what the office submitted.
        $data = [
            'environment'           => $_POST['environment']            ?? 'mock',
            'auth_method'           => $_POST['auth_method']            ?? 'cert_qual',
            'upl1_status'           => $_POST['upl1_status']            ?? 'none',
            'upl1_valid_from'       => $_POST['upl1_valid_from']        ?? null,
            'upl1_valid_to'         => $_POST['upl1_valid_to']          ?? null,
            'upl1_scope'            => is_array($_POST['upl1_scope'] ?? null)
                                        ? implode(',', $_POST['upl1_scope'])
                                        : ($_POST['upl1_scope'] ?? null),
            'bramka_b_enabled'      => !empty($_POST['bramka_b_enabled']) ? 1 : 0,
            'bramka_c_enabled'      => !empty($_POST['bramka_c_enabled']) ? 1 : 0,
            'auto_submit_eus'       => !empty($_POST['auto_submit_eus']) ? 1 : 0,
            'poll_incoming_enabled' => !empty($_POST['poll_incoming_enabled']) ? 1 : 0,
            'poll_interval_minutes' => max(15, min(60, (int) ($_POST['poll_interval_minutes'] ?? 15))),
        ];

        EusConfig::upsertForOffice($cid, $officeId, $data);

        AuditLog::log(
            Auth::isEmployee() ? 'office_employee' : 'office',
            (int) (Auth::isEmployee() ? Session::get('employee_id') : Session::get('office_id')),
            'eus_config_saved',
            "client #{$cid}: env={$data['environment']}, auth={$data['auth_method']}, B={$data['bramka_b_enabled']}, C={$data['bramka_c_enabled']}",
            'eus_config'
        );

        Session::flash('success', 'Konfiguracja e-US zapisana.');
        $this->redirect("/office/eus/{$cid}/configure");
    }

    /**
     * POST /office/eus/{clientId}/test-connection — exercises
     * EusApiService::healthCheck{B,C} for the configured environment.
     * Pure read; no DB writes besides audit log.
     */
    public function testConnection(string $clientId): void
    {
        if (!$this->validateCsrf()) {
            $this->redirect('/office/eus');
            return;
        }
        $cid = (int) $clientId;
        $client = $this->requireClientForOffice($cid);
        if ($client === null) return;

        $config = EusConfig::findByClient($cid);
        $env    = (string) ($config['environment'] ?? 'mock');

        $api = new EusApiService();
        $b = !empty($config['bramka_b_enabled']) ? $api->healthCheckB($env) : null;
        $c = !empty($config['bramka_c_enabled']) ? $api->healthCheckC($env) : null;

        AuditLog::log(
            Auth::isEmployee() ? 'office_employee' : 'office',
            (int) (Auth::isEmployee() ? Session::get('employee_id') : Session::get('office_id')),
            'eus_test_connection',
            "client #{$cid} env={$env} B=" . ($b ? ($b['ok'] ? 'OK' : 'FAIL') : 'skip')
                . " C=" . ($c ? ($c['ok'] ? 'OK' : 'FAIL') : 'skip'),
            'eus_config'
        );

        $messages = [];
        if ($b) $messages[] = 'Bramka B: ' . $b['message'];
        if ($c) $messages[] = 'Bramka C: ' . $c['message'];
        if (empty($messages)) {
            $messages[] = 'Włącz przynajmniej Bramkę B lub C aby przeprowadzić test.';
        }

        Session::flash('success', implode(' • ', $messages));
        $this->redirect("/office/eus/{$cid}/configure");
    }
}
