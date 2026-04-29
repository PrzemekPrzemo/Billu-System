<h1><?= $lang('system_settings') ?></h1>

<p style="margin-bottom:16px;">
    <a href="/admin/api-settings" class="btn btn-secondary">
        Konfiguracja API (GUS / KSeF / CEIDG / White List / Mobile) &rarr;
    </a>
</p>

<form method="POST" action="/admin/settings" enctype="multipart/form-data" class="form-card">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

    <!-- General Settings -->
    <div class="section">
        <h2><?= $lang('general_settings') ?></h2>

        <div class="form-group">
            <label class="form-label"><?= $lang('company_name') ?></label>
            <input type="text" name="company_name" class="form-input"
                   value="<?= htmlspecialchars($values['company_name'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label class="form-label"><?= $lang('company_email') ?></label>
            <input type="email" name="company_email" class="form-input"
                   value="<?= htmlspecialchars($values['company_email'] ?? '') ?>">
        </div>
    </div>

    <!-- SMTP Configuration -->
    <div class="section">
        <h2><?= $lang('smtp_settings') ?></h2>
        <p style="color:var(--gray-500); margin-bottom:16px; font-size:13px;">
            <?= $lang('smtp_settings_description') ?>
        </p>

        <div class="form-row">
            <div class="form-group" style="flex:2;">
                <label class="form-label">SMTP Host</label>
                <input type="text" name="smtp_host" class="form-input"
                       value="<?= htmlspecialchars($values['smtp_host'] ?? '') ?>" placeholder="smtp.example.com">
            </div>
            <div class="form-group" style="flex:1;">
                <label class="form-label">Port</label>
                <input type="number" name="smtp_port" class="form-input"
                       value="<?= htmlspecialchars($values['smtp_port'] ?? '587') ?>" placeholder="587">
            </div>
            <div class="form-group" style="flex:1;">
                <label class="form-label"><?= $lang('encryption') ?></label>
                <select name="smtp_encryption" class="form-input">
                    <option value="tls" <?= ($values['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
                    <option value="ssl" <?= ($values['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                    <option value="none" <?= ($values['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>><?= $lang('none') ?></option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label"><?= $lang('smtp_user') ?></label>
                <input type="text" name="smtp_user" class="form-input"
                       value="<?= htmlspecialchars($values['smtp_user'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label"><?= $lang('smtp_password') ?></label>
                <input type="password" name="smtp_pass" class="form-input"
                       placeholder="<?= !empty($values['smtp_pass']) ? '********' : '' ?>">
                <?php if (!empty($values['smtp_pass'])): ?>
                <small class="form-hint"><?= $lang('leave_empty_no_change') ?></small>
                <?php endif; ?>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label"><?= $lang('smtp_from_email') ?></label>
                <input type="email" name="smtp_from_email" class="form-input"
                       value="<?= htmlspecialchars($values['smtp_from_email'] ?? '') ?>" placeholder="noreply@example.com">
            </div>
            <div class="form-group">
                <label class="form-label"><?= $lang('smtp_from_name') ?></label>
                <input type="text" name="smtp_from_name" class="form-input"
                       value="<?= htmlspecialchars($values['smtp_from_name'] ?? '') ?>" placeholder="BiLLU">
            </div>
        </div>
        <div class="form-group">
            <button type="button" class="btn btn-secondary" onclick="testSystemSmtp()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M22 2L11 13"/><path d="M22 2L15 22L11 13L2 9L22 2Z"/></svg>
                <?= $lang('test_smtp') ?>
            </button>
            <span id="smtp-system-test-result" style="margin-left:8px;"></span>
        </div>
    </div>

    <!-- Technical Support Contact -->
    <div class="section">
        <h2><?= $lang('tech_support_settings') ?></h2>

        <div class="form-group">
            <label class="form-label"><?= $lang('support_contact_name') ?></label>
            <input type="text" name="support_contact_name" class="form-input"
                   value="<?= htmlspecialchars($values['support_contact_name'] ?? '') ?>"
                   placeholder="Jan Kowalski">
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label"><?= $lang('support_contact_email') ?></label>
                <input type="email" name="support_contact_email" class="form-input"
                       value="<?= htmlspecialchars($values['support_contact_email'] ?? '') ?>"
                       placeholder="support@example.com">
            </div>

            <div class="form-group">
                <label class="form-label"><?= $lang('support_contact_phone') ?></label>
                <input type="text" name="support_contact_phone" class="form-input"
                       value="<?= htmlspecialchars($values['support_contact_phone'] ?? '') ?>"
                       placeholder="+48 123 456 789">
            </div>
        </div>
        <small class="form-hint"><?= $lang('support_contact_hint') ?></small>
    </div>

    <!-- Verification Settings -->
    <div class="section">
        <h2><?= $lang('verification_settings') ?></h2>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label"><?= $lang('verification_deadline_day') ?></label>
                <input type="number" name="verification_deadline_day" class="form-input" min="1" max="28"
                       value="<?= htmlspecialchars($values['verification_deadline_day'] ?? '5') ?>">
                <small class="form-hint"><?= $lang('verification_deadline_hint_next_month') ?></small>
            </div>

            <div class="form-group">
                <label class="form-label"><?= $lang('auto_accept_on_deadline') ?></label>
                <select name="auto_accept_on_deadline" class="form-input">
                    <option value="1" <?= ($values['auto_accept_on_deadline'] ?? '1') === '1' ? 'selected' : '' ?>><?= $lang('yes') ?></option>
                    <option value="0" <?= ($values['auto_accept_on_deadline'] ?? '1') === '0' ? 'selected' : '' ?>><?= $lang('no') ?></option>
                </select>
                <small class="form-hint"><?= $lang('auto_accept_hint') ?></small>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label"><?= $lang('password_expiry_days') ?></label>
                <input type="number" name="password_expiry_days" class="form-input" min="30" max="365"
                       value="<?= htmlspecialchars($values['password_expiry_days'] ?? '90') ?>">
                <small class="form-hint"><?= $lang('password_expiry_hint') ?></small>
            </div>

            <div class="form-group">
                <label class="form-label"><?= $lang('notification_days_before') ?></label>
                <input type="number" name="notification_days_before" class="form-input" min="1" max="14"
                       value="<?= htmlspecialchars($values['notification_days_before'] ?? '3') ?>">
                <small class="form-hint"><?= $lang('notification_days_hint') ?></small>
            </div>
        </div>
    </div>

    <!-- KSeF Auto-Import Settings -->
    <div class="section">
        <h2><?= $lang('ksef_auto_import_settings') ?></h2>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label"><?= $lang('ksef_auto_import_day') ?></label>
                <select name="ksef_auto_import_day" class="form-input">
                    <option value="0" <?= ($values['ksef_auto_import_day'] ?? '0') === '0' ? 'selected' : '' ?>><?= $lang('last_day_of_month') ?></option>
                    <?php for ($d = 1; $d <= 28; $d++): ?>
                    <option value="<?= $d ?>" <?= ($values['ksef_auto_import_day'] ?? '0') === (string)$d ? 'selected' : '' ?>><?= $d ?></option>
                    <?php endfor; ?>
                </select>
                <small class="form-hint"><?= $lang('ksef_auto_import_day_hint') ?></small>
            </div>
        </div>
    </div>

    <!-- Session Settings -->
    <div class="section">
        <h2><?= $lang('session_settings') ?></h2>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label"><?= $lang('session_timeout') ?></label>
                <input type="number" name="session_timeout_minutes" class="form-input" min="5" max="1440"
                       value="<?= htmlspecialchars($values['session_timeout_minutes'] ?? '30') ?>">
            </div>

            <div class="form-group">
                <label class="form-label"><?= $lang('max_sessions') ?></label>
                <input type="number" name="max_sessions_per_user" class="form-input" min="1" max="10"
                       value="<?= htmlspecialchars($values['max_sessions_per_user'] ?? '3') ?>">
            </div>
        </div>
    </div>


    <!-- Branding Settings -->
    <div class="section">
        <h2><?= $lang('branding_settings') ?></h2>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label"><?= $lang('system_name') ?></label>
                <input type="text" name="system_name" class="form-input"
                       value="<?= htmlspecialchars($values['system_name'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label"><?= $lang('system_description') ?></label>
                <input type="text" name="system_description" class="form-input"
                       value="<?= htmlspecialchars($values['system_description'] ?? '') ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label"><?= $lang('primary_color') ?></label>
                <input type="color" name="primary_color" class="form-input"
                       value="<?= htmlspecialchars($values['primary_color'] ?? '#008F8F') ?>">
            </div>

            <div class="form-group">
                <label class="form-label"><?= $lang('secondary_color') ?></label>
                <input type="color" name="secondary_color" class="form-input"
                       value="<?= htmlspecialchars($values['secondary_color'] ?? '#0B2430') ?>">
            </div>

            <div class="form-group">
                <label class="form-label"><?= $lang('accent_color') ?></label>
                <input type="color" name="accent_color" class="form-input"
                       value="<?= htmlspecialchars($values['accent_color'] ?? '#22C55E') ?>">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label"><?= $lang('logo') ?> (tryb jasny)</label>
            <input type="file" name="logo" class="form-input" accept="image/*">
            <?php if (!empty($values['logo'])): ?>
                <small class="form-hint"><?= $lang('current') ?>: <?= htmlspecialchars($values['logo']) ?></small>
            <?php endif; ?>
            <small class="form-hint">Logo wyswietlane w sidebarze w trybie jasnym</small>
        </div>

        <div class="form-group">
            <label class="form-label">Logo (tryb ciemny)</label>
            <input type="file" name="logo_dark" class="form-input" accept="image/*">
            <?php if (!empty($values['logo_dark'])): ?>
                <small class="form-hint"><?= $lang('current') ?>: <?= htmlspecialchars($values['logo_dark']) ?></small>
            <?php endif; ?>
            <small class="form-hint">Logo wyswietlane w sidebarze w trybie ciemnym. Jesli puste, uzyje logo jasnego.</small>
        </div>

        <div class="form-group">
            <label class="form-label">Logo (ekran logowania)</label>
            <input type="file" name="logo_login" class="form-input" accept="image/*">
            <?php if (!empty($values['logo_login'])): ?>
                <small class="form-hint"><?= $lang('current') ?>: <?= htmlspecialchars($values['logo_login']) ?></small>
            <?php endif; ?>
            <small class="form-hint">Logo wyswietlane na stronie logowania. Jesli puste, uzyje logo jasnego.</small>
        </div>
    </div>

    <!-- Two-Factor Authentication Settings -->
    <div class="section">
        <h2><?= $lang('2fa_settings') ?></h2>

        <div class="form-group">
            <label class="form-label"><?= $lang('2fa_allow') ?></label>
            <select name="2fa_enabled" class="form-input">
                <option value="1" <?= ($values['2fa_enabled'] ?? '1') === '1' ? 'selected' : '' ?>><?= $lang('yes') ?></option>
                <option value="0" <?= ($values['2fa_enabled'] ?? '1') === '0' ? 'selected' : '' ?>><?= $lang('no') ?></option>
            </select>
            <small class="form-hint"><?= $lang('2fa_allow_hint') ?></small>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label"><?= $lang('2fa_require_all') ?></label>
                <select name="2fa_required" class="form-input">
                    <option value="1" <?= ($values['2fa_required'] ?? '0') === '1' ? 'selected' : '' ?>><?= $lang('yes') ?></option>
                    <option value="0" <?= ($values['2fa_required'] ?? '0') === '0' ? 'selected' : '' ?>><?= $lang('no') ?></option>
                </select>
                <small class="form-hint"><?= $lang('2fa_require_all_hint') ?></small>
            </div>

            <div class="form-group">
                <label class="form-label"><?= $lang('2fa_require_admin') ?></label>
                <select name="2fa_required_admin" class="form-input">
                    <option value="1" <?= ($values['2fa_required_admin'] ?? '0') === '1' ? 'selected' : '' ?>><?= $lang('yes') ?></option>
                    <option value="0" <?= ($values['2fa_required_admin'] ?? '0') === '0' ? 'selected' : '' ?>><?= $lang('no') ?></option>
                </select>
                <small class="form-hint"><?= $lang('2fa_require_admin_hint') ?></small>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">2FA wymagane dla biur</label>
                <select name="2fa_required_office" class="form-input">
                    <option value="1" <?= ($values['2fa_required_office'] ?? '0') === '1' ? 'selected' : '' ?>><?= $lang('yes') ?></option>
                    <option value="0" <?= ($values['2fa_required_office'] ?? '0') === '0' ? 'selected' : '' ?>><?= $lang('no') ?></option>
                </select>
                <small class="form-hint">Wymusza konfigurację 2FA dla wszystkich kont biurowych przy następnym logowaniu.</small>
            </div>

            <div class="form-group">
                <label class="form-label">2FA wymagane dla klientów</label>
                <select name="2fa_required_client" class="form-input">
                    <option value="1" <?= ($values['2fa_required_client'] ?? '0') === '1' ? 'selected' : '' ?>><?= $lang('yes') ?></option>
                    <option value="0" <?= ($values['2fa_required_client'] ?? '0') === '0' ? 'selected' : '' ?>><?= $lang('no') ?></option>
                </select>
                <small class="form-hint">Wymusza konfigurację 2FA dla wszystkich kont klienckich przy następnym logowaniu.</small>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Zaufane urządzenia — okres pamiętania (dni)</label>
            <input type="number" name="trusted_device_ttl_days" class="form-input"
                   min="1" max="90" step="1"
                   value="<?= htmlspecialchars($values['trusted_device_ttl_days'] ?? '5') ?>"
                   style="max-width:160px;">
            <small class="form-hint">
                Po pomyślnej weryfikacji 2FA użytkownik może zaznaczyć „Zapamiętaj to urządzenie".
                Przez tyle dni system nie zapyta go o kod 2FA z tej przeglądarki.
                Dopuszczalny zakres: 1–90 dni. Domyślnie: 5.
            </small>
        </div>
    </div>

    <!-- Privacy Policy Settings -->
    <div class="section">
        <h2><?= $lang('privacy_settings') ?></h2>

        <div class="form-group">
            <label class="form-label"><?= $lang('privacy_policy_enabled') ?></label>
            <select name="privacy_policy_enabled" class="form-input">
                <option value="1" <?= ($values['privacy_policy_enabled'] ?? '0') === '1' ? 'selected' : '' ?>><?= $lang('yes') ?></option>
                <option value="0" <?= ($values['privacy_policy_enabled'] ?? '0') === '0' ? 'selected' : '' ?>><?= $lang('no') ?></option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label"><?= $lang('privacy_policy_text') ?></label>
            <textarea name="privacy_policy_text" class="form-input" rows="10"><?= htmlspecialchars($values['privacy_policy_text'] ?? '') ?></textarea>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $lang('save_settings') ?></button>
    </div>
</form>

<script>
function testSystemSmtp() {
    var result = document.getElementById('smtp-system-test-result');
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
    fetch('/admin/settings/test-smtp', { method: 'POST', body: formData })
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
