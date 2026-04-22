<div class="section-header">
    <div>
        <div class="breadcrumb-path" style="font-size:13px;color:var(--text-muted);margin-bottom:4px;">
            <a href="/office/hr/settings"><?= $lang('hr_module') ?></a> &rsaquo;
            <?= htmlspecialchars($client['company_name']) ?>
        </div>
        <h1>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93l-1.41 1.41M4.93 4.93l1.41 1.41M12 2v2M12 20v2M20 12h2M2 12h2M19.07 19.07l-1.41-1.41M4.93 19.07l1.41-1.41"/></svg>
            <?= $lang('hr_client_settings') ?> — <?= htmlspecialchars($client['company_name']) ?>
        </h1>
    </div>
    <div>
        <a href="/office/hr/<?= $clientId ?>/employees" class="btn btn-secondary"><?= $lang('hr_employees') ?></a>
    </div>
</div>

<?php include __DIR__ . '/../hr_nav.php'; ?>

<?php if ($flash_success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($flash_success) ?></div>
<?php endif; ?>
<?php if ($flash_error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($flash_error) ?></div>
<?php endif; ?>

<form method="POST" action="/office/hr/<?= $clientId ?>/settings">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

    <!-- USTAWIENIA PŁACOWE -->
    <div class="card" style="margin-bottom:16px;">
        <div class="card-header"><h3><?= $lang('hr_payroll_settings') ?></h3></div>
        <div class="card-body">
            <div class="form-grid-3">
                <div class="form-group">
                    <label class="form-label"><?= $lang('hr_wypadkowe_rate') ?> (%)</label>
                    <input type="number" name="wypadkowe_rate" class="form-control"
                           step="0.0001" min="0.0067" max="0.0333"
                           value="<?= htmlspecialchars($hrSettings['wypadkowe_rate'] ?? '0.0167') ?>">
                    <span class="form-hint"><?= $lang('hr_wypadkowe_hint') ?></span>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('hr_default_kup') ?> (PLN)</label>
                    <select name="default_kup" class="form-control">
                        <option value="250" <?= ($hrSettings['default_kup'] ?? '250') === '250' ? 'selected' : '' ?>>250 PLN (standardowe)</option>
                        <option value="300" <?= ($hrSettings['default_kup'] ?? '') === '300' ? 'selected' : '' ?>>300 PLN (dojeżdżający)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('hr_min_wage') ?> (PLN)</label>
                    <input type="number" name="min_wage_monthly" class="form-control"
                           step="1" min="1000"
                           value="<?= htmlspecialchars($hrSettings['min_wage_monthly'] ?? '4666') ?>">
                    <span class="form-hint"><?= $lang('hr_min_wage_hint') ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- USTAWIENIA ZUS -->
    <div class="card" style="margin-bottom:16px;">
        <div class="card-header"><h3><?= $lang('hr_zus_payer_settings') ?></h3></div>
        <div class="card-body">
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label"><?= $lang('hr_zus_payer_nip') ?></label>
                    <input type="text" name="zus_payer_nip" class="form-control" maxlength="10"
                           placeholder="10 cyfr NIP płatnika"
                           value="<?= htmlspecialchars($hrSettings['zus_payer_nip'] ?? '') ?>">
                    <span class="form-hint"><?= $lang('hr_zus_payer_nip_hint') ?></span>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('hr_zus_payer_name') ?></label>
                    <input type="text" name="zus_payer_name" class="form-control" maxlength="255"
                           placeholder="Pełna nazwa płatnika ZUS"
                           value="<?= htmlspecialchars($hrSettings['zus_payer_name'] ?? '') ?>">
                    <span class="form-hint"><?= $lang('hr_zus_payer_name_hint') ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- WYSYŁKA PASKÓW PŁACOWYCH EMAIL -->
    <div class="card" style="margin-bottom:20px;">
        <div class="card-header">
            <h3>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                <?= $lang('hr_payslip_email_settings') ?>
            </h3>
        </div>
        <div class="card-body">
            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label-check">
                    <input type="hidden" name="payslip_email_enabled" value="0">
                    <input type="checkbox" name="payslip_email_enabled" value="1" id="payslip_email_cb"
                           <?= !empty($hrSettings['payslip_email_enabled']) ? 'checked' : '' ?>
                           onchange="document.getElementById('payslip_email_fields').style.display=this.checked?'block':'none'">
                    <?= $lang('hr_payslip_email_enabled') ?>
                </label>
                <p class="form-hint" style="margin-top:4px;"><?= $lang('hr_payslip_email_enabled_hint') ?></p>
            </div>

            <div id="payslip_email_fields" style="display:<?= !empty($hrSettings['payslip_email_enabled']) ? 'block' : 'none' ?>">
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label"><?= $lang('hr_payslip_email_from') ?></label>
                        <input type="email" name="payslip_email_from" class="form-control"
                               placeholder="np. kadry@firma.pl"
                               value="<?= htmlspecialchars($hrSettings['payslip_email_from'] ?? '') ?>">
                        <span class="form-hint"><?= $lang('hr_payslip_email_from_hint') ?></span>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= $lang('hr_payslip_email_subject') ?></label>
                        <input type="text" name="payslip_email_subject_template" class="form-control"
                               placeholder="Odcinek płacowy za {month} {year} — {company}"
                               value="<?= htmlspecialchars($hrSettings['payslip_email_subject_template'] ?? '') ?>">
                        <span class="form-hint"><?= $lang('hr_payslip_email_subject_hint') ?></span>
                    </div>
                </div>
                <div class="alert" style="background:var(--bg-subtle);border-left:3px solid var(--info);padding:10px 14px;font-size:13px;margin-top:4px;">
                    <strong><?= $lang('hr_payslip_email_tokens_label') ?>:</strong>
                    <code>{month}</code> — <?= $lang('hr_token_month') ?>,
                    <code>{year}</code> — <?= $lang('hr_token_year') ?>,
                    <code>{company}</code> — <?= $lang('hr_token_company') ?>
                </div>
            </div>
        </div>
    </div>

    <div style="display:flex;gap:8px;">
        <button type="submit" class="btn btn-primary"><?= $lang('save') ?></button>
        <a href="/office/hr/<?= $clientId ?>/employees" class="btn btn-secondary"><?= $lang('cancel') ?></a>
    </div>
</form>