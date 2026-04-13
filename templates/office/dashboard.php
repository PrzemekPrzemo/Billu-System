<h1><?= $lang('welcome') ?>, <?= htmlspecialchars(\App\Core\Session::get('office_name')) ?></h1>

<?php if (!empty($exchangeRates)): ?>
<div class="form-card" style="padding:10px 16px; margin-bottom:16px;">
    <div style="display:flex; gap:20px; align-items:center; flex-wrap:wrap; font-size:14px;">
        <span style="font-weight:600; color:var(--gray-500); display:inline-flex; align-items:center; gap:6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            <?= $lang('nbp_exchange_rates') ?>:
        </span>
        <?php foreach ($exchangeRates as $cur => $info): ?>
        <span>
            <strong><?= $cur ?></strong> = <?= number_format($info['rate'], 4, ',', '') ?> PLN
            <span style="color:var(--gray-400); font-size:12px;">(<?= htmlspecialchars($info['date']) ?>)</span>
        </span>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background:#dbeafe; color:#2563eb;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= count($clients) ?></div>
            <div class="stat-label"><?= $lang('total_clients') ?></div>
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
            <div class="stat-value"><?= $totalPending ?></div>
            <div class="stat-label"><?= $lang('pending_invoices') ?></div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px;">
    <a href="/office/import" class="btn btn-primary" style="display:inline-flex; align-items:center; gap:6px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        <?= $lang('import_invoices') ?>
    </a>
    <a href="/office/messages" class="btn" style="display:inline-flex; align-items:center; gap:6px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
        <?= $lang('new_message') ?>
    </a>
    <a href="/office/tasks" class="btn" style="display:inline-flex; align-items:center; gap:6px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
        <?= $lang('tasks') ?>
    </a>
    <a href="/office/erp-export" class="btn" style="display:inline-flex; align-items:center; gap:6px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        <?= $lang('erp_export') ?>
    </a>
    <a href="/office/duplicates" class="btn" style="display:inline-flex; align-items:center; gap:6px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <?= $lang('duplicates_report') ?>
    </a>
    <a href="/office/tax-calendar" class="btn" style="display:inline-flex; align-items:center; gap:6px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <?= $lang('tax_calendar') ?>
    </a>
</div>

<!-- Messages & Tasks widgets -->
<?php
    $offDashMsgCount = \App\Core\Auth::isEmployee()
        ? \App\Models\Message::countAllUnreadForEmployee(\App\Core\Session::get('employee_id'))
        : \App\Models\Message::countAllUnreadForOffice(\App\Core\Session::get('office_id'));
    $offDashTaskOverdue = \App\Core\Auth::isEmployee()
        ? \App\Models\ClientTask::countOverdueByEmployee(\App\Core\Session::get('employee_id'))
        : \App\Models\ClientTask::countOverdueByOffice(\App\Core\Session::get('office_id'));
?>
<?php if ($offDashMsgCount > 0 || $offDashTaskOverdue > 0): ?>
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:16px; margin-bottom:20px;">
    <?php if ($offDashMsgCount > 0): ?>
    <a href="/office/messages" class="card" style="text-decoration:none; border-left:4px solid var(--primary);">
        <div class="card-body" style="display:flex; align-items:center; gap:12px;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
            <div>
                <strong><?= $offDashMsgCount ?> nieprzeczytanych wiadomosci od klientow</strong>
            </div>
        </div>
    </a>
    <?php endif; ?>
    <?php if ($offDashTaskOverdue > 0): ?>
    <a href="/office/tasks" class="card" style="text-decoration:none; border-left:4px solid #dc2626;">
        <div class="card-body" style="display:flex; align-items:center; gap:12px;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
            <div>
                <strong><?= $offDashTaskOverdue ?> przeterminowanych zadan</strong>
            </div>
        </div>
    </a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (!empty($pinnedNotes)): ?>
