<?php
$monthNames = ['','Sty','Lut','Mar','Kwi','Maj','Cze','Lip','Sie','Wrz','Paź','Lis','Gru'];
$n2 = fn($v) => number_format((float)$v, 2, ',', ' ');
$contractLabels = ['uop' => 'Umowa o pracę', 'uz' => 'Umowa zlecenie', 'uod' => 'Umowa o dzieło'];

// Bar chart calculation
$maxCost = 0;
foreach ($costTrend as $row) {
    $maxCost = max($maxCost, (float)$row['total_employer_cost']);
}
$maxCost = $maxCost > 0 ? $maxCost : 1;

// Donut chart calculation
$totalEmp = array_sum(array_column($distribution, 'count'));
$donutColors = ['uop' => '#2563eb', 'uz' => '#16a34a', 'uod' => '#f59e0b', '' => '#9ca3af'];
$circumference = 100.53; // 2π×16
$donutSegments = [];
$offset = 0;
foreach ($distribution as $dist) {
    $pct  = $totalEmp > 0 ? $dist['count'] / $totalEmp : 0;
    $dash = round($pct * $circumference, 2);
    $gap  = round($circumference - $dash, 2);
    $color = $donutColors[$dist['contract_type']] ?? '#9ca3af';
    $donutSegments[] = compact('dash', 'gap', 'offset', 'color', 'pct') + ['type' => $dist['contract_type'], 'count' => $dist['count']];
    $offset += $dash;
}
?>

<div class="section-header">
    <div>
        <h1>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            <?= $lang('hr_analytics') ?> — <?= $year ?>
        </h1>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <?php foreach ($years as $y): ?>
        <a href="?year=<?= $y ?>" class="btn btn-sm <?= $y == $year ? 'btn-primary' : 'btn-secondary' ?>"><?= $y ?></a>
        <?php endforeach; ?>
    </div>
</div>

<?php include __DIR__ . '/../hr_nav.php'; ?>

