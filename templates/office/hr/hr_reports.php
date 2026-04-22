<?php
$monthNames = ['','Styczeń','Luty','Marzec','Kwiecień','Maj','Czerwiec',
               'Lipiec','Sierpień','Wrzesień','Październik','Listopad','Grudzień'];
?>

<div class="section-header">
    <div>
        <div class="breadcrumb-path" style="font-size:13px;color:var(--text-muted);margin-bottom:4px;">
            <a href="/office/hr/<?= $clientId ?>/employees"><?= $lang('hr_employees') ?></a> &rsaquo;
            <?= $lang('hr_reports') ?>
        </div>
        <h1><?= $lang('hr_reports') ?></h1>
    </div>
</div>

<?php include __DIR__ . '/../hr_nav.php'; ?>

<!-- Filters -->
<div class="card" style="margin-bottom:16px;">
    <div class="card-body">
        <form method="GET" action="/office/hr/<?= $clientId ?>/reports"
              style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
            <div class="form-group" style="margin:0;">
                <label><?= $lang('hr_pit_year') ?></label>
                <select name="year" class="form-control">
                    <?php foreach ($years as $y): ?>
                    <option value="<?= $y ?>" <?= $y == $selectedYear ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label><?= $lang('month') ?></label>
                <select name="month" class="form-control">
                    <option value="0" <?= $selectedMonth == 0 ? 'selected' : '' ?>>— Cały rok —</option>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $m == $selectedMonth ? 'selected' : '' ?>>
                        <?= $monthNames[$m] ?>
                    </option>
                    <?php endfor; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Pokaż</button>
            <!-- Export buttons -->
            <?php if ($selectedMonth > 0): ?>
            <a href="/office/hr/<?= $clientId ?>/reports/monthly-excel?year=<?= $selectedYear ?>&month=<?= $selectedMonth ?>"
               class="btn btn-secondary">↓ <?= $lang('hr_report_export_excel') ?> (mies.)</a>
            <?php endif; ?>
            <a href="/office/hr/<?= $clientId ?>/reports/annual-excel?year=<?= $selectedYear ?>"
               class="btn btn-secondary">↓ <?= $lang('hr_report_export_excel') ?> (roczny)</a>
        </form>
    </div>
</div>

