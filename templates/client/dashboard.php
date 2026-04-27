<?php if (isset($passwordDaysLeft) && $passwordDaysLeft <= 14): ?>
<div class="alert <?= $passwordDaysLeft === 0 ? 'alert-error' : 'alert-warning' ?>" style="margin-bottom:16px;">
    <?php if ($passwordDaysLeft === 0): ?>
        <?= $lang('password_expires_today') ?>
    <?php else: ?>
        <?= sprintf($lang('password_expires_in'), $passwordDaysLeft) ?>
    <?php endif; ?>
    <?= $lang('password_change_recommended') ?>
    <a href="/client/security" style="margin-left:8px;font-weight:600;"><?= $lang('change_password') ?> &rarr;</a>
</div>
<?php endif; ?>

<?php if (!empty($ksefEnabled) && !empty($ksefConnectionStatus) && ($ksefConnectionStatus['status'] ?? '') === 'failed'): ?>
<div class="alert alert-error" style="margin-bottom:16px; display:flex; align-items:flex-start; gap:12px;">
    <div style="flex-shrink:0; margin-top:2px;">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    </div>
    <div>
        <strong><?= $lang('ksef_connection_failed') ?></strong><br>
        <span style="font-size:13px;">
            <?= htmlspecialchars($ksefConnectionStatus['error'] ?? 'Serwer KSeF jest niedostępny') ?>
            <?php if (!empty($ksefConnectionStatus['checked_at'])): ?>
                <br><span style="color:var(--gray-500);">Sprawdzono: <?= htmlspecialchars($ksefConnectionStatus['checked_at']) ?></span>
            <?php endif; ?>
        </span>
        <div style="margin-top:8px; font-size:13px;">
            Wystawianie faktur jest nadal możliwe. Faktury zostaną automatycznie wysłane do KSeF po przywróceniu połączenia (w ciągu 24h).
        </div>
    </div>
</div>
<?php endif; ?>

<h1><?= $lang('welcome') ?>, <?= htmlspecialchars(\App\Core\Session::get('client_name', '')) ?></h1>

<!-- Contact cards (Twoje biuro, opiekun, wsparcie) -->
<?php if (!empty($officeBranding) || !empty($assignedEmployees) || !empty($supportContact['name']) || !empty($supportContact['email'])): ?>
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:16px; margin-bottom:20px;">

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
    </div>
    <?php endif; ?>

    <?php if (!empty($assignedEmployees)): ?>
    <div class="contact-card">
        <div class="contact-card-header">
            <div class="contact-card-icon" style="background:#e0e7ff; color:#4338ca;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
            <h3><?= $lang('assigned_employee') ?></h3>
        </div>
        <?php foreach ($assignedEmployees as $empIdx => $emp): ?>
            <?php if ($empIdx > 0): ?>
                <hr style="border:none; border-top:1px solid var(--gray-200); margin:10px 0;">
            <?php endif; ?>
            <div class="contact-card-name"><?= htmlspecialchars($emp['name']) ?></div>
            <?php if (!empty($emp['position'])): ?>
                <div class="contact-card-detail text-muted" style="margin-bottom:4px; font-size:13px;"><?= htmlspecialchars($emp['position']) ?></div>
            <?php endif; ?>
            <?php if (!empty($emp['email'])): ?>
            <div class="contact-card-detail">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                <a href="mailto:<?= htmlspecialchars($emp['email']) ?>"><?= htmlspecialchars($emp['email']) ?></a>
            </div>
            <?php endif; ?>
            <?php if (!empty($emp['phone'])): ?>
            <div class="contact-card-detail">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
                <a href="tel:<?= htmlspecialchars($emp['phone']) ?>"><?= htmlspecialchars($emp['phone']) ?></a>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
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

