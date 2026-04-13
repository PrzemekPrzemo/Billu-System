<?php
$monthNames = ['', 'Styczeń', 'Luty', 'Marzec', 'Kwiecień', 'Maj', 'Czerwiec', 'Lipiec', 'Sierpień', 'Wrzesień', 'Październik', 'Listopad', 'Grudzień'];
$monthNamesShort = ['', 'sty', 'lut', 'mar', 'kwi', 'maj', 'cze', 'lip', 'sie', 'wrz', 'paź', 'lis', 'gru'];
$firstDow = date('N', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear));
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $selectedMonth, $selectedYear);
$today = date('Y-m-d');
$dayNames = [$lang('day_mon'), $lang('day_tue'), $lang('day_wed'), $lang('day_thu'), $lang('day_fri'), $lang('day_sat'), $lang('day_sun')];

// Prev/next month links
$prevMonth = $selectedMonth - 1;
$prevYear = $selectedYear;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $selectedMonth + 1;
$nextYear = $selectedYear;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }
?>

<h1><?= $lang('tax_calendar_title') ?></h1>

<!-- ══════ UPCOMING DEADLINES — TILES ══════ -->
<?php if (!empty($upcomingDeadlines)): ?>
<div style="margin-bottom:24px;">
    <h3 style="margin-bottom:12px; color:var(--gray-700);"><?= $lang('upcoming_deadlines') ?></h3>
    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:12px;">
        <?php foreach ($upcomingDeadlines as $dl):
            $daysLeft = (int) $dl['days_left'];
            if ($daysLeft === 0) {
                $color = '#dc2626'; $bg = '#fef2f2'; $border = '#fecaca';
                $label = $lang('today') . '!';
            } elseif ($daysLeft <= 3) {
                $color = '#ea580c'; $bg = '#fff7ed'; $border = '#fed7aa';
                $label = $daysLeft . ' ' . ($daysLeft === 1 ? $lang('day_singular') : $lang('days'));
            } elseif ($daysLeft <= 7) {
                $color = '#ca8a04'; $bg = '#fefce8'; $border = '#fef08a';
                $label = $daysLeft . ' ' . $lang('days');
            } else {
                $color = '#16a34a'; $bg = '#f0fdf4'; $border = '#bbf7d0';
                $label = $daysLeft . ' ' . $lang('days');
            }

            // Progress: how much of the 30-day window has elapsed
            $progressPct = max(0, min(100, (int)(((30 - $daysLeft) / 30) * 100)));

            // Date formatting
            $dlDay = (int) date('j', strtotime($dl['date']));
            $dlMon = (int) date('n', strtotime($dl['date']));
            $dateFormatted = $dlDay . ' ' . $monthNamesShort[$dlMon];
        ?>
        <div style="background:<?= $bg ?>; border:1px solid <?= $border ?>; border-radius:12px; padding:16px; position:relative; overflow:hidden;">
            <!-- Progress bar background -->
            <div style="position:absolute; bottom:0; left:0; height:4px; width:<?= $progressPct ?>%; background:<?= $color ?>30; border-radius:0 0 0 12px;"></div>

            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px;">
                <div style="font-weight:700; font-size:15px; color:var(--gray-800);"><?= htmlspecialchars($dl['type']) ?></div>
                <div style="background:<?= $color ?>18; color:<?= $color ?>; font-size:11px; font-weight:600; padding:2px 8px; border-radius:10px; white-space:nowrap;">
                    <?= $dateFormatted ?>
                </div>
            </div>

            <div style="display:flex; align-items:center; gap:6px; margin-top:12px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="<?= $color ?>" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
                <span style="font-size:20px; font-weight:700; color:<?= $color ?>; line-height:1;"><?= $label ?></span>
            </div>

            <!-- Progress bar -->
            <div style="margin-top:10px; height:4px; background:<?= $color ?>15; border-radius:2px; overflow:hidden;">
                <div style="height:100%; width:<?= $progressPct ?>%; background:<?= $color ?>; border-radius:2px; transition:width 0.3s;"></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ══════ MONTH NAVIGATION ══════ -->
