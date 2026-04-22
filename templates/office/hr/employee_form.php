<div class="section-header">
    <div>
        <div class="breadcrumb-path" style="font-size:13px;color:var(--text-muted);margin-bottom:4px;">
            <a href="/office/hr/settings"><?= $lang('hr_module') ?></a> &rsaquo;
            <a href="/office/hr/<?= $clientId ?>/employees"><?= htmlspecialchars($client['company_name']) ?></a> &rsaquo;
            <?= $editing ? $lang('edit') : $lang('hr_add_employee') ?>
        </div>
        <h1><?= $editing ? $lang('hr_edit_employee') : $lang('hr_add_employee') ?></h1>
    </div>
</div>

<?php if ($flash_error): ?>
    <div class="alert alert-error"><?= $flash_error ?></div>
<?php endif; ?>

<form method="POST" action="/office/hr/<?= $clientId ?>/employees/<?= $editing ? $employee['id'] . '/edit' : 'create' ?>">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

    <!-- DANE OSOBOWE -->
    <div class="card" style="margin-bottom:16px;">
        <div class="card-header"><h3><?= $lang('hr_personal_data') ?></h3></div>
        <div class="card-body">
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label"><?= $lang('hr_first_name') ?> <span class="text-danger">*</span></label>
                    <input type="text" name="first_name" class="form-control" required
                           value="<?= htmlspecialchars($employee['first_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('hr_last_name') ?> <span class="text-danger">*</span></label>
                    <input type="text" name="last_name" class="form-control" required
                           value="<?= htmlspecialchars($employee['last_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('pesel') ?></label>
                    <input type="text" name="pesel" class="form-control" maxlength="11" pattern="\d{11}"
                           placeholder="11 cyfr"
                           value="<?= htmlspecialchars($employee['pesel'] ?? '') ?>">
                    <span class="form-hint"><?= $lang('hr_pesel_hint') ?></span>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('nip') ?> <span class="text-muted">(<?= $lang('optional') ?>)</span></label>
                    <input type="text" name="nip" class="form-control" maxlength="10" pattern="\d{10}"
                           value="<?= htmlspecialchars($employee['nip'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('hr_birth_date') ?></label>
                    <input type="date" name="birth_date" class="form-control"
                           value="<?= htmlspecialchars($employee['birth_date'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('hr_gender') ?></label>
                    <select name="gender" class="form-control">
                        <option value="">— <?= $lang('optional') ?> —</option>
                        <option value="M" <?= ($employee['gender'] ?? '') === 'M' ? 'selected' : '' ?>>Mężczyzna</option>
                        <option value="K" <?= ($employee['gender'] ?? '') === 'K' ? 'selected' : '' ?>>Kobieta</option>
                    </select>
                </div>
            </div>

            <div class="form-grid-3" style="margin-top:12px;">
                <div class="form-group">
                    <label class="form-label"><?= $lang('hr_address_street') ?></label>
                    <input type="text" name="address_street" class="form-control"
                           value="<?= htmlspecialchars($employee['address_street'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('hr_address_city') ?></label>
                    <input type="text" name="address_city" class="form-control"
                           value="<?= htmlspecialchars($employee['address_city'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('hr_address_zip') ?></label>
                    <input type="text" name="address_zip" class="form-control" placeholder="00-000"
                           value="<?= htmlspecialchars($employee['address_zip'] ?? '') ?>">
                </div>
            </div>

            <div class="form-grid-2" style="margin-top:12px;">
                <div class="form-group">
                    <label class="form-label"><?= $lang('email') ?></label>
                    <input type="email" name="email" class="form-control"
                           value="<?= htmlspecialchars($employee['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('hr_phone') ?></label>
                    <input type="text" name="phone" class="form-control"
                           value="<?= htmlspecialchars($employee['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('hr_bank_account') ?></label>
                    <input type="text" name="bank_account_iban" class="form-control" placeholder="PL00 0000 0000 ..."
                           value="<?= htmlspecialchars($employee['bank_account_iban'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('hr_bank_name') ?></label>
                    <input type="text" name="bank_name" class="form-control"
                           value="<?= htmlspecialchars($employee['bank_name'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- USTAWIENIA PODATKOWE -->
    <div class="card" style="margin-bottom:16px;">
        <div class="card-header"><h3><?= $lang('hr_tax_settings') ?></h3></div>
        <div class="card-body">
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label"><?= $lang('hr_tax_office_code') ?></label>
                    <input type="text" name="tax_office_code" class="form-control"
                           value="<?= htmlspecialchars($employee['tax_office_code'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('hr_tax_office_name') ?></label>
                    <input type="text" name="tax_office_name" class="form-control"
                           value="<?= htmlspecialchars($employee['tax_office_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('hr_kup_amount') ?></label>
                    <select name="kup_amount" class="form-control">
                        <option value="250" <?= ($employee['kup_amount'] ?? '250') === '250' ? 'selected' : '' ?>>250 PLN (standardowe)</option>
                        <option value="300" <?= ($employee['kup_amount'] ?? '') === '300' ? 'selected' : '' ?>>300 PLN (dojazd z innej miejscowości)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('hr_annual_leave_days') ?></label>
                    <select name="annual_leave_days" class="form-control">
                        <option value="26" <?= (int)($employee['annual_leave_days'] ?? 26) === 26 ? 'selected' : '' ?>>26 dni (staż ≥ 10 lat)</option>
                        <option value="20" <?= (int)($employee['annual_leave_days'] ?? 26) === 20 ? 'selected' : '' ?>>20 dni (staż < 10 lat)</option>
                    </select>
                </div>
            </div>
            <div class="form-group" style="margin-top:12px;">
                <label class="form-label-check">
                    <input type="hidden" name="pit2_submitted" value="0">
                    <input type="checkbox" name="pit2_submitted" value="1"
                           <?= ($employee['pit2_submitted'] ?? 0) ? 'checked' : '' ?>>
                    <?= $lang('hr_pit2_submitted') ?> — <?= $lang('hr_pit2_hint') ?>
                </label>
            </div>
        </div>
    </div>

    <!-- USTAWIENIA ZUS -->
    <div class="card" style="margin-bottom:16px;">
        <div class="card-header"><h3><?= $lang('hr_zus_settings') ?></h3></div>
        <div class="card-body">
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label"><?= $lang('hr_zus_title_code') ?></label>
                    <input type="text" name="zus_title_code" class="form-control" placeholder="np. 0110"
                           value="<?= htmlspecialchars($employee['zus_title_code'] ?? '0110') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('hr_disability_level') ?></label>
                    <select name="disability_level" class="form-control">
                        <option value="none"     <?= ($employee['disability_level'] ?? 'none') === 'none'     ? 'selected' : '' ?>>Brak</option>
                        <option value="mild"     <?= ($employee['disability_level'] ?? '') === 'mild'     ? 'selected' : '' ?>>Lekki</option>
                        <option value="moderate" <?= ($employee['disability_level'] ?? '') === 'moderate' ? 'selected' : '' ?>>Umiarkowany</option>
                        <option value="severe"   <?= ($employee['disability_level'] ?? '') === 'severe'   ? 'selected' : '' ?>>Znaczny</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- PPK -->
    <div class="card" style="margin-bottom:16px;">
        <div class="card-header"><h3><?= $lang('hr_ppk') ?> (<?= $lang('hr_ppk_full') ?>)</h3></div>
        <div class="card-body">
            <div class="form-group" style="margin-bottom:12px;">
                <label class="form-label-check">
                    <input type="hidden" name="ppk_enrolled" value="0">
                    <input type="checkbox" name="ppk_enrolled" value="1" id="ppk_enrolled_cb"
                           <?= ($employee['ppk_enrolled'] ?? 0) ? 'checked' : '' ?>
                           onchange="document.getElementById('ppk_rates').style.display=this.checked?'grid':'none'">
                    <?= $lang('hr_ppk_enrolled') ?>
                </label>
            </div>
            <div id="ppk_rates" class="form-grid-2" style="display:<?= ($employee['ppk_enrolled'] ?? 0) ? 'grid' : 'none' ?>">
                <div class="form-group">
                    <label class="form-label"><?= $lang('hr_ppk_employee_rate') ?> (%)</label>
                    <input type="number" name="ppk_employee_rate" class="form-control"
                           min="2" max="4" step="0.5" value="<?= (float)($employee['ppk_employee_rate'] ?? 2.0) ?>">
                    <span class="form-hint">2–4%</span>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('hr_ppk_employer_rate') ?> (%)</label>
                    <input type="number" name="ppk_employer_rate" class="form-control"
                           min="1.5" max="4" step="0.5" value="<?= (float)($employee['ppk_employer_rate'] ?? 1.5) ?>">
                    <span class="form-hint">1.5–4%</span>
                </div>
            </div>
        </div>
    </div>

    <!-- UWAGI -->
    <div class="card" style="margin-bottom:16px;">
        <div class="card-header"><h3><?= $lang('description') ?></h3></div>
        <div class="card-body">
            <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($employee['notes'] ?? '') ?></textarea>
        </div>
    </div>

    <!-- WYSYŁKA PASKÓW PŁACOWYCH -->
    <div class="card" style="margin-bottom:20px;">
        <div class="card-header">
            <h3>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                <?= $lang('hr_payslip_email_settings') ?>
            </h3>
        </div>
        <div class="card-body">
            <div class="form-group" style="margin-bottom:12px;">
                <label class="form-label-check">
                    <input type="hidden" name="receive_payslip_email" value="0">
                    <input type="checkbox" name="receive_payslip_email" value="1" id="receive_payslip_cb"
                           <?= !empty($employee['receive_payslip_email']) ? 'checked' : '' ?>
                           onchange="document.getElementById('payslip_email_row').style.display=this.checked?'block':'none'">
                    <?= $lang('hr_receive_payslip_email') ?>
                </label>
                <p class="form-hint" style="margin-top:4px;"><?= $lang('hr_receive_payslip_email_hint') ?></p>
            </div>
            <div id="payslip_email_row" style="display:<?= !empty($employee['receive_payslip_email']) ? 'block' : 'none' ?>">
                <div class="form-group">
                    <label class="form-label"><?= $lang('hr_payslip_email_address') ?> <span class="text-muted">(<?= $lang('optional') ?>)</span></label>
                    <input type="email" name="email_payslip" class="form-control"
                           placeholder="<?= $lang('hr_payslip_email_address_hint') ?>"
                           value="<?= htmlspecialchars($employee['email_payslip'] ?? '') ?>">
                    <span class="form-hint"><?= $lang('hr_payslip_email_fallback_hint') ?></span>
                </div>
            </div>
        </div>
    </div>

    <div style="display:flex;gap:8px;">
        <button type="submit" class="btn btn-primary">
            <?= $lang('save') ?>
        </button>
        <a href="/office/hr/<?= $clientId ?>/employees<?= $editing ? '/' . $employee['id'] : '' ?>" class="btn btn-secondary"><?= $lang('cancel') ?></a>
    </div>
</form>