<h2 style="margin-bottom:14px; font-size:16px; color:var(--gray-500); font-weight:600;">Faktury kosztowe - <?= sprintf('%02d/%04d', date('n'), date('Y')) ?></h2>
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= $stats['total'] ?? 0 ?></div>
            <div class="stat-label"><?= $lang('all_invoices') ?></div>
        </div>
    </div>
    <?php if (!empty($latestActiveBatchId)): ?>
    <a href="/client/invoices/<?= $latestActiveBatchId ?>" style="text-decoration:none; color:inherit;">
        <div class="stat-card stat-warning" style="cursor:pointer;">
            <div class="stat-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?= $stats['pending'] ?? 0 ?></div>
                <div class="stat-label"><?= $lang('pending') ?></div>
                <div style="font-size:11px; color:var(--primary); margin-top:2px;"><?= $lang('go_to_verification') ?> &rarr;</div>
            </div>
        </div>
    </a>
    <?php else: ?>
    <div class="stat-card stat-warning">
        <div class="stat-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= $stats['pending'] ?? 0 ?></div>
            <div class="stat-label"><?= $lang('pending') ?></div>
        </div>
    </div>
    <?php endif; ?>
    <div class="stat-card stat-success">
        <div class="stat-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= $stats['accepted'] ?? 0 ?></div>
            <div class="stat-label"><?= $lang('accepted') ?></div>
        </div>
    </div>
    <?php if (($stats['whitelist_failed'] ?? 0) > 0): ?>
    <div class="stat-card" style="border-left:4px solid #dc2626;">
        <div class="stat-icon" style="background:#fef2f2; color:#dc2626;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </div>
        <div class="stat-content">
            <div class="stat-value" style="color:#dc2626;"><?= $stats['whitelist_failed'] ?></div>
            <div class="stat-label"><?= $lang('whitelist_failed_label') ?></div>
        </div>
    </div>
    <?php endif; ?>
    <div class="stat-card stat-error">
        <div class="stat-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= $stats['rejected'] ?? 0 ?></div>
            <div class="stat-label"><?= $lang('rejected') ?></div>
        </div>
    </div>
</div>

<!-- Communication panel (green box) -->
<?php
    $dashClientId = \App\Core\Session::get('client_id');
    $dashMsgCount = \App\Models\Message::countUnreadByClient($dashClientId);
    $dashTaskCounts = \App\Models\ClientTask::countByClientAndStatus($dashClientId);
    $dashTasksOpen = ($dashTaskCounts['open'] ?? 0) + ($dashTaskCounts['in_progress'] ?? 0);
    $dashTasksNotStarted = $dashTaskCounts['open'] ?? 0;
?>

