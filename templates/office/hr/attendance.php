<?php
$monthNames = [
    1=>'Styczeń',2=>'Luty',3=>'Marzec',4=>'Kwiecień',5=>'Maj',6=>'Czerwiec',
    7=>'Lipiec',8=>'Sierpień',9=>'Wrzesień',10=>'Październik',11=>'Listopad',12=>'Grudzień',
];
$prevMonth = $month - 1; $prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $month + 1; $nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

$days      = $grid['days'];
$holidays  = $grid['holidays'];  // day => true
$weekends  = $grid['weekends'];  // day => true
$empGrid   = $grid['grid'];      // empId => [day => row|null]
$employees = $grid['employees'];
$totals    = $grid['totals'];    // empId => ['work_h', 'overtime_h']
?>

<div class="section-header">
    <div>
        <div class="breadcrumb-path" style="font-size:13px;color:var(--text-muted);margin-bottom:4px;">
            <a href="/office/hr/settings"><?= $lang('hr_module') ?></a> &rsaquo;
            <a href="/office/hr/<?= $clientId ?>/employees"><?= htmlspecialchars($client['company_name']) ?></a> &rsaquo;
            <?= $lang('hr_attendance') ?>
        </div>
        <h1>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <?= $lang('hr_attendance') ?> — <?= htmlspecialchars($client['company_name']) ?>
        </h1>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="btn btn-secondary" title="Poprzedni miesiąc">&#8592;</a>
        <span style="font-weight:600;min-width:120px;text-align:center;"><?= $monthNames[$month] ?> <?= $year ?></span>
        <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="btn btn-secondary" title="Następny miesiąc">&#8594;</a>
        <a href="/office/hr/<?= $clientId ?>/attendance/pdf?month=<?= $month ?>&year=<?= $year ?>"
           class="btn btn-secondary" target="_blank">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            <?= $lang('hr_att_export_pdf') ?>
        </a>
    </div>
</div>

<?php include __DIR__ . '/../hr_nav.php'; ?>

