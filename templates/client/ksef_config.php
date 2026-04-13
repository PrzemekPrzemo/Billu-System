<h1><?= $lang('ksef_configuration') ?></h1>

<?php if (!empty($certWarning)): ?>
<div class="alert alert-warning"><?= htmlspecialchars($certWarning) ?></div>
<?php endif; ?>

<div class="section">
    <h2><?= $lang('ksef_connection_status') ?></h2>
    <div class="form-card" style="padding:16px;">
        <div class="stats-grid">
            <div class="stat-card <?= ($config && $config['is_active']) ? 'stat-success' : 'stat-error' ?>">
                <div class="stat-value"><?= ($config && $config['is_active']) ? $lang('active') : $lang('inactive') ?></div>
                <div class="stat-label"><?= $lang('status') ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $config ? $lang('ksef_auth_' . ($config['auth_method'] ?? 'none')) : $lang('ksef_auth_none') ?></div>
                <div class="stat-label"><?= $lang('ksef_auth_method') ?></div>
            </div>
            <div class="stat-card">
                <?php
                $envLabel = match($config['ksef_environment'] ?? 'test') {
                    'production' => 'PRODUKCJA',
                    'demo' => 'DEMO',
                    default => 'TEST',
                };
                $envClass = ($config['ksef_environment'] ?? 'test') === 'production' ? 'stat-error' : 'stat-warning';
                ?>
                <div class="stat-value"><?= $envLabel ?></div>
                <div class="stat-label"><?= $lang('environment') ?></div>
            </div>
            <?php if (!empty($config['last_import_at'])): ?>
            <div class="stat-card">
                <div class="stat-value" style="font-size:0.9rem;"><?= htmlspecialchars($config['last_import_at']) ?></div>
                <div class="stat-label"><?= $lang('ksef_last_import') ?></div>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($config['last_error'])): ?>
        <div class="alert alert-error" style="margin-top:12px;">
            <strong><?= $lang('ksef_last_error') ?>:</strong> <?= htmlspecialchars($config['last_error']) ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Environment selector -->
<div class="section">
    <h2><?= $lang('ksef_environment_settings') ?></h2>
    <div class="form-card" style="padding:16px;">
        <?php if (\App\Core\Auth::isImpersonating()): ?>
        <form method="POST" action="/client/ksef/environment" style="display:flex; align-items:flex-end; gap:12px; flex-wrap:wrap;">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label"><?= $lang('environment') ?></label>
                <select name="ksef_environment" class="form-input" style="width:auto;">
                    <option value="test" <?= ($config['ksef_environment'] ?? 'test') === 'test' ? 'selected' : '' ?>>Test</option>
                    <option value="demo" <?= ($config['ksef_environment'] ?? '') === 'demo' ? 'selected' : '' ?>>Demo</option>
                    <option value="production" <?= ($config['ksef_environment'] ?? '') === 'production' ? 'selected' : '' ?>>Produkcja</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><?= $lang('save') ?></button>
        </form>
        <small class="form-hint" style="margin-top:8px; display:block;">
            <?= $lang('ksef_environment_hint') ?>
        </small>
        <?php else: ?>
        <p><?= $lang('environment') ?>: <strong>Produkcja</strong></p>
        <?php endif; ?>

        <!-- UPO toggle (admin only) -->
        <?php if (\App\Core\Auth::isImpersonating()): ?>
        <div style="margin-top:16px; padding-top:16px; border-top:1px solid var(--gray-200);">
            <form method="POST" action="/client/ksef/upo-toggle" style="display:flex; align-items:center; gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="checkbox" name="upo_enabled" value="1" <?= ($config['upo_enabled'] ?? 1) ? 'checked' : '' ?> onchange="this.form.submit()">
                    <span style="font-weight:500;"><?= $lang('upo_enabled') ?></span>
                </label>
            </form>
            <small class="form-hint" style="margin-top:4px; display:block;"><?= $lang('upo_enabled_hint') ?></small>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Mobile-only message -->
<div class="mobile-only-client">
    <div class="alert alert-info" style="margin-top:16px;">
        <?= $lang('ksef_desktop_only_msg') ?>
    </div>
</div>