<!-- Quick actions panel -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:24px;">
    <?php if (!empty($ksefEnabled)): ?>
    <div class="form-card quick-action-card" id="ksef-import-card" style="padding:20px; cursor:pointer; display:flex; align-items:center; gap:14px; transition:transform 0.15s, box-shadow 0.15s;" onclick="quickImportKsef()" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.12)';" onmouseout="this.style.transform='';this.style.boxShadow='';">
        <div style="width:44px; height:44px; border-radius:10px; background:var(--blue-100, #dbeafe); color:var(--primary); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        </div>
        <div>
            <div style="font-weight:600; font-size:14px;" id="ksef-import-label"><?= $lang('download_from_ksef') ?></div>
            <div style="font-size:12px; color:var(--gray-500);" id="ksef-import-sublabel">Import za <?= sprintf('%02d/%04d', date('n'), date('Y')) ?></div>
        </div>
    </div>
    <?php endif; ?>
    <a href="<?= !empty($latestActiveBatchId) ? '/client/invoices/' . $latestActiveBatchId : '/client' ?>" class="form-card" style="padding:20px; text-decoration:none; color:inherit; display:flex; align-items:center; gap:14px; transition:transform 0.15s, box-shadow 0.15s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.12)';" onmouseout="this.style.transform='';this.style.boxShadow='';">
        <div style="width:44px; height:44px; border-radius:10px; background:var(--yellow-100, #fef3c7); color:var(--yellow-700, #a16207); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/><path d="M9 14l2 2 4-4"/></svg>
        </div>
        <div>
            <div style="font-weight:600; font-size:14px;"><?= $lang('invoices_to_verify') ?></div>
            <div style="font-size:12px; color:var(--gray-500);"><?= ($stats['pending'] ?? 0) ?> oczekujacych</div>
        </div>
    </a>
    <?php if (!empty($ksefEnabled)): ?>
    <div class="form-card quick-action-card" id="ksef-send-card" style="padding:20px; cursor:pointer; display:flex; align-items:center; gap:14px; transition:transform 0.15s, box-shadow 0.15s;" onclick="quickSendKsef()" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.12)';" onmouseout="this.style.transform='';this.style.boxShadow='';">
        <div style="width:44px; height:44px; border-radius:10px; background:var(--orange-100, #ffedd5); color:var(--orange-700, #c2410c); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        </div>
        <div>
            <div style="font-weight:600; font-size:14px;" id="ksef-send-label"><?= $lang('send_to_ksef') ?></div>
            <div style="font-size:12px; color:var(--gray-500);" id="ksef-send-sublabel"><?= ($salesCounts['issued_not_sent'] ?? 0) ?> do wysłania</div>
        </div>
    </div>
    <?php endif; ?>
    <a href="/client/sales/create" class="form-card" style="padding:20px; text-decoration:none; color:inherit; display:flex; align-items:center; gap:14px; transition:transform 0.15s, box-shadow 0.15s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.12)';" onmouseout="this.style.transform='';this.style.boxShadow='';">
        <div style="width:44px; height:44px; border-radius:10px; background:var(--green-100, #dcfce7); color:var(--green-700, #15803d); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
        </div>
        <div>
            <div style="font-weight:600; font-size:14px;"><?= $lang('issue_new_invoice') ?></div>
            <div style="font-size:12px; color:var(--gray-500);"><?= $lang('sales_invoice') ?></div>
        </div>
    </a>
    <a href="/client/contractors/create" class="form-card" style="padding:20px; text-decoration:none; color:inherit; display:flex; align-items:center; gap:14px; transition:transform 0.15s, box-shadow 0.15s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.12)';" onmouseout="this.style.transform='';this.style.boxShadow='';">
        <div style="width:44px; height:44px; border-radius:10px; background:var(--purple-100, #f3e8ff); color:var(--purple-700, #7c3aed); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
        </div>
        <div>
            <div style="font-weight:600; font-size:14px;"><?= $lang('add_contractor') ?></div>
            <div style="font-size:12px; color:var(--gray-500);"><?= $lang('new_business_partner') ?></div>
        </div>
    </a>
</div>

<!-- Tax payments info (previous month) -->
<?php
$monthNames = ['', 'Styczen', 'Luty', 'Marzec', 'Kwiecien', 'Maj', 'Czerwiec', 'Lipiec', 'Sierpien', 'Wrzesien', 'Pazdziernik', 'Listopad', 'Grudzien'];
$hasTaxData = !empty($taxPrevMonth);
?>
<div style="border:2px solid var(--info, #3b82f6); border-radius:12px; padding:20px; margin-bottom:24px;">
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:16px;">
        <div style="width:36px; height:36px; border-radius:8px; background:#dbeafe; color:#2563eb; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
        </div>
        <h3 style="margin:0; font-size:16px; font-weight:600; color:var(--info, #2563eb);">
            <?= $lang('tax_payments') ?> &mdash; <?= $monthNames[$taxPrevMonthNum] ?? '' ?> <?= $taxPrevYear ?>
        </h3>
    </div>

    <?php if ($hasTaxData): ?>
    <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:12px; margin-bottom:16px;" class="responsive-grid-3">
        <?php foreach (['VAT', 'PIT', 'CIT'] as $taxType):
            $entry = $taxPrevMonth[$taxType] ?? null;
            $amount = $entry ? (float) $entry['amount'] : 0;
            $status = $entry['status'] ?? 'do_zaplaty';
            $statusLabel = $status === 'do_przeniesienia' ? ($lang('tax_do_przeniesienia')) : ($lang('tax_do_zaplaty'));
            $statusColor = $status === 'do_przeniesienia' ? '#16a34a' : '#dc2626';
            $statusBg = $status === 'do_przeniesienia' ? '#dcfce7' : '#fee2e2';
        ?>
        <div style="text-align:center; padding:16px; background:var(--gray-50, #f9fafb); border-radius:8px;">
            <div style="font-size:13px; font-weight:600; color:var(--gray-500); margin-bottom:6px;"><?= $taxType ?></div>
            <?php if ($entry): ?>
                <div style="font-size:22px; font-weight:700; color:var(--text-primary);"><?= number_format($amount, 2, ',', ' ') ?> PLN</div>
                <div style="margin-top:6px;">
                    <span style="display:inline-block; padding:2px 10px; border-radius:10px; font-size:11px; font-weight:600; background:<?= $statusBg ?>; color:<?= $statusColor ?>;">
                        <?= htmlspecialchars($statusLabel) ?>
                    </span>
                </div>
            <?php else: ?>
                <div style="font-size:14px; color:var(--gray-400);">&mdash;</div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="text-align:center; padding:16px; color:var(--gray-400);">
        <?= $lang('tax_no_data') ?>
    </div>
    <?php endif; ?>

    <div style="display:flex; gap:12px; flex-wrap:wrap;">
        <a href="/client/tax-payments" class="btn btn-sm" style="display:inline-flex; align-items:center; gap:6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
            <?= $lang('tax_payments') ?>
        </a>
    </div>
