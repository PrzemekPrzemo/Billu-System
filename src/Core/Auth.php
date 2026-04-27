<?php

namespace App\Core;

use App\Models\Client;
use App\Models\ClientEmployee;
use App\Models\Office;
use App\Models\OfficeEmployee;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Models\AuditLog;

class Auth
{
    private const PASSWORD_MIN_LENGTH = 12;

    public static function validatePasswordStrength(string $password): array
    {
        $errors = [];
        if (strlen($password) < self::PASSWORD_MIN_LENGTH) {
            $errors[] = 'password_min_length';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'password_lowercase';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'password_uppercase';
        }
        if (!preg_match('/\d/', $password)) {
            $errors[] = 'password_digit';
        }
        if (!preg_match('/[\W_]/', $password)) {
            $errors[] = 'password_special';
        }
        return $errors;
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function generateRandomPassword(int $length = 18): string
    {
        $lower = 'abcdefghijklmnopqrstuvwxyz';
        $upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $digits = '0123456789';
        $special = '!@#$%^&*()-_=+';

        // Ensure at least one of each type
        $password = $lower[random_int(0, strlen($lower) - 1)]
            . $upper[random_int(0, strlen($upper) - 1)]
            . $digits[random_int(0, strlen($digits) - 1)]
            . $special[random_int(0, strlen($special) - 1)];

        $all = $lower . $upper . $digits . $special;
        for ($i = 4; $i < $length; $i++) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }

        return str_shuffle($password);
    }

    // ── Admin Login ────────────────────────────────

    public static function loginAdmin(string $username, string $password): bool|string
    {
        $user = User::findByUsername($username);
        if (!$user || !$user['is_active']) {
            self::logLoginAttempt('admin', 0, false);
            return false;
        }

        if (!self::verifyPassword($password, $user['password_hash'])) {
            self::logLoginAttempt('admin', $user['id'], false);
            return false;
        }

        // Check 2FA — but skip prompt if request comes from a trusted device.
        if (!empty($user['two_factor_enabled']) && !self::isTrustedDevice('admin', (int) $user['id'])) {
            Session::set('2fa_pending_user_type', 'admin');
            Session::set('2fa_pending_user_id', $user['id']);
            self::logLoginAttempt('admin', $user['id'], true);
            return 'require_2fa';
        }

        // Check if 2FA is required for admins
        if (self::is2faRequired('admin') && empty($user['two_factor_enabled'])) {
            Session::set('2fa_setup_user_type', 'admin');
            Session::set('2fa_setup_user_id', $user['id']);
            self::logLoginAttempt('admin', $user['id'], true);
            return 'require_2fa_setup';
        }

        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);

        Session::set('user_id', $user['id']);
        Session::set('user_type', 'admin');
        Session::set('user_role', $user['role']);
        Session::set('username', $user['username']);
        Session::registerSession('admin', $user['id']);
        self::logLoginAttempt('admin', $user['id'], true);

