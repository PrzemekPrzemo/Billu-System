<?php
$monthNames = [
    1=>'Sty',2=>'Lut',3=>'Mar',4=>'Kwi',5=>'Maj',6=>'Cze',
    7=>'Lip',8=>'Sie',9=>'Wrz',10=>'Paź',11=>'Lis',12=>'Gru',
];

$contractLabels = [
    'uop' => 'Umowa o pracę',
    'uz'  => 'Umowa zlecenie',
    'uod' => 'Umowa o dzieło',
];

$n = fn($v) => number_format((float)$v, 0, ',', ' ');

// ── Bar chart calculation ──────────────────────────────────────
$svgW      = 520;
$svgH      = 120;
$padLeft   = 0;
$padBottom = 18;
$chartH    = $svgH - $padBottom;

$maxVal = 0;
foreach ($costTrend as $row) {
    $maxVal = max($maxVal, $row['total_employer_cost']);
}
$maxVal = $maxVal > 0 ? $maxVal : 1;

$barCount = count($costTrend);
$barW     = $barCount > 0 ? ($svgW - 4) / ($barCount * 2 + 1) : 30;
$barW     = max(8, min(32, $barW));
$gapW     = $barW * 0.6;

// ── Donut chart calculation ────────────────────────────────────
$totalEmp = array_sum(array_column($distribution, 'count'));
$donutColors = ['uop'=>'#2563eb','uz'=>'#16a34a','uod'=>'#f59e0b',''=>'#9ca3af'];
$circumference = 100.53; // 2π×16 (radius=16)
$donutSegments = [];
$offset = 0;
foreach ($distribution as $dist) {
    $pct    = $totalEmp > 0 ? $dist['count'] / $totalEmp : 0;
    $dash   = round($pct * $circumference, 2);
    $gap    = round($circumference - $dash, 2);
    $color  = $donutColors[$dist['contract_type']] ?? '#9ca3af';
    $donutSegments[] = compact('dash', 'gap', 'offset', 'color', 'pct') + ['type' => $dist['contract_type'], 'count' => $dist['count']];
    $offset += $dash;
}
?>

<div class="section-header">
    <div>
        <div class="breadcrumb-path" style="font-size:13px;color:var(--text-muted);margin-bottom:4px;">
            <a href="/office/hr/settings"><?= $lang('hr_module') ?></a> &rsaquo;
            <a href="/office/hr/<?= $clientId ?>/employees"><?= htmlspecialchars($client['company_name']) ?></a> &rsaquo;
            <?= $lang('hr_analytics') ?>
        </div>
        <h1>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            <?= $lang('hr_analytics') ?> — <?= htmlspecialchars($client['company_name']) ?>
        </h1>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <span style="font-size:13px;color:var(--text-muted);"><?= $lang('hr_analytics_year') ?>:</span>
        <select onchange="window.location='?year='+this.value" style="padding:4px 8px;border-radius:6px;border:1px solid #ddd;">
            <?php for ($y = (int)date('Y'); $y >= (int)date('Y')-3; $y--): ?>
            <option value="<?= $y ?>" <?= $y===$year?'selected':'' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
        <a href="/office/hr/<?= $clientId ?>/employees" class="btn btn-secondary"><?= $lang('back') ?></a>
    </div>
</div>

<?php include __DIR__ . '/../hr_nav.php'; ?>

<?php if ($flash_success): ?><div class="alert alert-success"><?= htmlspecialchars($flash_success) ?></div><?php endif; ?>
<?php if ($flash_error): ?><div class="alert alert-error"><?= htmlspecialchars($flash_error) ?></div><?php endif; ?>

<!-- ── KPI Cards ───────────────────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:20px;">

    <div class="card" style="padding:16px 20px;">
        <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">
            <?= $lang('hr_kpi_active_employees') ?>
        </div>
        <div style="font-size:28px;font-weight:700;color:var(--primary);">
            <?= $kpi['active_employees'] ?>
        </div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">
            <?php if ($kpi['employees_hired_ytd'] > 0): ?>
            &#8593; <?= $kpi['employees_hired_ytd'] ?> przyjętych w <?= $year ?>
            <?php endif; ?>
            <?php if ($kpi['employees_left_ytd'] > 0): ?>
            &nbsp;&#8595; <?= $kpi['employees_left_ytd'] ?> odeszło
            <?php endif; ?>
        </div>
    </div>

    <div class="card" style="padding:16px 20px;">
        <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">
            <?= $lang('hr_kpi_monthly_cost') ?>
        </div>
        <div style="font-size:28px;font-weight:700;">
            <?= $n($kpi['cost_current_month']) ?> <span style="font-size:16px;color:var(--text-muted);">PLN</span>
        </div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:4px;"><?= $lang('hr_kpi_employer_cost') ?></div>
    </div>

    <div class="card" style="padding:16px 20px;">
        <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">
            <?= $lang('hr_kpi_ytd_cost') ?>
        </div>
        <div style="font-size:28px;font-weight:700;">
            <?= $n($kpi['cost_ytd']) ?> <span style="font-size:16px;color:var(--text-muted);">PLN</span>
        </div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:4px;"><?= $year ?></div>
    </div>

    <div class="card" style="padding:16px 20px;">
        <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">
            <?= $lang('hr_kpi_avg_salary') ?>
        </div>
        <div style="font-size:28px;font-weight:700;">
            <?= $n($kpi['avg_salary']) ?> <span style="font-size:16px;color:var(--text-muted);">PLN</span>
        </div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:4px;"><?= $lang('hr_kpi_brutto') ?></div>
    </div>

