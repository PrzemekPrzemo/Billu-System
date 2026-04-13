<h2><?= $lang('tasks') ?></h2>

<!-- Summary badges -->
<div style="display:flex; gap:12px; margin-bottom:20px; flex-wrap:wrap;">
    <div class="stat-badge stat-open">
        <span class="stat-count"><?= $counts['open'] ?? 0 ?></span>
        <span class="stat-label"><?= $lang('task_open') ?></span>
    </div>
    <div class="stat-badge stat-progress">
        <span class="stat-count"><?= $counts['in_progress'] ?? 0 ?></span>
        <span class="stat-label"><?= $lang('task_in_progress') ?></span>
    </div>
    <div class="stat-badge stat-done">
        <span class="stat-count"><?= $counts['done'] ?? 0 ?></span>
        <span class="stat-label"><?= $lang('task_done') ?></span>
    </div>
</div>

<?php if (empty($tasks)): ?>
    <div class="alert alert-info"><?= $lang('no_tasks') ?></div>
<?php else: ?>
    <div class="card">
        <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th><?= $lang('task_title') ?></th>
                    <th><?= $lang('task_priority') ?></th>
                    <th><?= $lang('task_due_date') ?></th>
                    <th><?= $lang('task_status') ?></th>
                    <th><?= $lang('attachment') ?></th>
                    <th><?= $lang('actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tasks as $task):
                    $isOverdue = $task['due_date'] && $task['status'] !== 'done' && $task['due_date'] < date('Y-m-d');
                    $safePriority = in_array($task['priority'], ['low','normal','high'], true) ? $task['priority'] : 'normal';
                    $safeStatus = in_array($task['status'], ['open','in_progress','done'], true) ? $task['status'] : 'open';
                ?>
                    <tr class="<?= $isOverdue ? 'task-overdue' : '' ?>">
                        <td>
                            <strong><?= htmlspecialchars($task['title']) ?></strong>
                            <?php if ($task['description']): ?>
                                <br><small style="color:var(--text-muted);"><?= htmlspecialchars($task['description']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge priority-<?= $safePriority ?>"><?= $lang('priority_' . $safePriority) ?></span></td>
                        <td class="<?= $isOverdue ? 'text-danger' : '' ?>">
                            <?= $task['due_date'] ? date('d.m.Y', strtotime($task['due_date'])) : '-' ?>
                            <?= $isOverdue ? ' (' . $lang('overdue') . ')' : '' ?>
                        </td>
                        <td><span class="badge status-<?= $safeStatus ?>"><?= $lang('task_' . $safeStatus) ?></span></td>
                        <td>
                            <?php if (!empty($task['attachment_path'])): ?>
                                <a href="/client/tasks/attachment/<?= $task['id'] ?>" class="btn btn-sm" title="<?= htmlspecialchars($task['attachment_name'] ?? '') ?>">&#128206; <?= htmlspecialchars(mb_substr($task['attachment_name'] ?? '', 0, 20)) ?></a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($task['status'] === 'open'): ?>
                                <form method="post" action="/client/tasks/<?= $task['id'] ?>/status" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="status" value="in_progress">
                                    <button type="submit" class="btn btn-sm btn-primary"><?= $lang('mark_in_progress') ?></button>
                                </form>
                            <?php elseif ($task['status'] === 'in_progress'): ?>
                                <form method="post" action="/client/tasks/<?= $task['id'] ?>/status" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="status" value="done">
                                    <button type="submit" class="btn btn-sm btn-success"><?= $lang('mark_done') ?></button>
                                </form>
                            <?php else: ?>
                                <span style="color:var(--success);">&#10003; <?= $lang('task_done') ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
<?php endif; ?>
