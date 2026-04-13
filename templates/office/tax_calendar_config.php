<h1><?= $lang('tax_calendar_config') ?>: <?= htmlspecialchars($clientData['company_name']) ?></h1>

<?php $flashSuccess = \App\Core\Session::getFlash('success'); ?>
<?php if ($flashSuccess): ?>
    <div class="alert alert-success"><?= $lang($flashSuccess) ?></div>
<?php endif; ?>

<div class="form-card" style="padding:20px; max-width:600px;">
    <form method="POST" action="/office/tax-calendar/config/<?= $clientData['id'] ?>">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

        <div class="form-group">
            <label class="form-label"><?= $lang('vat_period') ?></label>
            <select name="vat_period" class="form-input">
                <option value="monthly" <?= ($config['vat_period'] ?? 'monthly') === 'monthly' ? 'selected' : '' ?>><?= $lang('vat_monthly') ?></option>
                <option value="quarterly" <?= ($config['vat_period'] ?? '') === 'quarterly' ? 'selected' : '' ?>><?= $lang('vat_quarterly') ?></option>
                <option value="none" <?= ($config['vat_period'] ?? '') === 'none' ? 'selected' : '' ?>><?= $lang('vat_exempt') ?></option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label"><?= $lang('taxation_type') ?></label>
            <select name="taxation_type" class="form-input">
                <option value="PIT" <?= ($config['taxation_type'] ?? 'PIT') === 'PIT' ? 'selected' : '' ?>>PIT</option>
                <option value="CIT" <?= ($config['taxation_type'] ?? '') === 'CIT' ? 'selected' : '' ?>>CIT</option>
                <option value="none" <?= ($config['taxation_type'] ?? '') === 'none' ? 'selected' : '' ?>><?= $lang('not_applicable') ?></option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label"><?= $lang('tax_form') ?></label>
            <select name="tax_form" class="form-input">
                <option value="skala" <?= ($config['tax_form'] ?? 'skala') === 'skala' ? 'selected' : '' ?>><?= $lang('tax_form_skala') ?></option>
                <option value="liniowy" <?= ($config['tax_form'] ?? '') === 'liniowy' ? 'selected' : '' ?>><?= $lang('tax_form_liniowy') ?></option>
                <option value="ryczalt" <?= ($config['tax_form'] ?? '') === 'ryczalt' ? 'selected' : '' ?>><?= $lang('tax_form_ryczalt') ?></option>
                <option value="karta" <?= ($config['tax_form'] ?? '') === 'karta' ? 'selected' : '' ?>><?= $lang('tax_form_karta') ?></option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label"><?= $lang('zus_payer_type') ?></label>
            <select name="zus_payer_type" class="form-input">
                <option value="self_employed" <?= ($config['zus_payer_type'] ?? 'self_employed') === 'self_employed' ? 'selected' : '' ?>><?= $lang('zus_self_employed') ?></option>
                <option value="employer" <?= ($config['zus_payer_type'] ?? '') === 'employer' ? 'selected' : '' ?>><?= $lang('zus_employer') ?></option>
                <option value="none" <?= ($config['zus_payer_type'] ?? '') === 'none' ? 'selected' : '' ?>><?= $lang('zus_none') ?></option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">
                <input type="checkbox" name="jpk_vat_required" value="1" <?= ($config['jpk_vat_required'] ?? 1) ? 'checked' : '' ?>>
                <?= $lang('jpk_vat_required') ?>
            </label>
        </div>

        <div class="form-group">
            <label class="form-label"><?= $lang('alert_days_before') ?></label>
            <input type="number" name="alert_days_before" class="form-input" min="1" max="30" value="<?= (int) ($config['alert_days_before'] ?? 5) ?>" style="width:100px;">
        </div>

        <button type="submit" class="btn btn-primary"><?= $lang('save') ?></button>
        <a href="/office/tax-calendar" class="btn btn-secondary" style="margin-left:8px;"><?= $lang('back') ?></a>
    </form>
</div>
