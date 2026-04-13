<?php
$fmt = fn($v) => number_format((float)$v, 2, ',', ' ');
$st = $statusTotals;
$totalInv = (int)($st['total'] ?? 0);
$accPct = $totalInv > 0 ? round((int)$st['accepted'] / $totalInv * 100) : 0;
$rejPct = $totalInv > 0 ? round((int)$st['rejected'] / $totalInv * 100) : 0;
$pendPct = $totalInv > 0 ? round((int)$st['pending'] / $totalInv * 100) : 0;
?>

<h1><?= $lang('office_analytics') ?></h1>

<!-- Row 1: Summary stat cards -->
<div class="stats-grid" style="margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-icon" style="background:#dbeafe; color:#2563eb;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= $totalClients ?></div>
            <div class="stat-label"><?= $lang('total_clients') ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#dcfce7; color:#16a34a;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= $st['accepted'] ?? 0 ?></div>
            <div class="stat-label"><?= $lang('accepted') ?> (<?= $accPct ?>%)</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fef3c7; color:#d97706;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= $st['pending'] ?? 0 ?></div>
            <div class="stat-label"><?= $lang('pending') ?> (<?= $pendPct ?>%)</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fee2e2; color:#dc2626;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= $st['rejected'] ?? 0 ?></div>
            <div class="stat-label"><?= $lang('rejected') ?> (<?= $rejPct ?>%)</div>
        </div>
    </div>
</div>

