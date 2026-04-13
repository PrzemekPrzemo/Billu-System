<?php
$fmt = fn($v) => number_format((float)$v, 2, ',', ' ');
$fmtPct = fn($v) => number_format((float)$v, 1, ',', '') . '%';
$currentTab = $tab ?? 'tax';
$tabs = [
    'tax'    => ['label' => $lang('calc_tab_tax'),    'icon' => '<path d="M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>'],
    'vat'    => ['label' => $lang('calc_tab_vat'),    'icon' => '<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>'],
    'margin' => ['label' => $lang('calc_tab_margin'), 'icon' => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>'],
    'salary'   => ['label' => $lang('calc_tab_salary'),   'icon' => '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4-4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/>'],
    'mileage'  => ['label' => $lang('calc_tab_mileage'),  'icon' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>'],
    'currency'      => ['label' => $lang('calc_tab_currency'),      'icon' => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>'],
    'car_allowance' => ['label' => $lang('calc_tab_car_allowance'), 'icon' => '<rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>'],
    'profit'        => ['label' => $lang('calc_tab_profit'),        'icon' => '<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>'],
];
?>

<h1><?= $lang('calculators') ?></h1>

<?php $flash = \App\Core\Session::getFlash('success'); ?>
<?php if ($flash): ?>
    <div class="alert alert-success"><?= $lang($flash) !== $flash ? $lang($flash) : htmlspecialchars($flash) ?></div>
<?php endif; ?>

<!-- ══════ TABS ══════ -->
<div style="display:flex; gap:4px; margin-bottom:20px; border-bottom:2px solid var(--gray-200); overflow-x:auto;">
    <?php foreach ($tabs as $tKey => $tConf): ?>
    <a href="/office/tax-calculator?tab=<?= $tKey ?>" style="padding:10px 18px; font-size:13px; font-weight:600; text-decoration:none; border-bottom:2px solid <?= $currentTab === $tKey ? 'var(--blue-600)' : 'transparent' ?>; margin-bottom:-2px; color:<?= $currentTab === $tKey ? 'var(--blue-600)' : 'var(--gray-500)' ?>; white-space:nowrap; display:flex; align-items:center; gap:6px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $tConf['icon'] ?></svg>
        <?= $tConf['label'] ?>
    </a>
    <?php endforeach; ?>
</div>

<?php if ($currentTab === 'tax'): ?>
<!-- ══════════════════════════════════════════════════════ -->
<!-- TAB: OPŁACALNOŚĆ FORM OPODATKOWANIA                   -->
<!-- ══════════════════════════════════════════════════════ -->
<?php include __DIR__ . '/calc_tab_tax.php'; ?>

<?php elseif ($currentTab === 'vat'): ?>
<!-- ══════════════════════════════════════════════════════ -->
<!-- TAB: BRUTTO / NETTO / VAT                              -->
<!-- ══════════════════════════════════════════════════════ -->
<div class="form-card" style="padding:20px; margin-bottom:20px;">
    <h3 style="margin-bottom:16px;"><?= $lang('calc_vat_title') ?></h3>
    <form method="GET" action="/office/tax-calculator">
        <input type="hidden" name="tab" value="vat">
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; align-items:flex-end;">
            <div class="form-group" style="margin:0;">
                <label class="form-label"><?= $lang('amount') ?></label>
                <input type="number" name="amount" class="form-input" value="<?= htmlspecialchars($_GET['amount'] ?? '') ?>" placeholder="np. 1000" step="0.01" min="0" required style="font-size:16px; font-weight:600;">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label"><?= $lang('amount_type_label') ?></label>
                <select name="amount_type" class="form-input">
                    <option value="netto" <?= ($_GET['amount_type'] ?? 'netto') === 'netto' ? 'selected' : '' ?>><?= $lang('netto_amount') ?></option>
                    <option value="brutto" <?= ($_GET['amount_type'] ?? '') === 'brutto' ? 'selected' : '' ?>><?= $lang('brutto_amount') ?></option>
                    <option value="vat" <?= ($_GET['amount_type'] ?? '') === 'vat' ? 'selected' : '' ?>><?= $lang('vat_amount') ?></option>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label"><?= $lang('vat_rate_label') ?></label>
                <select name="vat_rate" class="form-input">
                    <?php foreach (\App\Services\CalculatorService::VAT_RATES as $r): ?>
                        <option value="<?= $r ?>" <?= (int)($_GET['vat_rate'] ?? 23) === $r ? 'selected' : '' ?>><?= $r ?>%</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div style="margin-top:16px;"><button type="submit" class="btn btn-primary"><?= $lang('calculate') ?></button></div>
    </form>
</div>

<?php if ($vatResult): ?>
<div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:16px;">
    <?php foreach ([['netto', $lang('netto_amount'), '#16a34a'], ['vat', 'VAT ' . $vatResult['vat_rate'] . '%', '#ea580c'], ['brutto', $lang('brutto_amount'), '#2563eb']] as [$key, $label, $color]): ?>
    <div class="form-card" style="padding:20px; text-align:center; border-top:3px solid <?= $color ?>;">
        <div style="font-size:13px; color:var(--gray-500); margin-bottom:8px;"><?= $label ?></div>
        <div style="font-size:28px; font-weight:700; color:<?= $color ?>;"><?= $fmt($vatResult[$key]) ?> PLN</div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php elseif ($currentTab === 'margin'): ?>
<!-- ══════════════════════════════════════════════════════ -->
<!-- TAB: KALKULATOR MARŻY                                  -->
<!-- ══════════════════════════════════════════════════════ -->
<div class="form-card" style="padding:20px; margin-bottom:20px;">
    <h3 style="margin-bottom:16px;"><?= $lang('calc_margin_title') ?></h3>
    <form method="GET" action="/office/tax-calculator">
        <input type="hidden" name="tab" value="margin">
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:16px; align-items:flex-end;">
            <div class="form-group" style="margin:0;">
                <label class="form-label"><?= $lang('calc_mode') ?></label>
                <select name="calc_mode" class="form-input" onchange="document.getElementById('marginSellGroup').style.display=this.value==='from_prices'?'':'none'; document.getElementById('marginPctGroup').style.display=this.value!=='from_prices'?'':'none';">
                    <option value="from_prices" <?= ($_GET['calc_mode'] ?? 'from_prices') === 'from_prices' ? 'selected' : '' ?>><?= $lang('from_prices') ?></option>
                    <option value="from_margin" <?= ($_GET['calc_mode'] ?? '') === 'from_margin' ? 'selected' : '' ?>><?= $lang('from_margin') ?></option>
                    <option value="from_markup" <?= ($_GET['calc_mode'] ?? '') === 'from_markup' ? 'selected' : '' ?>><?= $lang('from_markup') ?></option>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label"><?= $lang('buy_price') ?></label>
                <input type="number" name="buy_price" class="form-input" value="<?= htmlspecialchars($_GET['buy_price'] ?? '') ?>" step="0.01" min="0" required>
            </div>
            <div class="form-group" style="margin:0; <?= ($_GET['calc_mode'] ?? 'from_prices') !== 'from_prices' ? 'display:none;' : '' ?>" id="marginSellGroup">
                <label class="form-label"><?= $lang('sell_price') ?></label>
                <input type="number" name="sell_price" class="form-input" value="<?= htmlspecialchars($_GET['sell_price'] ?? '') ?>" step="0.01" min="0">
            </div>
            <div class="form-group" style="margin:0; <?= ($_GET['calc_mode'] ?? 'from_prices') === 'from_prices' ? 'display:none;' : '' ?>" id="marginPctGroup">
                <label class="form-label"><?= $lang('percent') ?> %</label>
                <input type="number" name="margin_percent" class="form-input" value="<?= htmlspecialchars($_GET['margin_percent'] ?? '') ?>" step="0.1" min="0">
            </div>
            <div><button type="submit" class="btn btn-primary"><?= $lang('calculate') ?></button></div>
        </div>
    </form>
</div>

<?php if ($marginResult): ?>
<div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:16px; margin-bottom:16px;">
    <div class="form-card" style="padding:20px; text-align:center; border-top:3px solid #ef4444;">
        <div style="font-size:13px; color:var(--gray-500);"><?= $lang('buy_price') ?></div>
        <div style="font-size:24px; font-weight:700; color:#ef4444;"><?= $fmt($marginResult['buy_price']) ?> PLN</div>
    </div>
    <div class="form-card" style="padding:20px; text-align:center; border-top:3px solid #16a34a;">
        <div style="font-size:13px; color:var(--gray-500);"><?= $lang('sell_price') ?></div>
        <div style="font-size:24px; font-weight:700; color:#16a34a;"><?= $fmt($marginResult['sell_price']) ?> PLN</div>
    </div>
    <div class="form-card" style="padding:20px; text-align:center; border-top:3px solid #2563eb;">
        <div style="font-size:13px; color:var(--gray-500);"><?= $lang('profit') ?></div>
        <div style="font-size:24px; font-weight:700; color:#2563eb;"><?= $fmt($marginResult['profit']) ?> PLN</div>
    </div>
</div>
<div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
    <div class="form-card" style="padding:16px; text-align:center;">
        <div style="font-size:13px; color:var(--gray-500);"><?= $lang('margin_label') ?></div>
        <div style="font-size:28px; font-weight:700;"><?= $fmtPct($marginResult['margin_percent']) ?></div>
        <div style="font-size:11px; color:var(--gray-400);">(<?= $lang('margin_formula') ?>)</div>
    </div>
    <div class="form-card" style="padding:16px; text-align:center;">
        <div style="font-size:13px; color:var(--gray-500);"><?= $lang('markup_label') ?></div>
        <div style="font-size:28px; font-weight:700;"><?= $fmtPct($marginResult['markup_percent']) ?></div>
        <div style="font-size:11px; color:var(--gray-400);">(<?= $lang('markup_formula') ?>)</div>
    </div>
</div>
<?php endif; ?>

<?php elseif ($currentTab === 'salary'): ?>
<!-- ══════════════════════════════════════════════════════ -->
<!-- TAB: KALKULATOR WYNAGRODZEŃ                            -->
<!-- ══════════════════════════════════════════════════════ -->
<div class="form-card" style="padding:20px; margin-bottom:20px;">
    <h3 style="margin-bottom:16px;"><?= $lang('calc_salary_title') ?></h3>
    <form method="GET" action="/office/tax-calculator">
        <input type="hidden" name="tab" value="salary">
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; align-items:flex-end;">
            <div class="form-group" style="margin:0;">
                <label class="form-label"><?= $lang('salary_brutto') ?></label>
                <input type="number" name="salary_brutto" class="form-input" value="<?= htmlspecialchars($_GET['salary_brutto'] ?? '') ?>" placeholder="np. 8000" step="100" min="0" required style="font-size:16px; font-weight:600;">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label"><?= $lang('kup_type') ?></label>
                <select name="cost_type" class="form-input">
                    <option value="1" <?= ($_GET['cost_type'] ?? '1') === '1' ? 'selected' : '' ?>><?= $lang('kup_standard') ?> (250 PLN)</option>
                    <option value="2" <?= ($_GET['cost_type'] ?? '') === '2' ? 'selected' : '' ?>><?= $lang('kup_elevated') ?> (300 PLN)</option>
                </select>
            </div>
            <div class="form-group" style="margin:0; display:flex; flex-direction:column; gap:8px;">
                <label style="font-size:13px; display:flex; align-items:center; gap:6px; cursor:pointer;">
                    <input type="checkbox" name="under26" <?= isset($_GET['under26']) ? 'checked' : '' ?>> <?= $lang('under_26') ?>
                </label>
                <label style="font-size:13px; display:flex; align-items:center; gap:6px; cursor:pointer;">
                    <input type="checkbox" name="ppk" <?= isset($_GET['ppk']) ? 'checked' : '' ?>> PPK
                </label>
            </div>
        </div>
        <div style="margin-top:16px;"><button type="submit" class="btn btn-primary"><?= $lang('calculate') ?></button></div>
    </form>
</div>

<?php if ($salaryResult): $s = $salaryResult; ?>
<!-- Headline cards -->
<div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:16px; margin-bottom:20px;">
    <div class="form-card" style="padding:20px; text-align:center; border-top:3px solid #2563eb;">
        <div style="font-size:12px; color:var(--gray-500);"><?= $lang('salary_brutto') ?></div>
        <div style="font-size:28px; font-weight:700; color:#2563eb;"><?= $fmt($s['brutto']) ?> PLN</div>
    </div>
    <div class="form-card" style="padding:20px; text-align:center; border-top:3px solid #16a34a;">
        <div style="font-size:12px; color:var(--gray-500);"><?= $lang('salary_netto') ?></div>
        <div style="font-size:28px; font-weight:700; color:#16a34a;"><?= $fmt($s['netto']) ?> PLN</div>
    </div>
    <div class="form-card" style="padding:20px; text-align:center; border-top:3px solid #ef4444;">
        <div style="font-size:12px; color:var(--gray-500);"><?= $lang('employer_cost') ?></div>
        <div style="font-size:28px; font-weight:700; color:#ef4444;"><?= $fmt($s['koszt_pracodawcy']) ?> PLN</div>
    </div>
</div>

<!-- Detail breakdown -->
<div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
    <!-- Pracownik -->
    <div class="form-card" style="padding:20px;">
        <h4 style="margin-bottom:12px; color:var(--gray-700);"><?= $lang('employee_deductions') ?></h4>
        <table style="width:100%; font-size:13px;">
            <tr><td><?= $lang('zus_emerytalna') ?> (9.76%)</td><td class="text-right"><?= $fmt($s['prac_emerytalna']) ?> PLN</td></tr>
            <tr><td><?= $lang('zus_rentowa') ?> (1.5%)</td><td class="text-right"><?= $fmt($s['prac_rentowa']) ?> PLN</td></tr>
            <tr><td><?= $lang('zus_chorobowa') ?> (2.45%)</td><td class="text-right"><?= $fmt($s['prac_chorobowa']) ?> PLN</td></tr>
            <tr style="font-weight:600; border-top:1px solid var(--gray-200);"><td><?= $lang('social_total') ?></td><td class="text-right"><?= $fmt($s['prac_spoleczne_suma']) ?> PLN</td></tr>
            <tr><td><?= $lang('zus_health') ?> (9%)</td><td class="text-right"><?= $fmt($s['prac_zdrowotna']) ?> PLN</td></tr>
            <tr><td>PIT <?= $s['under26'] ? '(' . $lang('under_26') . ')' : '' ?></td><td class="text-right"><?= $fmt($s['prac_pit']) ?> PLN</td></tr>
            <?php if ($s['ppk_enabled']): ?>
            <tr><td>PPK (<?= $_GET['ppk_employee_rate'] ?? '2.0' ?>%)</td><td class="text-right"><?= $fmt($s['prac_ppk']) ?> PLN</td></tr>
            <?php endif; ?>
            <tr style="font-weight:700; border-top:2px solid var(--gray-300); color:#16a34a;"><td><?= $lang('salary_netto') ?></td><td class="text-right"><?= $fmt($s['netto']) ?> PLN</td></tr>
        </table>
    </div>
    <!-- Pracodawca -->
    <div class="form-card" style="padding:20px;">
        <h4 style="margin-bottom:12px; color:var(--gray-700);"><?= $lang('employer_contributions') ?></h4>
        <table style="width:100%; font-size:13px;">
            <tr><td><?= $lang('zus_emerytalna') ?> (9.76%)</td><td class="text-right"><?= $fmt($s['pracodawca_emerytalna']) ?> PLN</td></tr>
            <tr><td><?= $lang('zus_rentowa') ?> (6.5%)</td><td class="text-right"><?= $fmt($s['pracodawca_rentowa']) ?> PLN</td></tr>
            <tr><td><?= $lang('zus_wypadkowa') ?> (1.67%)</td><td class="text-right"><?= $fmt($s['pracodawca_wypadkowa']) ?> PLN</td></tr>
            <tr><td><?= $lang('zus_fp') ?> (2.45%)</td><td class="text-right"><?= $fmt($s['pracodawca_fp']) ?> PLN</td></tr>
            <tr><td>FGŚP (0.1%)</td><td class="text-right"><?= $fmt($s['pracodawca_fgsp']) ?> PLN</td></tr>
            <?php if ($s['ppk_enabled']): ?>
            <tr><td>PPK (<?= $_GET['ppk_employer_rate'] ?? '1.5' ?>%)</td><td class="text-right"><?= $fmt($s['pracodawca_ppk']) ?> PLN</td></tr>
            <?php endif; ?>
            <tr style="font-weight:600; border-top:1px solid var(--gray-200);"><td><?= $lang('employer_additions') ?></td><td class="text-right"><?= $fmt($s['pracodawca_suma']) ?> PLN</td></tr>
            <tr style="font-weight:700; border-top:2px solid var(--gray-300); color:#ef4444;"><td><?= $lang('employer_cost') ?></td><td class="text-right"><?= $fmt($s['koszt_pracodawcy']) ?> PLN</td></tr>
        </table>
    </div>
</div>
<?php endif; ?>

<?php elseif ($currentTab === 'mileage'): ?>
<!-- ══════════════════════════════════════════════════════ -->
<!-- TAB: KILOMETRÓWKA                                      -->
<!-- ══════════════════════════════════════════════════════ -->
<div class="form-card" style="padding:20px; margin-bottom:20px;">
    <h3 style="margin-bottom:16px;"><?= $lang('calc_mileage_title') ?></h3>
    <form method="GET" action="/office/tax-calculator">
        <input type="hidden" name="tab" value="mileage">
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; align-items:flex-end;">
            <div class="form-group" style="margin:0;">
                <label class="form-label"><?= $lang('vehicle_type_label') ?></label>
                <select name="vehicle_type" class="form-input">
                    <?php foreach (\App\Services\CalculatorService::VEHICLE_TYPES as $vk => $vlabel): ?>
                        <option value="<?= $vk ?>" <?= ($_GET['vehicle_type'] ?? 'car_over_900') === $vk ? 'selected' : '' ?>><?= $lang($vlabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label"><?= $lang('distance_km') ?></label>
                <input type="number" name="km" class="form-input" value="<?= htmlspecialchars($_GET['km'] ?? '') ?>" placeholder="np. 150" step="0.1" min="0" required style="font-size:16px; font-weight:600;">
            </div>
            <div><button type="submit" class="btn btn-primary"><?= $lang('calculate') ?></button></div>
        </div>
    </form>
</div>

<?php if ($mileageResult): ?>
<div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px;">
    <div class="form-card" style="padding:20px; text-align:center; border-top:3px solid #2563eb;">
        <div style="font-size:13px; color:var(--gray-500);"><?= $lang('distance_km') ?></div>
        <div style="font-size:28px; font-weight:700; color:#2563eb;"><?= number_format($mileageResult['km'], 1, ',', ' ') ?> km</div>
    </div>
    <div class="form-card" style="padding:20px; text-align:center; border-top:3px solid #ea580c;">
        <div style="font-size:13px; color:var(--gray-500);"><?= $lang('rate_per_km') ?></div>
        <div style="font-size:28px; font-weight:700; color:#ea580c;"><?= number_format($mileageResult['rate_per_km'], 2, ',', ' ') ?> PLN/km</div>
    </div>
    <div class="form-card" style="padding:20px; text-align:center; border-top:3px solid #16a34a;">
        <div style="font-size:13px; color:var(--gray-500);"><?= $lang('reimbursement_amount') ?></div>
        <div style="font-size:28px; font-weight:700; color:#16a34a;"><?= $fmt($mileageResult['amount']) ?> PLN</div>
    </div>
</div>
<?php endif; ?>

<?php elseif ($currentTab === 'currency'): ?>
<!-- ══════════════════════════════════════════════════════ -->
<!-- TAB: KALKULATOR WALUTOWY                               -->
<!-- ══════════════════════════════════════════════════════ -->
<div class="form-card" style="padding:20px; margin-bottom:20px;">
    <h3 style="margin-bottom:16px;"><?= $lang('calc_currency_title') ?></h3>
    <form method="GET" action="/office/tax-calculator">
        <input type="hidden" name="tab" value="currency">
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:16px; align-items:flex-end;">
            <div class="form-group" style="margin:0;">
                <label class="form-label"><?= $lang('amount') ?></label>
                <input type="number" name="curr_amount" class="form-input" value="<?= htmlspecialchars($_GET['curr_amount'] ?? '') ?>" placeholder="np. 1000" step="0.01" min="0" required style="font-size:16px; font-weight:600;">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label"><?= $lang('from_currency') ?></label>
                <select name="from_currency" class="form-input">
                    <?php foreach (\App\Services\CalculatorService::CURRENCIES as $cur): ?>
                        <option value="<?= $cur ?>" <?= ($_GET['from_currency'] ?? 'EUR') === $cur ? 'selected' : '' ?>><?= $cur ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label"><?= $lang('to_currency') ?></label>
                <select name="to_currency" class="form-input">
                    <?php foreach (\App\Services\CalculatorService::CURRENCIES as $cur): ?>
                        <option value="<?= $cur ?>" <?= ($_GET['to_currency'] ?? 'PLN') === $cur ? 'selected' : '' ?>><?= $cur ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label"><?= $lang('rate_date') ?></label>
                <input type="date" name="rate_date" class="form-input" value="<?= htmlspecialchars($_GET['rate_date'] ?? date('Y-m-d')) ?>">
            </div>
        </div>
        <div style="margin-top:16px;"><button type="submit" class="btn btn-primary"><?= $lang('calculate') ?></button></div>
    </form>
</div>

<?php if ($currencyResult): ?>
<div style="display:grid; grid-template-columns:1fr auto 1fr; gap:16px; align-items:center;">
    <div class="form-card" style="padding:20px; text-align:center; border-top:3px solid #2563eb;">
        <div style="font-size:13px; color:var(--gray-500);"><?= $currencyResult['from'] ?></div>
        <div style="font-size:28px; font-weight:700; color:#2563eb;"><?= $fmt($currencyResult['amount']) ?></div>
    </div>
    <div style="text-align:center;">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--gray-400)" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        <div style="font-size:11px; color:var(--gray-500); margin-top:4px;"><?= $lang('exchange_rate') ?>: <?= number_format($currencyResult['rate'], 4, ',', ' ') ?></div>
        <div style="font-size:10px; color:var(--gray-400);"><?= $lang('nbp_date') ?>: <?= $currencyResult['date'] ?></div>
    </div>
    <div class="form-card" style="padding:20px; text-align:center; border-top:3px solid #16a34a;">
        <div style="font-size:13px; color:var(--gray-500);"><?= $currencyResult['to'] ?></div>
        <div style="font-size:28px; font-weight:700; color:#16a34a;"><?= $fmt($currencyResult['result']) ?></div>
    </div>
</div>
<?php elseif ($currentTab === 'currency' && !empty($_GET['curr_amount'])): ?>
<div class="alert alert-error"><?= $lang('currency_rate_error') ?></div>
<?php endif; ?>

<?php elseif ($currentTab === 'car_allowance'): ?>
<!-- ══════════════════════════════════════════════════════ -->
<!-- TAB: RYCZAŁT SAMOCHODOWY                               -->
<!-- ══════════════════════════════════════════════════════ -->
<div class="form-card" style="padding:20px; margin-bottom:20px;">
    <h3 style="margin-bottom:16px;"><?= $lang('calc_car_allowance_title') ?></h3>
    <form method="GET" action="/office/tax-calculator">
        <input type="hidden" name="tab" value="car_allowance">
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:16px; align-items:flex-end;">
            <div class="form-group" style="margin:0;">
                <label class="form-label"><?= $lang('vehicle_type_label') ?></label>
                <select name="vehicle_type" class="form-input">
                    <?php foreach (\App\Services\CalculatorService::VEHICLE_TYPES as $vk => $vlabel): ?>
                        <option value="<?= $vk ?>" <?= ($_GET['vehicle_type'] ?? 'car_over_900') === $vk ? 'selected' : '' ?>><?= $lang($vlabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label"><?= $lang('monthly_km_limit') ?></label>
                <input type="number" name="monthly_km" class="form-input" value="<?= htmlspecialchars($_GET['monthly_km'] ?? '') ?>" placeholder="np. 300" step="10" min="0" required style="font-size:16px; font-weight:600;">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label"><?= $lang('absence_days') ?> (0-22)</label>
                <input type="number" name="absence_days" class="form-input" value="<?= htmlspecialchars($_GET['absence_days'] ?? '0') ?>" min="0" max="22" step="1">
            </div>
            <div><button type="submit" class="btn btn-primary"><?= $lang('calculate') ?></button></div>
        </div>
    </form>
</div>

<?php if ($carAllowanceResult): $ca = $carAllowanceResult; ?>
<div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:16px; margin-bottom:16px;">
    <div class="form-card" style="padding:20px; text-align:center; border-top:3px solid #2563eb;">
        <div style="font-size:13px; color:var(--gray-500);"><?= $lang('full_allowance') ?></div>
        <div style="font-size:28px; font-weight:700; color:#2563eb;"><?= $fmt($ca['full_amount']) ?> PLN</div>
        <div style="font-size:11px; color:var(--gray-400);"><?= $ca['monthly_km_limit'] ?> km × <?= number_format($ca['rate_per_km'], 2, ',', '') ?> PLN</div>
    </div>
    <?php if ($ca['absence_days'] > 0): ?>
    <div class="form-card" style="padding:20px; text-align:center; border-top:3px solid #ea580c;">
        <div style="font-size:13px; color:var(--gray-500);"><?= $lang('reduction') ?></div>
        <div style="font-size:28px; font-weight:700; color:#ea580c;">- <?= $fmt($ca['reduction']) ?> PLN</div>
        <div style="font-size:11px; color:var(--gray-400);"><?= $ca['absence_days'] ?> <?= $lang('days') ?> <?= $lang('absence') ?></div>
    </div>
    <?php else: ?>
    <div class="form-card" style="padding:20px; text-align:center; border-top:3px solid var(--gray-200);">
        <div style="font-size:13px; color:var(--gray-500);"><?= $lang('reduction') ?></div>
        <div style="font-size:28px; font-weight:700; color:var(--gray-400);">0,00 PLN</div>
        <div style="font-size:11px; color:var(--gray-400);"><?= $lang('no_absence') ?></div>
    </div>
    <?php endif; ?>
    <div class="form-card" style="padding:20px; text-align:center; border-top:3px solid #16a34a;">
        <div style="font-size:13px; color:var(--gray-500);"><?= $lang('final_allowance') ?></div>
        <div style="font-size:28px; font-weight:700; color:#16a34a;"><?= $fmt($ca['final_amount']) ?> PLN</div>
        <div style="font-size:11px; color:var(--gray-400);"><?= $lang('monthly') ?></div>
    </div>
</div>
<?php endif; ?>

<?php elseif ($currentTab === 'profit'): ?>
<!-- ══════════════════════════════════════════════════════ -->
<!-- TAB: ZYSK FIRMOWY                                      -->
<!-- ══════════════════════════════════════════════════════ -->
<div class="form-card" style="padding:20px; margin-bottom:20px;">
    <h3 style="margin-bottom:16px;"><?= $lang('calc_profit_title') ?></h3>
    <form method="GET" action="/office/tax-calculator">
        <input type="hidden" name="tab" value="profit">
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:16px; align-items:flex-end;">
            <div class="form-group" style="margin:0;">
                <label class="form-label"><?= $lang('biz_revenue') ?></label>
                <input type="number" name="biz_revenue" class="form-input" value="<?= htmlspecialchars($_GET['biz_revenue'] ?? '') ?>" placeholder="np. 500000" step="1000" min="0" required style="font-size:16px; font-weight:600;">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label"><?= $lang('cost_of_sales') ?></label>
                <input type="number" name="cost_of_sales" class="form-input" value="<?= htmlspecialchars($_GET['cost_of_sales'] ?? '') ?>" placeholder="0" step="1000" min="0">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label"><?= $lang('fixed_costs') ?></label>
                <input type="number" name="fixed_costs" class="form-input" value="<?= htmlspecialchars($_GET['fixed_costs'] ?? '') ?>" placeholder="0" step="1000" min="0">
            </div>
            <div><button type="submit" class="btn btn-primary"><?= $lang('calculate') ?></button></div>
        </div>
    </form>
</div>

<?php if ($profitResult): $pr = $profitResult; ?>
<div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:16px; margin-bottom:16px;">
    <div class="form-card" style="padding:20px; text-align:center; border-top:3px solid #2563eb;">
        <div style="font-size:13px; color:var(--gray-500);"><?= $lang('biz_revenue') ?></div>
        <div style="font-size:28px; font-weight:700; color:#2563eb;"><?= $fmt($pr['revenue']) ?> PLN</div>
    </div>
    <div class="form-card" style="padding:20px; text-align:center; border-top:3px solid #ea580c;">
        <div style="font-size:13px; color:var(--gray-500);"><?= $lang('total_costs_label') ?></div>
        <div style="font-size:28px; font-weight:700; color:#ea580c;"><?= $fmt($pr['total_costs']) ?> PLN</div>
        <div style="font-size:11px; color:var(--gray-400);"><?= $lang('cost_of_sales') ?>: <?= $fmt($pr['cost_of_sales']) ?> + <?= $lang('fixed_costs') ?>: <?= $fmt($pr['fixed_costs']) ?></div>
    </div>
    <div class="form-card" style="padding:20px; text-align:center; border-top:3px solid <?= $pr['is_loss'] ? '#dc2626' : '#16a34a' ?>;">
        <div style="font-size:13px; color:var(--gray-500);"><?= $pr['is_loss'] ? $lang('loss') : $lang('net_profit_label') ?></div>
        <div style="font-size:28px; font-weight:700; color:<?= $pr['is_loss'] ? '#dc2626' : '#16a34a' ?>;"><?= $fmt(abs($pr['net_profit'])) ?> PLN</div>
        <div style="font-size:11px; color:var(--gray-400);"><?= $lang('margin_label') ?>: <?= $fmtPct($pr['margin_percent']) ?></div>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<style>
@media (max-width: 900px) {
    div[style*="grid-template-columns:repeat(4"], div[style*="grid-template-columns:repeat(3"] { grid-template-columns: repeat(2, 1fr) !important; }
    div[style*="grid-template-columns:1fr 1fr 1fr;"], div[style*="grid-template-columns:1fr 1fr 1fr 1fr"] { grid-template-columns: 1fr 1fr !important; }
}
@media (max-width: 600px) {
    div[style*="grid-template-columns:repeat(2"], div[style*="grid-template-columns:1fr 1fr"] { grid-template-columns: 1fr !important; }
}
</style>
