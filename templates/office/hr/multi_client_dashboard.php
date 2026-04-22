<?php
$monthNames = [1=>'Styczeń',2=>'Luty',3=>'Marzec',4=>'Kwiecień',5=>'Maj',6=>'Czerwiec',
               7=>'Lipiec',8=>'Sierpień',9=>'Wrzesień',10=>'Październik',11=>'Listopad',12=>'Grudzień'];
$n2 = fn($v) => number_format((float)$v, 2, ',', ' ');
$prevMonth = $month == 1 ? 12 : $month - 1;
$prevYear  = $month == 1 ? $year - 1 : $year;
$nextMonth = $month == 12 ? 1 : $month + 1;
$nextYear  = $month == 12 ? $year + 1 : $year;

$statusLabels = ['draft' => 'Szkic', 'calculated' => 'Obliczona', 'approved' => 'Zatwierdzona', 'locked' => 'Zablokowana'];
$statusBadge  = ['draft' => 'badge-secondary', 'calculated' => 'badge-info', 'approved' => 'badge-success', 'locked' => 'badge-dark'];
$zusLabels    = ['draft' => 'Szkic', 'generated' => 'Wygenerowana', 'submitted' => 'Wysłana'];
$zusBadge     = ['draft' => 'badge-secondary', 'generated' => 'badge-info', 'submitted' => 'badge-success'];
?>

<div class="section-header">
    <div>
        <h1>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
            <?= $lang('hr_dashboard_title') ?> — Wszystkie firmy
        </h1>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="btn btn-secondary">&#8592;</a>
        <span style="font-weight:600;min-width:140px;text-align:center;"><?= $monthNames[$month] ?> <?= $year ?></span>
        <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="btn btn-secondary">&#8594;</a>
        <a href="/office/hr/compliance?month=<?= $month ?>&year=<?= $year ?>" class="btn btn-primary">Matryca compliance</a>
    </div>
</div>

<!-- KPI Cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:20px;">
    <div class="card" style="padding:16px 20px;">
        <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Firmy z HR</div>
        <div style="font-size:28px;font-weight:700;color:var(--primary);"><?= $clientsWithHr ?></div>
    </div>
    <div class="card" style="padding:16px 20px;">
        <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Łącznie pracowników</div>
        <div style="font-size:28px;font-weight:700;"><?= $totalEmployees ?></div>
    </div>
    <div class="card" style="padding:16px 20px;">
        <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Oczekujące urlopy</div>
        <div style="font-size:28px;font-weight:700;color:<?= $totalPending > 0 ? 'var(--warning)' : 'var(--success)' ?>;"><?= $totalPending ?></div>
    </div>
    <div class="card" style="padding:16px 20px;">
        <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Brak payroll za <?= $monthNames[$month] ?></div>
        <div style="font-size:28px;font-weight:700;color:<?= $missingPayroll > 0 ? 'var(--danger)' : 'var(--success)' ?>;"><?= $missingPayroll ?></div>
    </div>
</div>

<!-- Client Table -->
<?php if (empty($clients)): ?>
<div class="card">
    <div class="card-body" style="text-align:center;padding:48px;color:var(--text-muted);">
        <p>Brak firm z włączonym modułem HR. Włącz HR dla klientów w <a href="/office/hr/settings">ustawieniach</a>.</p>
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
                    <th style="text-align:center;">Urlopy</th>
                    <th>Payroll <?= $monthNames[$month] ?></th>
                    <th style="text-align:right;">Koszt pracodawcy</th>
                    <th>ZUS DRA</th>
                    <th>Akcje</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clients as $c): ?>
                <tr style="<?= (!$c['payroll_status'] && $c['employee_count'] > 0) ? 'background:#fff5f5;' : '' ?>">
                    <td>
                        <a href="/office/hr/<?= $c['id'] ?>/employees" style="font-weight:600;">
                            <?= htmlspecialchars($c['company_name']) ?>
                        </a>
                        <?php if ($c['nip']): ?>
                        <div style="font-size:11px;color:var(--text-muted);">NIP: <?= htmlspecialchars($c['nip']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;"><?= $c['employee_count'] ?></td>
                    <td style="text-align:center;">
                        <?php if ($c['pending_leaves'] > 0): ?>
                        <span class="badge badge-warning"><?= $c['pending_leaves'] ?></span>
                        <?php else: ?>
                        <span class="text-muted">0</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($c['payroll_status']): ?>
                        <span class="badge <?= $statusBadge[$c['payroll_status']] ?? 'badge-secondary' ?>">
                            <?= $statusLabels[$c['payroll_status']] ?? $c['payroll_status'] ?>
                        </span>
                        <?php elseif ($c['employee_count'] > 0): ?>
                        <span style="color:var(--danger);font-weight:600;">Brak</span>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;">
                        <?= $c['total_employer_cost'] ? $n2($c['total_employer_cost']) . ' PLN' : '—' ?>
                    </td>
                    <td>
                        <?php if ($c['zus_status']): ?>
                        <span class="badge <?= $zusBadge[$c['zus_status']] ?? 'badge-secondary' ?>">
                            <?= $zusLabels[$c['zus_status']] ?? $c['zus_status'] ?>
                        </span>
                        <?php elseif ($c['employee_count'] > 0): ?>
                        <span style="color:var(--danger);font-size:12px;">Brak</span>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display:flex;gap:4px;">
                            <a href="/office/hr/<?= $c['id'] ?>/payroll" class="btn btn-xs btn-secondary" title="Payroll">Listy</a>
                            <a href="/office/hr/<?= $c['id'] ?>/zus" class="btn btn-xs btn-secondary" title="ZUS">ZUS</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>