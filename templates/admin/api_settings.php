<h1>Konfiguracja API</h1>

<p style="margin-bottom:16px;">
    <a href="/admin/settings" class="btn btn-sm">&larr; Powrót do ustawień</a>
</p>

<?php $flashSuccess = \App\Core\Session::getFlash('success'); ?>
<?php if ($flashSuccess): ?>
    <div class="alert alert-success"><?= $lang($flashSuccess) ?></div>
<?php endif; ?>
<?php $flashError = \App\Core\Session::getFlash('error'); ?>
<?php if ($flashError): ?>
    <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<form method="POST" action="/admin/api-settings" class="form-card">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

    <!-- GUS API -->
    <div class="section">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h2><?= $lang('gus_settings') ?></h2>
            <a href="/admin/gus-diagnostic" class="btn btn-secondary btn-sm"><?= $lang('gus_diagnostics') ?></a>
        </div>
        <p style="color:var(--gray-500); font-size:13px; margin-bottom:12px;">
            Wyszukiwarka REGON (BIR1) — pobieranie danych podmiotów po NIP. Wymaga klucza z portalu
            <a href="https://api.stat.gov.pl/Home/RegonApi" target="_blank" rel="noopener">api.stat.gov.pl</a>.
        </p>

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

    <!-- CEIDG API -->
    <div class="section">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h2>CEIDG API</h2>
            <a href="/admin/ceidg-diagnostic" class="btn btn-secondary btn-sm">Diagnostyka CEIDG</a>
        </div>
        <p style="color:var(--gray-500); font-size:13px; margin-bottom:12px;">
            Fallback dla GUS — automatycznie odpytywane gdy podmiot nie zostanie znaleziony w rejestrze GUS.
            Token API można uzyskać na portalu
            <a href="https://dane.biznes.gov.pl" target="_blank" rel="noopener">dane.biznes.gov.pl</a>.
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

    <!-- White List VAT API -->
    <div class="section">
        <h2>Biała Lista VAT API</h2>
        <p style="color:var(--gray-500); font-size:13px; margin-bottom:12px;">
            Weryfikacja kontrahentów na białej liście podatników VAT przed eksportem przelewów bankowych.
            API publiczne, nie wymaga klucza.
        </p>

        <div class="form-group">
            <label class="form-label">URL API</label>
            <input type="url" name="whitelist_api_url" class="form-input"
                   value="<?= htmlspecialchars($values['whitelist_api_url'] ?? 'https://wl-api.mf.gov.pl') ?>"
                   placeholder="https://wl-api.mf.gov.pl">
            <small style="color:var(--gray-500);">Domyślnie: https://wl-api.mf.gov.pl</small>
        </div>

        <div class="form-group">
            <label class="form-label">Weryfikacja przed eksportem bankowym</label>
            <select name="whitelist_check_enabled" class="form-input" style="width:auto;">
                <option value="1" <?= ($values['whitelist_check_enabled'] ?? '1') === '1' ? 'selected' : '' ?>>Włączona (zalecane)</option>
                <option value="0" <?= ($values['whitelist_check_enabled'] ?? '1') === '0' ? 'selected' : '' ?>>Wyłączona</option>
            </select>
            <small style="color:var(--gray-500);">Przy włączonej weryfikacji system sprawdza NIP i konto bankowe sprzedawcy przed dodaniem do paczki przelewów.</small>
        </div>
    </div>

    <!-- KSeF API v2 -->
    <div class="section">
        <h2><?= $lang('ksef_settings') ?></h2>
        <p style="color:var(--gray-500); font-size:13px; margin-bottom:12px;">
            <?= $lang('ksef_global_hint') ?>
        </p>

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

    <!-- KRS / CRBR (public APIs, no key needed) -->
    <div class="section">
        <h2>KRS Open Data + CRBR</h2>
        <p style="color:var(--gray-500); font-size:13px; margin-bottom:12px;">
            Publiczne API rejestrów państwowych — nie wymagają klucza, są wyłączalne tylko przez .env (PRZESTRZEŃ NAZW <code>KRS_API_BASE_URL</code> / <code>CRBR_API_BASE_URL</code>).
        </p>
        <ul style="color:var(--gray-500); font-size:13px; line-height:1.6;">
            <li>KRS (Krajowy Rejestr Sądowy): <code>https://api-krs.ms.gov.pl/api/krs</code> — odpisy aktualne i pełne</li>
            <li>CRBR (Beneficjenci rzeczywiści): <code>https://crbr.podatki.gov.pl/adcrbr/api</code> — wymaga uprawnień office_admin</li>
        </ul>
        <p style="color:var(--gray-500); font-size:13px; margin-top:8px;">
            Cache TTL: KRS 30 dni, CRBR 7 dni (PII). Notatki widoczne tylko dla biura rachunkowego.
        </p>
    </div>

    <!-- Mobile API (Android) -->
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