</div>

<!-- ── Row 2: Bar chart + Donut chart ────────────────────────────── -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:14px;margin-bottom:20px;">

    <!-- Bar chart: 12-month cost trend -->
    <div class="card" style="padding:16px 20px;">
        <h3 style="margin:0 0 12px;font-size:14px;"><?= $lang('hr_cost_trend_12m') ?></h3>
        <?php if (empty($costTrend)): ?>
        <p style="color:var(--text-muted);font-size:13px;"><?= $lang('hr_analytics_no_data') ?></p>
        <?php else: ?>
        <svg viewBox="0 0 <?= $svgW ?> <?= $svgH ?>" width="100%" style="overflow:visible;">
            <?php
            $xCursor = $barW * 0.5;
            foreach ($costTrend as $i => $row):
                $grossH    = round($chartH * ($row['total_gross'] / $maxVal), 1);
                $empCostH  = round($chartH * ($row['total_employer_cost'] / $maxVal), 1);
                $grossY    = $chartH - $grossH;
                $empCostY  = $chartH - $empCostH;
                $label     = ($monthNames[$row['month']] ?? $row['month']) . "'" . substr($row['year'],-2);
            ?>
            <!-- Gross bar (lighter) -->
            <rect x="<?= round($xCursor, 1) ?>" y="<?= $grossY ?>"
                  width="<?= round($barW*0.45, 1) ?>" height="<?= $grossH ?>"
                  fill="#93c5fd" rx="2" title="Brutto: <?= $n($row['total_gross']) ?> PLN"/>
            <!-- Employer cost bar (darker) -->
            <rect x="<?= round($xCursor + $barW*0.5, 1) ?>" y="<?= $empCostY ?>"
                  width="<?= round($barW*0.45, 1) ?>" height="<?= $empCostH ?>"
                  fill="#2563eb" rx="2" title="Koszt: <?= $n($row['total_employer_cost']) ?> PLN"/>
            <!-- Month label -->
            <text x="<?= round($xCursor + $barW*0.45, 1) ?>" y="<?= $svgH - 2 ?>"
                  text-anchor="middle" font-size="8" fill="#6b7280"><?= $label ?></text>
            <?php
                $xCursor += $barW + $gapW;
            endforeach;
            ?>
            <!-- Baseline -->
            <line x1="0" y1="<?= $chartH ?>" x2="<?= $svgW ?>" y2="<?= $chartH ?>" stroke="#e5e7eb" stroke-width="1"/>
        </svg>
        <div style="display:flex;gap:16px;margin-top:8px;font-size:12px;">
            <span><span style="display:inline-block;width:12px;height:12px;background:#93c5fd;border-radius:2px;vertical-align:middle;margin-right:4px;"></span><?= $lang('hr_chart_gross') ?></span>
            <span><span style="display:inline-block;width:12px;height:12px;background:#2563eb;border-radius:2px;vertical-align:middle;margin-right:4px;"></span><?= $lang('hr_chart_employer_cost') ?></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Donut chart: contract type distribution -->
    <div class="card" style="padding:16px 20px;">
        <h3 style="margin:0 0 12px;font-size:14px;"><?= $lang('hr_contract_distribution') ?></h3>
        <?php if (empty($distribution) || $totalEmp === 0): ?>
        <p style="color:var(--text-muted);font-size:13px;"><?= $lang('hr_analytics_no_data') ?></p>
        <?php else: ?>
        <div style="display:flex;align-items:center;gap:16px;">
            <svg viewBox="0 0 36 36" width="100" height="100" style="flex-shrink:0;">
                <circle cx="18" cy="18" r="16" fill="none" stroke="#f3f4f6" stroke-width="3.8"/>
                <?php $svgOffset = 25; /* start at top */ foreach ($donutSegments as $seg): ?>
                <circle cx="18" cy="18" r="16" fill="none"
                        stroke="<?= htmlspecialchars($seg['color']) ?>"
                        stroke-width="3.8"
                        stroke-dasharray="<?= $seg['dash'] ?> <?= $seg['gap'] ?>"
                        stroke-dashoffset="-<?= round($svgOffset, 2) ?>"
                        transform="rotate(-90 18 18)"/>
                <?php $svgOffset += $seg['dash']; endforeach; ?>
                <text x="18" y="19" text-anchor="middle" font-size="6" font-weight="bold" fill="#374151"><?= $totalEmp ?></text>
                <text x="18" y="24" text-anchor="middle" font-size="4.5" fill="#9ca3af">os.</text>
            </svg>
            <div style="flex:1;font-size:12px;">
                <?php foreach ($donutSegments as $seg): ?>
                <div style="display:flex;justify-content:space-between;margin-bottom:6px;align-items:center;">
                    <span>
                        <span style="display:inline-block;width:10px;height:10px;background:<?= htmlspecialchars($seg['color']) ?>;border-radius:2px;vertical-align:middle;margin-right:5px;"></span>
                        <?= htmlspecialchars($contractLabels[$seg['type']] ?? $seg['type']) ?>
                    </span>
                    <strong><?= $seg['count'] ?></strong>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>