        return true;
    }

    // ── Client Login ───────────────────────────────

    public static function loginClient(string $nip, string $password): bool|string
    {
        $client = Client::findByNip($nip);
        if (!$client) {
            self::logLoginAttempt('client', 0, false);
            return false;
        }
        if (!$client['is_active']) {
            self::logLoginAttempt('client', $client['id'], false);
            // Store office contact info for blocked page
            if (!empty($client['office_id'])) {
                $office = Office::findById((int) $client['office_id']);
                if ($office) {
                    Session::flash('blocked_office_name', $office['name']);
                    Session::flash('blocked_office_email', $office['email']);
                    Session::flash('blocked_office_phone', $office['phone'] ?? '');
                }
            }
            Session::flash('blocked_account_type', 'client');
            return 'account_deactivated';
        }

        if (!self::verifyPassword($password, $client['password_hash'])) {
            self::logLoginAttempt('client', $client['id'], false);
            return false;
        }

        // Check 2FA — but skip prompt if request comes from a trusted device.
        if (!empty($client['two_factor_enabled']) && !self::isTrustedDevice('client', (int) $client['id'])) {
            Session::set('2fa_pending_user_type', 'client');
            Session::set('2fa_pending_user_id', $client['id']);
            self::logLoginAttempt('client', $client['id'], true);
            return 'require_2fa';
        }

        // Check if 2FA is required
        if (self::is2faRequired('client') && empty($client['two_factor_enabled'])) {
            Session::set('2fa_setup_user_type', 'client');
            Session::set('2fa_setup_user_id', $client['id']);
            self::logLoginAttempt('client', $client['id'], true);
            return 'require_2fa_setup';
        }

        // Force password change (bulk import)
        if ($client['force_password_change']) {
            Session::set('client_id', $client['id']);
            Session::set('user_type', 'client');
            Session::set('force_password_change', true);
            self::logLoginAttempt('client', $client['id'], true);
            return 'force_password_change';
        }

        // Check password expiry
        $db = Database::getInstance();
        $setting = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'password_expiry_days'");
        $expiryDays = $setting ? (int) $setting['setting_value'] : 90;

        $passwordAge = (new \DateTime($client['password_changed_at']))->diff(new \DateTime());
        if ($passwordAge->days >= $expiryDays) {
            Session::set('client_id', $client['id']);
            Session::set('user_type', 'client');
            Session::set('force_password_change', true);
            return 'password_expired';
        }

        // Check privacy policy acceptance
        $privacyEnabled = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'privacy_policy_enabled'");
        if ($privacyEnabled && $privacyEnabled['setting_value'] === '1' && !$client['privacy_accepted']) {
            Session::set('client_id', $client['id']);
            Session::set('user_type', 'client');
            Session::set('require_privacy_acceptance', true);
            self::logLoginAttempt('client', $client['id'], true);
            return 'require_privacy';
        }

        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);

        self::setClientSession($client);
        self::logLoginAttempt('client', $client['id'], true);
        return true;
    }

    public static function setClientSession(array $client): void
    {
        Session::set('client_id', $client['id']);
        Session::set('user_type', 'client');
        Session::set('client_nip', $client['nip']);
        Session::set('client_name', $client['company_name']);
        Session::set('client_language', $client['language']);
        Session::registerSession('client', $client['id']);
        Client::updateLastLogin($client['id']);
    }

    // ── Office Login ───────────────────────────────

    public static function loginOffice(string $identifier, string $password): bool|string
    {
        // Try email first, then NIP for backwards compatibility
        $office = Office::findByEmail($identifier);
        if (!$office) {
            $office = Office::findByNip($identifier);
        }
        if (!$office) {
            self::logLoginAttempt('office', 0, false);
            return false;
        }
        if (!$office['is_active']) {
            self::logLoginAttempt('office', $office['id'], false);
            Session::flash('blocked_account_type', 'office');
            return 'account_deactivated';
        }

        if (!self::verifyPassword($password, $office['password_hash'])) {
            self::logLoginAttempt('office', $office['id'], false);
            return false;
        }

        // Check 2FA — but skip prompt if request comes from a trusted device.
        if (!empty($office['two_factor_enabled']) && !self::isTrustedDevice('office', (int) $office['id'])) {
            Session::set('2fa_pending_user_type', 'office');
            Session::set('2fa_pending_user_id', $office['id']);
            self::logLoginAttempt('office', $office['id'], true);
            return 'require_2fa';
        }

        // Check if 2FA is required
        if (self::is2faRequired('office') && empty($office['two_factor_enabled'])) {
            Session::set('2fa_setup_user_type', 'office');
            Session::set('2fa_setup_user_id', $office['id']);
            self::logLoginAttempt('office', $office['id'], true);
            return 'require_2fa_setup';
        }

        // Check password expiry
        $db = Database::getInstance();
        $setting = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'password_expiry_days'");
        $expiryDays = $setting ? (int) $setting['setting_value'] : 90;

        $passwordAge = (new \DateTime($office['password_changed_at']))->diff(new \DateTime());
        if ($passwordAge->days >= $expiryDays) {
            Session::set('office_id', $office['id']);
            Session::set('user_type', 'office');
            Session::set('force_password_change', true);
            return 'password_expired';
        }

        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);

        Session::set('office_id', $office['id']);
        Session::set('user_type', 'office');
        Session::set('office_nip', $office['nip']);
        Session::set('office_name', $office['name']);
        Session::set('office_language', $office['language']);
        Session::registerSession('office', $office['id']);

        Office::updateLastLogin($office['id']);
        self::logLoginAttempt('office', $office['id'], true);
        return true;
    }

    // ── Employee Login ─────────────────────────────

    public static function loginEmployee(string $email, string $password): bool|string
    {
        $employee = OfficeEmployee::findByEmail($email);
        if (!$employee) {
            self::logLoginAttempt('employee', 0, false);
            return false;
        }
        if (!$employee['is_active']) {
            self::logLoginAttempt('employee', $employee['id'], false);
            Session::flash('blocked_account_type', 'employee');
            return 'account_deactivated';
        }
        if (!$employee['office_is_active']) {
            self::logLoginAttempt('employee', $employee['id'], false);
            Session::flash('blocked_account_type', 'employee');
            return 'account_deactivated';
        }
        if (empty($employee['password_hash'])) {
            self::logLoginAttempt('employee', $employee['id'], false);
            return false;
        }

        if (!self::verifyPassword($password, $employee['password_hash'])) {
            self::logLoginAttempt('employee', $employee['id'], false);
            return false;
        }

        // Force password change
        if ($employee['force_password_change']) {
            Session::set('employee_id', $employee['id']);
            Session::set('user_type', 'employee');
            Session::set('office_id', $employee['office_id']);
            Session::set('force_password_change', true);
            self::logLoginAttempt('employee', $employee['id'], true);
            return 'force_password_change';
        }

        // Check password expiry
        if (!empty($employee['password_changed_at'])) {
            $db = Database::getInstance();
            $setting = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'password_expiry_days'");
            $expiryDays = $setting ? (int) $setting['setting_value'] : 90;

            $passwordAge = (new \DateTime($employee['password_changed_at']))->diff(new \DateTime());
            if ($passwordAge->days >= $expiryDays) {
                Session::set('employee_id', $employee['id']);
                Session::set('user_type', 'employee');
                Session::set('office_id', $employee['office_id']);
                Session::set('force_password_change', true);
                return 'password_expired';
            }
        }

        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);

        Session::set('employee_id', $employee['id']);
        Session::set('user_type', 'employee');
        Session::set('employee_name', $employee['name']);
        Session::set('office_id', $employee['office_id']);
        Session::set('office_name', $employee['office_name']);
        Session::set('office_nip', $employee['office_nip']);
        Session::set('office_language', $employee['office_language']);
        Session::registerSession('employee', $employee['id']);

        OfficeEmployee::updateLastLogin($employee['id']);
        self::logLoginAttempt('employee', $employee['id'], true);
        return true;
    }

    /**
     * Login a client-side employee (worker hired by a client of an office).
     * Distinct from loginEmployee() which authenticates accounting-firm staff.
     */
    public static function loginClientEmployee(string $email, string $password): bool|string
    {
        $employee = ClientEmployee::findByLoginEmail($email);
        if (!$employee) {
            self::logLoginAttempt('client_employee', 0, false);
            return false;
        }
        if (empty($employee['can_login']) || empty($employee['password_hash'])) {
            self::logLoginAttempt('client_employee', $employee['id'], false);
            return false;
        }
        if (!$employee['is_active'] || !$employee['client_is_active']) {
            self::logLoginAttempt('client_employee', $employee['id'], false);
            Session::flash('blocked_account_type', 'client_employee');
            return 'account_deactivated';
        }

        if (!self::verifyPassword($password, $employee['password_hash'])) {
            self::logLoginAttempt('client_employee', $employee['id'], false);
            return false;
        }

        // 2FA verify — skip prompt if request comes from a trusted device.
        if (!empty($employee['two_factor_enabled']) && !self::isTrustedDevice('client_employee', (int) $employee['id'])) {
            Session::set('2fa_pending_user_type', 'client_employee');
            Session::set('2fa_pending_user_id', $employee['id']);
            self::logLoginAttempt('client_employee', $employee['id'], true);
            return 'require_2fa';
        }

        // 2FA enforcement (master admin turned 2fa_required_client_employee on)
        if (self::is2faRequired('client_employee') && empty($employee['two_factor_enabled'])) {
            Session::set('2fa_setup_user_type', 'client_employee');
            Session::set('2fa_setup_user_id', $employee['id']);
            self::logLoginAttempt('client_employee', $employee['id'], true);
            return 'require_2fa_setup';
        }

        if (!empty($employee['force_password_change'])) {
            Session::set('client_employee_id', $employee['id']);
            Session::set('client_employee_client_id', $employee['client_id']);
            Session::set('user_type', 'client_employee');
            Session::set('force_password_change', true);
            self::logLoginAttempt('client_employee', $employee['id'], true);
            return 'force_password_change';
        }

        // Regenerate session ID to prevent fixation
        session_regenerate_id(true);

        Session::set('client_employee_id', $employee['id']);
        Session::set('client_employee_client_id', $employee['client_id']);
        Session::set('client_employee_office_id', $employee['office_id'] ?? null);
        Session::set('client_employee_name', trim($employee['first_name'] . ' ' . $employee['last_name']));
        Session::set('client_employee_company', $employee['company_name'] ?? '');
        Session::set('user_type', 'client_employee');
        Session::registerSession('client_employee', $employee['id']);

        ClientEmployee::updateLastLogin($employee['id']);
        self::logLoginAttempt('client_employee', $employee['id'], true);
        return true;
    }

    // ── Impersonation ──────────────────────────────

    public static function impersonate(string $targetType, int $targetId): bool
    {
        if (!self::isAdmin()) {
            return false;
        }

        // Store original admin session
        Session::set('impersonator_id', Session::get('user_id'));
        Session::set('impersonator_type', 'admin');
        Session::set('impersonator_username', Session::get('username'));

        if ($targetType === 'client') {
            $client = Client::findById($targetId);
            if (!$client) return false;
            Session::set('user_type', 'client');
            Session::set('client_id', $client['id']);
            Session::set('client_nip', $client['nip']);
            Session::set('client_name', $client['company_name']);
            Session::set('client_language', $client['language']);
            AuditLog::log('admin', Session::get('impersonator_id'), 'impersonate', "Impersonating client: {$client['company_name']} (NIP: {$client['nip']})", 'client', $targetId);
        } elseif ($targetType === 'office') {
            $office = Office::findById($targetId);
            if (!$office) return false;
            Session::set('user_type', 'office');
            Session::set('office_id', $office['id']);
            Session::set('office_nip', $office['nip']);
            Session::set('office_name', $office['name']);
            Session::set('office_language', $office['language']);
            AuditLog::log('admin', Session::get('impersonator_id'), 'impersonate', "Impersonating office: {$office['name']} (NIP: {$office['nip']})", 'office', $targetId);
        }

        return true;
    }

    public static function stopImpersonation(): void
    {
        $adminId = Session::get('impersonator_id');
        $adminUsername = Session::get('impersonator_username');

        if (!$adminId) {
            return;
        }

        // Clear impersonation data
        Session::remove('impersonator_id');
        Session::remove('impersonator_type');
        Session::remove('impersonator_username');
        Session::remove('client_id');
        Session::remove('client_nip');
        Session::remove('client_name');
        Session::remove('client_language');
        Session::remove('office_id');
        Session::remove('office_nip');
        Session::remove('office_name');
        Session::remove('office_language');

        // Restore admin session
        Session::set('user_type', 'admin');
        Session::set('user_id', $adminId);
        Session::set('username', $adminUsername);
        Session::set('user_role', 'superadmin');

        AuditLog::log('admin', $adminId, 'stop_impersonation', 'Returned to admin session');
    }

    public static function isImpersonating(): bool
    {
        return Session::has('impersonator_id');
    }

    // ── Employee Impersonation (as client) ────────

    public static function employeeImpersonateClient(int $clientId): bool
    {
        if (!self::isEmployee()) {
            return false;
        }

        $employeeId = Session::get('employee_id');
        $assignedIds = OfficeEmployee::getAssignedClientIds($employeeId);
        if (!in_array($clientId, $assignedIds)) {
            return false;
        }

        $client = Client::findById($clientId);
        if (!$client) return false;

        // Store employee session for return
        Session::set('impersonator_id', $employeeId);
        Session::set('impersonator_type', 'employee');
        Session::set('impersonator_name', Session::get('employee_name'));
        Session::set('impersonator_office_id', Session::get('office_id'));
        Session::set('impersonator_office_name', Session::get('office_name'));
        Session::set('impersonator_office_nip', Session::get('office_nip'));
        Session::set('impersonator_office_language', Session::get('office_language'));

        // Switch to client context
        Session::set('user_type', 'client');
        Session::set('client_id', $client['id']);
        Session::set('client_nip', $client['nip']);
        Session::set('client_name', $client['company_name']);
        Session::set('client_language', $client['language']);

        AuditLog::log('employee', $employeeId, 'employee_impersonate_client',
            "Employee acting as client: {$client['company_name']} (NIP: {$client['nip']})",
            'client', $clientId);

        return true;
    }

    public static function stopEmployeeImpersonation(): void
    {
        $employeeId = Session::get('impersonator_id');
        $impersonatorType = Session::get('impersonator_type');

        if (!$employeeId || $impersonatorType !== 'employee') {
            return;
        }

        // Clear client data
        Session::remove('client_id');
        Session::remove('client_nip');
        Session::remove('client_name');
        Session::remove('client_language');

        // Restore employee session
        Session::set('user_type', 'employee');
        Session::set('employee_id', $employeeId);
        Session::set('employee_name', Session::get('impersonator_name'));
        Session::set('office_id', Session::get('impersonator_office_id'));
        Session::set('office_name', Session::get('impersonator_office_name'));
        Session::set('office_nip', Session::get('impersonator_office_nip'));
        Session::set('office_language', Session::get('impersonator_office_language'));

        // Clear impersonation data
        Session::remove('impersonator_id');
        Session::remove('impersonator_type');
        Session::remove('impersonator_name');
        Session::remove('impersonator_office_id');
        Session::remove('impersonator_office_name');
        Session::remove('impersonator_office_nip');
        Session::remove('impersonator_office_language');

        AuditLog::log('employee', $employeeId, 'employee_stop_impersonation', 'Returned to employee session');
    }

    public static function isEmployeeImpersonating(): bool
    {
        return Session::get('impersonator_type') === 'employee';
    }

    // ── State Checks ───────────────────────────────

    public static function isLoggedIn(): bool
    {
        return Session::has('user_type');
    }

    public static function isAdmin(): bool
    {
        return Session::get('user_type') === 'admin';
    }

    public static function isClient(): bool
    {
        return Session::get('user_type') === 'client';
    }

    public static function isOffice(): bool
    {
        return Session::get('user_type') === 'office';
    }

    public static function isEmployee(): bool
    {
        return Session::get('user_type') === 'employee';
    }

    public static function isClientEmployee(): bool
    {
        return Session::get('user_type') === 'client_employee';
    }

    public static function isOfficeOrEmployee(): bool
    {
        return self::isOffice() || self::isEmployee();
    }

    public static function requireAdmin(): void
    {
        if (!self::isLoggedIn() || !self::isAdmin()) {
            header('Location: /masterLogin');
            exit;
        }
    }

    public static function requireClientEmployee(): void
    {
        if (!self::isLoggedIn() || !self::isClientEmployee()) {
            header('Location: /employee/login');
            exit;
        }
        if (Session::get('force_password_change')) {
            header('Location: /employee/change-password');
            exit;
        }
    }

    public static function requireClient(): void
    {
        if (!self::isLoggedIn() || !self::isClient()) {
            header('Location: /login');
            exit;
        }
        if (Session::get('force_password_change')) {
            header('Location: /change-password');
            exit;
        }
        if (Session::get('require_privacy_acceptance')) {
            header('Location: /accept-privacy');
            exit;
        }
        // IP whitelist check
        $clientId = Session::get('client_id');
        if ($clientId && !self::isIpAllowed($clientId)) {
            self::logout();
            Session::start();
            Session::flash('error', 'ip_not_allowed');
            header('Location: /login');
            exit;
        }
    }

    public static function isIpAllowed(int $clientId): bool
    {
        try {
            $client = Client::findById($clientId);
            if (!$client || empty($client['ip_whitelist'])) {
                return true; // No whitelist = all IPs allowed
            }

            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            if (empty($ip)) return true;

            $allowed = array_map('trim', explode(',', $client['ip_whitelist']));
            $allowed = array_filter($allowed);

            if (empty($allowed)) return true;

            foreach ($allowed as $entry) {
                if ($entry === $ip) return true;
                // CIDR support
                if (str_contains($entry, '/') && self::ipInCidr($ip, $entry)) return true;
            }

            return false;
        } catch (\Exception $e) {
            return true;
        }
    }

    private static function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr, 2);
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - (int)$bits);
        return ($ip & $mask) === ($subnet & $mask);
    }

    public static function requireOffice(): void
    {
        if (!self::isLoggedIn() || !self::isOffice()) {
            header('Location: /login');
            exit;
        }
        if (Session::get('force_password_change')) {
            header('Location: /change-password');
            exit;
        }
    }

    public static function requireOfficeOrEmployee(): void
    {
        if (!self::isLoggedIn() || !self::isOfficeOrEmployee()) {
            header('Location: /login');
            exit;
        }
        if (Session::get('force_password_change')) {
            header('Location: /change-password');
            exit;
        }
    }

    public static function logout(): void
    {
        Session::destroy();
    }

    public static function currentUserId(): ?int
    {
        if (self::isAdmin()) return Session::get('user_id');
        if (self::isClient()) return Session::get('client_id');
        if (self::isOffice()) return Session::get('office_id');
        if (self::isEmployee()) return Session::get('employee_id');
        if (self::isClientEmployee()) return Session::get('client_employee_id');
        return null;
    }

    public static function currentUserType(): string
    {
        return Session::get('user_type', 'unknown');
    }

    // ── Two-Factor Authentication ─────────────────

    public static function is2faRequired(string $userType): bool
    {
        try {
            $db = Database::getInstance();
            $enabled = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = '2fa_enabled'");
            if (!$enabled || $enabled['setting_value'] !== '1') {
                return false;
            }

            // Per-type setting (2fa_required_admin / _client / _office) takes precedence.
            $perTypeKey = '2fa_required_' . $userType;
            $perType = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = ?", [$perTypeKey]);
            if ($perType && $perType['setting_value'] === '1') {
                return true;
            }

            // Fallback: global enforcement.
            $global = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = '2fa_required'");
            return $global && $global['setting_value'] === '1';
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function is2faEnabled(): bool
    {
        try {
            $db = Database::getInstance();
            $enabled = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = '2fa_enabled'");
            return $enabled && $enabled['setting_value'] === '1';
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function completeTwoFactorLogin(): bool
    {
        $userType = Session::get('2fa_pending_user_type');
        $userId = Session::get('2fa_pending_user_id');

        if (!$userType || !$userId) return false;

        // Clear pending 2FA data
        Session::remove('2fa_pending_user_type');
        Session::remove('2fa_pending_user_id');

        session_regenerate_id(true);

        if ($userType === 'admin') {
            $user = User::findById($userId);
            if (!$user) return false;
            Session::set('user_id', $user['id']);
            Session::set('user_type', 'admin');
            Session::set('user_role', $user['role']);
            Session::set('username', $user['username']);
            Session::registerSession('admin', $user['id']);
            return true;
        }

        if ($userType === 'client') {
            $client = Client::findById($userId);
            if (!$client) return false;

            // Check force_password_change
            if ($client['force_password_change']) {
                Session::set('client_id', $client['id']);
                Session::set('user_type', 'client');
                Session::set('force_password_change', true);
                return true;
            }

            // Check password expiry
            if (self::isPasswordExpired($client['password_changed_at'])) {
                Session::set('client_id', $client['id']);
                Session::set('user_type', 'client');
                Session::set('force_password_change', true);
                return true;
            }

            // Check privacy policy
            $db = Database::getInstance();
            $privacyEnabled = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'privacy_policy_enabled'");
            if ($privacyEnabled && $privacyEnabled['setting_value'] === '1' && !$client['privacy_accepted']) {
                Session::set('client_id', $client['id']);
                Session::set('user_type', 'client');
                Session::set('require_privacy_acceptance', true);
                return true;
            }

            self::setClientSession($client);
            return true;
        }

        if ($userType === 'office') {
            $office = Office::findById($userId);
            if (!$office) return false;

            // Check password expiry
            if (self::isPasswordExpired($office['password_changed_at'])) {
                Session::set('office_id', $office['id']);
                Session::set('user_type', 'office');
                Session::set('force_password_change', true);
                return true;
            }

            Session::set('office_id', $office['id']);
            Session::set('user_type', 'office');
            Session::set('office_nip', $office['nip']);
            Session::set('office_name', $office['name']);
            Session::set('office_language', $office['language']);
            Session::registerSession('office', $office['id']);
            Office::updateLastLogin($office['id']);
            return true;
        }

        return false;
    }

    public static function complete2faSetupLogin(): bool
    {
        $userType = Session::get('2fa_setup_user_type');
        $userId = Session::get('2fa_setup_user_id');

        if (!$userType || !$userId) return false;

        Session::remove('2fa_setup_user_type');
        Session::remove('2fa_setup_user_id');

        session_regenerate_id(true);

        if ($userType === 'admin') {
            $user = User::findById($userId);
            if (!$user) return false;
            Session::set('user_id', $user['id']);
            Session::set('user_type', 'admin');
            Session::set('user_role', $user['role']);
            Session::set('username', $user['username']);
            Session::registerSession('admin', $user['id']);
            return true;
        }

        if ($userType === 'client') {
            $client = Client::findById($userId);
            if (!$client) return false;

            // Check password expiry after 2FA setup
            if ($client['force_password_change'] || self::isPasswordExpired($client['password_changed_at'])) {
                Session::set('client_id', $client['id']);
                Session::set('user_type', 'client');
                Session::set('force_password_change', true);
                return true;
            }

            self::setClientSession($client);
            return true;
        }

        if ($userType === 'office') {
            $office = Office::findById($userId);
            if (!$office) return false;

            // Check password expiry after 2FA setup
            if (self::isPasswordExpired($office['password_changed_at'])) {
                Session::set('office_id', $office['id']);
                Session::set('user_type', 'office');
                Session::set('force_password_change', true);
                return true;
            }

            Session::set('office_id', $office['id']);
            Session::set('user_type', 'office');
            Session::set('office_nip', $office['nip']);
            Session::set('office_name', $office['name']);
            Session::set('office_language', $office['language']);
            Session::registerSession('office', $office['id']);
            Office::updateLastLogin($office['id']);
            return true;
        }

        return false;
    }

    private static function isPasswordExpired(?string $passwordChangedAt): bool
    {
        if (!$passwordChangedAt) return false;
        try {
            $db = Database::getInstance();
            $setting = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'password_expiry_days'");
            $expiryDays = $setting ? (int) $setting['setting_value'] : 90;
            $passwordAge = (new \DateTime($passwordChangedAt))->diff(new \DateTime());
            return $passwordAge->days >= $expiryDays;
        } catch (\Exception $e) {
            return false;
        }
    }

    // ── Trusted devices (2FA bypass for known browsers) ────

    /**
     * True iff the request carries a valid trusted-device cookie for
     * (userType, userId). Used by login* methods to skip the 2FA prompt.
     */
    public static function isTrustedDevice(string $userType, int $userId): bool
    {
        $token = $_COOKIE[TrustedDevice::COOKIE_NAME] ?? '';
        if ($token === '') {
            return false;
        }
        return TrustedDevice::verify($userType, $userId, (string) $token);
    }

    /**
     * Issue a fresh trusted-device cookie + DB row after a successful 2FA
     * verification when the user opted in via the "remember this device" checkbox.
     */
    public static function issueTrustedDeviceCookie(string $userType, int $userId, int $ttlDays = TrustedDevice::DEFAULT_TTL_DAYS): void
    {
        $token = TrustedDevice::issue($userType, $userId, $ttlDays);
        $expires = time() + $ttlDays * 86400;
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        setcookie(TrustedDevice::COOKIE_NAME, $token, [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /** Clear the trusted-device cookie on the client (logout / device list revoke). */
    public static function clearTrustedDeviceCookie(): void
    {
        if (!isset($_COOKIE[TrustedDevice::COOKIE_NAME])) {
            return;
        }
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        setcookie(TrustedDevice::COOKIE_NAME, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE[TrustedDevice::COOKIE_NAME]);
    }

    // ── Brute Force Protection ─────────────────────

    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES = 15;

    public static function isRateLimited(): bool
    {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            if (empty($ip)) return false;

            $db = Database::getInstance();
            $result = $db->fetchOne(
                "SELECT COUNT(*) as attempts FROM login_history
                 WHERE ip_address = ? AND success = 0
                 AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)",
                [$ip, self::LOCKOUT_MINUTES]
            );

            return ($result['attempts'] ?? 0) >= self::MAX_LOGIN_ATTEMPTS;
        } catch (\Exception $e) {
            return false;
        }
    }

    // ── Login History ──────────────────────────────

    private static function logLoginAttempt(string $userType, int $userId, bool $success): void
    {
        try {
            Database::getInstance()->insert('login_history', [
                'user_type'  => $userType,
                'user_id'    => $userId,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'success'    => $success ? 1 : 0,
            ]);
        } catch (\Exception $e) {
            // Silently fail
        }
    }
}
