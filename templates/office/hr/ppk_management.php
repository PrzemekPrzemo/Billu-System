<?php
$monthNames = [
    1=>'Styczeń',2=>'Luty',3=>'Marzec',4=>'Kwiecień',5=>'Maj',6=>'Czerwiec',
    7=>'Lipiec',8=>'Sierpień',9=>'Wrzesień',10=>'Październik',11=>'Listopad',12=>'Grudzień',
];
$n = fn($v) => number_format((float)$v, 2, ',', ' ');
?>

<div class="section-header">
    <div>
        <div class="breadcrumb-path" style="font-size:13px;color:var(--text-muted);margin-bottom:4px;">
            <a href="/office/hr/settings"><?= $lang('hr_module') ?></a> &rsaquo;
            <a href="/office/hr/<?= $clientId ?>/employees"><?= htmlspecialchars($client['company_name']) ?></a> &rsaquo;
            <?= $lang('hr_ppk_management') ?>
        </div>
        <h1>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            <?= $lang('hr_ppk_management') ?> — <?= htmlspecialchars($client['company_name']) ?>
        </h1>
    </div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <!-- Export form -->
        <form method="GET" action="/office/hr/<?= $clientId ?>/ppk/export" style="display:flex;gap:6px;align-items:center;">
            <select name="month" class="form-control" style="padding:4px 8px;">
                <?php for ($m=1;$m<=12;$m++): ?>
                <option value="<?= $m ?>" <?= $m===$month?'selected':'' ?>><?= $monthNames[$m] ?></option>
                <?php endfor; ?>
            </select>
            <select name="year" class="form-control" style="padding:4px 8px;">
                <?php for ($y=(int)date('Y');$y>=(int)date('Y')-2;$y--): ?>
                <option value="<?= $y ?>" <?= $y===$year?'selected':'' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
            <button type="submit" class="btn btn-secondary">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <?= $lang('hr_ppk_report_export') ?>
            </button>
        </form>
        <a href="/office/hr/<?= $clientId ?>/employees" class="btn btn-secondary"><?= $lang('back') ?></a>
    </div>
</div>

<?php include __DIR__ . '/../hr_nav.php'; ?>

<?php if ($flash_success): ?><div class="alert alert-success"><?= htmlspecialchars($flash_success) ?></div><?php endif; ?>
<?php if ($flash_error): ?><div class="alert alert-error"><?= htmlspecialchars($flash_error) ?></div><?php endif; ?>

