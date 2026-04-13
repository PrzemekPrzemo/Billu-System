<h1><?= $lang('analytics') ?></h1>

<!-- Row 1: Logins + System Growth -->
<div class="charts-row" style="margin-bottom:24px;">
    <!-- Daily login activity (30 days) -->
    <div class="chart-card">
        <h3><?= $lang('logins_last_30_days') ?></h3>
        <?php if (!empty($dailyActivity)):
            $maxLogins = max(array_column($dailyActivity, 'login_count')) ?: 1;
        ?>
        <div class="bar-chart" style="height:160px;">
            <?php foreach ($dailyActivity as $d): ?>
            <div class="bar-group">
                <div class="bar-stack" style="height: <?= round(($d['login_count'] / $maxLogins) * 100) ?>%">
                    <div class="bar-segment" style="flex:1; background:var(--primary);" title="<?= $d['day'] ?>: <?= $d['login_count'] ?>"></div>
                </div>
                <div class="bar-label" style="font-size:9px;"><?= substr($d['day'], 8) ?></div>
                <div class="bar-value"><?= $d['login_count'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <p class="text-muted"><?= $lang('no_chart_data') ?></p>
        <?php endif; ?>
    </div>

    <!-- System Growth (12 months) -->
    <div class="chart-card">
        <h3><?= $lang('system_growth') ?></h3>
        <?php
        // Merge client and office growth into unified months
        $growthMonths = [];
        foreach ($clientGrowth as $cg) $growthMonths[$cg['month']]['clients'] = (int) $cg['count'];
        foreach ($officeGrowth as $og) $growthMonths[$og['month']]['offices'] = (int) $og['count'];
        ksort($growthMonths);
        $maxGrowth = 1;
        foreach ($growthMonths as $gm) $maxGrowth = max($maxGrowth, ($gm['clients'] ?? 0) + ($gm['offices'] ?? 0));
        ?>
        <?php if (!empty($growthMonths)): ?>
        <div class="bar-chart" style="height:160px;">
            <?php foreach ($growthMonths as $month => $gm): ?>
            <div class="bar-group">
                <div class="bar-stack" style="height: <?= round((($gm['clients'] ?? 0) + ($gm['offices'] ?? 0)) / $maxGrowth * 100) ?>%">
                    <div class="bar-segment" style="flex:<?= $gm['clients'] ?? 0 ?>; background:var(--primary);" title="<?= $lang('clients') ?>: <?= $gm['clients'] ?? 0 ?>"></div>
                    <div class="bar-segment" style="flex:<?= $gm['offices'] ?? 0 ?>; background:#7c3aed;" title="<?= $lang('offices') ?>: <?= $gm['offices'] ?? 0 ?>"></div>
                </div>
                <div class="bar-label" style="font-size:9px;"><?= substr($month, 5) ?>/<?= substr($month, 2, 2) ?></div>
                <div class="bar-value"><?= ($gm['clients'] ?? 0) + ($gm['offices'] ?? 0) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="chart-legend">
            <span class="legend-item"><span class="legend-dot" style="background:var(--primary)"></span> <?= $lang('new_clients') ?></span>
            <span class="legend-item"><span class="legend-dot" style="background:#7c3aed"></span> <?= $lang('new_offices') ?></span>
        </div>
        <?php else: ?>
            <p class="text-muted"><?= $lang('no_chart_data') ?></p>
        <?php endif; ?>
    </div>
</div>

<!-- Row 2: Purchase invoices + Sales+Purchase volume -->
<div class="charts-row" style="margin-bottom:24px;">
    <!-- Monthly invoice stats (existing) -->
    <div class="chart-card">
        <h3><?= $lang('invoices_last_6_months') ?></h3>
        <?php if (!empty($monthlyStats)):
            $maxVal = max(array_column($monthlyStats, 'total')) ?: 1;
        ?>
        <div class="bar-chart">
            <?php foreach ($monthlyStats as $m): ?>
            <div class="bar-group">
                <div class="bar-stack" style="height: <?= round(($m['total'] / $maxVal) * 100) ?>%">
                    <div class="bar-segment bar-accepted" style="flex: <?= (int)$m['accepted'] ?>" title="<?= $lang('accepted') ?>: <?= $m['accepted'] ?>"></div>
                    <div class="bar-segment bar-rejected" style="flex: <?= (int)$m['rejected'] ?>" title="<?= $lang('rejected') ?>: <?= $m['rejected'] ?>"></div>
                    <div class="bar-segment bar-pending" style="flex: <?= (int)$m['pending'] ?>" title="<?= $lang('pending') ?>: <?= $m['pending'] ?>"></div>
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

    <!-- Invoice Volume: Sales + Purchases combined -->
    <div class="chart-card">
        <h3><?= $lang('invoice_volume') ?></h3>
        <?php
        $volMonths = [];
        foreach ($purchaseMonthly as $pm) $volMonths[$pm['month']]['purchases'] = (int) $pm['count'];
        foreach ($salesMonthly as $sm) $volMonths[$sm['month']]['sales'] = (int) $sm['count'];
        ksort($volMonths);
        $maxVol = 1;
        foreach ($volMonths as $vm) $maxVol = max($maxVol, ($vm['purchases'] ?? 0) + ($vm['sales'] ?? 0));
        ?>
        <?php if (!empty($volMonths)): ?>
        <div class="bar-chart">
            <?php foreach ($volMonths as $month => $vm): ?>
            <div class="bar-group">
                <div class="bar-stack" style="height: <?= round((($vm['purchases'] ?? 0) + ($vm['sales'] ?? 0)) / $maxVol * 100) ?>%">
                    <div class="bar-segment" style="flex:<?= $vm['sales'] ?? 0 ?>; background:#2563eb;" title="<?= $lang('issued') ?>: <?= $vm['sales'] ?? 0 ?>"></div>
                    <div class="bar-segment" style="flex:<?= $vm['purchases'] ?? 0 ?>; background:#ea580c;" title="<?= $lang('purchases') ?>: <?= $vm['purchases'] ?? 0 ?>"></div>
                </div>
                <div class="bar-label"><?= substr($month, 5) ?>/<?= substr($month, 2, 2) ?></div>
                <div class="bar-value"><?= ($vm['purchases'] ?? 0) + ($vm['sales'] ?? 0) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="chart-legend">
            <span class="legend-item"><span class="legend-dot" style="background:#2563eb"></span> <?= $lang('issued_invoices') ?></span>
            <span class="legend-item"><span class="legend-dot" style="background:#ea580c"></span> <?= $lang('purchase_invoices') ?></span>
        </div>
        <?php else: ?>
            <p class="text-muted"><?= $lang('no_chart_data') ?></p>
        <?php endif; ?>
    </div>
</div>

<!-- Row 3: Office ranking (full width) -->
<div class="charts-row" style="margin-bottom:24px;">
    <div class="chart-card" style="flex:1;">
        <h3><?= $lang('office_ranking') ?></h3>
        <?php if (!empty($officeRanking)): ?>
        <table class="table" style="margin:0;">
            <thead>
                <tr>
                    <th>#</th>
                    <th><?= $lang('office_name') ?></th>
                    <th class="text-center"><?= $lang('total_clients') ?></th>
                    <th class="text-center"><?= $lang('monthly_invoices') ?></th>
                    <th class="text-right"><?= $lang('net_value') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($officeRanking as $i => $o): ?>
                <tr>
                    <td><strong><?= $i + 1 ?></strong></td>
                    <td><?= htmlspecialchars($o['name']) ?></td>
                    <td class="text-center"><?= (int)$o['client_count'] ?></td>
                    <td class="text-center">
                        <span class="badge badge-info"><?= (int)$o['monthly_invoices'] ?></span>
                    </td>
                    <td class="text-right" style="font-variant-numeric:tabular-nums;"><?= number_format((float)($o['monthly_net'] ?? 0), 2, ',', ' ') ?> PLN</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p class="text-muted"><?= $lang('no_data') ?></p>
        <?php endif; ?>
    </div>
</div>

<!-- Row 4: Client Activity + KSeF Health -->
<div class="charts-row" style="margin-bottom:24px;">
    <!-- Client Activity Breakdown -->
    <div class="chart-card">
        <h3><?= $lang('client_activity') ?></h3>
        <?php $ab = $activityBreakdown; $abTotal = $ab['active_30d'] + $ab['dormant'] + $ab['never']; ?>
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; text-align:center; padding:16px 0;">
            <div>
                <div style="font-size:32px; font-weight:700; color:var(--success);"><?= $ab['active_30d'] ?></div>
                <div style="font-size:12px; color:var(--gray-500);"><?= $lang('active_last_30d') ?></div>
                <?php if ($abTotal > 0): ?>
                <div style="margin-top:8px; height:6px; background:var(--gray-100); border-radius:3px; overflow:hidden;">
                    <div style="height:100%; width:<?= round($ab['active_30d'] / $abTotal * 100) ?>%; background:var(--success); border-radius:3px;"></div>
                </div>
                <?php endif; ?>
            </div>
            <div>
                <div style="font-size:32px; font-weight:700; color:var(--warning);"><?= $ab['dormant'] ?></div>
                <div style="font-size:12px; color:var(--gray-500);"><?= $lang('dormant_clients') ?></div>
                <?php if ($abTotal > 0): ?>
                <div style="margin-top:8px; height:6px; background:var(--gray-100); border-radius:3px; overflow:hidden;">
                    <div style="height:100%; width:<?= round($ab['dormant'] / $abTotal * 100) ?>%; background:var(--warning); border-radius:3px;"></div>
                </div>
                <?php endif; ?>
            </div>
            <div>
                <div style="font-size:32px; font-weight:700; color:var(--danger);"><?= $ab['never'] ?></div>
                <div style="font-size:12px; color:var(--gray-500);"><?= $lang('never_logged_in') ?></div>
                <?php if ($abTotal > 0): ?>
                <div style="margin-top:8px; height:6px; background:var(--gray-100); border-radius:3px; overflow:hidden;">
                    <div style="height:100%; width:<?= round($ab['never'] / $abTotal * 100) ?>%; background:var(--danger); border-radius:3px;"></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- KSeF Health -->
    <div class="chart-card">
        <h3><?= $lang('ksef_health') ?></h3>
        <?php if (!empty($ksefHealth)):
            $maxKsef = max(array_column($ksefHealth, 'total')) ?: 1;
            $totalKsefOps = array_sum(array_column($ksefHealth, 'total'));
            $totalKsefOk = array_sum(array_column($ksefHealth, 'success_count'));
            $ksefSuccessRate = $totalKsefOps > 0 ? round($totalKsefOk / $totalKsefOps * 100, 1) : 0;
            $avgDuration = $totalKsefOps > 0 ? round(array_sum(array_column($ksefHealth, 'avg_duration_ms')) / count($ksefHealth)) : 0;
        ?>
        <div style="display:flex; gap:16px; margin-bottom:12px;">
            <div style="flex:1; text-align:center; padding:8px; background:var(--gray-50); border-radius:8px;">
                <div style="font-size:20px; font-weight:700; color:var(--success);"><?= $ksefSuccessRate ?>%</div>
                <div style="font-size:11px; color:var(--gray-500);"><?= $lang('success_rate') ?></div>
            </div>
            <div style="flex:1; text-align:center; padding:8px; background:var(--gray-50); border-radius:8px;">
                <div style="font-size:20px; font-weight:700;"><?= number_format($avgDuration / 1000, 1) ?>s</div>
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
                <div class="bar-stack" style="height: <?= round($kh['total'] / $maxKsef * 100) ?>%">
                    <div class="bar-segment" style="flex:<?= (int)$kh['success_count'] ?>; background:var(--success);" title="OK: <?= $kh['success_count'] ?>"></div>
                    <div class="bar-segment" style="flex:<?= (int)$kh['failed_count'] ?>; background:var(--danger);" title="<?= $lang('errors') ?>: <?= $kh['failed_count'] ?>"></div>
                </div>
                <div class="bar-label" style="font-size:9px;"><?= substr($kh['month'], 5) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <p class="text-muted"><?= $lang('no_chart_data') ?></p>
        <?php endif; ?>
    </div>
</div>

<!-- Row 5: KSeF Usage (existing) + Feature Adoption -->
<div class="charts-row" style="margin-bottom:24px;">
    <!-- KSeF usage donut -->
    <div class="chart-card">
        <h3><?= $lang('ksef_usage') ?></h3>
        <div class="stats-grid" style="margin-bottom:16px;">
            <div class="stat-card">
                <div class="stat-icon" style="background:#dbeafe; color:#2563eb;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= $ksefActiveCount ?></div>
                    <div class="stat-label"><?= $lang('ksef_active_clients') ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#f3e8ff; color:#7c3aed;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= $totalClients ?></div>
                    <div class="stat-label"><?= $lang('total_clients') ?></div>
                </div>
            </div>
        </div>
        <?php
            $ksefPct = $totalClients > 0 ? round($ksefActiveCount / $totalClients * 100) : 0;
            $nonKsefPct = 100 - $ksefPct;
        ?>
        <div class="donut-chart-container">
            <svg viewBox="0 0 36 36" class="donut-chart">
                <circle cx="18" cy="18" r="15.9155" fill="none" stroke="var(--success)" stroke-width="3"
                    stroke-dasharray="<?= $ksefPct ?> <?= 100 - $ksefPct ?>"
                    stroke-dashoffset="25" />
                <circle cx="18" cy="18" r="15.9155" fill="none" stroke="var(--gray-200)" stroke-width="3"
                    stroke-dasharray="<?= $nonKsefPct ?> <?= 100 - $nonKsefPct ?>"
                    stroke-dashoffset="<?= 25 - $ksefPct ?>" />
                <text x="18" y="18" text-anchor="middle" dominant-baseline="central" class="donut-text"><?= $ksefPct ?>%</text>
            </svg>
            <div class="donut-legend">
                <div class="donut-legend-item">
                    <span class="legend-dot" style="background:var(--success)"></span>
                    <?= $lang('ksef_active') ?> <strong><?= $ksefActiveCount ?></strong> (<?= $ksefPct ?>%)
                </div>
                <div class="donut-legend-item">
                    <span class="legend-dot" style="background:var(--gray-200)"></span>
                    <?= $lang('without_ksef') ?> <strong><?= $totalClients - $ksefActiveCount ?></strong> (<?= $nonKsefPct ?>%)
                </div>
            </div>
        </div>
    </div>

    <!-- Feature Adoption -->
    <div class="chart-card">
        <h3><?= $lang('feature_adoption') ?></h3>
        <?php
        $fa = $featureAdoption;
        $features = [
            ['label' => $lang('invoice_issuing'), 'count' => $fa['sales'], 'color' => '#2563eb'],
            ['label' => 'KSeF', 'count' => $fa['ksef_import'], 'color' => '#16a34a'],
            ['label' => $lang('bank_export_feature'), 'count' => $fa['bank_export'], 'color' => '#ea580c'],
        ];
        ?>
        <div style="display:flex; flex-direction:column; gap:16px; padding:16px 0;">
            <?php foreach ($features as $feat):
                $pct = $fa['total'] > 0 ? round($feat['count'] / $fa['total'] * 100) : 0;
            ?>
            <div>
                <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                    <span style="font-size:13px; font-weight:600;"><?= $feat['label'] ?></span>
                    <span style="font-size:13px; color:var(--gray-500);"><?= $feat['count'] ?> / <?= $fa['total'] ?> (<?= $pct ?>%)</span>
                </div>
                <div style="height:10px; background:var(--gray-100); border-radius:5px; overflow:hidden;">
                    <div style="height:100%; width:<?= $pct ?>%; background:<?= $feat['color'] ?>; border-radius:5px; transition:width 0.3s;"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Row 6: Rejection rate per office (full width) -->
<?php if (!empty($rejectionByOffice)): ?>
<div class="charts-row">
    <div class="chart-card" style="flex:1;">
        <h3><?= $lang('rejection_rate_by_office') ?></h3>
        <table class="table" style="margin:0;">
            <thead>
                <tr>
                    <th><?= $lang('office_name') ?></th>
                    <th class="text-center"><?= $lang('total_invoices') ?></th>
                    <th class="text-center"><?= $lang('accepted') ?></th>
                    <th class="text-center"><?= $lang('rejected') ?></th>
                    <th class="text-right"><?= $lang('rejection_rate') ?></th>
                    <th style="width:200px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rejectionByOffice as $ro):
                    $accPct = $ro['total'] > 0 ? round($ro['accepted'] / $ro['total'] * 100) : 0;
                    $rejPct = (float) $ro['rejection_pct'];
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($ro['name']) ?></strong></td>
                    <td class="text-center"><?= $ro['total'] ?></td>
                    <td class="text-center"><span class="badge badge-success"><?= $ro['accepted'] ?></span></td>
                    <td class="text-center"><span class="badge badge-error"><?= $ro['rejected'] ?></span></td>
                    <td class="text-right" style="font-weight:700; color:<?= $rejPct > 20 ? 'var(--danger)' : ($rejPct > 10 ? 'var(--warning)' : 'var(--success)') ?>;">
                        <?= number_format($rejPct, 1) ?>%
                    </td>
                    <td>
                        <div style="height:8px; background:var(--gray-100); border-radius:4px; overflow:hidden; display:flex;">
                            <div style="width:<?= $accPct ?>%; background:var(--success);"></div>
                            <div style="width:<?= $rejPct ?>%; background:var(--danger);"></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
