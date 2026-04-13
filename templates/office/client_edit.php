<h1><?= $lang('edit_client') ?>: <?= htmlspecialchars($clientData['company_name']) ?></h1>

<p style="margin-bottom:16px;">
    <a href="/office/clients" class="btn btn-sm">&larr; <?= $lang('back_to_clients') ?></a>
    <a href="/office/clients/<?= $clientData['id'] ?>/cost-centers" class="btn btn-sm" style="margin-left:6px;"><?= $lang('cost_centers_short') ?></a>
    <a href="/office/clients/<?= $clientData['id'] ?>/notes" class="btn btn-sm" style="margin-left:6px;"><?= $lang('notes') ?></a>
    <a href="/office/clients/<?= $clientData['id'] ?>/files" class="btn btn-sm" style="margin-left:6px;">Pliki</a>
</p>

<?php $flashSuccess = \App\Core\Session::getFlash('success'); ?>
<?php if ($flashSuccess): ?>
    <div class="alert alert-success"><?= $lang($flashSuccess) ?></div>
<?php endif; ?>
<?php $flashError = \App\Core\Session::getFlash('error'); ?>
<?php if ($flashError): ?>
    <div class="alert alert-error"><?= $lang($flashError) ?></div>
<?php endif; ?>

<!-- Tab Navigation -->
<div class="tabs" style="margin-bottom:20px;">
    <a href="#" class="tab-link active" onclick="showTab('contact', this); return false;"><?= $lang('contact_data') ?></a>
    <a href="#" class="tab-link" onclick="showTab('tax', this); return false;"><?= $lang('tax_calendar_config') ?></a>
</div>

<!-- Tab: Contact Data -->
<div id="tab-contact" class="tab-content" style="display:block;">
    <div class="form-card" style="padding:20px; max-width:600px;">
        <form method="POST" action="/office/clients/<?= $clientData['id'] ?>/edit">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="_form" value="contact">

            <div class="form-group">
                <label class="form-label">NIP</label>
                <input type="text" class="form-input" value="<?= htmlspecialchars($clientData['nip']) ?>" disabled>
            </div>

            <div class="form-group">
                <label class="form-label"><?= $lang('company_name') ?></label>
                <input type="text" class="form-input" value="<?= htmlspecialchars($clientData['company_name']) ?>" disabled>
                <small style="color:var(--gray-500);"><?= $lang('contact_admin_to_change') ?></small>
            </div>

            <div class="form-group">
                <label class="form-label"><?= $lang('representative') ?></label>
                <input type="text" name="representative_name" class="form-input" value="<?= htmlspecialchars($clientData['representative_name']) ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-input" value="<?= htmlspecialchars($clientData['email']) ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label"><?= $lang('phone') ?></label>
                <input type="text" name="phone" class="form-input" value="<?= htmlspecialchars($clientData['phone'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label"><?= $lang('report_email') ?></label>
                <input type="email" name="report_email" class="form-input" value="<?= htmlspecialchars($clientData['report_email'] ?? '') ?>">
            </div>

            <button type="submit" class="btn btn-primary"><?= $lang('save') ?></button>
        </form>
    </div>
</div>

