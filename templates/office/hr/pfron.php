<?php
$monthNames = [1=>'Styczeń',2=>'Luty',3=>'Marzec',4=>'Kwiecień',5=>'Maj',6=>'Czerwiec',
               7=>'Lipiec',8=>'Sierpień',9=>'Wrzesień',10=>'Październik',11=>'Listopad',12=>'Grudzień'];
$n2 = fn($v) => number_format((float)$v, 2, ',', ' ');
?>

<div class="section-header">
    <div>
        <div class="breadcrumb-path" style="font-size:13px;color:var(--text-muted);margin-bottom:4px;">
            <a href="/office/hr/settings"><?= $lang('hr_module') ?></a> &rsaquo;
            <a href="/office/hr/<?= $clientId ?>/employees"><?= htmlspecialchars($client['company_name']) ?></a> &rsaquo;
            PFRON
        </div>
        <h1>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="M20 8v6M23 11h-6"/></svg>
            Deklaracje PFRON — <?= htmlspecialchars($client['company_name']) ?>
        </h1>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <!-- Calculate form -->
        <form method="POST" action="/office/hr/<?= $clientId ?>/pfron/calculate" style="display:flex;gap:6px;align-items:center;">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <select name="month" class="form-control" style="padding:4px 8px;">
                <?php for ($m=1;$m<=12;$m++): ?>
                <option value="<?= $m ?>" <?= $m===(int)date('n')?'selected':'' ?>><?= $monthNames[$m] ?></option>
                <?php endfor; ?>
            </select>
            <select name="year" class="form-control" style="padding:4px 8px;">
                <?php for ($y=(int)date('Y');$y>=(int)date('Y')-2;$y--): ?>
                <option value="<?= $y ?>" <?= $y===$selectedYear?'selected':'' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
            <button type="submit" class="btn btn-primary">Oblicz PFRON</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../hr_nav.php'; ?>

<?php if ($flash_success): ?><div class="alert alert-success"><?= htmlspecialchars($flash_success) ?></div><?php endif; ?>
<?php if ($flash_error): ?><div class="alert alert-error"><?= htmlspecialchars($flash_error) ?></div><?php endif; ?>

<!-- Info box -->
<div class="alert" style="background:#f0f9ff;border-left:4px solid #2563eb;margin-bottom:16px;padding:12px 16px;font-size:13px;">
    <strong>Informacja:</strong> Pracodawcy zatrudniający ≥25 pracowników (w przeliczeniu na pełne etaty),
    którzy nie osiągają 6% wskaźnika zatrudnienia osób niepełnosprawnych, zobowiązani są do wpłat na PFRON
    (art. 21 ustawy z 27.08.1997 o rehabilitacji zawodowej i społecznej).
</div>

<!-- Year filter -->
<div style="margin-bottom:16px;display:flex;align-items:center;gap:12px;">
    <span style="font-size:13px;color:var(--text-muted);">Rok:</span>
    <?php foreach ($years as $y): ?>
    <a href="?year=<?= $y ?>" class="btn btn-sm <?= $y == $selectedYear ? 'btn-primary' : 'btn-secondary' ?>"><?= $y ?></a>
    <?php endforeach; ?>
</div>

<?php if (empty($declarations)): ?>
<div class="card">
    <div class="card-body" style="text-align:center;padding:48px;color:var(--text-muted);">
        <p>Brak deklaracji PFRON za wybrany rok. Oblicz pierwszą deklarację.</p>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body" style="padding:0;">
        <table class="table" style="margin:0;font-size:13px;">
            <thead>
                <tr>
                    <th>Miesiąc</th>
                    <th style="text-align:center;">Pracownicy</th>
                    <th style="text-align:center;">Niepełnosprawni</th>
                    <th style="text-align:center;">Wskaźnik</th>
                    <th>Zobowiązanie</th>
                    <th style="text-align:right;">Kwota wpłaty</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $totalLevy = 0;
                foreach ($declarations as $d):
                    $totalLevy += (float)$d['levy_amount'];
                    $ratioColor = (float)$d['disability_ratio'] >= 0.06 ? 'var(--success)' : 'var(--danger)';
                ?>
                <tr>
                    <td><strong><?= $monthNames[(int)$d['period_month']] ?> <?= $d['period_year'] ?></strong></td>
                    <td style="text-align:center;"><?= $d['total_employees'] ?></td>
                    <td style="text-align:center;"><?= $d['disabled_employees'] ?></td>
                    <td style="text-align:center;">
                        <span style="color:<?= $ratioColor ?>;font-weight:600;">
                            <?= number_format((float)$d['disability_ratio'] * 100, 2) ?>%
                        </span>
                        <span style="font-size:10px;color:var(--text-muted);">(wymagane: 6%)</span>
                    </td>
                    <td>
                        <?php if ($d['pfron_liable']): ?>
                        <span class="badge badge-danger">TAK</span>
                        <?php else: ?>
                        <span class="badge badge-success">NIE</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;font-weight:600;">
                        <?= $d['pfron_liable'] ? $n2($d['levy_amount']) . ' PLN' : '—' ?>
                    </td>
                    <td>
                        <?php $cls = match($d['status']) { 'calculated' => 'badge-info', 'submitted' => 'badge-success', default => 'badge-secondary' }; ?>
                        <span class="badge <?= $cls ?>"><?= ucfirst($d['status']) ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <?php if ($totalLevy > 0): ?>
            <tfoot>
                <tr style="font-weight:700;background:var(--bg-subtle);">
                    <td colspan="5">RAZEM <?= $selectedYear ?></td>
                    <td style="text-align:right;"><?= $n2($totalLevy) ?> PLN</td>
                    <td></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>
<?php endif; ?>