<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Core\Language;
use App\Core\TwoFactorAuth;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ClientEmployee;
use App\Models\Office;
use App\Models\OfficeEmployee;
use App\Models\User;
use App\Services\PasswordResetService;

class AuthController extends Controller
{
    // ── Client/Office Login (/login) ───────────────

    public function loginForm(): void
    {
        // Clean up leftover 2FA session data
        $this->clear2faSessionVars();

        if (Auth::isAdmin()) { $this->redirect('/admin'); return; }
        if (Auth::isClient()) { $this->redirect('/client'); return; }
        if (Auth::isOffice()) { $this->redirect('/office'); return; }
        if (Auth::isEmployee()) { $this->redirect('/office'); return; }
        $this->renderWithoutLayout('auth/login');
    }

    public function login(): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/login'); return; }

        if (Auth::isRateLimited()) {
            Session::flash('error', 'login_too_many_attempts');
            $this->redirect('/login');
            return;
        }

        $loginType = $_POST['login_type'] ?? ($_POST['user_type'] ?? 'client');
        $identifier = trim($_POST['identifier'] ?? ($_POST['nip'] ?? ''));
        $password = $_POST['password'] ?? '';

        if (empty($identifier) || empty($password)) {
            Session::flash('error', 'login_required_fields');
            $this->redirect('/login');
            return;
        }