</div>

<!-- Communication panel (green box) -->
<div style="border:2px solid var(--success, #22c55e); border-radius:12px; padding:20px; margin-bottom:24px;">
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:16px;">
        <div style="width:36px; height:36px; border-radius:8px; background:#dcfce7; color:#16a34a; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
        </div>
        <h3 style="margin:0; font-size:16px; font-weight:600; color:var(--success, #16a34a);">Komunikacja</h3>
    </div>
    <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:12px; margin-bottom:16px;" class="responsive-grid-3">
        <div style="text-align:center; padding:12px; background:var(--gray-50, #f9fafb); border-radius:8px;">
            <div style="font-size:28px; font-weight:700; color:var(--text-primary);"><?= $dashTasksOpen ?></div>
            <div style="font-size:12px; color:var(--gray-500); margin-top:2px;"><?= $lang('open_tasks') ?></div>
        </div>
        <div style="text-align:center; padding:12px; background:var(--gray-50, #f9fafb); border-radius:8px;">
            <div style="font-size:28px; font-weight:700; color:var(--text-primary);"><?= $dashTasksNotStarted ?></div>
            <div style="font-size:12px; color:var(--gray-500); margin-top:2px;">Nierozpoczete</div>
        </div>
        <div style="text-align:center; padding:12px; background:var(--gray-50, #f9fafb); border-radius:8px;">
            <div style="font-size:28px; font-weight:700; color:var(--text-primary);"><?= $dashMsgCount ?></div>
            <div style="font-size:12px; color:var(--gray-500); margin-top:2px;"><?= $lang('unread_messages') ?></div>
        </div>
    </div>
    <div style="display:flex; gap:12px; flex-wrap:wrap;">
        <a href="/client/tasks" class="btn btn-sm" style="display:inline-flex; align-items:center; gap:6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
            Zadania
        </a>
        <a href="/client/messages" class="btn btn-sm btn-primary" style="display:inline-flex; align-items:center; gap:6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
            Wiadomosci<?php if ($dashMsgCount > 0): ?> <span style="background:rgba(255,255,255,0.3); padding:1px 6px; border-radius:10px; font-size:11px;"><?= $dashMsgCount ?></span><?php endif; ?>
        </a>
    </div>
</div>

<!-- KSeF action result toast -->
<div id="ksef-action-result" style="display:none; margin-bottom:16px;"></div>

<?php
$pendingJobId = \App\Core\Session::get('ksef_import_job_id');
if ($pendingJobId) { \App\Core\Session::remove('ksef_import_job_id'); }
?>
<script>
var csrfToken = <?= json_encode($csrf) ?>;

