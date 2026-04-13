<?php

declare(strict_types=1);

namespace App\Api\Auth;

use App\Api\ApiResponse;
use App\Api\Middleware\RateLimitMiddleware;
use App\Core\Auth;
use App\Core\Database;
use App\Core\TwoFactorAuth;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Office;

class ApiAuthController
{
    // ── POST /api/v1/auth/login ────────────────────

    public function login(array $params, ?int $clientId): void
    {
        RateLimitMiddleware::checkAnonymous();

        if (Auth::isRateLimited()) {
            ApiResponse::tooManyRequests();
        }

        $body       = $this->getJsonBody();
        $nip        = trim($body['nip'] ?? '');
        $password   = $body['password'] ?? '';
        $deviceName = trim($body['device_name'] ?? '');

        if (empty($nip) || empty($password)) {
            ApiResponse::validation([
                'nip'      => empty($nip)      ? 'required' : null,
                'password' => empty($password) ? 'required' : null,
            ]);
        }

        $result = Auth::loginClient($nip, $password);

        if ($result === false) {
            ApiResponse::error(401, 'invalid_credentials', 'Invalid NIP or password');
        }

        if ($result === 'account_deactivated') {
            ApiResponse::error(403, 'account_deactivated', 'This account has been deactivated');
        }

        // Load client to get the ID
        $client = Client::findByNip($nip);
        if (!$client) {
            ApiResponse::error(401, 'invalid_credentials', 'Invalid NIP or password');
        }

        // 2FA required
        if ($result === 'require_2fa') {
            $preAuthToken = JwtService::issuePreAuthToken((int) $client['id']);
            ApiResponse::success([
                'requires_2fa'    => true,
                'pre_auth_token'  => $preAuthToken,
            ]);
        }

        // Other conditions that require web handling (force password change, privacy, etc.)
        // For mobile, treat these as requiring a special action
        if (in_array($result, ['force_password_change', 'password_expired'], true)) {
            ApiResponse::error(403, 'password_change_required', 'You must change your password before continuing');
        }

        if ($result === 'require_privacy') {
            ApiResponse::error(403, 'privacy_acceptance_required', 'You must accept the privacy policy before continuing');
        }

        if ($result === 'require_2fa_setup') {
            ApiResponse::error(403, '2fa_setup_required', '2FA setup is required for this account');
        }

        // Successful login — check mobile app access before issuing tokens

        // Per-office check: if the client's office has disabled mobile access
        if (!empty($client['office_id'])) {
            $office = Office::findById((int) $client['office_id']);
            if ($office && !(int) $office['mobile_app_enabled']) {
                ApiResponse::error(403, 'mobile_access_disabled', 'Twoje biuro nie ma dostępu do aplikacji mobilnej');
            }
        }

        // Per-client check
        if (isset($client['mobile_app_enabled']) && !(int) $client['mobile_app_enabled']) {
            ApiResponse::error(403, 'mobile_access_disabled', 'Dostęp do aplikacji mobilnej jest wyłączony dla Twojego konta');
        }

        $ip     = $_SERVER['REMOTE_ADDR'] ?? '';
        $tokens = JwtService::issueTokenPair((int) $client['id'], $deviceName, $ip);

        AuditLog::log('client', (int) $client['id'], 'api_login', 'Mobile app login');
        Client::updateLastLogin((int) $client['id']);

        ApiResponse::success(array_merge($tokens, [
            'client' => $this->formatClient($client),
        ]));
    }

    // ── POST /api/v1/auth/2fa/verify ──────────────

