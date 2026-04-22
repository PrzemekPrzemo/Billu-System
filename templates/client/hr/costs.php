<?php
$monthNames = ['','Sty','Lut','Mar','Kwi','Maj','Cze','Lip','Sie','Wrz','Paź','Lis','Gru'];
$n  = fn($v) => number_format((float)$v, 0, ',', ' ');
$n2 = fn($v) => number_format((float)$v, 2, ',', ' ');

// SVG bar chart
$maxCost = 0;
foreach ($monthlyCosts as $row) {
    $maxCost = max($maxCost, (float)$row['total_employer_cost']);
}
$maxCost = $maxCost > 0 ? $maxCost : 1;
?>

<div class="section-header">
    <div>
        <h1>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            <?= $lang('hr_costs') ?> — <?= $selectedYear ?>
        </h1>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <?php foreach ($years as $y): ?>
        <a href="?year=<?= $y ?>" class="btn btn-sm <?= $y == $selectedYear ? 'btn-primary' : 'btn-secondary' ?>"><?= $y ?></a>
        <?php endforeach; ?>
    </div>
</div>

<?php include __DIR__ . '/../hr_nav.php'; ?>

<!-- YTD Summary Cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:20px;">
    <div class="card" style="padding:14px 18px;">
        <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px;"><?= $lang('hr_kpi_employer_cost') ?> YTD</div>
        <div style="font-size:22px;font-weight:700;color:var(--primary);"><?= $n2($ytd['employer_cost']) ?> <span style="font-size:13px;font-weight:400;">PLN</span></div>
    </div>
    <div class="card" style="padding:14px 18px;">
        <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px;"><?= $lang('hr_chart_gross') ?> YTD</div>
        <div style="font-size:22px;font-weight:700;"><?= $n2($ytd['gross']) ?> <span style="font-size:13px;font-weight:400;">PLN</span></div>
    </div>
    <div class="card" style="padding:14px 18px;">
        <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px;">ZUS pracodawca YTD</div>
        <div style="font-size:22px;font-weight:700;"><?= $n2($ytd['zus_employer']) ?> <span style="font-size:13px;font-weight:400;">PLN</span></div>
    </div>
    <div class="card" style="padding:14px 18px;">
        <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px;">PIT YTD</div>
        <div style="font-size:22px;font-weight:700;"><?= $n2($ytd['pit']) ?> <span style="font-size:13px;font-weight:400;">PLN</span></div>
    </div>
</div>

<!-- Cost Trend Chart + Monthly Table -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">

    <!-- SVG Bar Chart -->
    <div class="card">
        <div class="card-header"><strong><?= $lang('hr_cost_trend_12m') ?></strong></div>
        <div class="card-body" style="padding:12px 16px;">
            <?php if (empty($monthlyCosts)): ?>
                <p style="color:var(--text-muted);font-size:13px;"><?= $lang('hr_no_payroll_yet') ?></p>
            <?php else: ?>
            <svg viewBox="0 0 340 120" style="width:100%;max-height:140px;">
                <?php
                $barCount = count($monthlyCosts);
                $barW = max(16, min(28, (320 - 10) / ($barCount * 2)));
                $gap = $barW * 0.4;
                foreach ($monthlyCosts as $i => $row):
                    $grossH  = round(((float)$row['total_gross'] / $maxCost) * 85, 1);
                    $costH   = round(((float)$row['total_employer_cost'] / $maxCost) * 85, 1);
                    $x = 10 + $i * ($barW * 2 + $gap);
                ?>
                <rect x="<?= $x ?>" y="<?= 95 - $grossH ?>" width="<?= $barW ?>" height="<?= $grossH ?>" fill="#93c5fd" rx="2">
                    <title><?= $monthNames[(int)$row['period_month']] ?>: Brutto <?= $n2($row['total_gross']) ?> PLN</title>
                </rect>
                <rect x="<?= $x + $barW ?>" y="<?= 95 - $costH ?>" width="<?= $barW ?>" height="<?= $costH ?>" fill="var(--primary)" rx="2">
                    <title><?= $monthNames[(int)$row['period_month']] ?>: Koszt pracodawcy <?= $n2($row['total_employer_cost']) ?> PLN</title>
                </rect>
                <text x="<?= $x + $barW ?>" y="108" text-anchor="middle" font-size="8" fill="var(--text-muted)"><?= $monthNames[(int)$row['period_month']] ?></text>
                <?php endforeach; ?>
            </svg>
            <div style="display:flex;gap:16px;font-size:11px;color:var(--text-muted);margin-top:4px;">
                <span><span style="display:inline-block;width:10px;height:10px;background:#93c5fd;border-radius:2px;vertical-align:middle;"></span> <?= $lang('hr_chart_gross') ?></span>
                <span><span style="display:inline-block;width:10px;height:10px;background:var(--primary);border-radius:2px;vertical-align:middle;"></span> <?= $lang('hr_chart_employer_cost') ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Monthly Table -->
    <div class="card">
        <div class="card-header"><strong>Zestawienie miesięczne</strong></div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($monthlyCosts)): ?>
                <p style="padding:16px;color:var(--text-muted);font-size:13px;"><?= $lang('hr_no_payroll_yet') ?></p>
            <?php else: ?>
            <table class="table" style="margin:0;font-size:12px;">
                <thead>
                    <tr>
                        <th>Miesiąc</th>
                        <th style="text-align:right;"><?= $lang('hr_chart_gross') ?></th>
                        <th style="text-align:right;">ZUS prac.</th>
                        <th style="text-align:right;"><?= $lang('hr_chart_employer_cost') ?></th>
                        <th style="text-align:center;">Prac.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthlyCosts as $row): ?>
                    <tr>
                        <td><?= $monthNames[(int)$row['period_month']] ?> <?= $row['period_year'] ?></td>
                        <td style="text-align:right;"><?= $n2($row['total_gross']) ?></td>
                        <td style="text-align:right;"><?= $n2($row['total_zus_employer']) ?></td>
                        <td style="text-align:right;font-weight:600;"><?= $n2($row['total_employer_cost']) ?></td>
                        <td style="text-align:center;"><?= $row['employee_count'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Per-employee breakdown (latest month) -->
<?php if (!empty($employeeCosts)): ?>
<div class="card">
    <div class="card-header"><strong>Koszt per pracownik — ostatni miesiąc</strong></div>
    <div class="card-body" style="padding:0;">
        <table class="table" style="margin:0;font-size:12px;">
            <thead>
                <tr>
                    <th><?= $lang('hr_leave_employee') ?></th>
                    <th style="text-align:right;">Brutto</th>
                    <th style="text-align:right;">ZUS prac.</th>
                    <th style="text-align:right;">ZUS pracodawca</th>
                    <th style="text-align:right;">PIT</th>
                    <th style="text-align:right;">Netto</th>
                    <th style="text-align:right;font-weight:700;">Koszt pracodawcy</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($employeeCosts as $ec): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($ec['employee_name']) ?></strong></td>
                    <td style="text-align:right;"><?= $n2($ec['gross_salary']) ?></td>
                    <td style="text-align:right;"><?= $n2($ec['zus_total_employee']) ?></td>
                    <td style="text-align:right;"><?= $n2($ec['zus_total_employer']) ?></td>
                    <td style="text-align:right;"><?= $n2($ec['pit_advance']) ?></td>
                    <td style="text-align:right;"><?= $n2($ec['net_salary']) ?></td>
                    <td style="text-align:right;font-weight:700;"><?= $n2($ec['employer_total_cost']) ?> PLN</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
