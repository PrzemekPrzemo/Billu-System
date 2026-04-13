<h1><?= $lang('admin_dashboard') ?></h1>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background:#dbeafe; color:#2563eb;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= $clientCount ?></div>
            <div class="stat-label"><?= $lang('total_clients') ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#f3e8ff; color:#7c3aed;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= $officeCount ?></div>
            <div class="stat-label"><?= $lang('total_offices') ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fef3c7; color:#d97706;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= $invoicesThisMonth ?></div>
            <div class="stat-label"><?= $lang('invoices_this_month') ?></div>
        </div>
    </div>
    <div class="stat-card stat-warning">
        <div class="stat-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= count(array_filter($batches, fn($b) => !$b['is_finalized'])) ?></div>
            <div class="stat-label"><?= $lang('active_batches') ?></div>
        </div>
    </div>
    <div class="stat-card stat-error">
        <div class="stat-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= array_sum(array_column($batches, 'pending_count')) ?></div>
            <div class="stat-label"><?= $lang('pending_invoices') ?></div>
        </div>
    </div>
</div>

<!-- Charts row -->
<div class="charts-row">
    <!-- Monthly bar chart -->
    <div class="chart-card">
        <h3><?= $lang('monthly_overview') ?></h3>
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

    <!-- Status donut chart -->
    <div class="chart-card">
        <h3><?= $lang('invoice_status_chart') ?></h3>
        <?php
        $total = (int)($statusTotals['total'] ?? 0);
        $accepted = (int)($statusTotals['accepted'] ?? 0);
        $rejected = (int)($statusTotals['rejected'] ?? 0);
        $pending = (int)($statusTotals['pending'] ?? 0);
        if ($total > 0):
            $pAccepted = round($accepted / $total * 100);
            $pRejected = round($rejected / $total * 100);
            $pPending = 100 - $pAccepted - $pRejected;
        ?>
        <div class="donut-chart-container">
            <svg viewBox="0 0 36 36" class="donut-chart">
                <circle cx="18" cy="18" r="15.9155" fill="none" stroke="var(--success)" stroke-width="3"
                    stroke-dasharray="<?= $pAccepted ?> <?= 100 - $pAccepted ?>"
                    stroke-dashoffset="25" />
                <circle cx="18" cy="18" r="15.9155" fill="none" stroke="var(--danger)" stroke-width="3"
                    stroke-dasharray="<?= $pRejected ?> <?= 100 - $pRejected ?>"
                    stroke-dashoffset="<?= 25 - $pAccepted ?>" />
                <circle cx="18" cy="18" r="15.9155" fill="none" stroke="var(--warning)" stroke-width="3"
                    stroke-dasharray="<?= $pPending ?> <?= 100 - $pPending ?>"
                    stroke-dashoffset="<?= 25 - $pAccepted - $pRejected ?>" />
                <text x="18" y="18" text-anchor="middle" dominant-baseline="central" class="donut-text"><?= $total ?></text>
            </svg>
            <div class="donut-legend">
                <div class="donut-legend-item">
                    <span class="legend-dot" style="background:var(--success)"></span>
                    <?= $lang('accepted') ?>: <strong><?= $accepted ?></strong> (<?= $pAccepted ?>%)
                </div>
                <div class="donut-legend-item">
                    <span class="legend-dot" style="background:var(--danger)"></span>
                    <?= $lang('rejected') ?>: <strong><?= $rejected ?></strong> (<?= $pRejected ?>%)
                </div>
                <div class="donut-legend-item">
                    <span class="legend-dot" style="background:var(--warning)"></span>
                    <?= $lang('pending') ?>: <strong><?= $pending ?></strong> (<?= $pPending ?>%)
                </div>
            </div>
        </div>
        <?php else: ?>
            <p class="text-muted"><?= $lang('no_chart_data') ?></p>
        <?php endif; ?>
    </div>
</div>

<div class="section">
    <h2><?= $lang('active_batches') ?></h2>
    <?php $active = array_filter($batches, fn($b) => !$b['is_finalized']); ?>
    <?php if (empty($active)): ?>
        <p class="text-muted"><?= $lang('no_active_batches') ?></p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th><?= $lang('client') ?></th>
                    <th>NIP</th>
                    <th><?= $lang('period') ?></th>
                    <th><?= $lang('invoices') ?></th>
                    <th><?= $lang('pending') ?></th>
                    <th><?= $lang('deadline') ?></th>
                    <th><?= $lang('actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($active as $b): ?>
                <tr>
                    <td><?= htmlspecialchars($b['company_name']) ?></td>
                    <td><?= htmlspecialchars($b['nip']) ?></td>
                    <td><?= sprintf('%02d/%04d', $b['period_month'], $b['period_year']) ?></td>
                    <td><?= $b['invoice_count'] ?></td>
                    <td>
                        <?php if ($b['pending_count'] > 0): ?>
                            <span class="badge badge-warning"><?= $b['pending_count'] ?></span>
                        <?php else: ?>
                            <span class="badge badge-success">0</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $b['verification_deadline'] ?></td>
                    <td><a href="/admin/batches/<?= $b['id'] ?>" class="btn btn-sm"><?= $lang('details') ?></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="section">
    <h2><?= $lang('recent_activity') ?></h2>
    <table class="table table-compact">
        <thead>
            <tr>
                <th><?= $lang('date') ?></th>
                <th><?= $lang('type') ?></th>
                <th><?= $lang('action') ?></th>
                <th><?= $lang('details') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($recentLogs as $log): ?>
            <tr>
                <td><?= $log['created_at'] ?></td>
                <td><span class="badge badge-<?= $log['user_type'] === 'admin' ? 'info' : 'default' ?>"><?= $log['user_type'] ?></span></td>
                <td><?= htmlspecialchars($log['action']) ?></td>
                <td class="text-muted"><?= htmlspecialchars(mb_substr($log['details'] ?? '', 0, 80)) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
