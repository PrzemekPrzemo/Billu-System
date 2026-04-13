<div class="section-header">
    <h1>Listy plac</h1>
</div>

<?php
    $fmt = fn($v) => number_format((float)$v, 2, ',', ' ');
    $statusBadge = fn($s) => match($s) {
        'draft' => 'badge-default',
        'calculated' => 'badge-info',
        'approved' => 'badge-success',
        'exported' => 'badge-warning',
        default => 'badge-default',
    };
    $statusLabel = fn($s) => match($s) {
        'draft' => 'Szkic',
        'calculated' => 'Obliczona',
        'approved' => 'Zatwierdzona',
        'exported' => 'Wyeksportowana',
        default => $s,
    };
?>

<?php if (empty($lists)): ?>
<div class="empty-state">
    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:12px;opacity:0.4;"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
    <p>Brak list plac.</p>
</div>
<?php else: ?>
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Okres</th>
                <th>Tytul</th>
                <th><?= $lang('status') ?></th>
                <th class="text-right">Brutto</th>
                <th class="text-right">Netto</th>
                <th class="text-right">Koszt pracodawcy</th>
                <th><?= $lang('actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lists as $list): ?>
            <tr>
                <td><strong><?= sprintf('%02d/%04d', $list['month'], $list['year']) ?></strong></td>
                <td><?= htmlspecialchars($list['title'] ?? 'Lista plac') ?></td>
                <td>
                    <span class="badge <?= $statusBadge($list['status'] ?? 'draft') ?>"><?= $statusLabel($list['status'] ?? 'draft') ?></span>
                </td>
                <td class="text-right" style="font-variant-numeric:tabular-nums;"><?= $fmt($list['total_gross'] ?? 0) ?> PLN</td>
                <td class="text-right" style="font-variant-numeric:tabular-nums;"><?= $fmt($list['total_net'] ?? 0) ?> PLN</td>
                <td class="text-right" style="font-variant-numeric:tabular-nums;"><?= $fmt($list['total_employer_cost'] ?? 0) ?> PLN</td>
                <td>
                    <div class="action-buttons">
                        <a href="/client/hr/payroll/<?= $list['id'] ?>" class="btn btn-xs"><?= $lang('details') ?></a>
                        <a href="/client/hr/payroll/<?= $list['id'] ?>/pdf" class="btn btn-xs">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            PDF
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
