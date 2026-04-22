<?php
$monthNames = ['','Sty','Lut','Mar','Kwi','Maj','Cze','Lip','Sie','Wrz','Paź','Lis','Gru'];
$n = fn($v) => number_format((float)$v, 0, ',', ' ');
$n2 = fn($v) => number_format((float)$v, 2, ',', ' ');

// SVG mini bar chart
$maxCost = 0;
foreach ($costTrend as $row) {
    $maxCost = max($maxCost, (float)$row['total_employer_cost']);
}
$maxCost = $maxCost > 0 ? $maxCost : 1;
?>

<div class="section-header">
    <div>
        <h1>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
            <?= $lang('hr_dashboard_title') ?>
        </h1>
    </div>
</div>

<?php include __DIR__ . '/../hr_nav.php'; ?>

<?php if ($flash_success): ?><div class="alert alert-success"><?= htmlspecialchars($flash_success) ?></div><?php endif; ?>
<?php if ($flash_error): ?><div class="alert alert-error"><?= htmlspecialchars($flash_error) ?></div><?php endif; ?>

<!-- KPI Cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:20px;">

    <div class="card" style="padding:16px 20px;">
        <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;"><?= $lang('hr_kpi_active_employees') ?></div>
        <div style="font-size:28px;font-weight:700;color:var(--primary);"><?= $n($activeCount) ?></div>
    </div>

    <div class="card" style="padding:16px 20px;">
        <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;"><?= $lang('hr_kpi_pending_leaves') ?></div>
        <div style="font-size:28px;font-weight:700;color:<?= $pendingLeaves > 0 ? 'var(--warning)' : 'var(--success)' ?>;"><?= $n($pendingLeaves) ?></div>
        <?php if ($pendingLeaves > 0): ?>
        <a href="/client/hr/leaves?status=pending" style="font-size:12px;"><?= $lang('hr_view_all') ?> &rarr;</a>
        <?php endif; ?>
    </div>

    <div class="card" style="padding:16px 20px;">
        <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;"><?= $lang('hr_kpi_expiring_contracts') ?></div>
        <div style="font-size:28px;font-weight:700;color:<?= count($expiringContracts) > 0 ? 'var(--danger)' : 'var(--success)' ?>;"><?= count($expiringContracts) ?></div>
    </div>

    <?php if ($latestPayroll): ?>
    <div class="card" style="padding:16px 20px;">
        <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;"><?= $lang('hr_latest_payroll') ?></div>
        <div style="font-size:16px;font-weight:600;"><?= $monthNames[$latestPayroll['period_month']] ?> <?= $latestPayroll['period_year'] ?></div>
        <div style="font-size:13px;color:var(--text-muted);">
            <span class="badge badge-<?= match($latestPayroll['status']) { 'approved' => 'success', 'locked' => 'dark', default => 'info' } ?>">
                <?= \App\Models\HrPayrollRun::getStatusLabel($latestPayroll['status']) ?>
            </span>
            &middot; <?= $n2($latestPayroll['total_employer_cost']) ?> PLN
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Cost Trend (mini chart) + Expiring Contracts -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">

    <!-- Cost Trend -->
    <div class="card">
        <div class="card-header"><strong><?= $lang('hr_kpi_employer_cost') ?> — <?= $lang('hr_cost_trend_12m') ?></strong></div>
        <div class="card-body" style="padding:12px 16px;">
            <?php if (empty($costTrend)): ?>
                <p style="color:var(--text-muted);font-size:13px;"><?= $lang('hr_no_payroll_yet') ?></p>
            <?php else: ?>
            <svg viewBox="0 0 320 100" style="width:100%;max-height:120px;">
                <?php
                $barCount = count($costTrend);
                $barW = max(20, min(40, (320 - 10) / ($barCount * 2)));
                $gap = $barW * 0.5;
                foreach ($costTrend as $i => $row):
                    $h = round(((float)$row['total_employer_cost'] / $maxCost) * 80, 1);
                    $x = 5 + $i * ($barW + $gap);
                    $y = 85 - $h;
                    $label = $monthNames[(int)$row['period_month']];
                ?>
                <rect x="<?= $x ?>" y="<?= $y ?>" width="<?= $barW ?>" height="<?= $h ?>" fill="var(--primary)" rx="2" opacity="0.85">
                    <title><?= $label ?> <?= $row['period_year'] ?>: <?= $n2($row['total_employer_cost']) ?> PLN</title>
                </rect>
                <text x="<?= $x + $barW/2 ?>" y="98" text-anchor="middle" font-size="8" fill="var(--text-muted)"><?= $label ?></text>
                <?php endforeach; ?>
            </svg>
            <?php endif; ?>
        </div>
    </div>

    <!-- Expiring Contracts -->
    <div class="card">
        <div class="card-header"><strong><?= $lang('hr_expiring_contracts_30d') ?></strong></div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($expiringContracts)): ?>
                <p style="padding:16px;color:var(--text-muted);font-size:13px;"><?= $lang('hr_no_expiring_contracts') ?></p>
            <?php else: ?>
            <table class="table" style="margin:0;font-size:13px;">
                <thead>
                    <tr>
                        <th><?= $lang('hr_leave_employee') ?></th>
                        <th><?= $lang('hr_contract_type') ?></th>
                        <th><?= $lang('hr_leave_date_to') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expiringContracts as $c): ?>
                    <tr>
                        <td><a href="/client/hr/employees/<?= $c['emp_id'] ?>"><?= htmlspecialchars($c['employee_name']) ?></a></td>
                        <td><span class="badge badge-info"><?= htmlspecialchars(HrContract::getContractTypeLabel($c['contract_type'])) ?></span></td>
                        <td>
                            <?php
                            $daysLeft = (int) ((strtotime($c['end_date']) - time()) / 86400);
                            $color = $daysLeft <= 7 ? 'var(--danger)' : ($daysLeft <= 14 ? 'var(--warning)' : 'var(--text)');
                            ?>
                            <span style="color:<?= $color ?>;font-weight:600;"><?= $c['end_date'] ?></span>
                            <span style="font-size:11px;color:var(--text-muted);">(<?= $daysLeft ?> dni)</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Quick Actions -->
<div class="card">
    <div class="card-header"><strong><?= $lang('hr_quick_actions') ?></strong></div>
    <div class="card-body" style="display:flex;gap:12px;flex-wrap:wrap;">
        <a href="/client/hr/employees" class="btn btn-secondary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            <?= $lang('hr_employees') ?>
        </a>
        <a href="/client/hr/leaves" class="btn btn-secondary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <?= $lang('hr_leaves') ?>
            <?php if ($pendingLeaves > 0): ?>
            <span class="badge badge-warning" style="margin-left:4px;"><?= $pendingLeaves ?></span>
            <?php endif; ?>
        </a>
        <a href="/client/hr/leaves/calendar" class="btn btn-secondary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <?= $lang('hr_leave_calendar') ?>
        </a>
        <a href="/client/hr/payslips" class="btn btn-secondary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            <?= $lang('hr_payslips') ?>
        </a>
    </div>
</div>