function esc(s) { var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

function showActionResult(html) {
    var el = document.getElementById('ksef-action-result');
    el.innerHTML = html;
    el.style.display = 'block';
}

// ── KSeF Import (quick action) ────────────────────
var importBusy = false;
function quickImportKsef() {
    if (importBusy) return;
    importBusy = true;
    var card = document.getElementById('ksef-import-card');
    var label = document.getElementById('ksef-import-label');
    var sub = document.getElementById('ksef-import-sublabel');
    card.style.opacity = '0.7'; card.style.pointerEvents = 'none';
    label.textContent = 'Importowanie...';
    sub.innerHTML = '<span class="ksef-spinner" style="vertical-align:-2px;margin-right:4px;"></span>Łączenie z KSeF...';

    var fd = new FormData();
    fd.append('csrf_token', csrfToken);
    fd.append('month', '<?= date('n') ?>');
    fd.append('year', '<?= date('Y') ?>');

    fetch('/client/import-ksef', { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) {
                resetImportCard();
                showActionResult('<div class="alert alert-error">' + esc(data.error) + '</div>');
                return;
            }
            if (data.job_id) {
                pollImport(data.job_id);
            } else {
                sub.textContent = 'Uruchamianie importu...';
                setTimeout(function() { window.location.reload(); }, 1500);
            }
        })
        .catch(function(err) {
            resetImportCard();
            showActionResult('<div class="alert alert-error">Błąd połączenia: ' + esc(err.message) + '</div>');
        });
}

function pollImport(jobId) {
    var sub = document.getElementById('ksef-import-sublabel');
    var pollStart = Date.now();
    function poll() {
        if (Date.now() - pollStart > 180000) {
            resetImportCard();
            showActionResult('<div class="alert alert-error">Import przekroczył limit czasu.</div>');
            return;
        }
        fetch('/client/import-ksef-status?job_id=' + encodeURIComponent(jobId))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.status === 'running' || data.status === 'queued') {
                    sub.innerHTML = '<span class="ksef-spinner" style="vertical-align:-2px;margin-right:4px;"></span>' + esc(data.message || 'Importowanie...');
                    setTimeout(poll, 2000);
                } else {
                    var r = data.result || {};
                    var cls = data.status === 'error' ? 'alert-error' : (r.success > 0 ? 'alert-success' : 'alert-info');
                    showActionResult('<div class="alert ' + cls + '">' + esc(data.message) + '</div>');
                    if (r.success > 0) { setTimeout(function() { window.location.reload(); }, 2000); }
                    else { resetImportCard(); }
                }
            })
            .catch(function() {
                resetImportCard();
                showActionResult('<div class="alert alert-error">Błąd połączenia</div>');
            });
    }
    poll();
}

function resetImportCard() {
    importBusy = false;
    var card = document.getElementById('ksef-import-card');
    if (card) { card.style.opacity = '1'; card.style.pointerEvents = ''; }
    var label = document.getElementById('ksef-import-label');
    var sub = document.getElementById('ksef-import-sublabel');
    if (label) label.textContent = 'Pobierz faktury z KSeF';
    if (sub) sub.textContent = 'Import za <?= sprintf('%02d/%04d', date('n'), date('Y')) ?>';
}

// Resume polling if there was a pending import job
(function() {
    var pendingJobId = <?= json_encode($pendingJobId ?? '') ?>;
    if (pendingJobId) {
        importBusy = true;
        var card = document.getElementById('ksef-import-card');
        if (card) { card.style.opacity = '0.7'; card.style.pointerEvents = 'none'; }
        var label = document.getElementById('ksef-import-label');
        if (label) label.textContent = 'Importowanie...';
        pollImport(pendingJobId);
    }
})();