<div class="desktop-only-client">
<!-- ═══ KSeF Certificate (enrolled via API) ═══ -->
<?php if ($enrollmentEnabled): ?>
<div class="section">
    <h2><?= $lang('ksef_cert_ksef_title') ?></h2>
    <div class="form-card" style="padding:16px;">

        <?php
        $ksefCertStatus = $config['cert_ksef_status'] ?? 'none';
        ?>

        <?php if ($ksefCertStatus === 'active'): ?>
        <!-- Active KSeF certificate info -->
        <div class="alert alert-success" style="margin-bottom:16px;">
            <?= $lang('ksef_cert_ksef_active') ?>
        </div>
        <div class="table-responsive" style="margin-bottom:16px;">
            <table class="table">
                <tr><th style="width:200px;"><?= $lang('ksef_cert_ksef_name') ?></th>
                    <td><?= htmlspecialchars($config['cert_ksef_name'] ?? '-') ?></td></tr>
                <tr><th><?= $lang('ksef_cert_ksef_serial') ?></th>
                    <td><code style="font-size:0.8rem;"><?= htmlspecialchars($config['cert_ksef_serial_number'] ?? '-') ?></code></td></tr>
                <tr><th><?= $lang('ksef_cert_valid') ?></th>
                    <td>
                        <?= htmlspecialchars($config['cert_ksef_valid_from'] ?? '-') ?> — <?= htmlspecialchars($config['cert_ksef_valid_to'] ?? '-') ?>
                        <?php if (!empty($config['cert_ksef_valid_to']) && \App\Services\KsefCertificateService::isExpiringSoon($config['cert_ksef_valid_to'])): ?>
                            <span class="badge badge-warning"><?= $lang('ksef_cert_expiring_soon') ?></span>
                        <?php endif; ?>
                    </td></tr>
                <tr><th><?= $lang('ksef_cert_type') ?></th>
                    <td><?= htmlspecialchars($config['cert_ksef_type'] ?? 'Authentication') ?></td></tr>
            </table>
        </div>

        <div style="display:flex;gap:12px;flex-wrap:wrap;">
            <?php if ($config['auth_method'] !== 'ksef_cert'): ?>
            <form method="POST" action="/client/ksef/save-token" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="use_ksef_cert" value="1">
                <button type="submit" class="btn btn-primary btn-sm"><?= $lang('ksef_use_ksef_cert') ?></button>
            </form>
            <?php endif; ?>
            <form method="POST" action="/client/ksef/delete-ksef-cert" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <button type="submit" class="btn btn-danger btn-sm"
                    onclick="return confirm('<?= $lang('ksef_confirm_delete_ksef_cert') ?>')"><?= $lang('ksef_delete_ksef_cert') ?></button>
            </form>
            <a href="/client/ksef/certificates" class="btn btn-secondary btn-sm"><?= $lang('ksef_view_certificates') ?></a>
        </div>

        <?php elseif ($ksefCertStatus === 'enrolling'): ?>
        <!-- Enrollment in progress -->
        <div class="alert alert-info" style="margin-bottom:16px;">
            <?= $lang('ksef_enrollment_in_progress_msg') ?>
            <br><small><?= $lang('ksef_enrollment_ref') ?>: <code><?= htmlspecialchars($config['cert_ksef_enrollment_ref'] ?? '') ?></code></small>
        </div>
        <a href="/client/ksef/check-enrollment" class="btn btn-primary"><?= $lang('ksef_check_enrollment_status') ?></a>

        <?php else: ?>
        <!-- No KSeF certificate - show enrollment form -->
        <p style="margin-bottom:12px;"><?= $lang('ksef_cert_ksef_desc') ?></p>

        <?php if ($config && $config['is_active']): ?>
        <form method="POST" action="/client/ksef/enroll-cert">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="form-group">
                <label class="form-label"><?= $lang('ksef_cert_ksef_name') ?></label>
                <input type="text" name="cert_name" class="form-input"
                       placeholder="<?= $lang('ksef_cert_ksef_name_placeholder') ?>"
                       maxlength="100">
                <small class="form-hint"><?= $lang('ksef_cert_ksef_name_hint') ?></small>
            </div>
            <button type="submit" class="btn btn-primary"
                onclick="return confirm('<?= $lang('ksef_confirm_enrollment') ?>')"><?= $lang('ksef_enroll_cert_btn') ?></button>
        </form>
        <?php else: ?>
        <div class="alert alert-warning">
            <?= $lang('ksef_enroll_auth_required_msg') ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- ═══ Qualified Certificate (PFX/P12) ═══ -->