<!-- Tab: Tax Configuration -->
<div id="tab-tax" class="tab-content" style="display:none;">
    <div class="form-card" style="padding:20px; max-width:600px;">
        <form method="POST" action="/office/clients/<?= $clientData['id'] ?>/edit">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="_form" value="tax">

            <div class="form-group">
                <label class="form-label"><?= $lang('vat_period') ?></label>
                <select name="vat_period" class="form-input">
                    <option value="monthly" <?= ($taxConfig['vat_period'] ?? 'monthly') === 'monthly' ? 'selected' : '' ?>><?= $lang('vat_monthly') ?></option>
                    <option value="quarterly" <?= ($taxConfig['vat_period'] ?? '') === 'quarterly' ? 'selected' : '' ?>><?= $lang('vat_quarterly') ?></option>
                    <option value="none" <?= ($taxConfig['vat_period'] ?? '') === 'none' ? 'selected' : '' ?>><?= $lang('vat_exempt') ?></option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label"><?= $lang('taxation_type') ?></label>
                <select name="taxation_type" class="form-input">
                    <option value="PIT" <?= ($taxConfig['taxation_type'] ?? 'PIT') === 'PIT' ? 'selected' : '' ?>>PIT</option>
                    <option value="CIT" <?= ($taxConfig['taxation_type'] ?? '') === 'CIT' ? 'selected' : '' ?>>CIT</option>
                    <option value="none" <?= ($taxConfig['taxation_type'] ?? '') === 'none' ? 'selected' : '' ?>><?= $lang('not_applicable') ?></option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label"><?= $lang('tax_form') ?></label>
                <select name="tax_form" class="form-input">
                    <option value="skala" <?= ($taxConfig['tax_form'] ?? 'skala') === 'skala' ? 'selected' : '' ?>><?= $lang('tax_form_skala') ?></option>
                    <option value="liniowy" <?= ($taxConfig['tax_form'] ?? '') === 'liniowy' ? 'selected' : '' ?>><?= $lang('tax_form_liniowy') ?></option>
                    <option value="ryczalt" <?= ($taxConfig['tax_form'] ?? '') === 'ryczalt' ? 'selected' : '' ?>><?= $lang('tax_form_ryczalt') ?></option>
                    <option value="karta" <?= ($taxConfig['tax_form'] ?? '') === 'karta' ? 'selected' : '' ?>><?= $lang('tax_form_karta') ?></option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label"><?= $lang('zus_payer_type') ?></label>
                <select name="zus_payer_type" class="form-input">
                    <option value="self_employed" <?= ($taxConfig['zus_payer_type'] ?? 'self_employed') === 'self_employed' ? 'selected' : '' ?>><?= $lang('zus_self_employed') ?></option>
                    <option value="employer" <?= ($taxConfig['zus_payer_type'] ?? '') === 'employer' ? 'selected' : '' ?>><?= $lang('zus_employer') ?></option>
                    <option value="none" <?= ($taxConfig['zus_payer_type'] ?? '') === 'none' ? 'selected' : '' ?>><?= $lang('zus_none') ?></option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <input type="checkbox" name="jpk_vat_required" value="1" <?= ($taxConfig['jpk_vat_required'] ?? 1) ? 'checked' : '' ?>>
                    <?= $lang('jpk_vat_required') ?>
                </label>
            </div>

            <div class="form-group">
                <label class="form-label"><?= $lang('alert_days_before') ?></label>
                <input type="number" name="alert_days_before" class="form-input" min="1" max="30" value="<?= (int) ($taxConfig['alert_days_before'] ?? 5) ?>" style="width:100px;">
            </div>

            <button type="submit" class="btn btn-primary"><?= $lang('save') ?></button>
        </form>
    </div>
</div>

<!-- RODO: Delete client data -->
<div class="section" style="margin-top:40px;">
    <h2 style="color:var(--danger);">Usuń klienta i wszystkie dane (RODO Art. 17)</h2>
    <div class="form-card rodo-delete-warning" style="padding:20px; max-width:600px; border:2px solid var(--danger); border-radius:var(--radius);">
        <div class="alert alert-error" style="margin-bottom:16px; background:rgba(220,38,38,0.08); border:1px solid rgba(220,38,38,0.25);">
            <strong>Uwaga! Ta operacja jest nieodwracalna.</strong>
            <p style="margin-top:8px; margin-bottom:0;">Usunięcie klienta spowoduje trwałe usunięcie wszystkich jego danych: faktur, wiadomości, plików, zadań, kontrahentów, konfiguracji KSeF i profilu firmy.</p>
        </div>

        <form method="POST" action="/office/clients/<?= $clientData['id'] ?>/delete-data" id="rodo-delete-form">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

            <div class="form-group">
                <label class="form-label">Aby potwierdzić, wpisz <strong>USUN</strong></label>
                <input type="text" name="confirm_text" id="rodo-confirm-text" class="form-input" placeholder="Wpisz USUN" autocomplete="off" required style="max-width:250px;">
            </div>

            <button type="submit" id="rodo-delete-btn" class="btn" style="background:var(--danger); color:#fff; opacity:0.5; cursor:not-allowed;" disabled>
                Usuń klienta i wszystkie dane
            </button>
        </form>
    </div>
</div>

<script>
function showTab(name, el) {
    document.querySelectorAll('.tab-content').forEach(function(t) { t.style.display = 'none'; });
    document.querySelectorAll('.tab-link').forEach(function(l) { l.classList.remove('active'); });
    document.getElementById('tab-' + name).style.display = 'block';
    el.classList.add('active');
}

(function() {
    var confirmInput = document.getElementById('rodo-confirm-text');
    var deleteBtn = document.getElementById('rodo-delete-btn');
    var deleteForm = document.getElementById('rodo-delete-form');

    if (confirmInput && deleteBtn) {
        confirmInput.addEventListener('input', function() {
            var enabled = this.value.trim() === 'USUN';
            deleteBtn.disabled = !enabled;
            deleteBtn.style.opacity = enabled ? '1' : '0.5';
            deleteBtn.style.cursor = enabled ? 'pointer' : 'not-allowed';
        });
    }

    if (deleteForm) {
        deleteForm.addEventListener('submit', function(e) {
            if (!confirm('Czy na pewno chcesz trwale usunąć tego klienta i wszystkie jego dane? Tej operacji nie można cofnąć.')) {
                e.preventDefault();
            }
        });
    }
})();
</script>
