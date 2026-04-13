<?php
$monthNames = ['', 'Styczeń', 'Luty', 'Marzec', 'Kwiecień', 'Maj', 'Czerwiec', 'Lipiec', 'Sierpień', 'Wrzesień', 'Październik', 'Listopad', 'Grudzień'];
$firstDow = date('N', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear));
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $selectedMonth, $selectedYear);
$today = date('Y-m-d');
$dayNames = [$lang('day_mon'), $lang('day_tue'), $lang('day_wed'), $lang('day_thu'), $lang('day_fri'), $lang('day_sat'), $lang('day_sun')];

$prevMonth = $selectedMonth - 1; $prevYear = $selectedYear;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $selectedMonth + 1; $nextYear = $selectedYear;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

$clientParam = !empty($selectedClientId) ? '&client_id=' . $selectedClientId : '';
$baseUrl = '/office/tax-calendar';
?>

<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:20px;">
    <h1 style="margin:0;"><?= $lang('tax_calendar_title') ?></h1>
    <button type="button" class="btn btn-primary" onclick="document.getElementById('addEventModal').style.display='flex'">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <?= $lang('add_event') ?>
    </button>
</div>

<?php $flashSuccess = \App\Core\Session::getFlash('success'); ?>
<?php if ($flashSuccess): ?>
    <div class="alert alert-success"><?= $lang($flashSuccess) !== $flashSuccess ? $lang($flashSuccess) : htmlspecialchars($flashSuccess) ?></div>
<?php endif; ?>
<?php $flashError = \App\Core\Session::getFlash('error'); ?>
<?php if ($flashError): ?>
    <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<!-- ══════ MONTH NAVIGATION + FILTERS ══════ -->
