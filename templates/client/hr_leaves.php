<div class="section-header">
    <h1>Urlopy</h1>
    <a href="/client/hr/leaves/request" class="btn btn-primary">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Zgloszenie urlopu
    </a>
</div>

<?php $flash = \App\Core\Session::getFlash('success'); ?>
<?php if ($flash): ?>
    <div class="alert alert-success"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<?php
    $statusBadge = fn($s) => match($s) {
        'approved' => 'badge-success',
        'pending' => 'badge-warning',
        'rejected' => 'badge-error',
        'cancelled' => 'badge-default',
        default => 'badge-default',
    };
    $statusLabel = fn($s) => match($s) {
        'approved' => 'Zatwierdzony',
        'pending' => 'Oczekujacy',
        'rejected' => 'Odrzucony',
        'cancelled' => 'Anulowany',
        default => $s,
    };
    $leaveTypeLabel = fn($t) => match($t) {
        'wypoczynkowy' => 'Urlop wypoczynkowy',
        'chorobowy' => 'Zwolnienie chorobowe',
        'okolicznosciowy' => 'Urlop okolicznosciowy',
        'bezplatny' => 'Urlop bezplatny',
        'macierzynski' => 'Urlop macierzynski',
        'rodzicielski' => 'Urlop rodzicielski',
        'ojcowski' => 'Urlop ojcowski',
        'opieka' => 'Opieka nad dzieckiem (art. 188)',
        'na_zadanie' => 'Urlop na zadanie',
        default => $t,
    };
?>

<?php if (empty($leaves)): ?>
<div class="empty-state">
    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:12px;opacity:0.4;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
    <p>Brak urlopow.</p>
</div>
<?php else: ?>
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Pracownik</th>
                <th>Typ urlopu</th>
                <th>Od</th>
                <th>Do</th>
                <th class="text-center">Dni robocze</th>
                <th><?= $lang('status') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($leaves as $leave): ?>
            <tr>
                <td><strong><?= htmlspecialchars($leave['employee_name'] ?? '-') ?></strong></td>
                <td><?= $leaveTypeLabel($leave['leave_type'] ?? '') ?></td>
                <td><?= htmlspecialchars($leave['start_date'] ?? '-') ?></td>
                <td><?= htmlspecialchars($leave['end_date'] ?? '-') ?></td>
                <td class="text-center"><?= (int)($leave['business_days'] ?? 0) ?></td>
                <td>
                    <span class="badge <?= $statusBadge($leave['status'] ?? 'pending') ?>"><?= $statusLabel($leave['status'] ?? 'pending') ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