<div class="card" style="margin-bottom:20px; border-left:4px solid #eab308;">
    <div class="card-header" style="display:flex; align-items:center; gap:8px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="#eab308" stroke="#eab308" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
        <?= $lang('pinned_notes') ?>
    </div>
    <div class="card-body" style="padding:0;">
        <?php foreach ($pinnedNotes as $pn): ?>
        <div style="padding:12px 16px; border-bottom:1px solid var(--gray-100); display:flex; justify-content:space-between; align-items:center;">
            <div>
                <strong><?= htmlspecialchars($pn['company_name']) ?></strong>
                <span style="color:var(--gray-500); margin-left:8px; font-size:13px;"><?= htmlspecialchars(mb_strimwidth($pn['note'], 0, 120, '...')) ?></span>
            </div>
            <a href="/office/clients/<?= $pn['client_id'] ?>/notes" class="btn btn-xs"><?= $lang('details') ?></a>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($supportContact['name']) || !empty($supportContact['email']) || !empty($officeBranding)): ?>
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:16px; margin-bottom:24px;">

    <?php if (!empty($officeBranding)): ?>
    <div class="contact-card">
        <div class="contact-card-header">
            <div class="contact-card-icon" style="background:#dbeafe; color:#2563eb;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            </div>
            <h3><?= $lang('your_office') ?></h3>
        </div>
        <?php if (!empty($officeBranding['logo_path'])): ?>
            <div style="margin-bottom:8px;">
                <img src="<?= htmlspecialchars($officeBranding['logo_path']) ?>" alt="" style="max-height:40px; max-width:160px; object-fit:contain;">
            </div>
        <?php endif; ?>
        <div class="contact-card-name"><?= htmlspecialchars($officeBranding['name']) ?></div>
        <?php if (!empty($officeBranding['nip'])): ?>
        <div class="contact-card-detail" style="margin-bottom:4px;">
            <span style="color:var(--gray-500); font-size:12px;">NIP: <?= htmlspecialchars($officeBranding['nip']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($officeBranding['email'])): ?>
        <div class="contact-card-detail">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            <a href="mailto:<?= htmlspecialchars($officeBranding['email']) ?>"><?= htmlspecialchars($officeBranding['email']) ?></a>
        </div>
        <?php endif; ?>
        <?php if (!empty($officeBranding['phone'])): ?>
        <div class="contact-card-detail">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
            <a href="tel:<?= htmlspecialchars($officeBranding['phone']) ?>"><?= htmlspecialchars($officeBranding['phone']) ?></a>
        </div>
        <?php endif; ?>
        <?php if (!empty($officeBranding['address'])): ?>
        <div class="contact-card-detail" style="margin-top:4px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <span><?= htmlspecialchars($officeBranding['address']) ?></span>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($supportContact['name']) || !empty($supportContact['email'])): ?>
    <div class="contact-card">
        <div class="contact-card-header">
            <div class="contact-card-icon" style="background:#dcfce7; color:#16a34a;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <h3><?= $lang('tech_support') ?></h3>
        </div>
        <?php if (!empty($supportContact['name'])): ?>
        <div class="contact-card-name"><?= htmlspecialchars($supportContact['name']) ?></div>
        <?php endif; ?>
        <?php if (!empty($supportContact['email'])): ?>
        <div class="contact-card-detail">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            <a href="mailto:<?= htmlspecialchars($supportContact['email']) ?>"><?= htmlspecialchars($supportContact['email']) ?></a>
        </div>
        <?php endif; ?>
        <?php if (!empty($supportContact['phone'])): ?>
        <div class="contact-card-detail">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
            <a href="tel:<?= htmlspecialchars($supportContact['phone']) ?>"><?= htmlspecialchars($supportContact['phone']) ?></a>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>
<?php endif; ?>