<!-- Row 2: Monthly invoice trend + Monthly gross value -->
<div class="charts-row" style="margin-bottom:24px;">
    <div class="chart-card">
        <h3><?= $lang('invoices_last_6_months') ?></h3>
        <?php if (!empty($monthlyStats)):
            $maxVal = max(array_column($monthlyStats, 'total')) ?: 1;
        ?>
        <div class="bar-chart">
            <?php foreach ($monthlyStats as $m): ?>
            <div class="bar-group">
                <div class="bar-stack" style="height:<?= round($m['total'] / $maxVal * 100) ?>%">
                    <div class="bar-segment bar-accepted" style="flex:<?= (int)$m['accepted'] ?>" title="<?= $lang('accepted') ?>: <?= $m['accepted'] ?>"></div>
                    <div class="bar-segment bar-rejected" style="flex:<?= (int)$m['rejected'] ?>" title="<?= $lang('rejected') ?>: <?= $m['rejected'] ?>"></div>
                    <div class="bar-segment bar-pending" style="flex:<?= (int)$m['pending'] ?>" title="<?= $lang('pending') ?>: <?= $m['pending'] ?>"></div>
                </div>
                <div class="bar-label"><?= substr($m['month'], 5) ?>/<?= substr($m['month'], 2, 2) ?></div>
                <div class="bar-value"><?= $m['total'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="chart-legend">
            <span class="legend-item"><span class="legend-dot" style="background:var(--success)"></span> <?= $lang('accepted') ?></span>
            <span class="legend-item"><span class="legend-dot" style="background:var(--danger)"></span> <?= $lang('rejected') ?></span>
            <span class="legend-item"><span class="legend-dot" style="background:var(--warning)"></span> <?= $lang('pending') ?></span>
        </div>
        <?php else: ?>
            <p class="text-muted"><?= $lang('no_chart_data') ?></p>
        <?php endif; ?>
    </div>

    <div class="chart-card">
        <h3><?= $lang('processed_value') ?></h3>
        <?php if (!empty($monthlyGross)):
            $maxGross = max(array_column($monthlyGross, 'gross')) ?: 1;
        ?>
        <div class="bar-chart">
            <?php foreach ($monthlyGross as $mg): ?>
            <div class="bar-group">
                <div class="bar-stack" style="height:<?= round((float)$mg['gross'] / $maxGross * 100) ?>%">
                    <div class="bar-segment" style="flex:<?= (float)$mg['net'] ?>; background:var(--primary);" title="Netto: <?= $fmt($mg['net']) ?>"></div>
                    <div class="bar-segment" style="flex:<?= ((float)$mg['gross'] - (float)$mg['net']) ?>; background:var(--info);" title="VAT"></div>
                </div>
                <div class="bar-label" style="font-size:9px;"><?= substr($mg['month'], 5) ?>/<?= substr($mg['month'], 2, 2) ?></div>
                <div class="bar-value" style="font-size:9px;"><?= number_format((float)$mg['gross'], 0, ',', ' ') ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="chart-legend">
            <span class="legend-item"><span class="legend-dot" style="background:var(--primary)"></span> Netto</span>
            <span class="legend-item"><span class="legend-dot" style="background:var(--info)"></span> VAT</span>
        </div>
        <?php else: ?>
            <p class="text-muted"><?= $lang('no_chart_data') ?></p>
        <?php endif; ?>
    </div>
</div>

<!-- Row 3: Verification efficiency + Client activity -->
<div class="charts-row" style="margin-bottom:24px;">
    <div class="chart-card">
        <h3><?= $lang('verification_efficiency') ?></h3>
        <?php if (!empty($batchEfficiency)): ?>
        <table class="table table-compact" style="box-shadow:none;">
            <thead>
                <tr>
                    <th><?= $lang('period') ?></th>
                    <th class="text-center"><?= $lang('batches') ?></th>
                    <th class="text-center"><?= $lang('finalized') ?></th>
                    <th class="text-center"><?= $lang('open') ?></th>
                    <th class="text-right"><?= $lang('avg_time') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($batchEfficiency as $be): ?>
                <tr>
                    <td><?= substr($be['month'], 5) ?>/<?= substr($be['month'], 0, 4) ?></td>
                    <td class="text-center"><?= $be['batch_count'] ?></td>
                    <td class="text-center"><span class="badge badge-success"><?= $be['finalized'] ?></span></td>
                    <td class="text-center">
                        <?php if ((int)$be['open'] > 0): ?><span class="badge badge-warning"><?= $be['open'] ?></span>
                        <?php else: ?><span class="text-muted">0</span><?php endif; ?>
                    </td>
                    <td class="text-right">
                        <?php if ($be['avg_hours'] !== null):
                            $h = (float)$be['avg_hours'];
                            if ($h < 24) echo round($h, 1) . ' h';
                            else echo round($h / 24, 1) . ' d';
                        ?>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p class="text-muted"><?= $lang('no_data') ?></p>
        <?php endif; ?>
    </div>

    <div class="chart-card">
        <h3><?= $lang('client_activity') ?></h3>
        <?php $ca = $clientActivity; $caTotal = $ca['active_30d'] + $ca['dormant'] + $ca['never']; ?>
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; text-align:center; padding:20px 0;">
            <div>
                <div style="font-size:32px; font-weight:700; color:var(--success);"><?= $ca['active_30d'] ?></div>
                <div style="font-size:12px; color:var(--gray-500);"><?= $lang('active_last_30d') ?></div>
                <?php if ($caTotal > 0): ?>
                <div style="margin-top:8px; height:6px; background:var(--gray-100); border-radius:3px; overflow:hidden;">
                    <div style="height:100%; width:<?= round($ca['active_30d'] / $caTotal * 100) ?>%; background:var(--success); border-radius:3px;"></div>
                </div>
                <?php endif; ?>
            </div>
            <div>
                <div style="font-size:32px; font-weight:700; color:var(--warning);"><?= $ca['dormant'] ?></div>
                <div style="font-size:12px; color:var(--gray-500);"><?= $lang('dormant_clients') ?></div>
                <?php if ($caTotal > 0): ?>
                <div style="margin-top:8px; height:6px; background:var(--gray-100); border-radius:3px; overflow:hidden;">
                    <div style="height:100%; width:<?= round($ca['dormant'] / $caTotal * 100) ?>%; background:var(--warning); border-radius:3px;"></div>
                </div>
                <?php endif; ?>
            </div>
            <div>
                <div style="font-size:32px; font-weight:700; color:var(--danger);"><?= $ca['never'] ?></div>
                <div style="font-size:12px; color:var(--gray-500);"><?= $lang('never_logged_in') ?></div>
                <?php if ($caTotal > 0): ?>
                <div style="margin-top:8px; height:6px; background:var(--gray-100); border-radius:3px; overflow:hidden;">
                    <div style="height:100%; width:<?= round($ca['never'] / $caTotal * 100) ?>%; background:var(--danger); border-radius:3px;"></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Row 4: KSeF Health + Rejection by client -->
<div class="charts-row" style="margin-bottom:24px;">
    <div class="chart-card">
        <h3><?= $lang('ksef_health') ?></h3>
        <?php if (!empty($ksefHealth)):
            $maxKsef = max(array_column($ksefHealth, 'total')) ?: 1;
            $totalKsefOps = array_sum(array_column($ksefHealth, 'total'));
            $totalKsefOk = array_sum(array_column($ksefHealth, 'success_count'));
            $ksefRate = $totalKsefOps > 0 ? round($totalKsefOk / $totalKsefOps * 100, 1) : 0;
            $avgDur = $totalKsefOps > 0 ? round(array_sum(array_column($ksefHealth, 'avg_duration_ms')) / count($ksefHealth)) : 0;
        ?>
        <div style="display:flex; gap:12px; margin-bottom:12px;">
            <div style="flex:1; text-align:center; padding:8px; background:var(--gray-50); border-radius:8px;">
                <div style="font-size:20px; font-weight:700; color:var(--success);"><?= $ksefRate ?>%</div>
                <div style="font-size:11px; color:var(--gray-500);"><?= $lang('success_rate') ?></div>
            </div>
            <div style="flex:1; text-align:center; padding:8px; background:var(--gray-50); border-radius:8px;">
                <div style="font-size:20px; font-weight:700;"><?= number_format($avgDur / 1000, 1) ?>s</div>
                <div style="font-size:11px; color:var(--gray-500);"><?= $lang('avg_duration') ?></div>
            </div>
            <div style="flex:1; text-align:center; padding:8px; background:var(--gray-50); border-radius:8px;">
                <div style="font-size:20px; font-weight:700;"><?= $totalKsefOps ?></div>
                <div style="font-size:11px; color:var(--gray-500);"><?= $lang('total_operations') ?></div>
            </div>
        </div>
        <div class="bar-chart" style="height:100px;">
            <?php foreach ($ksefHealth as $kh): ?>
            <div class="bar-group">
                <div class="bar-stack" style="height:<?= round($kh['total'] / $maxKsef * 100) ?>%">
                    <div class="bar-segment" style="flex:<?= (int)$kh['success_count'] ?>; background:var(--success);"></div>
                    <div class="bar-segment" style="flex:<?= (int)$kh['failed_count'] ?>; background:var(--danger);"></div>
                </div>
                <div class="bar-label" style="font-size:9px;"><?= substr($kh['month'], 5) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <p class="text-muted"><?= $lang('no_ksef_data') ?></p>
        <?php endif; ?>
    </div>

    <div class="chart-card">
        <h3><?= $lang('rejection_rate_by_client') ?></h3>
        <?php if (!empty($rejectionByClient)): ?>
        <table class="table table-compact" style="box-shadow:none;">
            <thead>
                <tr>
                    <th><?= $lang('client') ?></th>
                    <th class="text-center"><?= $lang('total') ?></th>
                    <th class="text-center"><?= $lang('rejected') ?></th>
                    <th class="text-right"><?= $lang('rejection_rate') ?></th>
                    <th style="width:120px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rejectionByClient as $rc):
                    $rPct = (float)$rc['rejection_pct'];
                ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($rc['company_name']) ?></strong>
                        <div style="font-size:11px; color:var(--gray-400);"><?= htmlspecialchars($rc['nip']) ?></div>
                    </td>
                    <td class="text-center"><?= $rc['total'] ?></td>
                    <td class="text-center"><span class="badge badge-error"><?= $rc['rejected'] ?></span></td>
                    <td class="text-right" style="font-weight:700; color:<?= $rPct > 20 ? 'var(--danger)' : ($rPct > 10 ? 'var(--warning)' : 'var(--success)') ?>;">
                        <?= number_format($rPct, 1) ?>%
                    </td>
                    <td>
                        <div style="height:6px; background:var(--gray-100); border-radius:3px; overflow:hidden; display:flex;">
                            <div style="width:<?= 100 - $rPct ?>%; background:var(--success);"></div>
                            <div style="width:<?= $rPct ?>%; background:var(--danger);"></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p class="text-muted"><?= $lang('no_data') ?></p>
        <?php endif; ?>
    </div>
</div>

<!-- Row 5: Employee workload (only for office owner) -->
<?php if (!empty($employeeWorkload)): ?>
<div class="charts-row" style="margin-bottom:24px;">
    <div class="chart-card" style="flex:1;">
        <h3><?= $lang('employee_workload') ?></h3>
        <table class="table table-compact" style="box-shadow:none;">
            <thead>
                <tr>
                    <th><?= $lang('employee') ?></th>
                    <th class="text-center"><?= $lang('assigned_clients') ?></th>
                    <th class="text-center"><?= $lang('batches_this_month') ?></th>
                    <th class="text-center"><?= $lang('invoices_this_month') ?></th>
                    <th style="width:180px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $maxInv = max(array_column($employeeWorkload, 'invoice_count')) ?: 1;
                foreach ($employeeWorkload as $ew):
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($ew['name']) ?></strong></td>
                    <td class="text-center"><span class="badge badge-info"><?= $ew['client_count'] ?></span></td>
                    <td class="text-center"><?= $ew['batch_count'] ?></td>
                    <td class="text-center"><strong><?= $ew['invoice_count'] ?></strong></td>
                    <td>
                        <div style="height:8px; background:var(--gray-100); border-radius:4px; overflow:hidden;">
                            <div style="height:100%; width:<?= round($ew['invoice_count'] / $maxInv * 100) ?>%; background:var(--primary); border-radius:4px;"></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Row 6: Verification progress per client (full width) -->
<?php if (!empty($verificationProgress)): ?>
<div class="charts-row">
    <div class="chart-card" style="flex:1;">
        <h3><?= $lang('verification_progress') ?></h3>
        <div class="table-responsive">
            <table class="table table-compact" style="box-shadow:none;">
                <thead>
                    <tr>
                        <th><?= $lang('client') ?></th>
                        <th class="text-center"><?= $lang('total') ?></th>
                        <th class="text-center"><?= $lang('accepted') ?></th>
                        <th class="text-center"><?= $lang('rejected') ?></th>
                        <th class="text-center"><?= $lang('pending') ?></th>
                        <th class="text-right"><?= $lang('gross_amount') ?></th>
                        <th style="width:160px;"><?= $lang('progress') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($verificationProgress as $vp):
                        if ((int)$vp['total_invoices'] === 0) continue;
                        $vpPct = round(((int)$vp['accepted_count'] + (int)$vp['rejected_count']) / (int)$vp['total_invoices'] * 100);
                    ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($vp['company_name']) ?></strong>
                            <div style="font-size:11px; color:var(--gray-400);"><?= htmlspecialchars($vp['nip']) ?></div>
                        </td>
                        <td class="text-center"><?= $vp['total_invoices'] ?></td>
                        <td class="text-center"><span class="badge badge-success"><?= $vp['accepted_count'] ?></span></td>
                        <td class="text-center">
                            <?php if ((int)$vp['rejected_count'] > 0): ?><span class="badge badge-error"><?= $vp['rejected_count'] ?></span>
                            <?php else: ?><span class="text-muted">0</span><?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ((int)$vp['pending_count'] > 0): ?><span class="badge badge-warning"><?= $vp['pending_count'] ?></span>
                            <?php else: ?><span class="text-muted">0</span><?php endif; ?>
                        </td>
                        <td class="text-right"><?= $fmt($vp['total_gross']) ?> PLN</td>
                        <td>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <div style="flex:1; height:8px; background:var(--gray-100); border-radius:4px; overflow:hidden;">
                                    <div style="height:100%; width:<?= $vpPct ?>%; background:<?= $vpPct === 100 ? 'var(--success)' : 'var(--primary)' ?>; border-radius:4px;"></div>
                                </div>
                                <span style="font-size:12px; font-weight:600; color:<?= $vpPct === 100 ? 'var(--success)' : 'var(--gray-500)' ?>;"><?= $vpPct ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
