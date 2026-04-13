<h1><?= $client ? $lang('edit_client') : $lang('add_client') ?></h1>

<form method="POST" action="<?= $client ? "/admin/clients/{$client['id']}/update" : '/admin/clients/create' ?>" class="form-card">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

    <div class="form-group">
        <label class="form-label"><?= $lang('office') ?></label>
        <select name="office_id" class="form-input">
            <option value="">-</option>
            <?php foreach ($offices as $o): ?>
                <option value="<?= $o['id'] ?>" <?= ($client['office_id'] ?? '') == $o['id'] ? 'selected' : '' ?>><?= htmlspecialchars($o['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if (!$client): ?>
    <div class="form-group">
        <label class="form-label">NIP *</label>
        <div class="input-group">
            <input type="text" name="nip" id="nip" class="form-input" required maxlength="10" pattern="[0-9]{10}"
                   placeholder="0000000000" value="<?= htmlspecialchars($_POST['nip'] ?? '') ?>">
            <button type="button" id="gus-lookup-btn" class="btn btn-secondary">Pobierz z GUS</button>
        </div>
    </div>
    <?php else: ?>
    <div class="form-group">
        <label class="form-label">NIP</label>
        <input type="text" class="form-input" value="<?= htmlspecialchars($client['nip']) ?>" disabled>
    </div>
    <?php endif; ?>

    <div class="form-group">
        <label class="form-label"><?= $lang('company_name') ?> *</label>
        <input type="text" name="company_name" id="company_name" class="form-input" required
               value="<?= htmlspecialchars($client['company_name'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label class="form-label"><?= $lang('address') ?></label>
        <input type="text" name="address" id="address" class="form-input"
               value="<?= htmlspecialchars($client['address'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label class="form-label"><?= $lang('representative') ?> *</label>
        <input type="text" name="representative_name" id="representative_name" class="form-input" required
               value="<?= htmlspecialchars($client['representative_name'] ?? '') ?>">
    </div>

    <div class="form-row">
        <div class="form-group">
            <label class="form-label"><?= $lang('email') ?> *</label>
            <input type="email" name="email" class="form-input" required
                   value="<?= htmlspecialchars($client['email'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label"><?= $lang('report_email') ?> *</label>
            <input type="email" name="report_email" class="form-input" required
                   value="<?= htmlspecialchars($client['report_email'] ?? '') ?>">
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label class="form-label"><?= $lang('phone') ?></label>
            <input type="text" name="phone" id="phone" class="form-input"
                   value="<?= htmlspecialchars($client['phone'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label">REGON</label>
            <input type="text" name="regon" id="regon" class="form-input"
                   value="<?= htmlspecialchars($client['regon'] ?? '') ?>">
        </div>
    </div>

    <div class="form-group">
        <label class="form-label"><?= $lang('password') ?> <?= $client ? '(' . $lang('leave_empty_no_change') . ')' : '*' ?></label>
        <input type="password" name="password" class="form-input" <?= $client ? '' : 'required' ?> minlength="12">
        <small class="form-hint"><?= $lang('password_requirements') ?></small>
    </div>

    <?php if ($client): ?>
    <div class="section" style="margin-top:16px; padding:16px; background:var(--gray-50); border:1px solid var(--gray-200); border-radius:8px;">
        <h3 style="margin-top:0;"><?= $lang('admin_reset_password') ?></h3>
        <p class="text-muted" style="font-size:13px; margin-bottom:12px;"><?= $lang('admin_reset_password_hint') ?></p>
        <form method="POST" action="/admin/clients/<?= $client['id'] ?>/reset-password" style="display:flex; gap:8px; align-items:flex-end;">
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
                <option value="pl" <?= ($client['language'] ?? 'pl') === 'pl' ? 'selected' : '' ?>>Polski</option>
                <option value="en" <?= ($client['language'] ?? 'pl') === 'en' ? 'selected' : '' ?>>English</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label"><?= $lang('status') ?></label>
            <label class="checkbox-label">
                <input type="checkbox" name="is_active" <?= $client['is_active'] ? 'checked' : '' ?>>
                <?= $lang('active') ?>
            </label>
        </div>
        <div class="form-group">
            <label class="form-label"><?= $lang('mobile_app_access') ?></label>
            <label class="checkbox-label">
                <input type="checkbox" name="mobile_app_enabled" value="1"
                    <?= ($client['mobile_app_enabled'] ?? 1) ? 'checked' : '' ?>>
                <?= $lang('mobile_app_access_label') ?>
            </label>
            <small class="form-hint">
                <?= $lang('mobile_app_access_hint') ?>
            </small>
        </div>
    </div>
    <?php endif; ?>

    <div class="section" style="margin-top: 24px;">
        <h2><?= $lang('ksef_integration') ?></h2>
        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="ksef_enabled" id="ksef-enabled"
                       <?= ($client['ksef_enabled'] ?? 0) ? 'checked' : '' ?>
                       onchange="toggleKsefToken()">
                <?= $lang('ksef_enable_client') ?>
            </label>
            <small class="form-hint"><?= $lang('ksef_enable_client_hint') ?></small>
        </div>
        <div id="ksef-token-section" style="<?= ($client['ksef_enabled'] ?? 0) ? '' : 'display:none;' ?>">
            <?php
            try {
                $ksefConfig = $client ? \App\Models\KsefConfig::findByClientId((int)$client['id']) : null;
            } catch (\Exception $e) {
                $ksefConfig = null;
            }
            ?>

            <?php if ($ksefConfig && $ksefConfig['auth_method'] === 'certificate' && !empty($ksefConfig['cert_fingerprint'])): ?>
            <div class="alert alert-success" style="margin-bottom:12px;">
                <strong><?= $lang('ksef_certificate_auth') ?></strong>
                — <?= htmlspecialchars($ksefConfig['cert_subject_cn'] ?? '') ?>
                (<?= htmlspecialchars($lang('ksef_cert_type_' . ($ksefConfig['cert_type'] ?? 'personal'))) ?>)
                <br><small><?= $lang('ksef_cert_valid') ?>: <?= $ksefConfig['cert_valid_from'] ?> — <?= $ksefConfig['cert_valid_to'] ?></small>
                <?php if (\App\Services\KsefCertificateService::isExpiringSoon($ksefConfig['cert_valid_to'])): ?>
                    <br><span class="badge badge-warning"><?= $lang('ksef_cert_expiring_soon') ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($ksefConfig && $ksefConfig['auth_method'] === 'ksef_cert' && !empty($ksefConfig['cert_ksef_pem'])): ?>
            <div class="alert alert-success" style="margin-bottom:12px;">
                <strong><?= $lang('ksef_auth_ksef_cert') ?></strong>
                — <?= htmlspecialchars($ksefConfig['cert_ksef_name'] ?? '') ?>
                <br><small><?= $lang('ksef_cert_ksef_serial') ?>: <?= htmlspecialchars($ksefConfig['cert_ksef_serial_number'] ?? '') ?></small>
                <?php if (!empty($ksefConfig['cert_ksef_valid_to']) && \App\Services\KsefCertificateService::isExpiringSoon($ksefConfig['cert_ksef_valid_to'])): ?>
                    <br><span class="badge badge-warning"><?= $lang('ksef_cert_expiring_soon') ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!$ksefConfig || empty($ksefConfig['cert_fingerprint'])): ?>
            <div class="alert alert-info" style="margin-bottom:12px;">
                <?= $lang('ksef_cert_upload_client_hint') ?>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label"><?= $lang('environment') ?></label>
                <select name="ksef_environment" class="form-input">
                    <option value="test" <?= ($ksefConfig['ksef_environment'] ?? 'test') === 'test' ? 'selected' : '' ?>><?= $lang('test') ?></option>
                    <option value="demo" <?= ($ksefConfig['ksef_environment'] ?? '') === 'demo' ? 'selected' : '' ?>><?= $lang('demo') ?></option>
                    <option value="production" <?= ($ksefConfig['ksef_environment'] ?? '') === 'production' ? 'selected' : '' ?>><?= $lang('production') ?></option>
                </select>
            </div>

            <small class="form-hint"><?= $lang('ksef_cert_upload_client_hint') ?></small>
        </div>
    </div>

    <div class="section" style="margin-top: 24px;">
        <h2><?= $lang('ip_whitelist') ?></h2>
        <div class="form-group">
            <label class="form-label"><?= $lang('ip_whitelist') ?></label>
            <input type="text" name="ip_whitelist" class="form-input"
                   value="<?= htmlspecialchars($client['ip_whitelist'] ?? '') ?>"
                   placeholder="<?= $lang('ip_whitelist_placeholder') ?>">
            <small class="form-hint"><?= $lang('ip_whitelist_hint') ?></small>
        </div>
    </div>

    <div class="section" style="margin-top: 24px;">
        <h2><?= $lang('cost_centers_management') ?></h2>
        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="has_cost_centers" id="has-cost-centers"
                       <?= ($client['has_cost_centers'] ?? 0) ? 'checked' : '' ?>
                       onchange="toggleCostCenters()">
                <?= $lang('enable_cost_centers') ?>
            </label>
            <small class="form-hint"><?= $lang('enable_cost_centers_hint') ?></small>
        </div>
        <div id="cost-centers-list" style="<?= ($client['has_cost_centers'] ?? 0) ? '' : 'display:none;' ?>">
            <div id="cc-entries">
                <?php
                $ccList = $costCenters ?? [];
                if (empty($ccList)) $ccList = [['name' => '']]; // At least one empty
                foreach ($ccList as $i => $cc):
                ?>
                <div class="form-group cc-entry" style="display:flex;gap:8px;align-items:center;">
                    <span style="min-width:20px;"><?= $i + 1 ?>.</span>
                    <input type="text" name="cost_center_names[]" class="form-input"
                           value="<?= htmlspecialchars($cc['name'] ?? '') ?>"
                           placeholder="<?= $lang('cost_center_placeholder') ?>" maxlength="255">
                    <button type="button" class="btn btn-xs btn-danger" onclick="removeCostCenter(this)">&times;</button>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-sm btn-secondary" onclick="addCostCenter()" id="add-cc-btn">
                + <?= $lang('add_cost_center') ?>
            </button>
            <small class="form-hint"><?= $lang('max_cost_centers') ?></small>
        </div>
    </div>

    <?php if ($client): ?>
    <div class="section" style="margin-top: 24px;">
        <h2><?= $lang('invoice_email_sending') ?></h2>
        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="can_send_invoices" id="can-send-invoices"
                       <?= ($client['can_send_invoices'] ?? 0) ? 'checked' : '' ?>
                       onchange="toggleClientSmtp()">
                <?= $lang('can_send_invoices') ?>
            </label>
            <small class="form-hint"><?= $lang('can_send_invoices_hint') ?></small>
        </div>

        <div id="client-smtp-section" style="<?= ($client['can_send_invoices'] ?? 0) ? '' : 'display:none;' ?>">
            <h3 style="margin:16px 0 8px;"><?= $lang('client_smtp_section') ?></h3>
            <p class="text-muted" style="font-size:13px; margin-bottom:12px;">
                <?= $lang('client_smtp_description') ?>
            </p>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="client_smtp_enabled" id="client-smtp-enabled"
                           <?= ($smtpConfig['is_enabled'] ?? 0) ? 'checked' : '' ?>
                           onchange="toggleClientSmtpFields()">
                    <?= $lang('client_smtp_enable') ?>
                </label>
            </div>
            <div id="client-smtp-fields" style="<?= ($smtpConfig['is_enabled'] ?? 0) ? '' : 'display:none;' ?>">
                <div class="form-row">
                    <div class="form-group" style="flex:2;">
                        <label class="form-label">SMTP Host</label>
                        <input type="text" name="client_smtp_host" class="form-input"
                               value="<?= htmlspecialchars($smtpConfig['smtp_host'] ?? '') ?>" placeholder="smtp.example.com">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label class="form-label">Port</label>
                        <input type="number" name="client_smtp_port" class="form-input"
                               value="<?= htmlspecialchars($smtpConfig['smtp_port'] ?? '587') ?>">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label class="form-label"><?= $lang('encryption') ?></label>
                        <select name="client_smtp_encryption" class="form-input">
                            <option value="tls" <?= ($smtpConfig['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
                            <option value="ssl" <?= ($smtpConfig['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                            <option value="none" <?= ($smtpConfig['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>><?= $lang('none') ?></option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= $lang('smtp_user') ?></label>
                        <input type="text" name="client_smtp_user" class="form-input"
                               value="<?= htmlspecialchars($smtpConfig['smtp_user'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= $lang('smtp_password') ?></label>
                        <input type="password" name="client_smtp_pass" class="form-input"
                               placeholder="<?= !empty($smtpConfig['smtp_pass_encrypted']) ? '********' : '' ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= $lang('smtp_from_email') ?></label>
                        <input type="email" name="client_smtp_from_email" class="form-input"
                               value="<?= htmlspecialchars($smtpConfig['from_email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= $lang('smtp_from_name') ?></label>
                        <input type="text" name="client_smtp_from_name" class="form-input"
                               value="<?= htmlspecialchars($smtpConfig['from_name'] ?? '') ?>">
                    </div>
                </div>
                <?php if ($client): ?>
                <button type="button" class="btn btn-sm" onclick="testClientSmtp(<?= $client['id'] ?>)">
                    <?= $lang('test_smtp') ?>
                </button>
                <span id="client-smtp-test-result" style="margin-left:8px;"></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $lang('save') ?></button>
        <a href="/admin/clients" class="btn btn-secondary"><?= $lang('cancel') ?></a>
    </div>
</form>

<?php if (!$client): ?>
<script>
document.getElementById('gus-lookup-btn').addEventListener('click', function() {
    const nip = document.getElementById('nip').value.trim();
    if (!nip || nip.length !== 10) {
        alert('Podaj prawidlowy NIP (10 cyfr)');
        return;
    }
    this.disabled = true;
    this.textContent = '...';
    fetch('/admin/gus-lookup?nip=' + encodeURIComponent(nip))
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                return;
            }
            if (data.company_name) document.getElementById('company_name').value = data.company_name;
            if (data.formatted_address) document.getElementById('address').value = data.formatted_address;
            else if (data.address) document.getElementById('address').value = data.address;
            if (data.regon) document.getElementById('regon').value = data.regon;
            if (data.source === 'ceidg') alert('Dane pobrano z CEIDG (brak w GUS)');
        })
        .catch(() => alert('Blad polaczenia z GUS/CEIDG'))
        .finally(() => {
            this.disabled = false;
            this.textContent = 'Pobierz z GUS';
        });
});
</script>
<?php endif; ?>

<script>
function toggleKsefToken() {
    var section = document.getElementById('ksef-token-section');
    section.style.display = document.getElementById('ksef-enabled').checked ? '' : 'none';
}
function toggleCostCenters() {
    var list = document.getElementById('cost-centers-list');
    list.style.display = document.getElementById('has-cost-centers').checked ? '' : 'none';
}
function addCostCenter() {
    var entries = document.querySelectorAll('.cc-entry');
    if (entries.length >= 10) { alert('<?= $lang('max_cost_centers') ?>'); return; }
    var num = entries.length + 1;
    var div = document.createElement('div');
    div.className = 'form-group cc-entry';
    div.style.cssText = 'display:flex;gap:8px;align-items:center;';
    div.innerHTML = '<span style="min-width:20px;">' + num + '.</span>' +
        '<input type="text" name="cost_center_names[]" class="form-input" placeholder="<?= $lang('cost_center_placeholder') ?>" maxlength="255">' +
        '<button type="button" class="btn btn-xs btn-danger" onclick="removeCostCenter(this)">&times;</button>';
    document.getElementById('cc-entries').appendChild(div);
    updateAddBtn();
}
function removeCostCenter(btn) {
    btn.closest('.cc-entry').remove();
    renumberCostCenters();
    updateAddBtn();
}
function renumberCostCenters() {
    document.querySelectorAll('.cc-entry').forEach(function(el, i) {
        el.querySelector('span').textContent = (i + 1) + '.';
    });
}
function updateAddBtn() {
    var btn = document.getElementById('add-cc-btn');
    if (btn) btn.style.display = document.querySelectorAll('.cc-entry').length >= 10 ? 'none' : '';
}
function toggleClientSmtp() {
    var section = document.getElementById('client-smtp-section');
    if (section) section.style.display = document.getElementById('can-send-invoices').checked ? '' : 'none';
}
function toggleClientSmtpFields() {
    var fields = document.getElementById('client-smtp-fields');
    if (fields) fields.style.display = document.getElementById('client-smtp-enabled').checked ? '' : 'none';
}
function testClientSmtp(clientId) {
    var result = document.getElementById('client-smtp-test-result');
    result.textContent = 'Testowanie...';
    result.style.color = 'var(--gray-600)';
    fetch('/admin/clients/' + clientId + '/test-smtp', { method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({csrf_token: '<?= $csrf ?>'})
    }).then(r => r.json()).then(data => {
        if (data.success) {
            result.textContent = '✓ Połączenie OK';
            result.style.color = 'var(--success)';
        } else {
            result.textContent = '✗ ' + (data.error || 'Błąd');
            result.style.color = 'var(--error)';
        }
    }).catch(() => {
        result.textContent = '✗ Błąd sieci';
        result.style.color = 'var(--error)';
    });
}
</script>
