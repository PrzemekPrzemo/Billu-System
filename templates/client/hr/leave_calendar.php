<div class="section-header">
    <div>
        <h1><?= $lang('hr_leave_calendar') ?></h1>
    </div>
    <a href="/client/hr/leaves" class="btn btn-secondary"><?= $lang('back') ?></a>
</div>

<?php include __DIR__ . '/../hr_nav.php'; ?>

<?php
$months = ['', 'Styczeń','Luty','Marzec','Kwiecień','Maj','Czerwiec',
           'Lipiec','Sierpień','Wrzesień','Październik','Listopad','Grudzień'];

$prevMonth = $month == 1 ? 12 : $month - 1;
$prevYear  = $month == 1 ? $year - 1 : $year;
$nextMonth = $month == 12 ? 1 : $month + 1;
$nextYear  = $month == 12 ? $year + 1 : $year;

$firstDow    = (int) date('N', mktime(0,0,0,$month,1,$year));
$daysInMonth = (int) date('t', mktime(0,0,0,$month,1,$year));
?>

<div class="card">
    <div class="card-header" style="display:flex;align-items:center;gap:16px;">
        <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="btn btn-sm btn-secondary">&lsaquo;</a>
        <h3 style="margin:0;flex:1;text-align:center;"><?= $months[$month] ?> <?= $year ?></h3>
        <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="btn btn-sm btn-secondary">&rsaquo;</a>
    </div>

    <?php if (!empty($employees)): ?>
    <div class="card-body" style="padding:8px 16px 4px;">
        <label for="filter-emp" style="font-size:13px;color:var(--text-muted);"><?= $lang('hr_leave_employee') ?>:</label>
        <select id="filter-emp" onchange="filterEmployee(this.value)" style="margin-left:8px;font-size:13px;padding:3px 8px;">
            <option value="">Wszyscy</option>
            <?php foreach ($employees as $emp): ?>
            <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['full_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <span style="margin-left:16px;font-size:13px;">
            <span style="display:inline-block;width:12px;height:12px;background:var(--success);border-radius:2px;vertical-align:middle;"></span> Zatwierdzony &nbsp;
            <span style="display:inline-block;width:12px;height:12px;background:var(--warning);border-radius:2px;vertical-align:middle;"></span> Oczekuje
        </span>
    </div>
    <?php endif; ?>

    <div class="card-body" style="padding:0;">
        <table style="width:100%;border-collapse:collapse;table-layout:fixed;">
            <thead>
                <tr>
                    <?php foreach (['Pon','Wt','Śr','Czw','Pt','Sob','Niedz'] as $d): ?>
                    <th style="text-align:center;padding:6px;font-size:12px;color:var(--text-muted);border-bottom:1px solid var(--border);"><?= $d ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php
            $cell = 0;
            $day  = 1;
            echo '<tr>';
            for ($i = 1; $i < $firstDow; $i++) {
                echo '<td style="height:80px;border:1px solid var(--border);background:var(--bg-subtle);"></td>';
                $cell++;
            }
            while ($day <= $daysInMonth) {
                $dateStr   = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $isWeekend = ((($firstDow - 1 + $day - 1) % 7) + 1) >= 6;
                $isHoliday = in_array($dateStr, $holidays, true);
                $bgStyle   = ($isWeekend || $isHoliday) ? 'background:var(--bg-subtle);' : '';

                echo '<td style="vertical-align:top;height:80px;border:1px solid var(--border);padding:4px;' . $bgStyle . '">';
                echo '<div style="font-size:11px;font-weight:600;color:' . ($isHoliday ? 'var(--danger)' : ($isWeekend ? 'var(--text-muted)' : 'var(--text)')) . ';">' . $day . '</div>';

                if (isset($calendarData[$dateStr])) {
                    foreach ($calendarData[$dateStr] as $entry) {
                        $color   = $entry['status'] === 'approved' ? 'var(--success)' : 'var(--warning)';
                        $empCls  = 'emp-' . $entry['employee_id'];
                        $initials = mb_substr($entry['first_name'], 0, 1) . mb_substr($entry['last_name'], 0, 1);
                        echo '<div class="leave-chip ' . $empCls . '" style="font-size:10px;background:' . $color . ';color:#fff;border-radius:3px;padding:1px 4px;margin-top:2px;cursor:default;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;" title="' . htmlspecialchars($entry['employee_name'] . ' — ' . $entry['leave_type']) . '">' . htmlspecialchars($initials . ' ' . $entry['leave_type_short']) . '</div>';
                    }
                }

                echo '</td>';
                $cell++;

                if ($cell % 7 === 0 && $day < $daysInMonth) {
                    echo '</tr><tr>';
                }
                $day++;
            }
            while ($cell % 7 !== 0) {
                echo '<td style="height:80px;border:1px solid var(--border);background:var(--bg-subtle);"></td>';
                $cell++;
            }
            echo '</tr>';
            ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function filterEmployee(empId) {
    document.querySelectorAll('.leave-chip').forEach(el => {
        if (!empId || el.classList.contains('emp-' + empId)) {
            el.style.display = '';
        } else {
            el.style.display = 'none';
        }
    });
}
</script>