<?php
// Show cert section if: cert upload enabled AND (no specific method configured, or method is certificate/ksef_cert)
?>
<?php $showCertSection = $certUploadEnabled && (!$configuredAuthMethod || in_array($configuredAuthMethod, ['certificate', 'ksef_cert', 'none'])); ?>
<?php if ($showCertSection): ?>
<div class="section">
    <h2><?= $lang('ksef_certificate_auth') ?></h2>
    <div class="form-card" style="padding:16px;">

        <?php if (!empty($config['cert_fingerprint'])): ?>
        <div class="table-responsive" style="margin-bottom:16px;">
            <table class="table">
                <tr><th style="width:200px;"><?= $lang('ksef_cert_subject') ?></th><td><?= htmlspecialchars($config['cert_subject_cn'] ?? '-') ?></td></tr>
                <tr><th><?= $lang('ksef_cert_nip') ?></th><td><?= htmlspecialchars($config['cert_subject_nip'] ?? '-') ?></td></tr>
                <tr><th><?= $lang('ksef_cert_issuer') ?></th><td><?= htmlspecialchars($config['cert_issuer'] ?? '-') ?></td></tr>
                <tr><th><?= $lang('ksef_cert_type') ?></th><td><?= htmlspecialchars($lang('ksef_cert_type_' . ($config['cert_type'] ?? 'personal'))) ?></td></tr>
                <tr><th><?= $lang('ksef_cert_valid') ?></th><td>
                    <?= htmlspecialchars($config['cert_valid_from'] ?? '') ?> — <?= htmlspecialchars($config['cert_valid_to'] ?? '') ?>
                    <?php if (\App\Services\KsefCertificateService::isExpiringSoon($config['cert_valid_to'])): ?>
                        <span class="badge badge-warning"><?= $lang('ksef_cert_expiring_soon') ?></span>
                    <?php endif; ?>
                </td></tr>
                <tr><th><?= $lang('ksef_cert_fingerprint') ?></th><td><code style="font-size:0.75rem;"><?= htmlspecialchars($config['cert_fingerprint']) ?></code></td></tr>
            </table>
        </div>

        <form method="POST" action="/client/ksef/delete-cert" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('<?= $lang('ksef_confirm_delete_cert') ?>')"><?= $lang('ksef_delete_cert') ?></button>
        </form>
        <hr style="margin:16px 0;">
        <?php endif; ?>

        <p style="margin-bottom:12px;"><?= $lang('ksef_cert_upload_desc') ?></p>
        <form method="POST" action="/client/ksef/upload-cert" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="form-group">
                <label class="form-label"><?= $lang('ksef_cert_file') ?> (PFX/P12)</label>
                <input type="file" name="certificate" class="form-input" accept=".pfx,.p12" required>
                <small class="form-hint"><?= $lang('ksef_cert_file_hint') ?></small>
            </div>
            <div class="form-group">
                <label class="form-label"><?= $lang('ksef_cert_password') ?></label>
                <input type="password" name="cert_password" class="form-input" required autocomplete="off">
                <small class="form-hint"><?= $lang('ksef_cert_password_hint') ?></small>
            </div>
            <button type="submit" class="btn btn-primary"><?= $lang('ksef_upload_cert') ?></button>
        </form>

        <hr style="margin:20px 0;">

        <h3 style="margin-bottom:8px;"><?= $lang('ksef_pem_upload_title') ?></h3>
        <p style="margin-bottom:12px;"><?= $lang('ksef_pem_upload_desc') ?></p>
        <form method="POST" action="/client/ksef/upload-pem" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="form-group">
                <label class="form-label"><?= $lang('ksef_cert_crt_file') ?> (.crt / .pem)</label>
                <input type="file" name="cert_crt" class="form-input" accept=".crt,.pem,.cer" required>
                <small class="form-hint"><?= $lang('ksef_cert_crt_hint') ?></small>
            </div>
            <div class="form-group">
                <label class="form-label"><?= $lang('ksef_cert_key_file') ?> (.key / .pem)</label>
                <input type="file" name="cert_key" class="form-input" accept=".key,.pem" required>
                <small class="form-hint"><?= $lang('ksef_cert_key_hint') ?></small>
            </div>
            <div class="form-group">
                <label class="form-label"><?= $lang('ksef_cert_password') ?> (<?= $lang('optional') ?>)</label>
                <input type="password" name="cert_password" class="form-input" autocomplete="off">
                <small class="form-hint"><?= $lang('ksef_pem_password_hint') ?></small>
            </div>
            <button type="submit" class="btn btn-primary"><?= $lang('ksef_upload_cert') ?></button>
        </form>
    </div>
</div>
<?php endif; ?>


<!-- ═══ Operations ═══ -->
<?php if (!empty($config) && $config['is_active']): ?>
<div class="section">
    <h2><?= $lang('ksef_operations') ?></h2>
    <div class="form-card" style="padding:16px;">
        <div style="display:flex;gap:12px;flex-wrap:wrap;">
            <a href="/client/ksef/test" class="btn btn-secondary"><?= $lang('ksef_test_connection') ?></a>
            <a href="/client/ksef/diagnostic" class="btn btn-secondary" style="margin-left:8px;"><?= $lang('ksef_diagnostics') ?></a>
            <?php if (in_array($config['auth_method'], ['certificate', 'ksef_cert'])): ?>
            <a href="/client/ksef/certificates" class="btn btn-secondary"><?= $lang('ksef_view_certificates') ?></a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php endif; ?>
</div><!-- /.desktop-only-client -->