<!-- Charts row -->
<?php if (!empty($monthlyStats) || ($statusTotals['total'] ?? 0) > 0): ?>
<div class="charts-row" style="margin-bottom:24px;">
    <!-- Monthly bar chart -->
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

    <!-- Status donut chart -->
    <div class="chart-card">
        <h3><?= $lang('invoice_status_distribution') ?></h3>
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
                    <?= $lang('accepted') ?> <strong><?= $accepted ?></strong> (<?= $pAccepted ?>%)
                </div>
                <div class="donut-legend-item">
                    <span class="legend-dot" style="background:var(--danger)"></span>
                    <?= $lang('rejected') ?> <strong><?= $rejected ?></strong> (<?= $pRejected ?>%)
                </div>
                <div class="donut-legend-item">
                    <span class="legend-dot" style="background:var(--warning)"></span>
                    <?= $lang('pending') ?> <strong><?= $pending ?></strong> (<?= $pPending ?>%)
                </div>
            </div>
        </div>
        <?php else: ?>
            <p class="text-muted"><?= $lang('no_chart_data') ?></p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($overdueBatches)): ?>
<div class="section" style="margin-top:20px;">
    <h2 style="color:#dc2626;"><?= $lang('overdue_batches') ?></h2>
    <div class="batch-cards">
        <?php foreach ($overdueBatches as $b): ?>
        <div class="batch-card" style="border-left:4px solid #dc2626;background:#fef2f2;">
            <div class="batch-card-header">
                <strong><?= htmlspecialchars($b['company_name']) ?></strong>
                <span class="badge badge-error"><?= sprintf('%02d/%04d', $b['period_month'], $b['period_year']) ?></span>
            </div>
            <div class="batch-card-body">
                <p><?= $lang('pending') ?>: <strong><?= $b['pending_count'] ?></strong></p>
                <p><?= $lang('deadline') ?>: <strong style="color:#dc2626;"><?= $b['verification_deadline'] ?></strong></p>
            </div>
            <div class="batch-card-footer">
                <a href="/office/batches/<?= $b['id'] ?>" class="btn btn-sm btn-danger"><?= $lang('details') ?></a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Top pending clients + Tax summary -->
