<?php
$monthNames = [1=>'Styczeń',2=>'Luty',3=>'Marzec',4=>'Kwiecień',5=>'Maj',6=>'Czerwiec',
               7=>'Lipiec',8=>'Sierpień',9=>'Wrzesień',10=>'Październik',11=>'Listopad',12=>'Grudzień'];
$prevMonth = $month == 1 ? 12 : $month - 1;
$prevYear  = $month == 1 ? $year - 1 : $year;
$nextMonth = $month == 12 ? 1 : $month + 1;
$nextYear  = $month == 12 ? $year + 1 : $year;

$dot = fn(string $color) => '<span style="display:inline-block;width:14px;height:14px;border-radius:50%;background:' . match($color) {
    'green' => '#16a34a', 'yellow' => '#f59e0b', 'red' => '#dc2626', default => '#d1d5db'
} . ';"></span>';
?>

<div class="section-header">
    <div>
        <h1>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
            Matryca compliance — <?= $monthNames[$month] ?> <?= $year ?>
        </h1>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="btn btn-secondary">&#8592;</a>
        <span style="font-weight:600;min-width:140px;text-align:center;"><?= $monthNames[$month] ?> <?= $year ?></span>
        <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="btn btn-secondary">&#8594;</a>
        <a href="/office/hr/dashboard?month=<?= $month ?>&year=<?= $year ?>" class="btn btn-secondary"><?= $lang('back') ?></a>
    </div>
</div>

<!-- Deadline info -->
<div class="alert" style="background:#f0f9ff;border-left:4px solid #2563eb;margin-bottom:20px;padding:12px 16px;font-size:13px;">
    <strong>Terminy za <?= $monthNames[$month] ?> <?= $year ?>:</strong>
    ZUS DRA — <strong><?= $zusDeadline ?></strong> &bull;
    PIT-4 zaliczka — <strong><?= $pitDeadline ?></strong>
</div>

<!-- Legend -->
<div style="display:flex;gap:20px;margin-bottom:16px;font-size:13px;">
    <span><?= $dot('green') ?> Gotowe / Wysłane</span>
    <span><?= $dot('yellow') ?> W trakcie</span>
    <span><?= $dot('red') ?> Brak / Przeterminowane</span>
    <span><?= $dot('gray') ?> Nie dotyczy</span>
</div>

<?php if (empty($matrix)): ?>
<div class="card">
    <div class="card-body" style="text-align:center;padding:48px;color:var(--text-muted);">
        Brak firm z włączonym HR.
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body" style="padding:0;">
        <table class="table" style="margin:0;font-size:13px;">
            <thead>
                <tr>
                    <th>Firma</th>
                    <th style="text-align:center;">Pracownicy</th>
                    <th style="text-align:center;">Lista płac</th>
                    <th style="text-align:center;">ZUS DRA<br><small style="font-weight:400;color:var(--text-muted);">do <?= $zusDeadline ?></small></th>
                    <th style="text-align:center;">PIT-4<br><small style="font-weight:400;color:var(--text-muted);">do <?= $pitDeadline ?></small></th>
                    <th>Akcje</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($matrix as $c): ?>
                <tr>
                    <td>
                        <a href="/office/hr/<?= $c['id'] ?>/employees" style="font-weight:600;">
                            <?= htmlspecialchars($c['company_name']) ?>
                        </a>
                    </td>
                    <td style="text-align:center;"><?= $c['employee_count'] ?></td>
                    <td style="text-align:center;"><?= $dot($c['payroll_compliance']) ?></td>
                    <td style="text-align:center;"><?= $dot($c['zus_compliance']) ?></td>
                    <td style="text-align:center;"><?= $dot($c['pit_compliance']) ?></td>
                    <td>
                        <div style="display:flex;gap:4px;">
                            <?php if ($c['payroll_compliance'] === 'red'): ?>
                            <a href="/office/hr/<?= $c['id'] ?>/payroll" class="btn btn-xs btn-danger">Utwórz payroll</a>
                            <?php elseif ($c['zus_compliance'] === 'red'): ?>
                            <a href="/office/hr/<?= $c['id'] ?>/zus" class="btn btn-xs btn-warning">Generuj ZUS</a>
                            <?php else: ?>
                            <a href="/office/hr/<?= $c['id'] ?>/payroll" class="btn btn-xs btn-secondary">Szczegóły</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>