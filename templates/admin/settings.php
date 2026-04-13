<h1><?= $lang('system_settings') ?></h1>

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

    <!-- GUS API Settings -->
    <div class="section">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h2><?= $lang('gus_settings') ?></h2>
            <a href="/admin/gus-diagnostic" class="btn btn-secondary btn-sm"><?= $lang('gus_diagnostics') ?></a>
        </div>

        <div class="form-group">
            <label class="form-label"><?= $lang('api_key') ?></label>
            <input type="text" name="gus_api_key" class="form-input"
                   value="<?= htmlspecialchars($values['gus_api_key'] ?? '') ?>">
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label"><?= $lang('api_url') ?></label>
                <input type="url" name="gus_api_url" class="form-input"
                       value="<?= htmlspecialchars($values['gus_api_url'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label"><?= $lang('environment') ?></label>
                <select name="gus_api_env" class="form-input">
                    <option value="test" <?= ($values['gus_api_env'] ?? 'test') === 'test' ? 'selected' : '' ?>>Test</option>
                    <option value="production" <?= ($values['gus_api_env'] ?? 'test') === 'production' ? 'selected' : '' ?>>Production</option>
                </select>
            </div>
        </div>
    </div>

    <!-- CEIDG API Settings -->
    <div class="section">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h2>CEIDG API</h2>
            <a href="/admin/ceidg-diagnostic" class="btn btn-secondary btn-sm">Diagnostyka CEIDG</a>
        </div>
        <p style="color:var(--gray-500); font-size:13px; margin-bottom:12px;">
            Fallback dla GUS — automatycznie odpytywane gdy podmiot nie zostanie znaleziony w rejestrze GUS.
            Token API można uzyskać na portalu <a href="https://dane.biznes.gov.pl" target="_blank" rel="noopener">dane.biznes.gov.pl</a>.
        </p>

        <div class="form-group">
            <label class="form-label">Token API (JWT)</label>
            <input type="text" name="ceidg_api_token" class="form-input"
                   value="<?= htmlspecialchars($values['ceidg_api_token'] ?? '') ?>"
                   placeholder="eyJhbGciOiJIUzI1NiIs...">
            <small style="color:var(--gray-500);">Token z portalu dane.biznes.gov.pl → Moje konto → API</small>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">URL API (opcjonalnie)</label>
                <input type="url" name="ceidg_api_url" class="form-input"
                       value="<?= htmlspecialchars($values['ceidg_api_url'] ?? '') ?>"
                       placeholder="https://dane.biznes.gov.pl/api/ceidg/v2/firmy">
                <small style="color:var(--gray-500);">Zostaw puste = domyślny URL dla wybranego środowiska. Ustaw ręcznie jeśli domyślny nie działa.</small>
            </div>

            <div class="form-group">
                <label class="form-label"><?= $lang('environment') ?></label>
                <select name="ceidg_api_env" class="form-input">
                    <option value="test" <?= ($values['ceidg_api_env'] ?? 'test') === 'test' ? 'selected' : '' ?>>Test</option>
                    <option value="production" <?= ($values['ceidg_api_env'] ?? 'test') === 'production' ? 'selected' : '' ?>>Production</option>
                </select>
                <small style="color:var(--gray-500);">Ignorowane jeśli podano URL ręcznie.</small>
            </div>
        </div>
    </div>

    <!-- White List VAT API Settings -->
    <div class="section">
        <h2>Biała Lista VAT API</h2>
        <p style="color:var(--gray-500); font-size:13px; margin-bottom:12px;">Weryfikacja kontrahentów na białej liście podatników VAT przed eksportem przelewów bankowych.</p>

        <div class="form-group">
            <label class="form-label">URL API</label>
            <input type="url" name="whitelist_api_url" class="form-input"
                   value="<?= htmlspecialchars($values['whitelist_api_url'] ?? 'https://wl-api.mf.gov.pl') ?>"
                   placeholder="https://wl-api.mf.gov.pl">
            <small style="color:var(--gray-500);">Domyślnie: https://wl-api.mf.gov.pl — API publiczne, nie wymaga klucza</small>
        </div>

        <div class="form-group">
            <label class="form-label">Weryfikacja przed eksportem bankowym</label>
            <select name="whitelist_check_enabled" class="form-input" style="width:auto;">
                <option value="1" <?= ($values['whitelist_check_enabled'] ?? '1') === '1' ? 'selected' : '' ?>>Włączona (zalecane)</option>
                <option value="0" <?= ($values['whitelist_check_enabled'] ?? '1') === '0' ? 'selected' : '' ?>>Wyłączona</option>
            </select>
            <small style="color:var(--gray-500);">Przy włączonej weryfikacji system sprawdza NIP i konto bankowe sprzedawcy przed dodaniem do paczki przelewów</small>
        </div>
    </div>

    <!-- KSeF API v2 Settings -->
    <div class="section">
        <h2><?= $lang('ksef_settings') ?></h2>
        <small class="form-hint" style="display:block;margin-bottom:12px;">
            <?= $lang('ksef_global_hint') ?>
        </small>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label"><?= $lang('environment') ?></label>
                <select name="ksef_api_env" id="ksef-env" class="form-input" onchange="updateKsefUrl()">
                    <option value="test" <?= ($values['ksef_api_env'] ?? 'test') === 'test' ? 'selected' : '' ?>>Test (api-test.ksef.mf.gov.pl)</option>
                    <option value="demo" <?= ($values['ksef_api_env'] ?? 'test') === 'demo' ? 'selected' : '' ?>>Demo (api-demo.ksef.mf.gov.pl)</option>
                    <option value="production" <?= ($values['ksef_api_env'] ?? 'test') === 'production' ? 'selected' : '' ?>>Produkcja (api.ksef.mf.gov.pl)</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label"><?= $lang('api_url') ?></label>
                <input type="url" name="ksef_api_url" id="ksef-url" class="form-input" readonly
                       value="<?= htmlspecialchars($values['ksef_api_url'] ?? 'https://api-test.ksef.mf.gov.pl/api/v2') ?>"
                       style="background:#f5f5f5;">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">NIP</label>
            <input type="text" name="ksef_nip" class="form-input" maxlength="10"
                   value="<?= htmlspecialchars($values['ksef_nip'] ?? '') ?>"
                   placeholder="0000000000">
            <small class="form-hint"><?= $lang('ksef_nip_hint') ?></small>
        </div>
    </div>

    <script>
    function updateKsefUrl() {
        var env = document.getElementById('ksef-env').value;
        var urls = {
            'test':       'https://api-test.ksef.mf.gov.pl/api/v2',
            'demo':       'https://api-demo.ksef.mf.gov.pl/api/v2',
            'production': 'https://api.ksef.mf.gov.pl/api/v2'
        };
        document.getElementById('ksef-url').value = urls[env] || urls['test'];
    }
    </script>

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

    <!-- Mobile API (Android) Settings -->
    <div class="section">
        <h2><?= $lang('mobile_api_settings') ?></h2>

        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="mobile_api_enabled" value="1"
                    <?= ($values['mobile_api_enabled'] ?? '1') !== '0' ? 'checked' : '' ?>>
                <?= $lang('mobile_api_enabled_label') ?>
            </label>
            <small class="form-hint">
                <?= $lang('mobile_api_enabled_hint') ?>
            </small>
        </div>

        <div style="display:flex;gap:1rem;margin-bottom:1rem;">
            <div class="stat-box" style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:8px;padding:1rem 1.5rem;text-align:center;min-width:120px;">
                <div style="font-size:2rem;font-weight:700;color:var(--primary);"><?= (int) ($activeSessions ?? 0) ?></div>
                <div style="font-size:0.85rem;color:var(--gray-500);">
                    <?= $lang('active_mobile_sessions') ?>
                </div>
            </div>
        </div>

        <a href="/admin/api/sessions" class="btn btn-secondary btn-sm">
            <?= $lang('manage_sessions') ?> &rarr;
        </a>
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