<?php if ($flash_success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($flash_success) ?></div>
<?php endif; ?>
<?php if ($flash_error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($flash_error) ?></div>
<?php endif; ?>

<?php if (empty($employees)): ?>
<div class="empty-state">
    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:12px;opacity:0.4;"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
    <p><?= $lang('hr_no_employees') ?></p>
</div>
<?php else: ?>

<form method="POST" action="/office/hr/<?= $clientId ?>/attendance">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
<input type="hidden" name="month" value="<?= $month ?>">
<input type="hidden" name="year"  value="<?= $year ?>">

<div style="overflow-x:auto;margin-bottom:12px;">
<table class="table" style="font-size:12px;border-collapse:collapse;min-width:max-content;">
    <thead>
        <tr>
            <th style="min-width:130px;position:sticky;left:0;background:var(--bg-card);z-index:2;"><?= $lang('name') ?></th>
            <?php for ($d = 1; $d <= $days; $d++):
                $isWeekend = !empty($weekends[$d]);
                $isHoliday = !empty($holidays[$d]);
                $dow = (int)date('N', mktime(0,0,0,$month,$d,$year));
                $dowLabels = [1=>'Pn',2=>'Wt',3=>'Śr',4=>'Cz',5=>'Pt',6=>'Sb',7=>'Nd'];
                $bg = ($isWeekend || $isHoliday) ? 'background:#e8f0fe;' : '';
            ?>
            <th style="width:38px;text-align:center;<?= $bg ?>" title="<?= $dowLabels[$dow] ?>">
                <?= $d ?><br><span style="font-size:10px;color:var(--text-muted);"><?= $dowLabels[$dow] ?></span>
            </th>
            <?php endfor; ?>
            <th style="min-width:58px;text-align:center;"><?= $lang('hr_att_work_hours') ?></th>
            <th style="min-width:58px;text-align:center;"><?= $lang('hr_att_overtime_hours') ?></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($employees as $emp):
        $empId  = (int)$emp['id'];
        $empRow = $empGrid[$empId] ?? [];
        $tot    = $totals[$empId] ?? ['work_h'=>0,'overtime_h'=>0];
    ?>
        <tr>
            <td style="font-weight:500;position:sticky;left:0;background:var(--bg-card);z-index:1;white-space:nowrap;">
                <a href="/office/hr/<?= $clientId ?>/employees/<?= $empId ?>" style="text-decoration:none;color:inherit;">
                    <?= htmlspecialchars($emp['last_name'] . ' ' . $emp['first_name']) ?>
                </a>
            </td>
            <?php for ($d = 1; $d <= $days; $d++):
                $isWeekend = !empty($weekends[$d]);
                $isHoliday = !empty($holidays[$d]);
                $row       = $empRow[$d] ?? null;
                $type      = $row['type']             ?? ($isHoliday ? 'holiday' : ($isWeekend ? 'other' : 'work'));
                $workMin   = $row['work_minutes']      ?? ($isWeekend || $isHoliday ? 0 : 480);
                $otMin     = $row['overtime_minutes']  ?? 0;
                $notes     = $row['notes']             ?? '';
                $cellBg    = ($isWeekend || $isHoliday) ? 'background:#f0f4ff;' : '';
            ?>
            <td style="padding:2px;vertical-align:top;<?= $cellBg ?>">
                <select name="day[<?= $empId ?>][<?= $d ?>][type]"
                        class="att-type"
                        data-emp="<?= $empId ?>" data-day="<?= $d ?>"
                        style="width:100%;font-size:11px;padding:1px;border:1px solid #ddd;border-radius:3px;">
                    <?php foreach ($typeLabels as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $type === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="day[<?= $empId ?>][<?= $d ?>][work_minutes]"
                       value="<?= (int)$workMin ?>" min="0" max="1440" step="15"
                       title="Minuty pracy"
                       style="width:100%;font-size:11px;margin-top:1px;padding:1px 2px;border:1px solid #ddd;border-radius:3px;">
                <input type="hidden" name="day[<?= $empId ?>][<?= $d ?>][overtime_minutes]" value="<?= (int)$otMin ?>">
                <input type="hidden" name="day[<?= $empId ?>][<?= $d ?>][notes]" value="<?= htmlspecialchars($notes) ?>">
            </td>
            <?php endfor; ?>
            <td style="text-align:center;font-weight:600;"><?= number_format($tot['work_h'], 1) ?></td>
            <td style="text-align:center;color:<?= $tot['overtime_h'] > 0 ? '#c0392b' : 'var(--text-muted)' ?>;">
                <?= $tot['overtime_h'] > 0 ? number_format($tot['overtime_h'], 1) : '—' ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:20px;">
    <button type="submit" class="btn btn-primary">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        <?= $lang('save') ?>
    </button>
    <span style="color:var(--text-muted);font-size:13px;">
        <?= $lang('hr_att_save_hint') ?>
    </span>
</div>
</form>

<?php if ($runForMonth): ?>
<div class="card" style="padding:16px;margin-top:8px;border-left:4px solid var(--primary);">
    <h3 style="margin:0 0 10px;"><?= $lang('hr_att_inject_overtime') ?></h3>
    <p style="margin:0 0 12px;font-size:14px;color:var(--text-muted);">
        <?= $lang('hr_att_inject_overtime_desc') ?>
        — <?= $lang('hr_payroll_run') ?>:
        <strong><?= $monthNames[$month] ?> <?= $year ?></strong>
        (<?= htmlspecialchars(\App\Models\HrPayrollRun::getStatusLabel($runForMonth['status'])) ?>)
    </p>
    <form method="POST" action="/office/hr/<?= $clientId ?>/attendance/inject-overtime"
          onsubmit="return confirm('Wstawić nadgodziny do listy płac? Wartości overtime_pay zostaną nadpisane.')">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="month"          value="<?= $month ?>">
        <input type="hidden" name="year"           value="<?= $year ?>">
        <input type="hidden" name="payroll_run_id" value="<?= $runForMonth['id'] ?>">
        <button type="submit" class="btn btn-warning">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0018 9h-1.26A8 8 0 103 16.3"/></svg>
            <?= $lang('hr_att_inject_overtime') ?>
        </button>
    </form>
</div>
<?php elseif (!empty($employees)): ?>
<div class="card" style="padding:12px 16px;margin-top:8px;background:#fffbf0;border-left:4px solid #f0ad4e;">
    <p style="margin:0;font-size:13px;">
        <strong><?= $lang('hr_att_no_run') ?></strong>
        <?= $lang('hr_att_no_run_desc') ?>
        <a href="/office/hr/<?= $clientId ?>/payroll"><?= $lang('hr_payroll') ?></a>.
    </p>
</div>
<?php endif; ?>

<!-- Legend -->
<div style="margin-top:20px;padding:12px 16px;background:var(--bg-card);border-radius:8px;font-size:12px;">
    <strong><?= $lang('hr_att_legend') ?>:</strong>
    <?php
    $codes = \App\Models\HrAttendance::TYPE_CODES;
    $parts = [];
    foreach ($typeLabels as $key => $label) {
        $code = $codes[$key] ?? '?';
        $parts[] = "<strong>{$code}</strong> = " . htmlspecialchars($label);
    }
    echo implode(' &nbsp;|&nbsp; ', $parts);
    ?>
    &nbsp;&nbsp;|&nbsp;&nbsp;
    <span style="display:inline-block;width:12px;height:12px;background:#f0f4ff;border:1px solid #ccc;vertical-align:middle;"></span>
    = weekend / święto
</div>

<?php endif; /* empty employees */ ?>

<style>
.att-type { background: white; }
.att-type option[value="vacation"] { background: #c6efce; }
.att-type option[value="sick"]     { background: #ffeb9c; }
.att-type option[value="holiday"]  { background: #bdd7ee; }
.att-type option[value="remote"]   { background: #e2efda; }
.att-type option[value="other"]    { background: #f2f2f2; }
</style>