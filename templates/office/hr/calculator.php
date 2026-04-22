<div class="section-header">
    <div>
        <div class="breadcrumb-path" style="font-size:13px;color:var(--text-muted);margin-bottom:4px;">
            <a href="/office/hr/settings"><?= $lang('hr_module') ?></a> &rsaquo;
            <?= $lang('hr_cost_calculator') ?>
        </div>
        <h1>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="12" y2="14"/></svg>
            <?= $lang('hr_cost_calculator') ?>
        </h1>
    </div>
</div>

<?php if ($flash_error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($flash_error) ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:360px 1fr;gap:24px;align-items:start;">

<!-- Input Form -->
<div class="card">
    <div class="card-header"><strong><?= $lang('calculator_params') ?></strong></div>
    <div class="card-body">
        <form method="POST" action="/office/hr/calculator">
            <?= csrf_field() ?>

            <?php if (!empty($clients)): ?>
            <div class="form-group">
                <label class="form-label"><?= $lang('client') ?> <small style="color:var(--text-muted)">(<?= $lang('optional') ?>)</small></label>
                <select name="client_id" class="form-control">
                    <option value="0"><?= $lang('default_settings') ?></option>
                    <?php foreach ($clients as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($inputs['client_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['company_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label"><?= $lang('hr_gross_salary') ?> (PLN) *</label>
                <input type="number" name="brutto" class="form-control" step="0.01" min="0"
                       value="<?= htmlspecialchars($inputs['brutto'] ?? '') ?>" required placeholder="np. 5000.00">
            </div>

            <div class="form-group">
                <label class="form-label"><?= $lang('hr_contract_type') ?> *</label>
                <select name="contract_type" class="form-control">
                    <option value="uop" <?= ($inputs['contract_type'] ?? 'uop') === 'uop' ? 'selected' : '' ?>>Umowa o pracę (UoP)</option>
                    <option value="uz"  <?= ($inputs['contract_type'] ?? '') === 'uz'  ? 'selected' : '' ?>>Umowa zlecenie (UZ)</option>
                    <option value="uod" <?= ($inputs['contract_type'] ?? '') === 'uod' ? 'selected' : '' ?>>Umowa o dzieło (UoD)</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label"><?= $lang('hr_kup') ?></label>
                <select name="kup" class="form-control">
                    <option value="250" <?= ($inputs['kup'] ?? '250') === '250' ? 'selected' : '' ?>>250 PLN (standardowe)</option>
                    <option value="300" <?= ($inputs['kup'] ?? '') === '300' ? 'selected' : '' ?>>300 PLN (podwyższone)</option>
                </select>
            </div>

            <div class="form-group" style="display:flex;gap:24px;">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="pit2" value="1" <?= !empty($inputs['pit2']) ? 'checked' : '' ?>>
                    <span><?= $lang('hr_pit2_submitted') ?></span>
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="ppk" value="1" <?= !empty($inputs['ppk']) ? 'checked' : '' ?>>
                    <span>PPK</span>
                </label>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                <?= $lang('calculate') ?>
            </button>
        </form>
    </div>
</div>

<!-- Results -->
<div>
<?php if ($result !== null): ?>
    <?php $n = fn($v) => number_format((float)$v, 2, ',', ' ') . ' PLN'; ?>

    <!-- Net salary highlight -->
    <div class="card" style="margin-bottom:16px;border:2px solid var(--primary);background:var(--bg-primary-light,#eff6ff);">
        <div class="card-body" style="display:flex;justify-content:space-between;align-items:center;padding:20px 24px;">
            <div>
                <div style="font-size:13px;color:var(--text-muted);margin-bottom:4px;"><?= $lang('hr_net_salary') ?></div>
                <div style="font-size:28px;font-weight:700;color:var(--primary);"><?= $n($result['net_salary']) ?></div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:13px;color:var(--text-muted);margin-bottom:4px;"><?= $lang('hr_employer_cost') ?></div>
                <div style="font-size:22px;font-weight:700;color:#dc2626;"><?= $n($result['employer_total_cost']) ?></div>
            </div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
        <!-- Employee deductions -->
        <div class="card">
            <div class="card-header"><strong><?= $lang('hr_employee_deductions') ?></strong></div>
            <table class="table table-sm">
                <tbody>
                    <tr><td><?= $lang('hr_gross_salary') ?></td><td class="text-right"><strong><?= $n($result['gross_salary']) ?></strong></td></tr>
                    <tr><td><?= $lang('hr_zus_emerytalne') ?></td><td class="text-right"><?= $n($result['zus_emerytalne_emp']) ?></td></tr>
                    <tr><td><?= $lang('hr_zus_rentowe') ?></td><td class="text-right"><?= $n($result['zus_rentowe_emp']) ?></td></tr>
                    <tr><td><?= $lang('hr_zus_chorobowe') ?></td><td class="text-right"><?= $n($result['zus_chorobowe_emp']) ?></td></tr>
                    <tr><td><em><?= $lang('hr_zus_total_employee') ?></em></td><td class="text-right"><em><?= $n($result['zus_total_employee']) ?></em></td></tr>
                    <tr><td><?= $lang('hr_kup') ?></td><td class="text-right"><?= $n($result['kup_amount']) ?></td></tr>
                    <tr><td><?= $lang('hr_tax_base') ?></td><td class="text-right"><?= number_format((float)$result['tax_base'], 2, ',', ' ') ?> PLN</td></tr>
                    <tr><td><?= $lang('hr_pit_advance') ?> (<?= $result['pit_rate'] ?>%)</td><td class="text-right"><?= $n($result['pit_advance']) ?></td></tr>
                    <?php if ((float)$result['ppk_employee'] > 0): ?>
                    <tr><td>PPK pracownik</td><td class="text-right"><?= $n($result['ppk_employee']) ?></td></tr>
                    <?php endif; ?>
                    <tr style="background:var(--bg-success-light,#f0fdf4);font-weight:bold;"><td><?= $lang('hr_net_salary') ?></td><td class="text-right" style="color:#15803d;"><?= $n($result['net_salary']) ?></td></tr>
                </tbody>
            </table>
        </div>

        <!-- Employer costs -->
        <div class="card">
            <div class="card-header"><strong><?= $lang('hr_employer_costs') ?></strong></div>
            <table class="table table-sm">
                <tbody>
                    <tr><td><?= $lang('hr_gross_salary') ?></td><td class="text-right"><?= $n($result['gross_salary']) ?></td></tr>
                    <tr><td><?= $lang('hr_zus_emerytalne') ?> (pracodawca)</td><td class="text-right"><?= $n($result['zus_emerytalne_emp2']) ?></td></tr>
                    <tr><td><?= $lang('hr_zus_rentowe') ?> (pracodawca)</td><td class="text-right"><?= $n($result['zus_rentowe_emp2']) ?></td></tr>
                    <tr><td><?= $lang('hr_zus_wypadkowe') ?></td><td class="text-right"><?= $n($result['zus_wypadkowe_emp2']) ?></td></tr>
                    <tr><td>Fundusz Pracy</td><td class="text-right"><?= $n($result['zus_fp_emp2']) ?></td></tr>
                    <tr><td>FGŚP</td><td class="text-right"><?= $n($result['zus_fgsp_emp2']) ?></td></tr>
                    <?php if ((float)$result['ppk_employer'] > 0): ?>
                    <tr><td>PPK pracodawca</td><td class="text-right"><?= $n($result['ppk_employer']) ?></td></tr>
                    <?php endif; ?>
                    <tr style="background:var(--bg-danger-light,#fef2f2);font-weight:bold;"><td><?= $lang('hr_employer_cost') ?></td><td class="text-right" style="color:#dc2626;"><?= $n($result['employer_total_cost']) ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="card" style="text-align:center;padding:48px;color:var(--text-muted);">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:12px;opacity:.4"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="12" y2="14"/></svg>
        <p><?= $lang('hr_calculator_hint') ?></p>
    </div>
<?php endif; ?>
</div>

</div>