<div class="form-card" style="padding:16px 20px; margin-bottom:20px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <!-- Arrow navigation -->
        <div style="display:flex; align-items:center; gap:16px;">
            <a href="/client/tax-calendar?month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="btn btn-sm" style="padding:10px 14px; min-width:44px; min-height:44px; display:inline-flex; align-items:center; justify-content:center;" title="<?= $monthNames[$prevMonth] ?> <?= $prevYear ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            </a>
            <h2 style="margin:0; font-size:20px; white-space:nowrap;"><?= $monthNames[$selectedMonth] ?> <?= $selectedYear ?></h2>
            <a href="/client/tax-calendar?month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="btn btn-sm" style="padding:10px 14px; min-width:44px; min-height:44px; display:inline-flex; align-items:center; justify-content:center;" title="<?= $monthNames[$nextMonth] ?> <?= $nextYear ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
            </a>
        </div>

        <!-- Quick jump -->
        <form method="GET" action="/client/tax-calendar" style="display:flex; gap:8px; align-items:center;">
            <select name="month" class="form-input" style="width:auto; padding:4px 8px; font-size:13px;" onchange="this.form.submit()">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $m == $selectedMonth ? 'selected' : '' ?>><?= $monthNames[$m] ?></option>
                <?php endfor; ?>
            </select>
            <select name="year" class="form-input" style="width:auto; padding:4px 8px; font-size:13px;" onchange="this.form.submit()">
                <?php for ($y = date('Y') - 1; $y <= date('Y') + 2; $y++): ?>
                    <option value="<?= $y ?>" <?= $y == $selectedYear ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </form>
    </div>
</div>

<!-- ══════ CALENDAR GRID ══════ -->
<div class="form-card" style="padding:16px;">
    <div style="overflow-x:auto; -webkit-overflow-scrolling:touch;">
    <table class="data-table" style="table-layout:fixed; min-width:600px;">
        <thead>
            <tr>
                <?php foreach ($dayNames as $i => $dn): ?>
                    <th style="text-align:center; width:14.28%; font-size:12px; color:<?= $i >= 5 ? 'var(--red-400)' : 'var(--gray-500)' ?>; text-transform:uppercase; letter-spacing:0.5px;"><?= $dn ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <tr>
            <?php
            for ($i = 1; $i < $firstDow; $i++) {
                echo '<td style="background:var(--gray-50); min-height:80px;"></td>';
            }

            $currentDow = $firstDow;
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $dateStr = sprintf('%04d-%02d-%02d', $selectedYear, $selectedMonth, $day);
                $isToday = ($dateStr === $today);
                $isWeekend = ($currentDow >= 6);

                $tdStyle = 'vertical-align:top; padding:6px 8px; min-height:85px; border:1px solid var(--gray-100);';
                if ($isToday) $tdStyle .= ' background:var(--blue-50);';
                elseif ($isWeekend) $tdStyle .= ' background:var(--gray-50);';

                echo '<td style="' . $tdStyle . '">';

                // Day number
                $dayNumStyle = 'font-weight:600; font-size:13px; width:24px; height:24px; display:flex; align-items:center; justify-content:center; border-radius:50%;';
                if ($isToday) {
                    $dayNumStyle .= ' background:var(--blue-600); color:white;';
                } else {
                    $dayNumStyle .= ' color:' . ($isWeekend ? 'var(--red-400)' : 'var(--gray-600)') . ';';
                }
                echo '<div style="' . $dayNumStyle . '">' . $day . '</div>';

                if (!empty($calendarGrid[$day])) {
                    foreach ($calendarGrid[$day] as $entry) {
                        $deadlineType = htmlspecialchars($entry['type']);
                        $isPast = ($dateStr < $today);
                        $isSoon = (!$isPast && (strtotime($dateStr) - strtotime($today)) / 86400 <= 3);

                        $color = '#16a34a';
                        if ($isPast) $color = '#dc2626';
                        elseif ($isSoon) $color = '#ea580c';

                        echo '<div style="font-size:11px; padding:2px 6px; margin-top:3px; background:' . $color . '12; border-left:3px solid ' . $color . '; border-radius:3px;">';
                        echo '<strong style="color:' . $color . ';">' . $deadlineType . '</strong>';
                        echo '</div>';
                    }
                }

                echo '</td>';

                if ($currentDow == 7 && $day < $daysInMonth) {
                    echo '</tr><tr>';
                    $currentDow = 0;
                }
                $currentDow++;
            }

            while ($currentDow <= 7 && $currentDow > 1) {
                echo '<td style="background:var(--gray-50);"></td>';
                $currentDow++;
            }
            ?>
            </tr>
        </tbody>
    </table>
    </div>
</div>