<div class="form-card" style="padding:16px 20px; margin-bottom:20px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <!-- Arrow navigation -->
        <div style="display:flex; align-items:center; gap:16px;">
            <a href="<?= $baseUrl ?>?month=<?= $prevMonth ?>&year=<?= $prevYear ?><?= $clientParam ?>" class="btn btn-sm" style="padding:10px 14px; min-width:44px; min-height:44px; display:inline-flex; align-items:center; justify-content:center;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            </a>
            <h2 style="margin:0; font-size:20px; white-space:nowrap;"><?= $monthNames[$selectedMonth] ?> <?= $selectedYear ?></h2>
            <a href="<?= $baseUrl ?>?month=<?= $nextMonth ?>&year=<?= $nextYear ?><?= $clientParam ?>" class="btn btn-sm" style="padding:10px 14px; min-width:44px; min-height:44px; display:inline-flex; align-items:center; justify-content:center;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
            </a>
        </div>

        <!-- Filters -->
        <form method="GET" action="<?= $baseUrl ?>" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
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
            <select name="client_id" class="form-input" style="width:auto; min-width:180px; padding:4px 8px; font-size:13px;" onchange="this.form.submit()">
                <option value=""><?= $lang('all_clients') ?></option>
                <?php foreach ($clients as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($selectedClientId ?? '') == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['company_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<!-- ══════ CALENDAR GRID ══════ -->
<div class="form-card" style="padding:16px; margin-bottom:20px;">
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

                $dayNumStyle = 'font-weight:600; font-size:13px; width:24px; height:24px; display:flex; align-items:center; justify-content:center; border-radius:50%;';
                if ($isToday) {
                    $dayNumStyle .= ' background:var(--blue-600); color:white;';
                } else {
                    $dayNumStyle .= ' color:' . ($isWeekend ? 'var(--red-400)' : 'var(--gray-600)') . ';';
                }
                echo '<div style="' . $dayNumStyle . '">' . $day . '</div>';

                // Tax deadlines
                if (!empty($calendarGrid[$day])) {
                    foreach ($calendarGrid[$day] as $entry) {
                        $deadlineType = htmlspecialchars($entry['type']);
                        $count = (int) ($entry['count'] ?? 1);
                        $isPast = ($dateStr < $today);
                        $isSoon = (!$isPast && (strtotime($dateStr) - strtotime($today)) / 86400 <= 3);

                        $color = '#16a34a';
                        if ($isPast) $color = '#dc2626';
                        elseif ($isSoon) $color = '#ea580c';

                        $tooltip = '';
                        if (!empty($entry['clients'])) {
                            $names = array_map(fn($c) => $c['client_name'], $entry['clients']);
                            $tooltip = htmlspecialchars(implode(', ', array_slice($names, 0, 10)));
                            if (count($names) > 10) $tooltip .= '...';
                        }

                        echo '<div style="font-size:11px; padding:2px 6px; margin-top:3px; background:' . $color . '12; border-left:3px solid ' . $color . '; border-radius:3px; cursor:default;" title="' . $tooltip . '">';
                        echo '<strong style="color:' . $color . ';">' . $deadlineType . '</strong>';
                        if ($count > 1) {
                            echo ' <span style="color:var(--gray-500); font-size:10px;">(' . $count . ')</span>';
                        }
                        echo '</div>';
                    }
                }

                // Custom events
                if (!empty($customEventsGrid[$day])) {
                    foreach ($customEventsGrid[$day] as $evt) {
                        $evtColor = htmlspecialchars($evt['color'] ?? '#6366f1');
                        $evtOwner = $evt['client_name'] ?? $evt['employee_name'] ?? '';
                        $evtTooltip = htmlspecialchars($evtOwner . ($evt['description'] ? ': ' . $evt['description'] : ''));
                        echo '<div style="font-size:11px; padding:2px 6px; margin-top:3px; background:' . $evtColor . '12; border-left:3px solid ' . $evtColor . '; border-radius:3px; cursor:default;" title="' . $evtTooltip . '">';
                        echo '<strong style="color:' . $evtColor . ';">' . htmlspecialchars($evt['title']) . '</strong>';
                        if ($evtOwner) {
                            echo '<br><span style="color:var(--gray-500); font-size:10px;">' . htmlspecialchars($evtOwner) . '</span>';
                        }
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

<!-- ══════ DEADLINES — GROUPED BY DATE ══════ -->
<?php if (!empty($deadlinesList)):
    // Group deadlines by date, then by type
    $groupedByDate = [];
    foreach ($deadlinesList as $dl) {
        $date = $dl['date'];
        $type = $dl['type'];
        $key = $date . '|' . $type;
        if (!isset($groupedByDate[$date][$type])) {
            $groupedByDate[$date][$type] = [
                'type' => $type,
                'date' => $date,
                'clients' => [],
                'total_amount' => 0,
            ];
        }
        $groupedByDate[$date][$type]['clients'][] = [
            'name' => $dl['client_name'] ?? '',
            'id' => $dl['client_id'] ?? 0,
            'amount' => $dl['amount'] ?? null,
        ];
        if (!empty($dl['amount'])) {
            $groupedByDate[$date][$type]['total_amount'] += (float) $dl['amount'];
        }
    }
    ksort($groupedByDate);
?>
<div class="form-card" style="padding:20px;">
    <h3 style="margin-bottom:16px;"><?= $lang('deadlines_this_month') ?></h3>

    <?php foreach ($groupedByDate as $date => $types):
        $dateObj = new DateTime($date);
        $dayNum = (int) $dateObj->format('j');
        $isPast = ($date < $today);
        $isDateToday = ($date === $today);
        $isSoon = (!$isPast && !$isDateToday && (strtotime($date) - strtotime($today)) / 86400 <= 3);

        if ($isDateToday) { $dateBadgeColor = '#2563eb'; $dateBadgeBg = '#eff6ff'; }
        elseif ($isPast) { $dateBadgeColor = '#dc2626'; $dateBadgeBg = '#fef2f2'; }
        elseif ($isSoon) { $dateBadgeColor = '#ea580c'; $dateBadgeBg = '#fff7ed'; }
        else { $dateBadgeColor = '#16a34a'; $dateBadgeBg = '#f0fdf4'; }
    ?>
    <div style="margin-bottom:16px; border:1px solid var(--gray-200); border-radius:8px; overflow:hidden;">
        <!-- Date header -->
        <div style="padding:10px 16px; background:<?= $dateBadgeBg ?>; border-bottom:1px solid var(--gray-200); display:flex; align-items:center; gap:12px;">
            <div style="font-size:24px; font-weight:700; color:<?= $dateBadgeColor ?>; min-width:36px; text-align:center;"><?= $dayNum ?></div>
            <div>
                <div style="font-size:14px; font-weight:600; color:var(--gray-800);"><?= $monthNames[$selectedMonth] ?> <?= $selectedYear ?></div>
                <?php if ($isDateToday): ?>
                    <span style="font-size:11px; color:<?= $dateBadgeColor ?>; font-weight:600;"><?= $lang('today') ?></span>
                <?php elseif (!$isPast): ?>
                    <?php $dLeft = (int)((strtotime($date) - strtotime($today)) / 86400); ?>
                    <span style="font-size:11px; color:<?= $dateBadgeColor ?>;"><?= $lang('in') ?> <?= $dLeft ?> <?= $dLeft === 1 ? $lang('day_singular') : $lang('days') ?></span>
                <?php else: ?>
                    <span style="font-size:11px; color:<?= $dateBadgeColor ?>;"><?= $lang('past_deadline') ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Obligation types for this date -->
        <div style="padding:12px 16px;">
            <?php foreach ($types as $type => $data):
                $clientCount = count($data['clients']);
                $uniqueId = 'dl-' . md5($date . $type);
            ?>
            <div style="margin-bottom:8px;">
                <div style="display:flex; align-items:center; gap:8px; cursor:pointer;" onclick="var el=document.getElementById('<?= $uniqueId ?>'); el.style.display = el.style.display==='none' ? 'block' : 'none';">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--gray-400)" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                    <strong style="font-size:14px;"><?= htmlspecialchars($type) ?></strong>
                    <span class="badge badge-info" style="font-size:11px;"><?= $clientCount ?> <?= $clientCount === 1 ? $lang('client_singular') : $lang('clients_plural') ?></span>
                    <?php if ($data['total_amount'] > 0): ?>
                        <span style="font-size:12px; color:var(--red-600); font-weight:600; margin-left:auto;"><?= number_format($data['total_amount'], 2, ',', ' ') ?> PLN</span>
                    <?php endif; ?>
                </div>
                <!-- Expandable client list -->
                <div id="<?= $uniqueId ?>" style="display:none; margin-top:6px; margin-left:20px;">
                    <?php foreach ($data['clients'] as $cl): ?>
                    <div style="display:flex; align-items:center; gap:8px; padding:4px 0; border-bottom:1px solid var(--gray-100); font-size:13px;">
                        <span style="color:var(--gray-700);"><?= htmlspecialchars($cl['name']) ?></span>
                        <?php if (!empty($cl['amount'])): ?>
                            <span style="color:var(--red-600); font-size:12px; margin-left:auto;"><?= number_format((float)$cl['amount'], 2, ',', ' ') ?> PLN</span>
                        <?php endif; ?>
                        <a href="/office/tax-calendar/config/<?= $cl['id'] ?>" style="color:var(--gray-400); font-size:11px;" title="<?= $lang('tax_calendar_config') ?>">&#9881;</a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ══════ CUSTOM EVENTS LIST ══════ -->
<?php if (!empty($customEvents)): ?>
<div class="form-card" style="padding:20px; margin-top:20px;">
    <h3 style="margin-bottom:12px;"><?= $lang('custom_events') ?></h3>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th><?= $lang('date') ?></th>
                    <th><?= $lang('title') ?></th>
                    <th><?= $lang('assigned_to') ?></th>
                    <th><?= $lang('description') ?></th>
                    <th style="width:60px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customEvents as $evt): ?>
                <tr>
                    <td><?= htmlspecialchars($evt['event_date']) ?></td>
                    <td>
                        <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:<?= htmlspecialchars($evt['color'] ?? '#6366f1') ?>; margin-right:6px;"></span>
                        <strong><?= htmlspecialchars($evt['title']) ?></strong>
                    </td>
                    <td>
                        <?php if (!empty($evt['client_name'])): ?>
                            <span style="color:var(--gray-600);"><?= htmlspecialchars($evt['client_name']) ?></span>
                        <?php elseif (!empty($evt['employee_name'])): ?>
                            <span style="color:var(--blue-600);"><?= htmlspecialchars($evt['employee_name']) ?></span>
                        <?php else: ?>
                            <span style="color:var(--gray-400);">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12px; color:var(--gray-500);"><?= htmlspecialchars($evt['description'] ?? '') ?></td>
                    <td>
                        <form method="POST" action="/office/tax-calendar/event/<?= $evt['id'] ?>/delete" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="month" value="<?= $selectedMonth ?>">
                            <input type="hidden" name="year" value="<?= $selectedYear ?>">
                            <button type="submit" class="btn btn-sm btn-danger" style="padding:2px 6px;" onclick="return confirm('<?= $lang('delete_event_confirm') ?>')" title="<?= $lang('delete') ?>">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ══════ ADD EVENT MODAL ══════ -->
<div id="addEventModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center; padding:16px;">
    <div style="background:white; border-radius:12px; padding:24px; max-width:480px; width:100%; max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,0.3);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="margin:0;"><?= $lang('add_event') ?></h3>
            <button type="button" onclick="document.getElementById('addEventModal').style.display='none'" style="background:none; border:none; cursor:pointer; padding:4px; color:var(--gray-400);">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form method="POST" action="/office/tax-calendar/event">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="redirect_month" value="<?= $selectedMonth ?>">
            <input type="hidden" name="redirect_year" value="<?= $selectedYear ?>">

            <div class="form-group">
                <label class="form-label"><?= $lang('recipient') ?> *</label>
                <?php $employees = \App\Models\OfficeEmployee::findByOffice((int) \App\Core\Session::get('office_id')); ?>
                <select name="target" class="form-input" required>
                    <option value="">-- <?= $lang('select_recipient') ?> --</option>
                    <optgroup label="<?= $lang('bulk_options') ?>">
                        <option value="all_clients"><?= $lang('all_clients_event') ?></option>
                        <?php if (!empty($employees)): ?>
                            <option value="all_employees"><?= $lang('all_employees_event') ?></option>
                        <?php endif; ?>
                    </optgroup>
                    <?php if (!empty($employees)): ?>
                    <optgroup label="<?= $lang('employee_clients') ?>">
                        <?php foreach ($employees as $emp): ?>
                            <option value="emp_clients_<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?> — <?= $lang('all_assigned_clients') ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="<?= $lang('single_employee') ?>">
                        <?php foreach ($employees as $emp): ?>
                            <option value="emp_<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endif; ?>
                    <optgroup label="<?= $lang('single_client') ?>">
                        <?php foreach ($clients as $c): ?>
                            <option value="client_<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?> (<?= $c['nip'] ?>)</option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label"><?= $lang('date') ?> *</label>
                <input type="date" name="event_date" class="form-input" required value="<?= sprintf('%04d-%02d-01', $selectedYear, $selectedMonth) ?>">
            </div>

            <div class="form-group">
                <label class="form-label"><?= $lang('title') ?> *</label>
                <input type="text" name="title" class="form-input" required maxlength="100" placeholder="<?= $lang('event_title_placeholder') ?>">
            </div>

            <div class="form-group">
                <label class="form-label"><?= $lang('description') ?></label>
                <textarea name="description" class="form-input" rows="2" maxlength="500" placeholder="<?= $lang('event_description_placeholder') ?>"></textarea>
            </div>

            <div class="form-group">
                <label class="form-label"><?= $lang('color') ?></label>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <?php
                    $colors = ['#6366f1', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#3b82f6', '#ef4444', '#64748b'];
                    foreach ($colors as $c):
                    ?>
                    <label style="cursor:pointer;">
                        <input type="radio" name="color" value="<?= $c ?>" <?= $c === '#6366f1' ? 'checked' : '' ?> style="display:none;">
                        <div class="color-swatch" style="width:28px; height:28px; border-radius:50%; background:<?= $c ?>; border:3px solid transparent; transition:border-color 0.15s;" onclick="this.parentElement.querySelector('input').checked=true; document.querySelectorAll('.color-swatch').forEach(s=>s.style.borderColor='transparent'); this.style.borderColor='var(--gray-800)';"></div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:20px;">
                <button type="button" class="btn" onclick="document.getElementById('addEventModal').style.display='none'"><?= $lang('cancel') ?></button>
                <button type="submit" class="btn btn-primary"><?= $lang('add_event') ?></button>
            </div>
        </form>
    </div>
</div>

<script>
// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.getElementById('addEventModal').style.display = 'none';
    }
});
// Close modal on backdrop click
document.getElementById('addEventModal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
// Mark first color swatch as selected on load
document.querySelector('.color-swatch')?.click();
</script>