<?php if (!empty($topPending) || !empty($taxSummary)): ?>
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(340px, 1fr)); gap:20px; margin-top:20px; margin-bottom:20px;">
    <?php if (!empty($topPending)): ?>
    <div class="card">
        <div class="card-header">
            <span style="display:flex; align-items:center; gap:8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <?= $lang('top_pending_clients') ?>
            </span>
        </div>
        <div class="card-body" style="padding:0;">
            <table class="table" style="margin:0;">
                <tbody>
                    <?php foreach ($topPending as $tp): ?>
                    <tr>
                        <td style="font-weight:600;"><?= htmlspecialchars($tp['company_name']) ?></td>
                        <td style="text-align:right;">
                            <span class="badge badge-warning"><?= (int) $tp['pending_count'] ?> <?= $lang('pending') ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($taxSummary)): ?>
    <div class="card">
        <div class="card-header">
            <span style="display:flex; align-items:center; gap:8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                <?= $lang('tax_summary') ?>
            </span>
        </div>
        <div class="card-body" style="padding:0;">
            <table class="table" style="margin:0;">
                <thead>
                    <tr>
                        <th><?= $lang('tax_type') ?></th>
                        <th style="text-align:right;"><?= $lang('total_amount') ?></th>
                        <th style="text-align:right;"><?= $lang('clients') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $taxTotal = 0; foreach ($taxSummary as $ts): $taxTotal += (float) $ts['total_amount']; ?>
                    <tr>
                        <td><span class="badge <?= match($ts['tax_type']) { 'VAT' => 'badge-info', 'PIT' => 'badge-success', 'CIT' => 'badge-warning', default => '' } ?>"><?= htmlspecialchars($ts['tax_type']) ?></span></td>
                        <td style="text-align:right; font-weight:600; font-variant-numeric:tabular-nums;"><?= number_format((float) $ts['total_amount'], 2, ',', ' ') ?> PLN</td>
                        <td style="text-align:right;"><?= (int) $ts['client_count'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="font-weight:700;">
                        <td><?= $lang('total') ?></td>
                        <td style="text-align:right; font-variant-numeric:tabular-nums;"><?= number_format($taxTotal, 2, ',', ' ') ?> PLN</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (!empty($clientProgress)): ?>
<div class="section" style="margin-top:20px;">
    <h2><?= $lang('verification_progress') ?></h2>
    <table class="table">
        <thead>
            <tr>
                <th><?= $lang('client') ?></th>
                <th>NIP</th>
                <th><?= $lang('total_invoices') ?></th>
                <th><?= $lang('accepted') ?></th>
                <th><?= $lang('rejected') ?></th>
                <th><?= $lang('pending') ?></th>
                <th><?= $lang('progress') ?></th>
                <th><?= $lang('gross_amount') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($clientProgress as $cp): ?>
            <?php
                $total = (int) ($cp['total_invoices'] ?? 0);
                $accepted = (int) ($cp['accepted_count'] ?? 0);
                $rejected = (int) ($cp['rejected_count'] ?? 0);
                $pending = (int) ($cp['pending_count'] ?? 0);
                $verified = $accepted + $rejected;
                $pct = $total > 0 ? round(($verified / $total) * 100) : 0;
                $isOverdue = $pending > 0 && $pct < 100;
            ?>
            <tr<?= $isOverdue ? ' style="background:#fef2f2;"' : '' ?>>
                <td><strong><?= htmlspecialchars($cp['company_name']) ?></strong></td>
                <td><?= htmlspecialchars($cp['nip']) ?></td>
                <td class="text-center"><?= $total ?></td>
                <td class="text-center"><span class="badge badge-success"><?= $accepted ?></span></td>
                <td class="text-center"><span class="badge badge-error"><?= $rejected ?></span></td>
                <td class="text-center">
                    <?php if ($pending > 0): ?>
                        <span class="badge badge-warning"><?= $pending ?></span>
                    <?php else: ?>
                        <span class="text-muted">0</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="progress-bar-container" style="background:#e5e7eb;border-radius:6px;height:20px;position:relative;min-width:120px;">
                        <div style="background:<?= $pct === 100 ? '#22c55e' : '#3b82f6' ?>;height:100%;border-radius:6px;width:<?= $pct ?>%;transition:width 0.3s;"></div>
                        <span style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:0.75em;font-weight:600;color:#333;"><?= $pct ?>%</span>
                    </div>
                </td>
                <td class="text-right"><?= number_format((float) ($cp['total_gross'] ?? 0), 2, ',', ' ') ?> PLN</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div class="section" style="margin-top:20px;">
    <h2><?= $lang('active_batches') ?></h2>
    <?php $activeBatches = array_filter($batches, fn($b) => !$b['is_finalized']); ?>
    <?php if (empty($activeBatches)): ?>
        <p class="text-muted"><?= $lang('no_active_batches') ?></p>
    <?php else: ?>
        <div class="batch-cards">
            <?php foreach ($activeBatches as $b): ?>
            <div class="batch-card">
                <div class="batch-card-header">
                    <strong><?= htmlspecialchars($b['company_name']) ?></strong>
                    <span class="badge badge-warning"><?= sprintf('%02d/%04d', $b['period_month'], $b['period_year']) ?></span>
                </div>
                <div class="batch-card-body">
                    <p><?= $lang('pending') ?>: <strong><?= $b['pending_count'] ?></strong> / <?= $b['invoice_count'] ?></p>
                    <p><?= $lang('deadline') ?>: <?= $b['verification_deadline'] ?></p>
                </div>
                <div class="batch-card-footer">
                    <a href="/office/batches/<?= $b['id'] ?>" class="btn btn-sm"><?= $lang('details') ?></a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
