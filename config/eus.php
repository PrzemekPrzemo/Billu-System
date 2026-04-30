<?php

declare(strict_types=1);

/**
 * e-Urząd Skarbowy configuration.
 *
 * URLs are best-effort defaults — confirm against MF docs before
 * going to production. Master admin can override per-environment via
 * settings table (eus_url_test_b, eus_url_test_c, etc.) once the
 * /admin/api-settings page gets the e-US section in PR-2.
 */

return [
    'urls' => [
        // 'mock_*' is consumed only by DemoEusMockService — never sent
        // over the network. The placeholder URL exists so logging
        // shows the intended target.
        'mock_B' => 'mock://eus/B',
        'mock_C' => 'mock://eus/C',

        'test_B' => 'https://test-eus.mf.gov.pl/api/b',
        'test_C' => 'https://test-eus.mf.gov.pl/api/c',

        'prod_B' => 'https://eus.mf.gov.pl/api/b',
        'prod_C' => 'https://eus.mf.gov.pl/api/c',
    ],

    // HTTP defaults — overridable per-call.
    'timeout_sec'    => 30,
    'connect_timeout_sec' => 10,
    'retry_attempts' => 2,
    'retry_backoff_sec' => [1, 5],

    // Profil Zaufany login.gov.pl OAuth-like flow. PR-2 wires this in
    // EusProfilZaufanyService; values come from MF onboarding form.
    // Empty means the PZ button is hidden in the UI.
    'profil_zaufany' => [
        'client_id'       => getenv('EUS_PZ_CLIENT_ID')     ?: '',
        'client_secret'   => getenv('EUS_PZ_CLIENT_SECRET') ?: '',
        'redirect_uri'    => getenv('EUS_PZ_REDIRECT_URI')  ?: '',
        'authorize_url'   => 'https://pz.gov.pl/oauth/authorize',
        'token_url'       => 'https://pz.gov.pl/oauth/token',
    ],

    // Trust anchors for X.509 chain validation of uploaded PFX certs.
    // Place a single PEM bundle here with NCCert / KIR / Sigillum /
    // Cencert / EuroCert root certs concatenated. PR-2 form rejects a
    // PFX that doesn't chain to one of these.
    'trust_anchors_path' => __DIR__ . '/eus/trust_anchors.pem',
];
