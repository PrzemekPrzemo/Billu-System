<div class="section-header">
    <h1>Deklaracje</h1>
</div>

<?php
    $statusBadge = fn($s) => match($s) {
        'generated' => 'badge-success',
        'pending' => 'badge-warning',
        'error' => 'badge-error',
        'sent' => 'badge-info',
        default => 'badge-default',
    };
    $statusLabel = fn($s) => match($s) {
        'generated' => 'Wygenerowana',
        'pending' => 'Oczekujaca',
        'error' => 'Blad',
        'sent' => 'Wyslana',
        default => $s,
    };
    $typeLabel = fn($t) => match($t) {
        'PIT-11' => 'PIT-11',
        'PIT-4R' => 'PIT-4R',
        'ZUS-DRA' => 'ZUS DRA',
        'ZUS-RCA' => 'ZUS RCA',
        default => $t,
    };
?>

<?php if (empty($declarations)): ?>
<div class="empty-state">
    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:12px;opacity:0.4;"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
    <p>Brak deklaracji.</p>
</div>
<?php else: ?>
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Typ</th>
                <th>Rok</th>
                <th>Miesiac</th>
                <th>Pracownik</th>
                <th><?= $lang('status') ?></th>
                <th>Data wygenerowania</th>
                <th><?= $lang('actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($declarations as $decl): ?>
            <tr>
                <td><strong><?= $typeLabel($decl['type'] ?? '') ?></strong></td>
                <td><?= (int)($decl['year'] ?? 0) ?></td>
                <td><?= $decl['month'] ? sprintf('%02d', (int)$decl['month']) : '-' ?></td>
                <td><?= htmlspecialchars($decl['employee_name'] ?? 'Wszyscy') ?></td>
                <td>
                    <span class="badge <?= $statusBadge($decl['status'] ?? 'pending') ?>"><?= $statusLabel($decl['status'] ?? 'pending') ?></span>
                </td>
                <td class="text-muted"><?= htmlspecialchars($decl['generated_at'] ?? '-') ?></td>
                <td>
                    <div class="action-buttons">
                        <?php if (!empty($decl['pdf_path'])): ?>
                        <a href="/client/hr/declarations/<?= $decl['id'] ?>/pdf" class="btn btn-xs">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            PDF
                        </a>
                        <?php endif; ?>
                        <?php if (!empty($decl['xml_path'])): ?>
                        <a href="/client/hr/declarations/<?= $decl['id'] ?>/xml" class="btn btn-xs">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            XML
                        </a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
