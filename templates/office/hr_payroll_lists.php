<div class="section-header">
    <h1>Listy plac - <?= htmlspecialchars($client['company_name']) ?></h1>
    <a href="/office/hr" class="btn btn-secondary"><?= $lang('back') ?></a>
</div>

<?php $flash = \App\Core\Session::getFlash('success'); ?>
<?php if ($flash): ?>
    <div class="alert alert-success"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

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

<!-- Generate payroll -->
<div class="form-card" style="padding:16px; margin-bottom:20px;">
    <h3 style="margin-bottom:12px;">Generuj liste plac</h3>
    <form method="POST" action="/office/hr/<?= $client['id'] ?>/payroll/generate" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div class="form-group" style="margin:0;">
            <label class="form-label">Rok</label>
            <select name="year" class="form-input">
                <?php for ($y = (int)date('Y') + 1; $y >= (int)date('Y') - 2; $y--): ?>
                <option value="<?= $y ?>" <?= $y == (int)date('Y') ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label class="form-label">Miesiac</label>
            <select name="month" class="form-input">
                <?php
                    $months = ['Styczen','Luty','Marzec','Kwiecien','Maj','Czerwiec','Lipiec','Sierpien','Wrzesien','Pazdziernik','Listopad','Grudzien'];
                    foreach ($months as $mi => $mName):
                ?>
                <option value="<?= $mi + 1 ?>" <?= ($mi + 1) == (int)date('n') ? 'selected' : '' ?>><?= sprintf('%02d', $mi + 1) ?> - <?= $mName ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary" onclick="return confirm('Czy na pewno chcesz wygenerowac liste plac?')">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0018 9h-1.26A8 8 0 103 16.3"/></svg>
            Generuj
        </button>
    </form>
</div>

<?php if (empty($lists)): ?>
<div class="empty-state">
    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:12px;opacity:0.4;"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
    <p>Brak list plac dla tego klienta.</p>
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
                        <a href="/office/hr/<?= $client['id'] ?>/payroll/<?= $list['id'] ?>" class="btn btn-xs"><?= $lang('details') ?></a>
                        <a href="/office/hr/<?= $client['id'] ?>/payroll/<?= $list['id'] ?>/pdf" class="btn btn-xs">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            PDF
                        </a>
                        <?php if (($list['status'] ?? '') !== 'approved' && ($list['status'] ?? '') !== 'exported'): ?>
                        <form method="POST" action="/office/hr/<?= $client['id'] ?>/payroll/<?= $list['id'] ?>/approve" class="inline-form">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <button type="submit" class="btn btn-xs btn-primary" onclick="return confirm('Zatwierdzic liste plac?')">Zatwierdz</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
