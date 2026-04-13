<h1><?= $lang('ksef_diagnostics') ?></h1>

<!-- Client selector -->
<div class="section" style="margin-bottom:20px;">
    <form method="GET" action="/admin/ksef-test" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
        <div class="form-group" style="margin-bottom:0;">
            <label class="form-label"><?= $lang('client') ?></label>
            <select name="client_id" class="form-input">
                <option value=""><?= $lang('ksef_global_config') ?></option>
                <?php foreach ($clients as $c): ?>
                <option value="<?= $c['id'] ?>" <?= ($selectedClient ?? 0) == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['company_name']) ?> (<?= $c['nip'] ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><?= $lang('ksef_run_diagnostics') ?></button>
        <a href="/admin/ksef-test?client_id=<?= $selectedClient ?? '' ?>&full_test=1" class="btn" style="background:#f59e0b;color:#fff;"><?= $lang('ksef_full_auth_test') ?></a>
    </form>
</div>

<?php $d = $diagnostics; ?>

<!-- Configuration -->
<div class="section">
    <h2><?= $lang('ksef_config_check') ?></h2>
    <table class="table">
        <tbody>
            <tr>
                <td style="width:250px;font-weight:600;"><?= $lang('environment') ?></td>
                <td><code><?= htmlspecialchars($d['environment']) ?></code></td>
                <td><?= $d['environment'] === 'production' ? '<span class="badge badge-success">PROD</span>' : '<span class="badge badge-warning">TEST/DEMO</span>' ?></td>
            </tr>
            <tr>
                <td style="font-weight:600;">API URL</td>
                <td><code><?= htmlspecialchars($d['api_url']) ?></code></td>
                <td></td>
            </tr>
            <tr>
                <td style="font-weight:600;">NIP</td>
                <td><code><?= htmlspecialchars($d['nip']) ?></code></td>
                <td><?= !empty($d['nip']) ? '<span class="badge badge-success">OK</span>' : '<span class="badge badge-error">BRAK</span>' ?></td>
            </tr>
            <tr>
                <td style="font-weight:600;">Token</td>
                <td><?= $d['token_source'] ?></td>
                <td><?= $d['has_token'] ? '<span class="badge badge-success">OK</span>' : '<span class="badge badge-error">BRAK</span>' ?></td>
            </tr>
            <tr>
                <td style="font-weight:600;"><?= $lang('ksef_configured') ?></td>
                <td></td>
                <td><?= $d['configured'] ? '<span class="badge badge-success">TAK</span>' : '<span class="badge badge-error">NIE</span>' ?></td>
            </tr>
        </tbody>
    </table>
</div>

<!-- PHP/System -->
<div class="section">
    <h2><?= $lang('ksef_system_check') ?></h2>
    <table class="table">
        <tbody>
            <tr>
                <td style="width:250px;font-weight:600;">PHP</td>
                <td><code><?= $d['php_version'] ?></code></td>
                <td><?= version_compare($d['php_version'], '8.1', '>=') ? '<span class="badge badge-success">OK</span>' : '<span class="badge badge-error">Wymaga 8.1+</span>' ?></td>
            </tr>
            <tr>
                <td style="font-weight:600;">OpenSSL</td>
                <td><code><?= $d['openssl_version'] ?></code></td>
                <td><?= $d['openssl'] ? '<span class="badge badge-success">OK</span>' : '<span class="badge badge-error">BRAK</span>' ?></td>
            </tr>
            <tr>
                <td style="font-weight:600;">cURL</td>
                <td><code><?= $d['curl_version'] ?></code> (SSL: <code><?= $d['curl_ssl'] ?></code>)</td>
                <td><?= $d['curl'] ? '<span class="badge badge-success">OK</span>' : '<span class="badge badge-error">BRAK</span>' ?></td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Connectivity -->
<?php if ($d['connectivity']): ?>
<div class="section">
    <h2><?= $lang('ksef_connectivity') ?></h2>
    <table class="table">
        <tbody>
            <tr>
                <td style="width:250px;font-weight:600;">URL</td>
                <td><code><?= htmlspecialchars($d['connectivity']['url']) ?></code></td>
                <td></td>
            </tr>
            <tr>
                <td style="font-weight:600;">HTTP</td>
                <td><code><?= $d['connectivity']['http_code'] ?></code></td>
                <td><?= $d['connectivity']['http_code'] === 200 ? '<span class="badge badge-success">OK</span>' : '<span class="badge badge-error">FAIL</span>' ?></td>
            </tr>
            <?php if ($d['connectivity']['curl_error']): ?>
            <tr>
                <td style="font-weight:600;">cURL Error</td>
                <td colspan="2" style="color:#dc2626;"><code><?= htmlspecialchars($d['connectivity']['curl_error']) ?></code></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td style="font-weight:600;">IP</td>
                <td><code><?= htmlspecialchars($d['connectivity']['ip'] ?? 'N/A') ?></code></td>
                <td></td>
            </tr>
            <tr>
                <td style="font-weight:600;">SSL Verify</td>
                <td><code><?= $d['connectivity']['ssl_verify'] ?></code></td>
                <td><?= $d['connectivity']['ssl_verify'] === 0 ? '<span class="badge badge-success">OK</span>' : '<span class="badge badge-error">FAIL (code: ' . $d['connectivity']['ssl_verify'] . ')</span>' ?></td>
            </tr>
            <tr>
                <td style="font-weight:600;"><?= $lang('ksef_response_time') ?></td>
                <td><code><?= $d['connectivity']['connect_time'] ?>s connect / <?= $d['connectivity']['total_time'] ?>s total</code></td>
                <td></td>
            </tr>
            <tr>
                <td style="font-weight:600;"><?= $lang('ksef_response_preview') ?></td>
                <td colspan="2"><pre style="background:#f5f5f5;padding:8px;border-radius:4px;font-size:11px;max-height:200px;overflow:auto;white-space:pre-wrap;"><?= htmlspecialchars($d['connectivity']['response_preview']) ?></pre></td>
            </tr>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Challenge test -->
<?php if ($d['challenge']): ?>
<div class="section">
    <h2><?= $lang('ksef_challenge_test') ?></h2>
    <table class="table">
        <tbody>
            <tr>
                <td style="width:250px;font-weight:600;">Challenge</td>
                <td></td>
                <td><?= ($d['challenge']['success'] ?? false) ? '<span class="badge badge-success">OK</span>' : '<span class="badge badge-error">FAIL</span>' ?></td>
            </tr>
            <?php if (!empty($d['challenge']['error'])): ?>
            <tr>
                <td style="font-weight:600;"><?= $lang('errors') ?></td>
                <td colspan="2" style="color:#dc2626;"><code><?= htmlspecialchars($d['challenge']['error']) ?></code></td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Full auth test -->
<?php if ($d['auth'] !== null): ?>
<div class="section">
    <h2><?= $lang('ksef_auth_test') ?></h2>
    <table class="table">
        <tbody>
            <tr>
                <td style="width:250px;font-weight:600;"><?= $lang('ksef_full_auth') ?></td>
                <td></td>
                <td><?= ($d['auth']['success'] ?? false) ? '<span class="badge badge-success">OK - Autentykacja udana!</span>' : '<span class="badge badge-error">FAIL</span>' ?></td>
            </tr>
            <?php if (!empty($d['auth']['error'])): ?>
            <tr>
                <td style="font-weight:600;"><?= $lang('errors') ?></td>
                <td colspan="2" style="color:#dc2626;"><code><?= htmlspecialchars($d['auth']['error']) ?></code></td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Errors -->
<?php if (!empty($d['errors'])): ?>
<div class="section">
    <h2 style="color:#dc2626;"><?= $lang('errors') ?></h2>
    <div class="alert alert-error">
        <ul style="margin:0;padding-left:20px;">
        <?php foreach ($d['errors'] as $e): ?>
            <li style="margin-bottom:4px;"><?= htmlspecialchars($e) ?></li>
        <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<!-- Log link -->
<?php if (!empty($d['log_session'])): ?>
<div class="section">
    <a href="/admin/ksef-logs?session=<?= urlencode($d['log_session']) ?>" class="btn btn-primary"><?= $lang('ksef_view_log') ?>: <?= htmlspecialchars($d['log_session']) ?></a>
</div>
<?php endif; ?>