<!-- Monthly view -->
<?php if ($selectedMonth > 0 && !empty($reportData['employees'])): ?>
<div class="card">
    <div class="card-header">
        <h3><?= $lang('hr_report_monthly') ?>: <?= $monthNames[$selectedMonth] ?> <?= $selectedYear ?></h3>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="table" style="margin:0;font-size:13px;min-width:900px;">
            <thead>
                <tr>
                    <th>Pracownik</th>
                    <th style="text-align:right;">Brutto</th>
                    <th style="text-align:right;">ZUS prac.</th>
                    <th style="text-align:right;">Podst. PIT</th>
                    <th style="text-align:right;">Zaliczka PIT</th>
                    <th style="text-align:right;">PPK prac.</th>
                    <th style="text-align:right;">Netto</th>
                    <th style="text-align:right;">ZUS pracodawcy</th>
                    <th style="text-align:right;"><?= $lang('hr_employer_total_cost') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reportData['employees'] as $emp): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($emp['employee_name']) ?></strong>
                        <?php if ($emp['position']): ?>
                        <div style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($emp['position']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;"><?= number_format($emp['gross_salary'],       2, ',', ' ') ?></td>
                    <td style="text-align:right;"><?= number_format($emp['zus_total_employee'],  2, ',', ' ') ?></td>
                    <td style="text-align:right;"><?= number_format($emp['tax_base'],            2, ',', ' ') ?></td>
                    <td style="text-align:right;"><?= number_format($emp['pit_advance'],         2, ',', ' ') ?></td>
                    <td style="text-align:right;"><?= number_format($emp['ppk_employee'],        2, ',', ' ') ?></td>
                    <td style="text-align:right;font-weight:600;"><?= number_format($emp['net_salary'],   2, ',', ' ') ?></td>
                    <td style="text-align:right;"><?= number_format($emp['zus_total_employer'],  2, ',', ' ') ?></td>
                    <td style="text-align:right;"><?= number_format($emp['employer_total_cost'], 2, ',', ' ') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot style="background:var(--bg-subtle);font-weight:600;">
                <tr>
                    <?php $t = $reportData['totals']; ?>
                    <td>Suma</td>
                    <td style="text-align:right;"><?= number_format($t['gross_salary'],       2, ',', ' ') ?></td>
                    <td style="text-align:right;"><?= number_format($t['zus_total_employee'],  2, ',', ' ') ?></td>
                    <td style="text-align:right;"><?= number_format($t['tax_base'],            2, ',', ' ') ?></td>
                    <td style="text-align:right;"><?= number_format($t['pit_advance'],         2, ',', ' ') ?></td>
                    <td style="text-align:right;"><?= number_format($t['ppk_employee'],        2, ',', ' ') ?></td>
                    <td style="text-align:right;"><?= number_format($t['net_salary'],          2, ',', ' ') ?></td>
                    <td style="text-align:right;"><?= number_format($t['zus_total_employer'],  2, ',', ' ') ?></td>
                    <td style="text-align:right;"><?= number_format($t['employer_total_cost'], 2, ',', ' ') ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Annual view -->
<?php elseif ($selectedMonth == 0 && !empty($reportData['monthly'])): ?>
<div class="card">
    <div class="card-header">
        <h3><?= $lang('hr_report_annual') ?>: <?= $selectedYear ?></h3>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="table" style="margin:0;font-size:13px;">
            <thead>
                <tr>
                    <th>Miesiąc</th>
                    <th style="text-align:right;">Brutto</th>
                    <th style="text-align:right;">Netto</th>
                    <th style="text-align:right;">Zaliczka PIT</th>
                    <th style="text-align:right;">ZUS pracodawcy</th>
                    <th style="text-align:right;">PPK pracodawcy</th>
                    <th style="text-align:right;"><?= $lang('hr_employer_total_cost') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sumGross = $sumNet = $sumPit = $sumZusEr = $sumPpkEr = $sumCost = 0;
                for ($m = 1; $m <= 12; $m++):
                    $md = $reportData['monthly'][$m] ?? null;
                    $gross = $md ? (float)$md['gross_salary'] : 0;
                    $net   = $md ? (float)$md['net_salary'] : 0;
                    $pit   = $md ? (float)$md['pit_advance'] : 0;
                    $zusEr = $md ? (float)$md['zus_total_employer'] : 0;
                    $ppkEr = $md ? (float)$md['ppk_employer'] : 0;
                    $cost  = $md ? (float)$md['employer_total_cost'] : 0;
                    $sumGross += $gross; $sumNet += $net; $sumPit += $pit;
                    $sumZusEr += $zusEr; $sumPpkEr += $ppkEr; $sumCost += $cost;
                ?>
                <tr <?= !$md ? 'style="color:var(--text-muted);"' : '' ?>>
                    <td><?= $monthNames[$m] ?></td>
                    <td style="text-align:right;"><?= $gross ? number_format($gross, 2, ',', ' ') : '—' ?></td>
                    <td style="text-align:right;"><?= $net   ? number_format($net,   2, ',', ' ') : '—' ?></td>
                    <td style="text-align:right;"><?= $pit   ? number_format($pit,   2, ',', ' ') : '—' ?></td>
                    <td style="text-align:right;"><?= $zusEr ? number_format($zusEr, 2, ',', ' ') : '—' ?></td>
                    <td style="text-align:right;"><?= $ppkEr ? number_format($ppkEr, 2, ',', ' ') : '—' ?></td>
                    <td style="text-align:right;"><?= $cost  ? number_format($cost,  2, ',', ' ') : '—' ?></td>
                </tr>
                <?php endfor; ?>
            </tbody>
            <tfoot style="background:var(--bg-subtle);font-weight:600;">
                <tr>
                    <td>RAZEM <?= $selectedYear ?></td>
                    <td style="text-align:right;"><?= number_format($sumGross, 2, ',', ' ') ?></td>
                    <td style="text-align:right;"><?= number_format($sumNet,   2, ',', ' ') ?></td>
                    <td style="text-align:right;"><?= number_format($sumPit,   2, ',', ' ') ?></td>
                    <td style="text-align:right;"><?= number_format($sumZusEr, 2, ',', ' ') ?></td>
                    <td style="text-align:right;"><?= number_format($sumPpkEr, 2, ',', ' ') ?></td>
                    <td style="text-align:right;"><?= number_format($sumCost,  2, ',', ' ') ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Summary KPIs -->
<?php if (!empty($reportData['totals'])): ?>
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:16px;">
    <?php
    $t = $reportData['totals'];
    $effectiveRate = $t['gross_salary'] > 0
        ? round(($t['employer_total_cost'] - $t['gross_salary']) / $t['gross_salary'] * 100, 1)
        : 0;
    $avgNet = count($reportData['employees']) > 0
        ? $t['net_salary'] / count($reportData['employees']) / 12
        : 0;
    $kpis = [
        ['Łączny koszt zatrudnienia',          $t['employer_total_cost'], 'var(--danger)', true],
        ['Efektywny narzut ZUS/PPK pracodawcy', $effectiveRate,           'var(--text)',   false],
        ['Śr. netto / pracownik / mies.',       $avgNet,                  'var(--success)',true],
    ];
    foreach ($kpis as [$label, $val, $color, $isMoney]):
    ?>
    <div class="card">
        <div class="card-body" style="text-align:center;padding:12px;">
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;"><?= $label ?></div>
            <div style="font-size:18px;font-weight:700;color:<?= $color ?>;margin-top:4px;">
                <?= $isMoney ? number_format($val, 2, ',', ' ') . ' zł' : number_format($val, 1, ',', '') . '%' ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php else: ?>
<div class="card">
    <div class="card-body" style="text-align:center;padding:32px;color:var(--text-muted);">
        Brak danych płacowych za wybrany okres. Oblicz listy płac, aby wygenerować raport.
    </div>
</div>
<?php endif; ?>