<!-- KPI Cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:20px;">
    <div class="card" style="padding:14px 18px;">
        <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px;"><?= $lang('hr_kpi_active_employees') ?></div>
        <div style="font-size:26px;font-weight:700;color:var(--primary);"><?= $activeCount ?></div>
    </div>
    <?php
    $ytdGross = 0; $ytdCost = 0;
    foreach ($costTrend as $row) {
        if ((int)$row['period_year'] === $year) {
            $ytdGross += (float)$row['total_gross'];
            $ytdCost  += (float)$row['total_employer_cost'];
        }
    }
    ?>
    <div class="card" style="padding:14px 18px;">
        <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px;"><?= $lang('hr_kpi_ytd_cost') ?></div>
        <div style="font-size:18px;font-weight:700;"><?= $n2($ytdCost) ?> <span style="font-size:12px;font-weight:400;">PLN</span></div>
    </div>
    <div class="card" style="padding:14px 18px;">
        <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px;"><?= $lang('hr_chart_gross') ?> YTD</div>
        <div style="font-size:18px;font-weight:700;"><?= $n2($ytdGross) ?> <span style="font-size:12px;font-weight:400;">PLN</span></div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">

    <!-- Cost Trend Chart -->
    <div class="card">
        <div class="card-header"><strong><?= $lang('hr_cost_trend_12m') ?></strong></div>
        <div class="card-body" style="padding:12px 16px;">
            <?php if (empty($costTrend)): ?>
                <p style="color:var(--text-muted);font-size:13px;"><?= $lang('hr_no_payroll_yet') ?></p>
            <?php else: ?>
            <svg viewBox="0 0 360 120" style="width:100%;max-height:140px;">
                <?php
                $barCount = count($costTrend);
                $barW = max(12, min(24, (340 - 10) / ($barCount * 2)));
                $gap = $barW * 0.4;
                foreach ($costTrend as $i => $row):
                    $grossH = round(((float)$row['total_gross'] / $maxCost) * 85, 1);
                    $costH  = round(((float)$row['total_employer_cost'] / $maxCost) * 85, 1);
                    $x = 10 + $i * ($barW * 2 + $gap);
                ?>
                <rect x="<?= $x ?>" y="<?= 95 - $grossH ?>" width="<?= $barW ?>" height="<?= $grossH ?>" fill="#93c5fd" rx="2">
                    <title><?= $monthNames[(int)$row['period_month']] ?> <?= $row['period_year'] ?>: Brutto <?= $n2($row['total_gross']) ?></title>
                </rect>
                <rect x="<?= $x + $barW ?>" y="<?= 95 - $costH ?>" width="<?= $barW ?>" height="<?= $costH ?>" fill="var(--primary)" rx="2">
                    <title>Koszt pracodawcy <?= $n2($row['total_employer_cost']) ?></title>
                </rect>
                <text x="<?= $x + $barW ?>" y="108" text-anchor="middle" font-size="7" fill="var(--text-muted)"><?= $monthNames[(int)$row['period_month']] ?></text>
                <?php endforeach; ?>
            </svg>
            <div style="display:flex;gap:16px;font-size:11px;color:var(--text-muted);margin-top:4px;">
                <span><span style="display:inline-block;width:10px;height:10px;background:#93c5fd;border-radius:2px;vertical-align:middle;"></span> <?= $lang('hr_chart_gross') ?></span>
                <span><span style="display:inline-block;width:10px;height:10px;background:var(--primary);border-radius:2px;vertical-align:middle;"></span> <?= $lang('hr_chart_employer_cost') ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Contract Type Donut -->
    <div class="card">
        <div class="card-header"><strong><?= $lang('hr_contract_distribution') ?></strong></div>
        <div class="card-body" style="display:flex;align-items:center;gap:20px;">
            <?php if (empty($distribution)): ?>
                <p style="color:var(--text-muted);font-size:13px;">Brak danych.</p>
            <?php else: ?>
            <svg viewBox="0 0 36 36" style="width:100px;height:100px;flex-shrink:0;transform:rotate(-90deg);">
                <?php foreach ($donutSegments as $seg): ?>
                <circle cx="18" cy="18" r="16" fill="none" stroke="<?= $seg['color'] ?>" stroke-width="3.5"
                        stroke-dasharray="<?= $seg['dash'] ?> <?= $seg['gap'] ?>"
                        stroke-dashoffset="-<?= $seg['offset'] ?>"/>
                <?php endforeach; ?>
                <text x="18" y="19" text-anchor="middle" font-size="7" fill="var(--text)" style="transform:rotate(90deg);transform-origin:18px 18px;"><?= $totalEmp ?></text>
            </svg>
            <div style="font-size:13px;">
                <?php foreach ($donutSegments as $seg): ?>
                <div style="margin-bottom:4px;">
                    <span style="display:inline-block;width:10px;height:10px;background:<?= $seg['color'] ?>;border-radius:2px;vertical-align:middle;"></span>
                    <?= $contractLabels[$seg['type']] ?? $seg['type'] ?> — <strong><?= $seg['count'] ?></strong>
                    <span style="color:var(--text-muted);">(<?= round($seg['pct'] * 100) ?>%)</span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Leave Usage -->
<?php if (!empty($leaveUsage)): ?>
<div class="card" style="margin-bottom:20px;">
    <div class="card-header"><strong>Wykorzystanie urlopów — <?= $year ?></strong></div>
    <div class="card-body" style="padding:0;">
        <table class="table" style="margin:0;font-size:13px;">
            <thead><tr><th>Typ urlopu</th><th style="text-align:right;">Łączne dni</th><th style="text-align:right;">Liczba wniosków</th></tr></thead>
            <tbody>
                <?php foreach ($leaveUsage as $lu): ?>
                <tr>
                    <td><?= htmlspecialchars($lu['leave_type']) ?></td>
                    <td style="text-align:right;font-weight:600;"><?= number_format((float)$lu['total_days'], 1) ?></td>
                    <td style="text-align:right;"><?= $lu['request_count'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
