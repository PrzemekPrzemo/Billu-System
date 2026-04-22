<div class="section-header">
    <div>
        <div class="breadcrumb-path" style="font-size:13px;color:var(--text-muted);margin-bottom:4px;">
            <a href="/office/hr/settings"><?= $lang('hr_module') ?></a> &rsaquo;
            <a href="/office/hr/<?= $client['id'] ?>/employees"><?= htmlspecialchars($client['company_name']) ?></a> &rsaquo;
            <?= $lang('hr_budget') ?>
        </div>
        <h1>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            <?= $lang('hr_budget') ?> — <?= htmlspecialchars($client['company_name']) ?>
        </h1>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <!-- Year selector -->
        <form method="GET" action="/office/hr/<?= $client['id'] ?>/budget" style="display:flex;gap:6px;align-items:center;">
            <select name="year" class="form-control" onchange="this.form.submit()" style="width:auto;">
                <?php foreach ($years as $y): ?>
                    <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <a href="/office/hr/<?= $client['id'] ?>/budget/export-excel?year=<?= $year ?>" class="btn btn-secondary">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            <?= $lang('hr_budget_export') ?>
        </a>
    </div>
</div>

<?php if ($flash_success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($flash_success) ?></div>
<?php endif; ?>
<?php if ($flash_error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($flash_error) ?></div>
<?php endif; ?>

<?php
$monthNames = [
    1=>'Styczeń', 2=>'Luty', 3=>'Marzec', 4=>'Kwiecień',
    5=>'Maj', 6=>'Czerwiec', 7=>'Lipiec', 8=>'Sierpień',
    9=>'Wrzesień', 10=>'Październik', 11=>'Listopad', 12=>'Grudzień',
];
$n = fn($v) => number_format((float)$v, 2, ',', ' ');
?>

<form method="POST" action="/office/hr/<?= $client['id'] ?>/budget">
    <?= csrf_field() ?>
    <input type="hidden" name="year" value="<?= $year ?>">

    <div class="card">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <strong><?= $lang('hr_budget') ?> <?= $year ?></strong>
            <button type="submit" class="btn btn-primary btn-sm">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                <?= $lang('save') ?>
            </button>
        </div>
        <div style="overflow-x:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:120px;"><?= $lang('month') ?></th>
                        <th><?= $lang('hr_budget_planned') ?> Brutto</th>
                        <th><?= $lang('hr_budget_planned') ?> Koszt</th>
                        <th style="background:var(--bg-secondary,#f9fafb);"><?= $lang('hr_budget_actual') ?> Brutto</th>
                        <th style="background:var(--bg-secondary,#f9fafb);"><?= $lang('hr_budget_actual') ?> Koszt</th>
                        <th><?= $lang('hr_budget_variance') ?> Brutto</th>
                        <th><?= $lang('hr_budget_variance') ?> Koszt</th>
                        <th style="width:200px;"><?= $lang('notes') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $totalPlanGross = $totalPlanCost = $totalActGross = $totalActCost = 0;
                    for ($m = 1; $m <= 12; $m++):
                        $plan   = $budget[$m] ?? null;
                        $actual = $actualByMonth[$m] ?? null;

                        $planGross = (float)($plan['planned_gross'] ?? 0);
                        $planCost  = (float)($plan['planned_cost']  ?? 0);
                        $actGross  = (float)($actual['gross_salary']         ?? 0);
                        $actCost   = (float)($actual['employer_total_cost']  ?? 0);
                        $deltaG    = $actGross - $planGross;
                        $deltaC    = $actCost  - $planCost;

                        $isOver    = ($planGross > 0 && $actGross > $planGross * 1.10)
                                  || ($planCost  > 0 && $actCost  > $planCost  * 1.10);
                        $rowStyle  = $isOver ? 'background:#fef2f2;' : '';

                        $totalPlanGross += $planGross;
                        $totalPlanCost  += $planCost;
                        $totalActGross  += $actGross;
                        $totalActCost   += $actCost;
                    ?>
                    <tr style="<?= $rowStyle ?>">
                        <td><strong><?= $monthNames[$m] ?></strong></td>
                        <td>
                            <input type="number" name="budget[<?= $m ?>][planned_gross]"
                                   class="form-control form-control-sm"
                                   step="0.01" min="0"
                                   value="<?= $planGross > 0 ? number_format($planGross, 2, '.', '') : '' ?>"
                                   placeholder="0.00" style="width:120px;">
                        </td>
                        <td>
                            <input type="number" name="budget[<?= $m ?>][planned_cost]"
                                   class="form-control form-control-sm"
                                   step="0.01" min="0"
                                   value="<?= $planCost > 0 ? number_format($planCost, 2, '.', '') : '' ?>"
                                   placeholder="0.00" style="width:120px;">
                        </td>
                        <td style="background:var(--bg-secondary,#f9fafb);text-align:right;">
                            <?= $actGross > 0 ? $n($actGross) . ' PLN' : '<span style="color:var(--text-muted)">—</span>' ?>
                        </td>
                        <td style="background:var(--bg-secondary,#f9fafb);text-align:right;">
                            <?= $actCost > 0 ? $n($actCost) . ' PLN' : '<span style="color:var(--text-muted)">—</span>' ?>
                        </td>
                        <td style="text-align:right;color:<?= $deltaG > 0 ? '#dc2626' : ($deltaG < 0 ? '#16a34a' : 'inherit') ?>">
                            <?= $actGross > 0 || $planGross > 0 ? ($deltaG >= 0 ? '+' : '') . $n($deltaG) . ' PLN' : '—' ?>
                        </td>
                        <td style="text-align:right;color:<?= $deltaC > 0 ? '#dc2626' : ($deltaC < 0 ? '#16a34a' : 'inherit') ?>">
                            <?= $actCost > 0 || $planCost > 0 ? ($deltaC >= 0 ? '+' : '') . $n($deltaC) . ' PLN' : '—' ?>
                        </td>
                        <td>
                            <input type="text" name="budget[<?= $m ?>][notes]"
                                   class="form-control form-control-sm"
                                   value="<?= htmlspecialchars($plan['notes'] ?? '') ?>"
                                   placeholder="<?= $lang('notes') ?>" style="width:180px;">
                        </td>
                        <?php if ($isOver): ?>
                        <td>
                            <span class="badge badge-danger" title="<?= $lang('hr_budget_over_threshold') ?>">+10%</span>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endfor; ?>
                </tbody>
                <tfoot>
                    <?php
                    $totalDeltaG = $totalActGross - $totalPlanGross;
                    $totalDeltaC = $totalActCost  - $totalPlanCost;
                    ?>
                    <tr style="font-weight:bold;background:var(--bg-secondary,#f9fafb);">
                        <td><?= $lang('total') ?></td>
                        <td style="text-align:right;"><?= $n($totalPlanGross) ?> PLN</td>
                        <td style="text-align:right;"><?= $n($totalPlanCost) ?> PLN</td>
                        <td style="text-align:right;"><?= $n($totalActGross) ?> PLN</td>
                        <td style="text-align:right;"><?= $n($totalActCost) ?> PLN</td>
                        <td style="text-align:right;color:<?= $totalDeltaG > 0 ? '#dc2626' : '#16a34a' ?>">
                            <?= ($totalDeltaG >= 0 ? '+' : '') . $n($totalDeltaG) ?> PLN
                        </td>
                        <td style="text-align:right;color:<?= $totalDeltaC > 0 ? '#dc2626' : '#16a34a' ?>">
                            <?= ($totalDeltaC >= 0 ? '+' : '') . $n($totalDeltaC) ?> PLN
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</form>