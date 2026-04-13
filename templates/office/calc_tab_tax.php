<?php
/** Tax profitability calculator tab — included from tax_calculator.php */
$fmt = fn($v) => number_format((float)$v, 2, ',', ' ');
$fmtPct = fn($v) => number_format((float)$v, 1, ',', '') . '%';
?>
<div class="form-card" style="padding:20px; margin-bottom:24px;">
    <form method="GET" action="/office/tax-calculator" id="calcForm">
        <input type="hidden" name="tab" value="tax">
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; align-items:flex-end;">
            <div class="form-group" style="margin:0;">
                <label class="form-label"><?= $lang('annual_revenue') ?></label>
                <input type="number" name="revenue" class="form-input" value="<?= htmlspecialchars($revenue) ?>" placeholder="np. 200000" step="1000" min="0" required style="font-size:16px; font-weight:600;">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label"><?= $lang('revenue_type') ?></label>
                <select name="is_gross" class="form-input">
                    <option value="0" <?= !$isGross ? 'selected' : '' ?>><?= $lang('net_without_vat') ?></option>
                    <option value="1" <?= $isGross ? 'selected' : '' ?>><?= $lang('gross_with_vat') ?></option>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label"><?= $lang('ryczalt_rate') ?></label>
                <select name="ryczalt_rate" class="form-input">
                    <?php foreach ($ryczaltRates as $rate): ?>
                        <option value="<?= $rate ?>" <?= abs($ryczaltRate - $rate) < 0.001 ? 'selected' : '' ?>><?= number_format($rate * 100, 1) ?>%</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; align-items:flex-end; margin-top:12px;">
            <div class="form-group" style="margin:0;">
                <label class="form-label"><?= $lang('zus_variant') ?></label>
                <select name="zus_variant" class="form-input">
                    <?php foreach ($zusVariants as $vKey => $vConf): ?>
                        <option value="<?= $vKey ?>" <?= $zusVariant === $vKey ? 'selected' : '' ?>><?= $lang($vConf['label_key']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label"><?= $lang('annual_costs') ?></label>
                <input type="number" name="costs" class="form-input" value="<?= $costs > 0 ? htmlspecialchars($costs) : '' ?>" placeholder="0" step="1000" min="0">
            </div>
        </div>
        <div style="margin-top:16px; display:flex; gap:8px; flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary" style="min-width:180px;"><?= $lang('calculate') ?></button>
            <?php if ($results): ?>
            <a href="/office/tax-calculator/pdf?<?= http_build_query(['revenue' => $revenue, 'is_gross' => (int)$isGross, 'ryczalt_rate' => $ryczaltRate, 'costs' => $costs, 'zus_variant' => $zusVariant]) ?>" class="btn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <?= $lang('export_pdf') ?>
            </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if ($results):
$best = $results['best'];
$zus = $results['zus_social'];
$allForms = [
    'ryczalt' => ['label' => $lang('tax_form_ryczalt'), 'data' => $results['ryczalt']],
    'liniowy' => ['label' => $lang('tax_form_liniowy'), 'data' => $results['liniowy']],
    'skala'   => ['label' => $lang('tax_form_skala'),   'data' => $results['skala']],
    'ip_box'  => ['label' => 'IP Box',                  'data' => $results['ip_box']],
];
?>

<!-- ZUS -->
<div class="form-card" style="padding:16px; margin-bottom:20px; background:var(--gray-50);">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
        <div>
            <strong><?= $lang('zus_social_annual') ?>:</strong>
            <span style="font-size:18px; font-weight:700; margin-left:8px;"><?= $fmt($zus['total_annual']) ?> PLN</span>
            <span style="color:var(--gray-500); font-size:13px; margin-left:8px;">(<?= $fmt($zus['total_monthly']) ?> PLN/<?= $lang('month_short') ?>)</span>
            <span class="badge badge-info" style="font-size:11px; margin-left:8px;"><?= $lang($zus['variant_label']) ?></span>
            <?php if ($zus['base_monthly'] > 0): ?>
                <span style="color:var(--gray-400); font-size:12px; margin-left:4px;"><?= $lang('zus_base') ?>: <?= $fmt($zus['base_monthly']) ?> PLN</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 4 CARDS -->
<div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:14px; margin-bottom:24px;">
    <?php foreach ($allForms as $key => $form):
        $d = $form['data']; $isBest = ($key === $best);
        $bc = $isBest ? '#16a34a' : 'var(--gray-200)';
    ?>
    <div style="border:2px solid <?= $bc ?>; border-radius:12px; overflow:hidden; position:relative;">
        <?php if ($isBest): ?><div style="position:absolute; top:10px; right:10px; background:#16a34a; color:white; font-size:9px; font-weight:700; padding:2px 6px; border-radius:10px; text-transform:uppercase;"><?= $lang('most_profitable') ?></div><?php endif; ?>
        <div style="padding:12px 16px; background:<?= $isBest ? '#f0fdf4' : 'var(--gray-50)' ?>; border-bottom:1px solid <?= $bc ?>;">
            <div style="font-size:12px; color:<?= $isBest ? '#16a34a' : 'var(--gray-700)' ?>; font-weight:600; text-transform:uppercase;"><?= $form['label'] ?></div>
            <div style="font-size:11px; color:var(--gray-500);"><?= $lang('tax_rate') ?>: <?= $d['tax_rate_label'] ?></div>
        </div>
        <div style="padding:14px 16px;">
            <div style="margin-bottom:10px; padding-bottom:8px; border-bottom:1px solid var(--gray-100); font-size:12px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:2px;"><span><?= $lang('revenue') ?></span><span><?= $fmt($d['revenue']) ?></span></div>
                <?php if ($d['costs'] > 0): ?><div style="display:flex; justify-content:space-between; margin-bottom:2px;"><span><?= $lang('costs') ?></span><span>- <?= $fmt($d['costs']) ?></span></div><?php endif; ?>
                <?php if ($d['free_amount'] > 0): ?><div style="display:flex; justify-content:space-between; color:var(--green-600);"><span><?= $lang('free_amount') ?></span><span>- <?= $fmt($d['free_amount']) ?></span></div><?php endif; ?>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:4px;"><span style="font-size:12px;"><?= $lang('income_tax') ?></span><span style="font-size:13px; font-weight:600; color:var(--red-600);"><?= $fmt($d['tax']) ?></span></div>
            <?php if ($key === 'ryczalt' && !empty($d['quarterly_tax'])): ?>
            <div style="font-size:11px; color:var(--gray-500); margin-bottom:4px; padding-left:4px;"><?= $lang('quarterly') ?>: <?= $fmt($d['quarterly_tax']) ?> | <?= $lang('monthly_short') ?>: <?= $fmt($d['monthly_tax']) ?></div>
            <?php endif; ?>
            <div style="display:flex; justify-content:space-between; margin-bottom:4px;"><span style="font-size:12px;"><?= $lang('zus_social') ?></span><span style="font-size:13px; font-weight:600; color:var(--orange-600);"><?= $fmt($d['zus_social']) ?></span></div>
            <div style="display:flex; justify-content:space-between; margin-bottom:10px; padding-bottom:8px; border-bottom:1px solid var(--gray-100);"><span style="font-size:12px;"><?= $lang('zus_health') ?></span><span style="font-size:13px; font-weight:600; color:var(--orange-600);"><?= $fmt($d['health_insurance']) ?></span></div>
            <div style="display:flex; justify-content:space-between; margin-bottom:4px;"><span style="font-weight:600;"><?= $lang('total_burden') ?></span><span style="font-size:14px; font-weight:700; color:var(--red-700);"><?= $fmt($d['total_burden']) ?></span></div>
            <div style="display:flex; justify-content:space-between; margin-bottom:10px;"><span style="font-size:11px; color:var(--gray-500);"><?= $lang('effective_rate') ?></span><span style="font-size:11px; color:var(--gray-500);"><?= $fmtPct($d['effective_rate']) ?></span></div>
            <div style="background:<?= $isBest ? '#f0fdf4' : 'var(--gray-50)' ?>; margin:-14px -16px 0; padding:12px 16px; border-top:2px solid <?= $isBest ? '#16a34a' : 'var(--gray-200)' ?>;">
                <div style="display:flex; justify-content:space-between;"><span style="font-size:12px; font-weight:600;"><?= $lang('net_income') ?></span><span style="font-size:18px; font-weight:700; color:<?= $isBest ? '#16a34a' : 'var(--gray-800)' ?>;"><?= $fmt($d['net_income']) ?></span></div>
                <div style="text-align:right; font-size:11px; color:var(--gray-500);"><?= $fmt($d['net_income'] / 12) ?> PLN/<?= $lang('month_short') ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- CHART -->
<div class="form-card" style="padding:20px; margin-bottom:24px;">
    <h3 style="margin-bottom:16px;"><?= $lang('visual_comparison') ?></h3>
    <?php $maxRev = $results['ryczalt']['revenue']; ?>
    <div style="display:flex; gap:24px; align-items:flex-end; justify-content:center; min-height:200px;">
        <?php foreach ($allForms as $key => $form):
            $d = $form['data']; $isBest = ($key === $best);
            $tP = $maxRev > 0 ? ($d['tax'] / $maxRev) * 100 : 0;
            $zP = $maxRev > 0 ? ($d['zus_social'] / $maxRev) * 100 : 0;
            $hP = $maxRev > 0 ? ($d['health_insurance'] / $maxRev) * 100 : 0;
            $nP = $maxRev > 0 ? (max(0, $d['net_income']) / $maxRev) * 100 : 0;
        ?>
        <div style="flex:1; max-width:140px; text-align:center;">
            <div style="display:flex; flex-direction:column; height:160px; border-radius:6px; overflow:hidden; border:<?= $isBest ? '2px solid #16a34a' : '1px solid var(--gray-200)' ?>;">
                <div style="flex:<?= $tP ?>; background:#ef4444;" title="<?= $fmt($d['tax']) ?>"></div>
                <div style="flex:<?= $zP ?>; background:#f59e0b;" title="<?= $fmt($d['zus_social']) ?>"></div>
                <div style="flex:<?= $hP ?>; background:#fbbf24;" title="<?= $fmt($d['health_insurance']) ?>"></div>
                <div style="flex:<?= $nP ?>; background:#22c55e;" title="<?= $fmt($d['net_income']) ?>"></div>
            </div>
            <div style="margin-top:6px; font-size:11px; font-weight:600; color:<?= $isBest ? '#16a34a' : 'var(--gray-700)' ?>;"><?= $form['label'] ?></div>
            <div style="font-size:10px; color:var(--gray-500);"><?= $fmt($d['net_income']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <div style="display:flex; gap:12px; justify-content:center; margin-top:10px; font-size:10px;">
        <span><span style="display:inline-block; width:8px; height:8px; border-radius:2px; background:#ef4444; margin-right:3px;"></span><?= $lang('income_tax') ?></span>
        <span><span style="display:inline-block; width:8px; height:8px; border-radius:2px; background:#f59e0b; margin-right:3px;"></span><?= $lang('zus_social') ?></span>
        <span><span style="display:inline-block; width:8px; height:8px; border-radius:2px; background:#fbbf24; margin-right:3px;"></span><?= $lang('zus_health') ?></span>
        <span><span style="display:inline-block; width:8px; height:8px; border-radius:2px; background:#22c55e; margin-right:3px;"></span><?= $lang('net_income') ?></span>
    </div>
</div>

<!-- SUMMARY -->
<div class="form-card" style="padding:20px; margin-bottom:24px;">
    <h3 style="margin-bottom:12px;"><?= $lang('comparison_summary') ?></h3>
    <?php $bestR = $results[$best]; $labels = ['ryczalt' => $lang('tax_form_ryczalt'), 'liniowy' => $lang('tax_form_liniowy'), 'skala' => $lang('tax_form_skala'), 'ip_box' => 'IP Box']; ?>
    <p><?= $lang('best_option_is') ?> <strong style="color:#16a34a;"><?= $labels[$best] ?></strong> (<?= $fmt($bestR['net_income']) ?> PLN <?= $lang('annually') ?>).</p>
    <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:12px;">
        <?php foreach ($allForms as $key => $form): if ($key === $best) continue; $diff = $bestR['net_income'] - $form['data']['net_income']; ?>
        <div style="padding:8px 14px; background:#fef2f2; border-radius:8px; border:1px solid #fecaca; flex:1; min-width:130px;">
            <div style="font-size:11px; color:var(--gray-500);"><?= $labels[$key] ?></div>
            <div style="font-size:14px; font-weight:700; color:#dc2626;">- <?= $fmt($diff) ?>/<?= $lang('year_short') ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- SAVE -->
<div class="form-card" style="padding:20px; margin-bottom:24px;">
    <h3 style="margin-bottom:12px;"><?= $lang('save_simulation') ?></h3>
    <form method="POST" action="/office/tax-calculator/save" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="revenue" value="<?= htmlspecialchars($revenue) ?>">
        <input type="hidden" name="is_gross" value="<?= (int)$isGross ?>">
        <input type="hidden" name="ryczalt_rate" value="<?= $ryczaltRate ?>">
        <input type="hidden" name="costs" value="<?= $costs ?>">
        <input type="hidden" name="zus_variant" value="<?= $zusVariant ?>">
        <div class="form-group" style="margin:0; flex:1; min-width:200px;">
            <select name="client_id" class="form-input" required>
                <option value="">-- <?= $lang('select_client_to_save') ?> --</option>
                <?php foreach ($clients as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><?= $lang('save_simulation') ?></button>
    </form>
</div>

<?php else: ?>
<div class="form-card" style="padding:40px; text-align:center; color:var(--gray-400);">
    <p style="font-size:15px;"><?= $lang('tax_calculator_empty') ?></p>
</div>
<?php endif; ?>

<!-- HISTORY -->
<?php if (!empty($simulations)): ?>
<div class="form-card" style="padding:20px;">
    <h3 style="margin-bottom:12px;"><?= $lang('saved_simulations') ?></h3>
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th><?= $lang('date') ?></th><th><?= $lang('client') ?></th><th class="text-right"><?= $lang('revenue') ?></th><th><?= $lang('most_profitable') ?></th><th style="width:80px;"></th></tr></thead>
            <tbody>
                <?php foreach ($simulations as $sim): $bl = ['ryczalt' => $lang('tax_form_ryczalt'), 'liniowy' => $lang('tax_form_liniowy'), 'skala' => $lang('tax_form_skala'), 'ip_box' => 'IP Box']; ?>
                <tr>
                    <td><?= date('d.m.Y', strtotime($sim['created_at'])) ?></td>
                    <td><strong><?= htmlspecialchars($sim['company_name'] ?? '') ?></strong></td>
                    <td class="text-right"><?= number_format((float)$sim['revenue'], 0, ',', ' ') ?> PLN</td>
                    <td><span class="badge badge-success" style="font-size:11px;"><?= $bl[$sim['best_option']] ?? $sim['best_option'] ?></span></td>
                    <td style="display:flex; gap:4px;">
                        <a href="/office/tax-calculator?tab=tax&revenue=<?= $sim['revenue'] ?>&is_gross=<?= $sim['is_gross'] ?>&ryczalt_rate=<?= $sim['ryczalt_rate'] ?>&costs=<?= $sim['costs'] ?>&zus_variant=<?= $sim['zus_variant'] ?? 'full' ?>" class="btn btn-sm">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
                        </a>
                        <form method="POST" action="/office/tax-calculator/simulation/<?= $sim['id'] ?>/delete" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <button type="submit" class="btn btn-sm btn-danger" style="padding:2px 6px;" onclick="return confirm('<?= $lang('delete_simulation_confirm') ?>')">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