// ── KSeF Send (quick action) ────────────────────
var sendBusy = false;
function quickSendKsef() {
    if (sendBusy) return;
    var count = parseInt(document.getElementById('ksef-send-sublabel').textContent) || 0;
    if (count === 0) { alert('Brak faktur do wysłania.'); return; }
    if (!confirm('Wysłać ' + count + ' faktur(y) do KSeF?')) return;

    sendBusy = true;
    var card = document.getElementById('ksef-send-card');
    var label = document.getElementById('ksef-send-label');
    var sub = document.getElementById('ksef-send-sublabel');
    card.style.opacity = '0.7'; card.style.pointerEvents = 'none';
    label.textContent = 'Wysyłanie...';
    sub.innerHTML = '<span class="ksef-spinner" style="vertical-align:-2px;margin-right:4px;"></span>Łączenie z KSeF...';

    var fd = new FormData();
    fd.append('csrf_token', csrfToken);

    fetch('/client/sales/bulk-send-ksef', { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) {
                resetSendCard();
                showActionResult('<div class="alert alert-error">' + esc(data.error) + '</div>');
                return;
            }
            if (data.batch_job_id) {
                pollSend(data.batch_job_id, data.invoice_count || 0);
            } else {
                resetSendCard();
                showActionResult('<div class="alert alert-info">' + esc(data.message || 'Brak faktur do wysłania') + '</div>');
            }
        })
        .catch(function(err) {
            resetSendCard();
            showActionResult('<div class="alert alert-error">Błąd połączenia: ' + esc(err.message) + '</div>');
        });
}

function pollSend(jobId, totalCount) {
    var sub = document.getElementById('ksef-send-sublabel');
    var pollStart = Date.now();
    function poll() {
        if (Date.now() - pollStart > 360000) {
            resetSendCard();
            showActionResult('<div class="alert alert-error">Wysyłka przekroczyła limit czasu.</div>');
            return;
        }
        fetch('/client/ksef-send-status?job_id=' + encodeURIComponent(jobId))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var job = data[jobId] || data;
                if (job.status === 'running' || job.status === 'queued') {
                    var done = (job.success_count || 0) + (job.error_count || 0);
                    sub.innerHTML = '<span class="ksef-spinner" style="vertical-align:-2px;margin-right:4px;"></span>Wysłano ' + done + ' / ' + totalCount;
                    setTimeout(poll, 2000);
                } else {
                    var ok = job.success_count || 0;
                    var err = job.error_count || 0;
                    var msg = 'Wysłano: ' + ok + ' z ' + (ok + err);
                    if (err > 0) msg += ' (błędy: ' + err + ')';
                    var cls = err > 0 ? 'alert-warning' : 'alert-success';
                    showActionResult('<div class="alert ' + cls + '">' + esc(msg) + '</div>');
                    if (ok > 0) { setTimeout(function() { window.location.reload(); }, 2000); }
                    else { resetSendCard(); }
                }
            })
            .catch(function() {
                resetSendCard();
                showActionResult('<div class="alert alert-error">Błąd połączenia</div>');
            });
    }
    poll();
}

function resetSendCard() {
    sendBusy = false;
    var card = document.getElementById('ksef-send-card');
    if (card) { card.style.opacity = '1'; card.style.pointerEvents = ''; }
    var label = document.getElementById('ksef-send-label');
    var sub = document.getElementById('ksef-send-sublabel');
    if (label) label.textContent = 'Wyślij do KSeF';
    if (sub) sub.textContent = '<?= ($salesCounts['issued_not_sent'] ?? 0) ?> do wysłania';
}
</script>


<?php
$finalized = array_filter($batches, fn($b) => $b['is_finalized']);
if (!empty($finalized)):
?>
<div class="section">
    <h2><?= $lang('history') ?></h2>
    <table class="table">
        <thead>
            <tr>
                <th><?= $lang('period') ?></th>
                <th><?= $lang('finalized_at') ?></th>
                <th><?= $lang('actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($finalized as $b): ?>
            <tr>
                <td><?= sprintf('%02d/%04d', $b['period_month'], $b['period_year']) ?></td>
                <td><?= $b['finalized_at'] ?></td>
                <td>
                    <a href="/client/invoices/<?= $b['id'] ?>" class="btn btn-sm"><?= $lang('view') ?></a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