    public function verify2fa(array $params, ?int $clientId): void
    {
        // $clientId here is from the pre_auth_token (JwtMiddleware::requirePreAuth)
        $body = $this->getJsonBody();
        $code = trim($body['code'] ?? '');
        $deviceName = trim($body['device_name'] ?? '');

        if (empty($code)) {
            ApiResponse::validation(['code' => 'required']);
        }

        $client = Client::findById($clientId);
        if (!$client) {
            ApiResponse::unauthorized('client_not_found');
        }

        // Get 2FA secret from DB
        $row = Database::getInstance()->fetchOne(
            "SELECT two_factor_secret, two_factor_recovery_codes FROM clients WHERE id = ?",
            [$clientId]
        );

        $secret = $row['two_factor_secret'] ?? null;
        if (!$secret) {
            ApiResponse::error(422, '2fa_not_configured', '2FA is not configured for this account');
        }

        // Try TOTP code
        if (TwoFactorAuth::verifyCode($secret, $code)) {
            $tokens = JwtService::issueTokenPair(
                $clientId,
                $deviceName,
                $_SERVER['REMOTE_ADDR'] ?? ''
            );
            AuditLog::log('client', $clientId, 'api_2fa_login', 'Mobile app 2FA login');
            Client::updateLastLogin($clientId);
            ApiResponse::success(array_merge($tokens, ['client' => $this->formatClient($client)]));
        }

        // Try recovery code
        $recoveryCodes = !empty($row['two_factor_recovery_codes'])
            ? json_decode($row['two_factor_recovery_codes'], true)
            : [];

        if (!empty($recoveryCodes)) {
            $index = TwoFactorAuth::verifyRecoveryCode($code, $recoveryCodes);
            if ($index >= 0) {
                unset($recoveryCodes[$index]);
                Database::getInstance()->execute(
                    "UPDATE clients SET two_factor_recovery_codes = ? WHERE id = ?",
                    [json_encode(array_values($recoveryCodes)), $clientId]
                );
                $tokens = JwtService::issueTokenPair(
                    $clientId,
                    $deviceName,
                    $_SERVER['REMOTE_ADDR'] ?? ''
                );
                AuditLog::log('client', $clientId, 'api_2fa_recovery_used', 'Mobile app login with recovery code');
                Client::updateLastLogin($clientId);
                ApiResponse::success(array_merge($tokens, ['client' => $this->formatClient($client)]));
            }
        }

        ApiResponse::error(401, 'invalid_2fa_code', 'Invalid or expired 2FA code');
    }

    // ── POST /api/v1/auth/token/refresh ───────────

    public function refreshToken(array $params, ?int $clientId): void
    {
        $body         = $this->getJsonBody();
        $refreshToken = $body['refresh_token'] ?? '';

        if (empty($refreshToken)) {
            ApiResponse::validation(['refresh_token' => 'required']);
        }

        $ip     = $_SERVER['REMOTE_ADDR'] ?? '';
        $tokens = JwtService::rotateRefreshToken($refreshToken, $ip);

        if ($tokens === null) {
            ApiResponse::unauthorized('invalid_or_expired_refresh_token');
        }

        ApiResponse::success($tokens);
    }

    // ── POST /api/v1/auth/logout ──────────────────

    public function logout(array $params, ?int $clientId): void
    {
        $body = $this->getJsonBody();
        $refreshToken = $body['refresh_token'] ?? '';

        if (!empty($refreshToken)) {
            JwtService::revokeRefreshToken($refreshToken);
        }

        AuditLog::log('client', $clientId, 'api_logout', 'Mobile app logout');

        ApiResponse::noContent();
    }

    // ── GET /api/v1/auth/me ───────────────────────

    public function me(array $params, ?int $clientId): void
    {
        $client = Client::findById($clientId);
        if (!$client) {
            ApiResponse::notFound('client_not_found');
        }

        ApiResponse::success($this->formatClient($client));
    }

    // ── Helpers ────────────────────────────────────

    private function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) return [];
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    private function formatClient(array $client): array
    {
        return [
            'id'           => (int) $client['id'],
            'nip'          => $client['nip'],
            'company_name' => $client['company_name'],
            'email'        => $client['email'] ?? null,
            'language'     => $client['language'] ?? 'pl',
            'is_active'    => (bool) $client['is_active'],
            'has_2fa'      => !empty($client['two_factor_enabled']),
        ];
    }
}
