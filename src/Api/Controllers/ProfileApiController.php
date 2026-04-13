<?php

declare(strict_types=1);

namespace App\Api\Controllers;

use App\Api\ApiResponse;
use App\Models\BankAccount;
use App\Models\Client;
use App\Models\ClientCostCenter;
use App\Models\CompanyProfile;
use App\Core\Database;

class ProfileApiController
{
    // GET /api/v1/profile
    public function show(array $params, ?int $clientId): void
    {
        $client  = Client::findById($clientId);
        if (!$client) {
            ApiResponse::notFound('client_not_found');
        }

        $profile      = CompanyProfile::findByClient($clientId);
        $bankAccounts = BankAccount::findByClient($clientId);
        $costCenters  = ClientCostCenter::findByClient($clientId, true);

        ApiResponse::success([
            'client'       => [
                'id'           => (int) $client['id'],
                'nip'          => $client['nip'],
                'company_name' => $client['company_name'],
                'email'        => $client['email'] ?? null,
                'phone'        => $client['phone'] ?? null,
                'address'      => $client['address'] ?? null,
                'city'         => $client['city'] ?? null,
                'postal_code'  => $client['postal_code'] ?? null,
                'language'     => $client['language'] ?? 'pl',
                'is_active'    => (bool) $client['is_active'],
                'has_2fa'      => !empty($client['two_factor_enabled']),
                'last_login'   => $client['last_login_at'] ?? null,
            ],
            'profile'      => $profile ? [
                'regon'             => $profile['regon'] ?? null,
                'krs'               => $profile['krs'] ?? null,
                'company_type'      => $profile['company_type'] ?? null,
                'invoice_prefix'    => $profile['invoice_prefix'] ?? null,
                'logo_path'         => !empty($profile['logo_path']) ? '/storage/' . basename($profile['logo_path']) : null,
            ] : null,
            'bank_accounts' => array_map(fn($b) => [
                'id'          => (int) $b['id'],
                'bank_name'   => $b['bank_name'] ?? null,
                'account_number' => $b['account_number'],
                'is_default_receiving' => (bool) ($b['is_default_receiving'] ?? false),
                'is_default_outgoing'  => (bool) ($b['is_default_outgoing'] ?? false),
            ], $bankAccounts),
            'cost_centers'  => array_map(fn($c) => [
                'id'   => (int) $c['id'],
                'name' => $c['name'],
            ], $costCenters),
        ]);
    }

    // POST /api/v1/profile/fcm-token
    // Body: {token: string, device_name?: string}
    public function saveFcmToken(array $params, ?int $clientId): void
    {
        $body       = $this->getJsonBody();
        $fcmToken   = trim($body['token'] ?? '');
        $deviceName = trim($body['device_name'] ?? '');

        if (empty($fcmToken)) {
            ApiResponse::validation(['token' => 'required']);
        }

        $db = Database::getInstance();

        // Upsert by client + device_name
        $existing = $db->fetchOne(
            "SELECT id FROM client_fcm_tokens WHERE client_id = ? AND device_name = ?",
            [$clientId, $deviceName ?: null]
        );

        if ($existing) {
            $db->execute(
                "UPDATE client_fcm_tokens SET fcm_token = ?, updated_at = NOW() WHERE id = ?",
                [$fcmToken, $existing['id']]
            );
        } else {
            $db->insert('client_fcm_tokens', [
                'client_id'   => $clientId,
                'fcm_token'   => $fcmToken,
                'device_name' => $deviceName ?: null,
            ]);
        }

        ApiResponse::success(['registered' => true]);
    }

    private function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) return [];
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}
