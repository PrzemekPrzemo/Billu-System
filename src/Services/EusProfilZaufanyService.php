<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EusConfig;

/**
 * Profil Zaufany / login.gov.pl OAuth-like authentication.
 *
 * Skeleton in PR-2. The OAuth dance has 4 steps:
 *   1. Office clicks "Login via PZ" → buildAuthorizeUrl() returns the
 *      PZ login URL with state + scope.
 *   2. User authenticates on pz.gov.pl, redirected to our callback
 *      with ?code= + ?state=.
 *   3. handleCallback() exchanges the code for a SAML artifact /
 *      token via tokenExchange().
 *   4. The artifact is encrypted and stored in
 *      client_eus_configs.auth_provider_token_encrypted.
 *
 * For now (PR-2) only step 1 is implemented + step 4 storage helper
 * so the office UI can show "PZ flow available" without breaking.
 * PR-2 follow-up commit fills 2/3 once we have docs + redirect URI
 * approved by MF.
 *
 * In 'mock' environment this returns a synthetic artifact immediately
 * — useful for dev iteration of the storage layer.
 */
class EusProfilZaufanyService
{
    private array $config;
    private EusLogger $logger;

    public function __construct(?array $config = null, ?EusLogger $logger = null)
    {
        $config ??= require __DIR__ . '/../../config/eus.php';
        $this->config = $config['profil_zaufany'] ?? [];
        $this->logger = $logger ?? new EusLogger();
    }

    /**
     * Whether the current installation has PZ credentials configured.
     * Office UI hides the "Login via Profil Zaufany" button when false.
     */
    public function isAvailable(): bool
    {
        return !empty($this->config['client_id'])
            && !empty($this->config['client_secret'])
            && !empty($this->config['redirect_uri']);
    }

    /**
     * Step 1: build the URL the office user is redirected to.
     * Caller should persist $state in session and verify on callback.
     */
    public function buildAuthorizeUrl(string $state, int $clientId): string
    {
        $params = [
            'client_id'     => $this->config['client_id']    ?? '',
            'redirect_uri'  => $this->config['redirect_uri'] ?? '',
            'response_type' => 'code',
            'scope'         => 'eus:declarations eus:correspondence',
            'state'         => $state,
            // Pass the client id through state so the callback knows
            // which row in client_eus_configs to update.
            'data'          => (string) $clientId,
        ];
        return ($this->config['authorize_url'] ?? '') . '?' . http_build_query($params);
    }

    /**
     * Step 3 (skeleton): exchange code for artifact. Real impl in
     * PR-2 follow-up. Mock-environment shortcut returns a synthetic
     * artifact so the storage path can be exercised today.
     *
     * @return array{ok:bool,artifact:?string,subject:?string,valid_to:?string,error:?string}
     */
    public function exchangeCodeForArtifact(string $code, string $environment = 'mock'): array
    {
        if ($environment === 'mock') {
            return [
                'ok'       => true,
                'artifact' => 'MOCK-PZ-ARTIFACT-' . substr(uniqid('', true), -16),
                'subject'  => '[MOCK] Jan Kowalski (PZ)',
                'valid_to' => date('Y-m-d H:i:s', strtotime('+30 days')),
                'error'    => null,
            ];
        }

        // Production exchange — implemented in PR-2 follow-up commit.
        return [
            'ok'       => false,
            'artifact' => null,
            'subject'  => null,
            'valid_to' => null,
            'error'    => 'Profil Zaufany OAuth flow not yet implemented (PR-2 follow-up).',
        ];
    }

    /**
     * Step 4: persist the artifact encrypted, alongside metadata.
     * Returns the EusConfig row id on success.
     */
    public function storeArtifact(int $clientId, int $officeId, array $exchange): int
    {
        if (empty($exchange['ok']) || empty($exchange['artifact'])) {
            throw new \RuntimeException('Cannot store empty PZ artifact');
        }

        $existing = EusConfig::findByClient($clientId);
        $encrypted = EusCertificateService::encrypt((string) $exchange['artifact']);

        if ($existing === null) {
            return \App\Core\Database::getInstance()->insert('client_eus_configs', [
                'client_id'                       => $clientId,
                'office_id'                       => $officeId,
                'auth_method'                     => 'profil_zaufany',
                'auth_provider_token_encrypted'   => $encrypted,
                'auth_provider_subject'           => $exchange['subject'] ?? null,
                'auth_provider_valid_to'          => $exchange['valid_to'] ?? null,
            ]);
        }

        if ((int) $existing['office_id'] !== $officeId) {
            throw new \RuntimeException('Tenant mismatch — existing config belongs to another office');
        }

        \App\Core\Database::getInstance()->update(
            'client_eus_configs',
            [
                'auth_method'                     => 'profil_zaufany',
                'auth_provider_token_encrypted'   => $encrypted,
                'auth_provider_subject'           => $exchange['subject'] ?? null,
                'auth_provider_valid_to'          => $exchange['valid_to'] ?? null,
            ],
            'id = ?',
            [$existing['id']]
        );
        return (int) $existing['id'];
    }
}