<!-- Auto-enrollment alerts -->
<?php if (!empty($alerts)): ?>
<div class="alert" style="background:#fffbeb;border-left:4px solid #f59e0b;margin-bottom:20px;padding:14px 16px;">
    <strong><?= $lang('hr_ppk_alert_auto_enroll') ?></strong>
    <p style="margin:6px 0 0;font-size:13px;"><?= $lang('hr_ppk_alert_auto_enroll_desc') ?>:</p>
    <ul style="margin:8px 0 0;padding-left:20px;font-size:13px;">
        <?php foreach ($alerts as $a): ?>
        <li>
            <strong><?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?></strong>
            — <?= $lang('hr_employment_start') ?>: <?= htmlspecialchars($a['employment_start']) ?>
            (<?= (int)$a['months_employed'] ?> mies. stażu)
        </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- Employee PPK status table -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <h3><?= $lang('hr_ppk_employee_status') ?></h3>
    </div>
    <div class="card-body" style="padding:0;">
        <?php if (empty($employees)): ?>
        <div class="empty-state" style="padding:24px;">
            <p><?= $lang('hr_no_employees') ?></p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th><?= $lang('name') ?></th>
                    <th><?= $lang('status') ?></th>
                    <th><?= $lang('hr_ppk_enrolled_at') ?></th>
                    <th><?= $lang('hr_ppk_institution') ?></th>
                    <th style="text-align:right;">Stawka prac.</th>
                    <th style="text-align:right;">Stawka pracodawcy</th>
                    <th style="text-align:right;">YTD prac.</th>
                    <th style="text-align:right;">YTD pracodawcy</th>
                    <th><?= $lang('actions') ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($employees as $emp):
                $empId   = (int)$emp['id'];
                $ytd     = $ytdSummary[$empId] ?? null;
                $enrolled = (bool)$emp['ppk_enrolled'];
                $optedOut = !empty($emp['ppk_opted_out_at']);
                if ($enrolled) {
                    $statusBadge = '<span class="badge badge-success">' . $lang('hr_ppk_enroll') . '</span>';
                } elseif ($optedOut) {
                    $statusBadge = '<span class="badge badge-warning">' . $lang('hr_ppk_opt_out') . '</span>';
                } else {
                    $statusBadge = '<span class="badge badge-default">Nie dotyczy</span>';
                }
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($emp['last_name'] . ' ' . $emp['first_name']) ?></strong></td>
                <td><?= $statusBadge ?></td>
                <td><?= $emp['ppk_enrolled_at'] ? htmlspecialchars($emp['ppk_enrolled_at']) : '—' ?></td>
                <td><?= $emp['ppk_institution'] ? htmlspecialchars($emp['ppk_institution']) : '—' ?></td>
                <td style="text-align:right;"><?= $enrolled ? number_format((float)$emp['ppk_employee_rate'],2,',','') . '%' : '—' ?></td>
                <td style="text-align:right;"><?= $enrolled ? number_format((float)$emp['ppk_employer_rate'],2,',','') . '%' : '—' ?></td>
                <td style="text-align:right;"><?= $ytd ? $n($ytd['employee_ytd']) : '—' ?></td>
                <td style="text-align:right;"><?= $ytd ? $n($ytd['employer_ytd']) : '—' ?></td>
                <td>
                    <?php if (!$enrolled): ?>
                    <!-- Enroll button triggers inline form -->
                    <button class="btn btn-xs btn-success" onclick="document.getElementById('enroll-form-<?= $empId ?>').style.display='block';this.style.display='none';">
                        <?= $lang('hr_ppk_enroll') ?>
                    </button>
                    <div id="enroll-form-<?= $empId ?>" style="display:none;">
                        <form method="POST" action="/office/hr/<?= $clientId ?>/ppk/<?= $empId ?>/enroll" style="margin-top:6px;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="date" name="effective_date" value="<?= date('Y-m-d') ?>" class="form-control" style="margin-bottom:4px;">
                            <input type="text" name="institution" placeholder="<?= $lang('hr_ppk_institution') ?>" class="form-control" style="margin-bottom:4px;">
                            <div style="display:flex;gap:4px;margin-bottom:6px;">
                                <input type="number" name="employee_rate" value="2.00" min="0.5" max="4" step="0.5" class="form-control" placeholder="Prac. %">
                                <input type="number" name="employer_rate" value="1.50" min="1.5" max="4" step="0.5" class="form-control" placeholder="Pracodawca %">
                            </div>
                            <button type="submit" class="btn btn-xs btn-primary"><?= $lang('save') ?></button>
                        </form>
                    </div>
                    <?php else: ?>
                    <!-- Opt out button -->
                    <button class="btn btn-xs btn-warning" onclick="document.getElementById('optout-form-<?= $empId ?>').style.display='block';this.style.display='none';">
                        <?= $lang('hr_ppk_opt_out') ?>
                    </button>
                    <div id="optout-form-<?= $empId ?>" style="display:none;">
                        <form method="POST" action="/office/hr/<?= $clientId ?>/ppk/<?= $empId ?>/opt-out" style="margin-top:6px;"
                              onsubmit="return confirm('Wypisać pracownika z PPK?')">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="date" name="opt_out_date" value="<?= date('Y-m-d') ?>" class="form-control" style="margin-bottom:6px;">
                            <button type="submit" class="btn btn-xs btn-danger"><?= $lang('hr_ppk_opt_out_confirm') ?></button>
                        </form>
                    </div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>