<!-- ── Row 3: Top employees + Rotation rate ──────────────────────── -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:14px;margin-bottom:20px;">

    <!-- Top 5 by cost -->
    <div class="card" style="padding:16px 20px;">
        <h3 style="margin:0 0 12px;font-size:14px;"><?= $lang('hr_top_employees_by_cost') ?></h3>
        <?php if (empty($topEmployees)): ?>
        <p style="color:var(--text-muted);font-size:13px;"><?= $lang('hr_analytics_no_data') ?></p>
        <?php else: ?>
        <table class="table" style="font-size:13px;">
            <thead><tr>
                <th>#</th>
                <th><?= $lang('name') ?></th>
                <th><?= $lang('hr_position') ?></th>
                <th style="text-align:right;"><?= $lang('hr_kpi_employer_cost') ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($topEmployees as $i => $emp): ?>
            <tr>
                <td style="color:var(--text-muted);"><?= $i+1 ?>.</td>
                <td><strong><?= htmlspecialchars($emp['employee_name']) ?></strong></td>
                <td><?= htmlspecialchars($emp['position'] ?? '—') ?></td>
                <td style="text-align:right;"><?= $n($emp['employer_total_cost']) ?> PLN</td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Rotation rate + YTD moves -->
    <div class="card" style="padding:16px 20px;">
        <h3 style="margin:0 0 12px;font-size:14px;"><?= $lang('hr_rotation_rate') ?> <?= $year ?></h3>

        <!-- Gauge-style CSS progress arc -->
        <div style="text-align:center;margin:8px 0 16px;">
            <?php
            $rateColor = $rotationRate <= 5 ? '#16a34a' : ($rotationRate <= 15 ? '#f59e0b' : '#dc2626');
            ?>
            <div style="font-size:40px;font-weight:700;color:<?= $rateColor ?>;">
                <?= number_format($rotationRate, 1) ?><span style="font-size:18px;">%</span>
            </div>
            <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">
                <?= $rotationRate <= 5 ? 'Niski' : ($rotationRate <= 15 ? 'Umiarkowany' : 'Wysoki') ?>
            </div>
        </div>

        <table style="width:100%;font-size:13px;border-collapse:collapse;">
            <tr>
                <td style="padding:4px 0;color:var(--text-muted);">&#8593; <?= $lang('hr_hired_ytd') ?></td>
                <td style="text-align:right;font-weight:600;color:#16a34a;"><?= $kpi['employees_hired_ytd'] ?></td>
            </tr>
            <tr>
                <td style="padding:4px 0;color:var(--text-muted);">&#8595; <?= $lang('hr_left_ytd') ?></td>
                <td style="text-align:right;font-weight:600;color:#dc2626;"><?= $kpi['employees_left_ytd'] ?></td>
            </tr>
            <tr style="border-top:1px solid #e5e7eb;">
                <td style="padding:6px 0;color:var(--text-muted);"><?= $lang('hr_kpi_active_employees') ?></td>
                <td style="text-align:right;font-weight:600;"><?= $kpi['active_employees'] ?></td>
            </tr>
            <tr>
                <td style="padding:4px 0;color:var(--text-muted);"><?= $lang('hr_kpi_avg_salary') ?></td>
                <td style="text-align:right;font-weight:600;"><?= $n($kpi['avg_salary']) ?> PLN</td>
            </tr>
        </table>

        <div style="margin-top:12px;">
            <a href="/office/hr/<?= $clientId ?>/attendance?year=<?= $year ?>" style="font-size:12px;">
                &#8594; <?= $lang('hr_attendance') ?>
            </a>
        </div>
    </div>

</div>