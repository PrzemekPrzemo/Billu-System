<h1><?= $office ? $lang('edit_office') : $lang('add_office') ?></h1>

<form method="POST" action="<?= $office ? "/admin/offices/{$office['id']}/update" : '/admin/offices/create' ?>" class="form-card">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

    <?php if (!$office): ?>
    <div class="form-group">
        <label class="form-label">NIP *</label>
        <div class="form-row" style="gap:8px;align-items:flex-end;">
            <input type="text" name="nip" id="nip" class="form-input" required maxlength="10" pattern="[0-9]{10}"
                   placeholder="0000000000" value="<?= htmlspecialchars($_POST['nip'] ?? '') ?>" style="flex:1;">
            <button type="button" id="gus-lookup-btn" class="btn btn-secondary" style="white-space:nowrap;">
                <?= $lang('fetch_from_gus') ?>
            </button>
        </div>
    </div>
    <?php else: ?>
    <div class="form-group">
        <label class="form-label">NIP</label>
        <input type="text" class="form-input" value="<?= htmlspecialchars($office['nip']) ?>" disabled>
    </div>
    <?php endif; ?>

    <div class="form-group">
        <label class="form-label"><?= $lang('office_name') ?> *</label>
        <input type="text" name="name" id="office_name" class="form-input" required
               value="<?= htmlspecialchars($office['name'] ?? $_POST['name'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label class="form-label"><?= $lang('address') ?></label>
        <input type="text" name="address" id="address" class="form-input"
               value="<?= htmlspecialchars($office['address'] ?? $_POST['address'] ?? '') ?>">
    </div>

    <div class="form-row">
        <div class="form-group">
            <label class="form-label"><?= $lang('email') ?> *</label>
            <input type="email" name="email" id="email" class="form-input" required
                   value="<?= htmlspecialchars($office['email'] ?? $_POST['email'] ?? '') ?>">
            <small class="form-hint"><?= $lang('office_email_login_hint') ?></small>
        </div>
        <div class="form-group">
            <label class="form-label"><?= $lang('phone') ?></label>
            <input type="text" name="phone" id="phone" class="form-input"
                   value="<?= htmlspecialchars($office['phone'] ?? $_POST['phone'] ?? '') ?>">
        </div>
    </div>

    <div class="form-group">
        <label class="form-label"><?= $lang('representative') ?></label>
        <input type="text" name="representative_name" id="representative_name" class="form-input"
               value="<?= htmlspecialchars($office['representative_name'] ?? $_POST['representative_name'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label class="form-label"><?= $lang('password') ?> <?= $office ? '(' . $lang('leave_empty_no_change') . ')' : '*' ?></label>
        <input type="password" name="password" class="form-input" <?= $office ? '' : 'required' ?> minlength="12">
        <small class="form-hint"><?= $lang('password_requirements') ?></small>
    </div>

    <?php if ($office): ?>
    <div class="section" style="margin-top:16px; padding:16px; background:var(--gray-50); border:1px solid var(--gray-200); border-radius:8px;">
        <h3 style="margin-top:0;"><?= $lang('admin_reset_password') ?></h3>
        <p class="text-muted" style="font-size:13px; margin-bottom:12px;"><?= $lang('admin_reset_password_hint') ?></p>
        <form method="POST" action="/admin/offices/<?= $office['id'] ?>/reset-password" style="display:flex; gap:8px; align-items:flex-end;">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="form-group" style="margin:0; flex:1;">
                <input type="password" name="new_password" class="form-input" required minlength="12" placeholder="<?= $lang('new_password') ?>">
            </div>
            <button type="submit" class="btn btn-warning" data-confirm="<?= $lang('reset_password_confirm') ?>"><?= $lang('reset_password_button') ?></button>
        </form>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label class="form-label"><?= $lang('language') ?></label>
            <select name="language" class="form-input">
                <option value="pl" <?= ($office['language'] ?? 'pl') === 'pl' ? 'selected' : '' ?>>Polski</option>
                <option value="en" <?= ($office['language'] ?? 'pl') === 'en' ? 'selected' : '' ?>>English</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label"><?= $lang('status') ?></label>
            <label class="checkbox-label">
                <input type="checkbox" name="is_active" id="office_is_active" <?= $office['is_active'] ? 'checked' : '' ?>>
                <?= $lang('active') ?>
            </label>
            <div id="deactivate-warning" style="display:none; margin-top:8px; padding:10px; background:#e74c3c22; border:1px solid #e74c3c; border-radius:6px; color:#e74c3c; font-size:0.9em;">
                <?= $lang('office_deactivate_warning') ?>
            </div>
        </div>
    </div>

    <div class="section">
        <h2><?= $lang('per_office_settings') ?></h2>

        <div class="form-group">
            <label class="form-label"><?= $lang('verification_deadline_day') ?></label>
            <input type="number" name="verification_deadline_day" class="form-input" min="1" max="28"
                   value="<?= htmlspecialchars($office['verification_deadline_day'] ?? '') ?>">
            <small class="form-hint"><?= $lang('use_global_setting') ?></small>
        </div>

        <div class="form-group">
            <label class="form-label"><?= $lang('auto_accept_on_deadline') ?></label>
            <select name="auto_accept_on_deadline" class="form-input">
                <option value=""><?= $lang('use_global_setting') ?></option>
                <option value="1" <?= ($office['auto_accept_on_deadline'] ?? '') === '1' ? 'selected' : '' ?>><?= $lang('yes') ?></option>
                <option value="0" <?= ($office['auto_accept_on_deadline'] ?? '') === '0' ? 'selected' : '' ?>><?= $lang('no') ?></option>
            </select>
            <label class="checkbox-label">
                <input type="checkbox" name="auto_accept_override" <?= !empty($office['auto_accept_override']) ? 'checked' : '' ?>>
                <?= $lang('auto_accept_override') ?>
            </label>
        </div>

        <div class="form-group">
            <label class="form-label"><?= $lang('notification_days_before') ?></label>
            <input type="number" name="notification_days_before" class="form-input" min="1" max="14"
                   value="<?= htmlspecialchars($office['notification_days_before'] ?? '') ?>">
            <small class="form-hint"><?= $lang('use_global_setting') ?></small>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label"><?= $lang('max_employees') ?></label>
                <input type="number" name="max_employees" class="form-input" min="1"
                       value="<?= htmlspecialchars($office['max_employees'] ?? '') ?>"
                       placeholder="<?= $lang('unlimited') ?>">
            </div>
            <div class="form-group">
                <label class="form-label"><?= $lang('max_clients') ?></label>
                <input type="number" name="max_clients" class="form-input" min="1"
                       value="<?= htmlspecialchars($office['max_clients'] ?? '') ?>"
                       placeholder="<?= $lang('unlimited') ?>">
            </div>
        </div>
    </div>

    <!-- SMTP Configuration -->
    <div class="section">
        <h2><?= $lang('office_smtp_config') ?></h2>
        <p style="color:var(--gray-500); margin-bottom:16px; font-size:13px;">
            <?= $lang('office_smtp_description') ?>
        </p>

        <?php $smtp = $smtpConfig ?? null; ?>
        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="smtp_enabled" id="smtp_enabled" value="1" <?= !empty($smtp['is_enabled']) ? 'checked' : '' ?>>
                <?= $lang('smtp_enable_custom') ?>
            </label>
        </div>

        <div id="smtp-fields" style="<?= empty($smtp['is_enabled']) ? 'display:none;' : '' ?>">
            <div class="form-row">
                <div class="form-group" style="flex:2;">
                    <label class="form-label">SMTP Host</label>
                    <input type="text" name="smtp_host" class="form-input" value="<?= htmlspecialchars($smtp['smtp_host'] ?? '') ?>" placeholder="smtp.example.com">
                </div>
                <div class="form-group" style="flex:1;">
                    <label class="form-label">Port</label>
                    <input type="number" name="smtp_port" class="form-input" value="<?= htmlspecialchars($smtp['smtp_port'] ?? '587') ?>" placeholder="587">
                </div>
                <div class="form-group" style="flex:1;">
                    <label class="form-label"><?= $lang('encryption') ?></label>
                    <select name="smtp_encryption" class="form-input">
                        <option value="tls" <?= ($smtp['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
                        <option value="ssl" <?= ($smtp['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                        <option value="none" <?= ($smtp['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>><?= $lang('none') ?></option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><?= $lang('smtp_user') ?></label>
                    <input type="text" name="smtp_user" class="form-input" value="<?= htmlspecialchars($smtp['smtp_user'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('smtp_password') ?></label>
                    <input type="password" name="smtp_pass" class="form-input" placeholder="<?= !empty($smtp['smtp_pass_encrypted']) ? '********' : '' ?>">
                    <?php if (!empty($smtp['smtp_pass_encrypted'])): ?>
                    <small class="form-hint"><?= $lang('leave_empty_no_change') ?></small>
                    <?php endif; ?>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><?= $lang('smtp_from_email') ?></label>
                    <input type="email" name="smtp_from_email" class="form-input" value="<?= htmlspecialchars($smtp['from_email'] ?? '') ?>" placeholder="biuro@example.com">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('smtp_from_name') ?></label>
                    <input type="text" name="smtp_from_name" class="form-input" value="<?= htmlspecialchars($smtp['from_name'] ?? '') ?>" placeholder="<?= htmlspecialchars($office['name'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <button type="button" class="btn btn-secondary" onclick="testOfficeSmtp(<?= $office['id'] ?>)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M22 2L11 13"/><path d="M22 2L15 22L11 13L2 9L22 2Z"/></svg>
                    <?= $lang('test_smtp') ?>
                </button>
                <span id="smtp-test-result" style="margin-left:8px;"></span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Mobile App Access -->
    <div class="section">
        <h2><?= $lang('mobile_app_access') ?></h2>

        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="mobile_app_enabled" value="1"
                    <?= ($office['mobile_app_enabled'] ?? 1) ? 'checked' : '' ?>>
                <?= $lang('mobile_app_access_office_label') ?>
            </label>
            <small class="form-hint">
                <?= $lang('mobile_app_access_office_hint') ?>
            </small>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $lang('save') ?></button>
        <a href="/admin/offices" class="btn btn-secondary"><?= $lang('cancel') ?></a>
        <?php if ($office): ?>
        <a href="/admin/offices/<?= $office['id'] ?>/modules" class="btn btn-secondary" style="margin-left:auto;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:4px;"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
            <?= $lang('modules_management') ?>
        </a>
        <?php endif; ?>
    </div>
</form>

<?php if ($office && !empty($office['id'])): ?>
<div class="section" style="margin-top:1.5rem;border:1px solid var(--gray-200);border-radius:8px;padding:1.5rem;">
    <h2 style="margin-top:0;"><?= $lang('bulk_mobile_control') ?></h2>
    <p class="text-muted">
        <?= $lang('bulk_mobile_hint') ?>
    </p>
    <form method="POST" action="/admin/offices/<?= (int) $office['id'] ?>/toggle-mobile-clients">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <button name="mobile_val" value="1" type="submit" class="btn btn-success btn-sm" style="margin-right:0.5rem;">
            &#10003; <?= $lang('enable_mobile_all_clients') ?>
        </button>
        <button name="mobile_val" value="0" type="submit" class="btn btn-danger btn-sm"
            data-confirm="<?= $lang('disable_mobile_all_confirm') ?>">
            &#10007; <?= $lang('disable_mobile_all_clients') ?>
        </button>
    </form>
</div>
<?php endif; ?>

<?php if ($office): ?>
<script>
(function() {
    var cb = document.getElementById('office_is_active');
    var warn = document.getElementById('deactivate-warning');
    var wasActive = <?= $office['is_active'] ? 'true' : 'false' ?>;
    function toggle() {
        warn.style.display = (wasActive && !cb.checked) ? 'block' : 'none';
    }
    cb.addEventListener('change', toggle);
    toggle();

    // SMTP toggle
    var smtpCb = document.getElementById('smtp_enabled');
    var smtpFields = document.getElementById('smtp-fields');
    if (smtpCb && smtpFields) {
        smtpCb.addEventListener('change', function() {
            smtpFields.style.display = this.checked ? '' : 'none';
        });
    }
})();

function testOfficeSmtp(officeId) {
    var result = document.getElementById('smtp-test-result');
    result.innerHTML = '<span style="color:var(--gray-500);">Wysyłanie...</span>';
    var formData = new FormData();
    formData.append('csrf_token', '<?= htmlspecialchars(\App\Core\Session::get('csrf_token') ?? '') ?>');
    formData.append('smtp_host', document.querySelector('[name=smtp_host]').value);
    formData.append('smtp_port', document.querySelector('[name=smtp_port]').value);
    formData.append('smtp_encryption', document.querySelector('[name=smtp_encryption]').value);
    formData.append('smtp_user', document.querySelector('[name=smtp_user]').value);
    formData.append('smtp_pass', document.querySelector('[name=smtp_pass]').value);
    formData.append('smtp_from_email', document.querySelector('[name=smtp_from_email]').value);
    formData.append('smtp_from_name', document.querySelector('[name=smtp_from_name]').value);
    fetch('/admin/offices/' + officeId + '/test-smtp', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                result.innerHTML = '<span class="badge badge-success">OK - email testowy wysłany</span>';
            } else {
                result.innerHTML = '<span class="badge badge-error">' + (data.error || 'Błąd') + '</span>';
            }
        })
        .catch(function() { result.innerHTML = '<span class="badge badge-error">Błąd połączenia</span>'; });
}
</script>
<?php endif; ?>

<?php if (!$office): ?>
<script>
document.getElementById('gus-lookup-btn').addEventListener('click', function() {
    var nip = document.getElementById('nip').value.trim();
    if (!nip || nip.length !== 10) {
        alert('Podaj prawidłowy NIP (10 cyfr)');
        return;
    }
    var btn = this;
    btn.disabled = true;
    btn.textContent = '...';
    fetch('/admin/gus-lookup?nip=' + encodeURIComponent(nip))
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) {
                alert(data.error);
                return;
            }
            if (data.company_name) document.getElementById('office_name').value = data.company_name;
            if (data.formatted_address) document.getElementById('address').value = data.formatted_address;
            if (data.email) document.getElementById('email').value = data.email;
            if (data.phone) document.getElementById('phone').value = data.phone;
            if (data.source === 'ceidg') alert('Dane pobrano z CEIDG (brak w GUS)');
        })
        .catch(function() { alert('Błąd połączenia z GUS/CEIDG'); })
        .finally(function() {
            btn.disabled = false;
            btn.textContent = '<?= $lang('fetch_from_gus') ?>';
        });
});
</script>
<?php endif; ?>
