<div class="section-header">
    <h1>Umowy - <?= htmlspecialchars($client['company_name']) ?></h1>
    <div style="display:flex; gap:8px;">
        <a href="/office/hr" class="btn btn-secondary"><?= $lang('back') ?></a>
        <a href="/office/hr/<?= $client['id'] ?>/contracts/create" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Dodaj umowe
        </a>
    </div>
</div>

<?php $flash = \App\Core\Session::getFlash('success'); ?>
<?php if ($flash): ?>
    <div class="alert alert-success"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<?php
    $contractTypeLabels = [
        'umowa_o_prace' => 'Umowa o prace',
        'umowa_zlecenie' => 'Umowa zlecenie',
        'umowa_o_dzielo' => 'Umowa o dzielo',
    ];
    $statusBadge = fn($s) => match($s) {
        'active' => 'badge-success',
        'draft' => 'badge-default',
        'terminated' => 'badge-error',
        'expired' => 'badge-warning',
        default => 'badge-default',
    };
    $statusLabel = fn($s) => match($s) {
        'active' => 'Aktywna',
        'draft' => 'Szkic',
        'terminated' => 'Rozwiazana',
        'expired' => 'Wygasla',
        default => $s,
    };
    $fmt = fn($v) => number_format((float)$v, 2, ',', ' ');
?>

<?php if (empty($contracts)): ?>
<div class="empty-state">
    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:12px;opacity:0.4;"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
    <p>Brak umow dla tego klienta.</p>
</div>
<?php else: ?>
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Lp</th>
                <th>Pracownik</th>
                <th>Typ umowy</th>
                <th>Brutto</th>
                <th>Etat</th>
                <th>Data od</th>
                <th>Data do</th>
                <th><?= $lang('status') ?></th>
                <th><?= $lang('actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($contracts as $i => $ct): ?>
            <tr>
                <td class="text-muted"><?= $i + 1 ?></td>
                <td><strong><?= htmlspecialchars($ct['employee_name'] ?? '-') ?></strong></td>
                <td><?= htmlspecialchars($contractTypeLabels[$ct['contract_type']] ?? $ct['contract_type']) ?></td>
                <td class="text-right" style="font-variant-numeric:tabular-nums;"><?= $fmt($ct['gross_salary'] ?? 0) ?> PLN</td>
                <td class="text-center"><?= htmlspecialchars($ct['work_time_fraction'] ?? '-') ?></td>
                <td><?= htmlspecialchars($ct['start_date'] ?? '-') ?></td>
                <td><?= htmlspecialchars($ct['end_date'] ?? 'bezterminowa') ?></td>
                <td>
                    <span class="badge <?= $statusBadge($ct['status'] ?? 'draft') ?>"><?= $statusLabel($ct['status'] ?? 'draft') ?></span>
                </td>
                <td>
                    <div class="action-buttons">
                        <a href="/office/hr/<?= $client['id'] ?>/contracts/<?= $ct['id'] ?>/edit" class="btn btn-xs"><?= $lang('edit') ?></a>
                        <form method="POST" action="/office/hr/<?= $client['id'] ?>/contracts/<?= $ct['id'] ?>/delete" class="inline-form">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <button type="submit" class="btn btn-xs btn-danger" onclick="return confirm('Czy na pewno chcesz usunac te umowe?')"><?= $lang('delete') ?></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