        if ($loginType === 'office') {
            // Unified login: auto-detect office vs employee by email
            $officeAccount = Office::findByEmail($identifier);
            $employeeAccount = OfficeEmployee::findByEmail($identifier);

            if ($officeAccount && $employeeAccount) {
                // Duplicate email across both tables
                Session::flash('error', 'duplicate_email_contact_support');
                $this->redirect('/login');
                return;
            }

            if ($employeeAccount) {
                // Login as employee
                $result = Auth::loginEmployee($identifier, $password);
                if ($result === true) {
                    AuditLog::log('employee', Auth::currentUserId(), 'employee_login', 'Employee login');
                    $this->redirect('/office');
                } elseif ($result === 'force_password_change' || $result === 'password_expired') {
                    $this->redirect('/change-password');
                } elseif ($result === 'account_deactivated') {
                    $this->redirect('/account-blocked');
                } else {
                    Session::flash('error', 'login_failed');
                    $this->redirect('/login');
                }
            } else {
                // Login as office (default)
                $result = Auth::loginOffice($identifier, $password);
                if ($result === true) {
                    AuditLog::log('office', Auth::currentUserId(), 'login', 'Office login');
                    $this->redirect('/office');
                } elseif ($result === 'require_2fa') {
                    $this->redirect('/two-factor-verify');
                } elseif ($result === 'require_2fa_setup') {
                    $this->redirect('/two-factor-setup');
                } elseif ($result === 'password_expired') {
                    $this->redirect('/change-password');
                } elseif ($result === 'account_deactivated') {
                    $this->redirect('/account-blocked');
                } else {
                    Session::flash('error', 'login_failed');
                    $this->redirect('/login');
                }
            }
        } else {
            $result = Auth::loginClient($identifier, $password);
            if ($result === true) {
                AuditLog::log('client', Auth::currentUserId(), 'login', 'Client login');
                $this->redirect('/client');
            } elseif ($result === 'require_2fa') {
                $this->redirect('/two-factor-verify');
            } elseif ($result === 'require_2fa_setup') {
                $this->redirect('/two-factor-setup');
            } elseif ($result === 'password_expired' || $result === 'force_password_change') {
                $this->redirect('/change-password');
            } elseif ($result === 'require_privacy') {
                $this->redirect('/accept-privacy');
            } elseif ($result === 'account_deactivated') {
                $this->redirect('/account-blocked');
            } else {
                Session::flash('error', 'login_failed');
                $this->redirect('/login');
            }
        }
    }

    // ── Admin Login (/masterLogin) ─────────────────

    public function masterLoginForm(): void
    {
        // Clean up leftover 2FA session data
        $this->clear2faSessionVars();

        if (Auth::isAdmin()) { $this->redirect('/admin'); return; }
        $this->renderWithoutLayout('auth/master_login');
    }

    public function masterLogin(): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/masterLogin'); return; }

        if (Auth::isRateLimited()) {
            Session::flash('error', 'login_too_many_attempts');
            $this->redirect('/masterLogin');
            return;
        }

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            Session::flash('error', 'login_required_fields');
            $this->redirect('/masterLogin');
            return;
        }

        $result = Auth::loginAdmin($username, $password);
        if ($result === true) {
            AuditLog::log('admin', Auth::currentUserId(), 'login', 'Admin login');
            $this->redirect('/admin');
        } elseif ($result === 'require_2fa') {
            $this->redirect('/two-factor-verify');
        } elseif ($result === 'require_2fa_setup') {
            $this->redirect('/two-factor-setup');
        } else {
            Session::flash('error', 'login_failed');
            $this->redirect('/masterLogin');
        }
    }

    // ── Account Blocked ───────────────────────────

    public function accountBlocked(): void
    {
        $accountType = Session::getFlash('blocked_account_type');
        $officeName = Session::getFlash('blocked_office_name');
        $officeEmail = Session::getFlash('blocked_office_email');
        $officePhone = Session::getFlash('blocked_office_phone');

        if (!$accountType) {
            $this->redirect('/login');
            return;
        }

        $this->renderWithoutLayout('auth/account_blocked', [
            'accountType' => $accountType,
            'officeName' => $officeName,
            'officeEmail' => $officeEmail,
            'officePhone' => $officePhone,
        ]);
    }

    // ── Logout ─────────────────────────────────────

    public function logout(): void
    {
        if (Auth::isLoggedIn()) {
            AuditLog::log(Auth::currentUserType(), Auth::currentUserId(), 'logout', 'User logged out');
        }
        Auth::logout();
        $this->redirect('/login');
    }

    // ── Change Password ────────────────────────────

    public function changePasswordForm(): void
    {
        if (!Auth::isLoggedIn()) { $this->redirect('/login'); return; }
        $this->render('auth/change_password');
    }

    public function changePassword(): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/login'); return; }

        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($newPassword !== $confirmPassword) {
            Session::flash('error', 'passwords_not_match');
            $this->redirect('/change-password');
            return;
        }

        $errors = Auth::validatePasswordStrength($newPassword);
        if (!empty($errors)) {
            Session::flash('error', $errors[0]);
            $this->redirect('/change-password');
            return;
        }

        if (Auth::isClient()) {
            $clientId = Session::get('client_id');
            $client = Client::findById($clientId);
            if (!Auth::verifyPassword($currentPassword, $client['password_hash'])) {
                Session::flash('error', 'wrong_current_password');
                $this->redirect('/change-password');
                return;
            }
            Client::updatePassword($clientId, Auth::hashPassword($newPassword));
            \App\Models\TrustedDevice::revokeAllForUser('client', (int) $clientId);
            Auth::clearTrustedDeviceCookie();
            Session::remove('force_password_change');
            AuditLog::log('client', $clientId, 'password_changed', 'Client changed password', 'client', $clientId);
        } elseif (Auth::isOffice()) {
            $officeId = Session::get('office_id');
            $office = Office::findById($officeId);
            if (!Auth::verifyPassword($currentPassword, $office['password_hash'])) {
                Session::flash('error', 'wrong_current_password');
                $this->redirect('/change-password');
                return;
            }
            Office::updatePassword($officeId, Auth::hashPassword($newPassword));
            \App\Models\TrustedDevice::revokeAllForUser('office', (int) $officeId);
            Auth::clearTrustedDeviceCookie();
            Session::remove('force_password_change');
            AuditLog::log('office', $officeId, 'password_changed', 'Office changed password', 'office', $officeId);
        } elseif (Auth::isEmployee()) {
            $employeeId = Session::get('employee_id');
            $employee = \App\Models\OfficeEmployee::findById($employeeId);
            if (!$employee || !Auth::verifyPassword($currentPassword, $employee['password_hash'])) {
                Session::flash('error', 'wrong_current_password');
                $this->redirect('/change-password');
                return;
            }
            \App\Models\OfficeEmployee::updatePassword($employeeId, Auth::hashPassword($newPassword));
            \App\Models\TrustedDevice::revokeAllForUser('employee', (int) $employeeId);
            Auth::clearTrustedDeviceCookie();
            Session::remove('force_password_change');
            AuditLog::log('employee', $employeeId, 'password_changed', 'Employee changed password', 'employee', $employeeId);
        } elseif (Auth::isAdmin()) {
            $userId = Session::get('user_id');
            $user = User::findById($userId);
            if (!Auth::verifyPassword($currentPassword, $user['password_hash'])) {
                Session::flash('error', 'wrong_current_password');
                $this->redirect('/change-password');
                return;
            }
            User::update($userId, ['password_hash' => Auth::hashPassword($newPassword)]);
            \App\Models\TrustedDevice::revokeAllForUser('admin', (int) $userId);
            Auth::clearTrustedDeviceCookie();
            AuditLog::log('admin', $userId, 'password_changed', 'Admin changed password');
        }

        Session::flash('success', 'password_changed');
        $redirect = Auth::isAdmin() ? '/admin/security' : (Auth::isEmployee() ? '/office' : (Auth::isClient() ? '/client' : '/office'));
        $this->redirect($redirect);
    }

    // ── Forgot Password ────────────────────────────

    public function forgotPasswordForm(): void
    {
        $this->renderWithoutLayout('auth/forgot_password');
    }

    public function forgotPassword(): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/forgot-password'); return; }

        $nip = preg_replace('/[^0-9]/', '', $_POST['nip'] ?? '');
        $type = $_POST['user_type'] ?? 'client';

        if (strlen($nip) === 10) {
            PasswordResetService::createReset($type, $nip);
        }

        // Always show success to prevent NIP enumeration
        Session::flash('success', 'reset_email_sent');
        $this->redirect('/forgot-password');
    }

    public function resetPasswordForm(): void
    {
        $token = $_GET['token'] ?? '';
        $reset = PasswordResetService::validateToken($token);

        if (!$reset) {
            Session::flash('error', 'invalid_reset_token');
            $this->redirect('/forgot-password');
            return;
        }

        $this->renderWithoutLayout('auth/reset_password', ['token' => $token]);
    }

    public function resetPassword(): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/login'); return; }

        $token = $_POST['token'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($newPassword !== $confirmPassword) {
            Session::flash('error', 'passwords_not_match');
            $this->redirect('/reset-password?token=' . urlencode($token));
            return;
        }

        $errors = Auth::validatePasswordStrength($newPassword);
        if (!empty($errors)) {
            Session::flash('error', $errors[0]);
            $this->redirect('/reset-password?token=' . urlencode($token));
            return;
        }

        if (PasswordResetService::resetPassword($token, $newPassword)) {
            Session::flash('success', 'password_reset_success');
            $this->redirect('/login');
        } else {
            Session::flash('error', 'invalid_reset_token');
            $this->redirect('/forgot-password');
        }
    }

    // ── Privacy Policy Acceptance ──────────────────

    public function acceptPrivacyForm(): void
    {
        if (!Auth::isClient()) { $this->redirect('/login'); return; }
        $this->render('auth/accept_privacy');
    }

    public function acceptPrivacy(): void
    {
        if (!$this->validateCsrf() || !Auth::isClient()) { $this->redirect('/login'); return; }

        $clientId = Session::get('client_id');
        Client::acceptPrivacy($clientId);
        Session::remove('require_privacy_acceptance');

        $client = Client::findById($clientId);
        Auth::setClientSession($client);

        AuditLog::log('client', $clientId, 'privacy_accepted', 'Client accepted privacy policy', 'client', $clientId);
        $this->redirect('/client');
    }

    // ── Two-Factor Authentication ─────────────────

    public function twoFactorVerifyForm(): void
    {
        if (!Session::has('2fa_pending_user_type')) {
            $this->redirect('/login');
            return;
        }
        $this->renderWithoutLayout('auth/two_factor_verify');
    }

    public function twoFactorVerify(): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/two-factor-verify'); return; }

        $userType = Session::get('2fa_pending_user_type');
        $userId = Session::get('2fa_pending_user_id');

        if (!$userType || !$userId) {
            $this->redirect('/login');
            return;
        }

        $code = trim($_POST['code'] ?? '');
        if (empty($code)) {
            Session::flash('error', '2fa_code_required');
            $this->redirect('/two-factor-verify');
            return;
        }

        // Load user's 2FA secret
        $secret = $this->get2faSecret($userType, $userId);
        if (!$secret) {
            Session::flash('error', '2fa_error');
            $this->redirect('/login');
            return;
        }

        $trustDevice = !empty($_POST['trust_device']);

        // Try TOTP code first
        if (TwoFactorAuth::verifyCode($secret, $code)) {
            Auth::completeTwoFactorLogin();
            if ($trustDevice) {
                Auth::issueTrustedDeviceCookie($userType, (int) $userId);
            }
            AuditLog::log($userType, $userId, 'login', 'Login with 2FA' . ($trustDevice ? ' (trusted device)' : ''));
            $this->redirectAfterLogin($userType);
            return;
        }

        // Try recovery code
        $recoveryCodes = $this->get2faRecoveryCodes($userType, $userId);
        if ($recoveryCodes) {
            $index = TwoFactorAuth::verifyRecoveryCode($code, $recoveryCodes);
            if ($index >= 0) {
                // Remove used recovery code
                unset($recoveryCodes[$index]);
                $this->save2faRecoveryCodes($userType, $userId, array_values($recoveryCodes));

                Auth::completeTwoFactorLogin();
                if ($trustDevice) {
                    Auth::issueTrustedDeviceCookie($userType, (int) $userId);
                }
                AuditLog::log($userType, $userId, '2fa_recovery_used', 'Login with recovery code' . ($trustDevice ? ' (trusted device)' : ''));
                $this->redirectAfterLogin($userType);
                return;
            }
        }

        Session::flash('error', '2fa_invalid_code');
        $this->redirect('/two-factor-verify');
    }

    public function twoFactorSetupForm(): void
    {
        // For forced setup during login
        $userType = Session::get('2fa_setup_user_type');
        $userId = Session::get('2fa_setup_user_id');

        // For voluntary setup when already logged in
        if (!$userType && Auth::isLoggedIn()) {
            $userType = Auth::currentUserType();
            $userId = Auth::currentUserId();
        }

        if (!$userType || !$userId) {
            $this->redirect('/login');
            return;
        }

        $secret = TwoFactorAuth::generateSecret();
        Session::set('2fa_temp_secret', $secret);

        $label = $this->get2faLabel($userType, $userId);
        $otpauthUri = TwoFactorAuth::getOtpAuthUri($secret, $label);
        $qrSvg = TwoFactorAuth::generateQrSvg($otpauthUri);

        $this->renderWithoutLayout('auth/two_factor_setup', [
            'secret' => $secret,
            'qrSvg' => $qrSvg,
            'isForced' => Session::has('2fa_setup_user_type'),
        ]);
    }

    public function twoFactorEnable(): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/two-factor-setup'); return; }

        $userType = Session::get('2fa_setup_user_type');
        $userId = Session::get('2fa_setup_user_id');

        // Voluntary setup
        if (!$userType && Auth::isLoggedIn()) {
            $userType = Auth::currentUserType();
            $userId = Auth::currentUserId();
        }

        if (!$userType || !$userId) {
            $this->redirect('/login');
            return;
        }

        $secret = Session::get('2fa_temp_secret');
        $code = trim($_POST['code'] ?? '');

        if (!$secret || !TwoFactorAuth::verifyCode($secret, $code)) {
            Session::flash('error', '2fa_invalid_code');
            $this->redirect('/two-factor-setup');
            return;
        }

        // Generate recovery codes
        $recoveryCodes = TwoFactorAuth::generateRecoveryCodes();
        $hashedCodes = TwoFactorAuth::hashRecoveryCodes($recoveryCodes);

        // Save to database
        $this->save2faData($userType, $userId, $secret, $hashedCodes);

        Session::remove('2fa_temp_secret');
        AuditLog::log($userType, $userId, '2fa_enabled', 'Two-factor authentication enabled');

        // If forced setup during login, complete login
        if (Session::has('2fa_setup_user_type')) {
            Auth::complete2faSetupLogin();
            Session::flash('success', '2fa_enabled_success');
            Session::set('2fa_recovery_codes', $recoveryCodes);
            // Check post-login redirects after showing recovery codes
            if (Session::get('force_password_change')) {
                Session::set('2fa_post_recovery_redirect', '/change-password');
            }
            $this->redirect('/two-factor-recovery');
            return;
        }

        // Voluntary setup - show recovery codes
        Session::set('2fa_recovery_codes', $recoveryCodes);
        Session::flash('success', '2fa_enabled_success');
        $this->redirect('/two-factor-recovery');
    }

    public function twoFactorRecovery(): void
    {
        $codes = Session::get('2fa_recovery_codes');
        if (!$codes) {
            $this->redirect(Auth::isLoggedIn() ? $this->getDashboardUrl(Auth::currentUserType()) : '/login');
            return;
        }

        Session::remove('2fa_recovery_codes');

        if (Auth::isLoggedIn()) {
            $this->render('auth/two_factor_recovery', ['codes' => $codes, 'standalone' => false]);
        } else {
            $this->renderWithoutLayout('auth/two_factor_recovery', ['codes' => $codes, 'standalone' => true]);
        }
    }

    public function twoFactorDisable(): void
    {
        if (!$this->validateCsrf() || !Auth::isLoggedIn()) { $this->redirect('/login'); return; }

        $password = $_POST['password'] ?? '';
        $userType = Auth::currentUserType();
        $userId = Auth::currentUserId();

        // Verify password
        if (!$this->verifyUserPassword($userType, $userId, $password)) {
            Session::flash('error', 'wrong_current_password');
            $this->redirect($this->getProfileUrl($userType));
            return;
        }

        // Disable 2FA
        $this->save2faData($userType, $userId, null, null);
        AuditLog::log($userType, $userId, '2fa_disabled', 'Two-factor authentication disabled');
        Session::flash('success', '2fa_disabled_success');
        $this->redirect($this->getProfileUrl($userType));
    }

    // ── 2FA Helper methods ────────────────────────

    private function get2faSecret(string $userType, int $userId): ?string
    {
        $db = Database::getInstance();
        $table = match ($userType) {
            'admin' => 'users',
            'client' => 'clients',
            'office' => 'offices',
            default => null,
        };
        if (!$table) return null;

        $row = $db->fetchOne("SELECT two_factor_secret FROM {$table} WHERE id = ?", [$userId]);
        return $row['two_factor_secret'] ?? null;
    }

    private function get2faRecoveryCodes(string $userType, int $userId): ?array
    {
        $db = Database::getInstance();
        $table = match ($userType) {
            'admin' => 'users',
            'client' => 'clients',
            'office' => 'offices',
            default => null,
        };
        if (!$table) return null;

        $row = $db->fetchOne("SELECT two_factor_recovery_codes FROM {$table} WHERE id = ?", [$userId]);
        if (!$row || empty($row['two_factor_recovery_codes'])) return null;

        return json_decode($row['two_factor_recovery_codes'], true);
    }

    private function save2faRecoveryCodes(string $userType, int $userId, array $codes): void
    {
        $db = Database::getInstance();
        $table = match ($userType) {
            'admin' => 'users',
            'client' => 'clients',
            'office' => 'offices',
            default => null,
        };
        if (!$table) return;

        $db->query("UPDATE {$table} SET two_factor_recovery_codes = ? WHERE id = ?", [json_encode($codes), $userId]);
    }

    private function save2faData(string $userType, int $userId, ?string $secret, ?array $hashedCodes): void
    {
        $db = Database::getInstance();
        $table = match ($userType) {
            'admin' => 'users',
            'client' => 'clients',
            'office' => 'offices',
            default => null,
        };
        if (!$table) return;

        $enabled = $secret !== null ? 1 : 0;
        $codesJson = $hashedCodes !== null ? json_encode($hashedCodes) : null;

        $db->query(
            "UPDATE {$table} SET two_factor_secret = ?, two_factor_enabled = ?, two_factor_recovery_codes = ? WHERE id = ?",
            [$secret, $enabled, $codesJson, $userId]
        );
    }

    private function get2faLabel(string $userType, int $userId): string
    {
        $db = Database::getInstance();
        return match ($userType) {
            'admin' => ($db->fetchOne("SELECT username FROM users WHERE id = ?", [$userId]))['username'] ?? 'admin',
            'client' => ($db->fetchOne("SELECT company_name FROM clients WHERE id = ?", [$userId]))['company_name'] ?? 'client',
            'office' => ($db->fetchOne("SELECT name FROM offices WHERE id = ?", [$userId]))['name'] ?? 'office',
            default => 'user',
        };
    }

    private function verifyUserPassword(string $userType, int $userId, string $password): bool
    {
        $db = Database::getInstance();
        $table = match ($userType) {
            'admin' => 'users',
            'client' => 'clients',
            'office' => 'offices',
            default => null,
        };
        if (!$table) return false;

        $row = $db->fetchOne("SELECT password_hash FROM {$table} WHERE id = ?", [$userId]);
        return $row && Auth::verifyPassword($password, $row['password_hash']);
    }

    private function getDashboardUrl(string $userType): string
    {
        return match ($userType) {
            'admin' => '/admin',
            'client' => '/client',
            'office' => '/office',
            default => '/login',
        };
    }

    private function getProfileUrl(string $userType): string
    {
        return match ($userType) {
            'admin' => '/admin/security',
            'client' => '/client/security',
            'office' => '/office/security',
            default => '/login',
        };
    }

    private function redirectAfterLogin(string $userType): void
    {
        // Check for post-login redirects (password change, privacy)
        if (Session::get('force_password_change')) {
            $this->redirect('/change-password');
            return;
        }
        if (Session::get('require_privacy_acceptance')) {
            $this->redirect('/accept-privacy');
            return;
        }
        $this->redirect($this->getDashboardUrl($userType));
    }

    private function clear2faSessionVars(): void
    {
        Session::remove('2fa_pending_user_type');
        Session::remove('2fa_pending_user_id');
        Session::remove('2fa_setup_user_type');
        Session::remove('2fa_setup_user_id');
        Session::remove('2fa_temp_secret');
    }

    // ── Legal Pages (Public) ─────────────────────────

    public function privacyPolicy(): void
    {
        $this->renderWithoutLayout('legal/privacy_policy');
    }

    public function terms(): void
    {
        $this->renderWithoutLayout('legal/terms');
    }

    // ── Stop Impersonation ─────────────────────────

    public function stopImpersonation(): void
    {
        if (Auth::isEmployeeImpersonating()) {
            Auth::stopEmployeeImpersonation();
            $this->redirect('/office');
        } else {
            Auth::stopImpersonation();
            $this->redirect('/admin');
        }
    }

    // ── Client-employee login & activation ─────────

    public function clientEmployeeLoginForm(): void
    {
        if (Auth::isClientEmployee()) { $this->redirect('/employee'); return; }
        $this->renderWithoutLayout('auth/employee_login');
    }

    public function clientEmployeeLogin(): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/employee/login'); return; }
        if (Auth::isRateLimited()) {
            Session::flash('error', 'rate_limited');
            $this->redirect('/employee/login');
            return;
        }

        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $result = Auth::loginClientEmployee($email, $password);
        if ($result === true) {
            $this->redirect('/employee');
            return;
        }
        if ($result === 'require_2fa') {
            $this->redirect('/two-factor-verify');
            return;
        }
        if ($result === 'require_2fa_setup') {
            $this->redirect('/two-factor-setup');
            return;
        }
        if ($result === 'force_password_change') {
            $this->redirect('/employee/change-password');
            return;
        }
        if ($result === 'account_deactivated') {
            $this->redirect('/account-blocked');
            return;
        }
        Session::flash('error', 'invalid_credentials');
        $this->redirect('/employee/login');
    }

    public function clientEmployeeLogout(): void
    {
        Session::destroy();
        $this->redirect('/employee/login');
    }

    public function employeeActivateForm(): void
    {
        $token = $_GET['token'] ?? '';
        $employee = $token !== '' ? ClientEmployee::findByActivationToken($token) : null;
        if (!$employee) {
            Session::flash('error', 'invalid_or_expired_token');
            $this->redirect('/employee/login');
            return;
        }
        $this->renderWithoutLayout('auth/employee_activate', [
            'token'    => $token,
            'employee' => $employee,
        ]);
    }

    public function employeeActivate(): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/employee/login'); return; }

        $token    = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        $employee = ClientEmployee::findByActivationToken($token);
        if (!$employee) {
            Session::flash('error', 'invalid_or_expired_token');
            $this->redirect('/employee/login');
            return;
        }
        if ($password !== $confirm) {
            Session::flash('error', 'passwords_not_match');
            $this->redirect('/employee/activate?token=' . urlencode($token));
            return;
        }
        $errors = Auth::validatePasswordStrength($password);
        if (!empty($errors)) {
            Session::flash('error', $errors[0]);
            $this->redirect('/employee/activate?token=' . urlencode($token));
            return;
        }

        ClientEmployee::setPasswordAndActivate(
            (int) $employee['id'],
            Auth::hashPassword($password)
        );
        AuditLog::log('client_employee', (int) $employee['id'], 'account_activated',
            'Account activated via invitation token', 'client_employee', (int) $employee['id']);

        Session::flash('success', 'account_activated_login');
        $this->redirect('/employee/login');
    }
}
