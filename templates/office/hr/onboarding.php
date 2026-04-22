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
            <a href="/office/hr/settings"><?= $lang('hr_module') ?></a> &rsaquo;
            <a href="/office/hr/<?= $clientId ?>/employees"><?= htmlspecialchars($client['company_name']) ?></a> &rsaquo;
            <a href="/office/hr/<?= $clientId ?>/employees/<?= $empId ?>"><?= htmlspecialchars($employee['full_name']) ?></a> &rsaquo;
            <?= htmlspecialchars($phaseLabel) ?>
        </div>
        <h1>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
            <?= htmlspecialchars($phaseLabel) ?> — <?= htmlspecialchars($employee['full_name']) ?>
        </h1>
    </div>
    <div>
        <a href="/office/hr/<?= $clientId ?>/employees/<?= $empId ?>" class="btn btn-secondary"><?= $lang('back') ?></a>
    </div>
</div>

<?php include __DIR__ . '/../hr_nav.php'; ?>

<?php if ($flash_success): ?><div class="alert alert-success"><?= htmlspecialchars($flash_success) ?></div><?php endif; ?>
<?php if ($flash_error): ?><div class="alert alert-error"><?= htmlspecialchars($flash_error) ?></div><?php endif; ?>

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
$barColor = $pct >= 100 ? '#16a34a' : ($pct > 50 ? '#3b82f6' : '#f59e0b');
?>
<div style="margin-bottom:20px;">
    <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:13px;">
        <span><?= $lang('hr_onboarding_progress') ?>: <strong><?= $prog['done'] ?>/<?= $prog['total'] ?></strong> <?= $lang('tasks') ?></span>
        <span style="font-weight:600;color:<?= $barColor ?>"><?= $pct ?>%</span>
    </div>
    <div style="background:#e5e7eb;border-radius:6px;height:8px;overflow:hidden;">
        <div style="width:<?= $pct ?>%;background:<?= $barColor ?>;height:100%;border-radius:6px;transition:width .3s;"></div>
    </div>
    <?php if ($pct >= 100): ?>
    <p style="margin:8px 0 0;font-size:13px;color:#16a34a;font-weight:600;">
        &#10003; <?= $lang('hr_onboarding_complete') ?>
    </p>
    <?php endif; ?>
</div>

<!-- Task list grouped by category -->
<?php if (empty($grouped)): ?>
<div class="empty-state">
    <p><?= $lang('hr_onboarding_no_tasks') ?></p>
</div>
<?php else: ?>

<?php foreach ($grouped as $category => $categoryTasks): ?>
<div class="card" style="margin-bottom:14px;">
    <div class="card-header" style="padding:10px 16px;">
        <h3 style="margin:0;font-size:14px;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);">
            <?= htmlspecialchars($categoryLabels[$category] ?? $category) ?>
        </h3>
    </div>
    <div class="card-body" style="padding:0;">
        <?php foreach ($categoryTasks as $task):
            $isDone    = (bool)$task['is_done'];
            $isOverdue = !$isDone && !empty($task['due_date']) && $task['due_date'] < date('Y-m-d');
            $rowBg     = $isOverdue ? '#fff5f5' : ($isDone ? '#f0fdf4' : 'transparent');
        ?>
        <div style="display:flex;align-items:center;gap:12px;padding:12px 16px;
                    border-bottom:1px solid #f3f4f6;background:<?= $rowBg ?>;">

            <!-- Toggle form -->
            <form method="POST" action="/office/hr/<?= $clientId ?>/employees/<?= $empId ?>/onboarding/<?= $task['id'] ?>"
                  style="flex-shrink:0;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <button type="submit" style="background:none;border:none;cursor:pointer;padding:0;"
                        title="<?= $isDone ? 'Oznacz jako niewykonane' : 'Oznacz jako wykonane' ?>">
                    <?php if ($isDone): ?>
                    <span style="display:inline-flex;width:22px;height:22px;border-radius:50%;background:#16a34a;
                                 align-items:center;justify-content:center;color:#fff;font-size:14px;">&#10003;</span>
                    <?php else: ?>
                    <span style="display:inline-flex;width:22px;height:22px;border-radius:50%;border:2px solid #d1d5db;
                                 background:#fff;"></span>
                    <?php endif; ?>
                </button>
            </form>

            <!-- Task info -->
            <div style="flex:1;">
                <div style="font-size:14px;<?= $isDone ? 'text-decoration:line-through;color:var(--text-muted);' : '' ?>
                            <?= $isOverdue ? 'color:#dc2626;font-weight:500;' : '' ?>">
                    <?= htmlspecialchars($task['title']) ?>
                </div>
                <?php if ($isDone && $task['done_at']): ?>
                <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">
                    <?= $lang('hr_task_done') ?>: <?= date('d.m.Y H:i', strtotime($task['done_at'])) ?>
                </div>
                <?php elseif ($isOverdue): ?>
                <div style="font-size:11px;color:#dc2626;margin-top:2px;">
                    <?= $lang('hr_task_overdue') ?>: <?= htmlspecialchars($task['due_date']) ?>
                </div>
                <?php elseif (!empty($task['due_date'])): ?>
                <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">
                    <?= $lang('hr_task_due') ?>: <?= htmlspecialchars($task['due_date']) ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Status badge -->
            <div style="flex-shrink:0;">
                <?php if ($isDone): ?>
                <span class="badge badge-success"><?= $lang('hr_task_done') ?></span>
                <?php elseif ($isOverdue): ?>
                <span class="badge badge-danger"><?= $lang('hr_task_overdue') ?></span>
                <?php else: ?>
                <span class="badge badge-default"><?= $lang('hr_task_pending') ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<?php endif; ?>