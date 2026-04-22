<?php
$categoryLabels = [
    'documents' => 'Dokumenty',
    'medical'   => 'Badania lekarskie',
    'training'  => 'Szkolenia',
    'payroll'   => 'Kadry / ZUS',
    'other'     => 'Inne',
];

$phaseLabel = $phase === 'onboarding' ? $lang('hr_onboarding') : $lang('hr_offboarding');
?>

<div class="section-header">
    <div>
        <div class="breadcrumb-path" style="font-size:13px;color:var(--text-muted);margin-bottom:4px;">
            <a href="/client/hr/employees"><?= $lang('hr_employees') ?></a> &rsaquo;
            <a href="/client/hr/employees/<?= $employee['id'] ?>"><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></a> &rsaquo;
            <?= $phaseLabel ?>
        </div>
        <h1><?= $phaseLabel ?> — <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></h1>
    </div>
    <a href="/client/hr/employees/<?= $employee['id'] ?>" class="btn btn-secondary"><?= $lang('back') ?></a>
</div>

<?php include __DIR__ . '/../hr_nav.php'; ?>

<!-- Phase tabs -->
<div style="display:flex;gap:0;margin-bottom:20px;border-bottom:2px solid #e5e7eb;">
    <?php foreach (['onboarding','offboarding'] as $tab):
        $tabLabel = $tab === 'onboarding' ? $lang('hr_onboarding') : $lang('hr_offboarding');
        $prog     = $tab === 'onboarding' ? $progressOn : $progressOff;
        $isActive = $phase === $tab;
        $pct      = $prog['pct'];
        $badgeColor = $pct >= 100 ? '#16a34a' : ($pct > 0 ? '#f59e0b' : '#9ca3af');
    ?>
    <a href="?phase=<?= $tab ?>"
       style="padding:10px 18px;font-size:14px;font-weight:<?= $isActive?'600':'400' ?>;
              border-bottom:<?= $isActive?'2px solid var(--primary)':'' ?>;
              margin-bottom:-2px;text-decoration:none;color:<?= $isActive?'var(--primary)':'var(--text-muted)' ?>;">
        <?= htmlspecialchars($tabLabel) ?>
        <span style="margin-left:6px;font-size:11px;background:<?= $badgeColor ?>;color:#fff;border-radius:10px;padding:1px 7px;">
            <?= $prog['done'] ?>/<?= $prog['total'] ?>
        </span>
    </a>
    <?php endforeach; ?>
</div>

<!-- Progress bar -->
<?php
$prog = $phase === 'onboarding' ? $progressOn : $progressOff;
$pct  = $prog['pct'];
$barColor = $pct >= 100 ? '#16a34a' : ($pct > 50 ? '#2563eb' : ($pct > 0 ? '#f59e0b' : '#e5e7eb'));
?>
<div style="margin-bottom:20px;">
    <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
        <span><?= $prog['done'] ?> z <?= $prog['total'] ?> zadań ukończonych</span>
        <span style="font-weight:600;"><?= $pct ?>%</span>
    </div>
    <div style="height:8px;background:#e5e7eb;border-radius:4px;overflow:hidden;">
        <div style="width:<?= $pct ?>%;height:100%;background:<?= $barColor ?>;border-radius:4px;transition:width .3s;"></div>
    </div>
</div>

<!-- Tasks grouped by category -->
<?php
$grouped = [];
foreach ($tasks as $task) {
    $grouped[$task['category']][] = $task;
}
?>

<?php if (empty($tasks)): ?>
<div class="empty-state"><p>Brak zadań do wyświetlenia.</p></div>
<?php else: ?>
<?php foreach ($grouped as $cat => $catTasks): ?>
<div class="card" style="margin-bottom:12px;">
    <div class="card-header" style="padding:10px 16px;">
        <strong><?= $categoryLabels[$cat] ?? ucfirst($cat) ?></strong>
        <span style="font-size:12px;color:var(--text-muted);margin-left:8px;">
            (<?= count(array_filter($catTasks, fn($t) => $t['is_done'])) ?>/<?= count($catTasks) ?>)
        </span>
    </div>
    <div class="card-body" style="padding:0;">
        <?php foreach ($catTasks as $task):
            $done = (bool)$task['is_done'];
            $overdue = !$done && $task['due_date'] && strtotime($task['due_date']) < time();
        ?>
        <div style="display:flex;align-items:center;gap:10px;padding:10px 16px;border-bottom:1px solid var(--border);
                     <?= $overdue ? 'background:#fff5f5;' : '' ?>">
            <span style="font-size:18px;color:<?= $done ? '#16a34a' : '#d1d5db' ?>;">
                <?= $done ? '&#10003;' : '&#9675;' ?>
            </span>
            <div style="flex:1;">
                <span style="<?= $done ? 'text-decoration:line-through;color:var(--text-muted);' : '' ?>"><?= htmlspecialchars($task['title']) ?></span>
                <?php if ($task['due_date']): ?>
                    <span style="font-size:11px;color:<?= $overdue ? 'var(--danger)' : 'var(--text-muted)' ?>;margin-left:8px;">
                        do <?= $task['due_date'] ?><?= $overdue ? ' (przeterminowane!)' : '' ?>
                    </span>
                <?php endif; ?>
            </div>
            <?php if ($done && $task['done_at']): ?>
            <span style="font-size:11px;color:var(--text-muted);">
                <?= date('d.m.Y', strtotime($task['done_at'])) ?>
            </span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
