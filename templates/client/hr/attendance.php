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
$holidays  = $grid['holidays'];
$weekends  = $grid['weekends'];
$empGrid   = $grid['grid'];
$employees = $grid['employees'];
$totals    = $grid['totals'];

$typeLabels = \App\Models\HrAttendance::TYPE_CODES;
$typeBg = [
    'work' => '#e8f5e9', 'vacation' => '#fff9c4', 'sick' => '#ffccbc',
    'holiday' => '#e3f2fd', 'remote' => '#f3e5f5', 'other' => '#f5f5f5',
];
?>

<div class="section-header">
    <div>
        <h1>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <?= $lang('hr_attendance') ?> — <?= $monthNames[$month] ?> <?= $year ?>
        </h1>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="btn btn-secondary">&#8592;</a>
        <span style="font-weight:600;min-width:120px;text-align:center;"><?= $monthNames[$month] ?> <?= $year ?></span>
        <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="btn btn-secondary">&#8594;</a>
    </div>
</div>

<?php include __DIR__ . '/../hr_nav.php'; ?>

<?php if (empty($employees)): ?>
<div class="empty-state">
    <p><?= $lang('hr_no_employees') ?></p>
</div>
<?php else: ?>

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
            <th style="width:32px;text-align:center;<?= $bg ?>" title="<?= $dowLabels[$dow] ?>">
                <?= $d ?><br><span style="font-size:10px;color:var(--text-muted);"><?= $dowLabels[$dow] ?></span>
            </th>
            <?php endfor; ?>
            <th style="min-width:50px;text-align:center;">Godz.</th>
            <th style="min-width:50px;text-align:center;">Nadg.</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($employees as $emp):
            $empId = $emp['id'];
            $empRow = $empGrid[$empId] ?? [];
        ?>
        <tr>
            <td style="position:sticky;left:0;background:var(--bg-card);z-index:1;font-weight:600;white-space:nowrap;">
                <?= htmlspecialchars($emp['full_name']) ?>
            </td>
            <?php for ($d = 1; $d <= $days; $d++):
                $cell = $empRow[$d] ?? null;
                $isWeekend = !empty($weekends[$d]);
                $isHoliday = !empty($holidays[$d]);
                $type = $cell['type'] ?? ($isHoliday ? 'holiday' : ($isWeekend ? '' : ''));
                $code = $type ? ($typeLabels[$type] ?? '') : '';
                $bg = $type ? ($typeBg[$type] ?? '#f5f5f5') : (($isWeekend || $isHoliday) ? '#e8f0fe' : '');
            ?>
            <td style="text-align:center;background:<?= $bg ?>;font-size:11px;font-weight:600;">
                <?= $code ?>
            </td>
            <?php endfor; ?>
            <td style="text-align:center;font-weight:600;">
                <?php $wh = ($totals[$empId]['work_h'] ?? 0); echo $wh ? number_format($wh, 1) : '—'; ?>
            </td>
            <td style="text-align:center;font-weight:600;color:<?= ($totals[$empId]['overtime_h'] ?? 0) > 0 ? 'var(--danger)' : 'var(--text-muted)' ?>;">
                <?php $oh = ($totals[$empId]['overtime_h'] ?? 0); echo $oh ? number_format($oh, 1) : '—'; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<!-- Legend -->
<div style="display:flex;gap:16px;flex-wrap:wrap;font-size:12px;color:var(--text-muted);">
    <?php foreach (\App\Models\HrAttendance::TYPE_LABELS as $type => $label): ?>
    <span>
        <span style="display:inline-block;width:16px;height:12px;background:<?= $typeBg[$type] ?? '#f5f5f5' ?>;border:1px solid #ddd;border-radius:2px;vertical-align:middle;"></span>
        <?= $typeLabels[$type] ?? '' ?> = <?= $label ?>
    </span>
    <?php endforeach; ?>
</div>

<?php endif; ?>
