<h2><?= $lang('tasks') ?></h2>

<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <span><?= $lang('new_task') ?></span>
    </div>
    <div class="card-body">
        <form method="post" action="/office/tasks/create" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="responsive-grid-2" style="margin-bottom:12px;">
                <div class="form-group">
                    <label class="form-label"><?= $lang('select_client') ?></label>
                    <select name="client_id" class="form-input" required>
                        <option value="">-- <?= $lang('select_client') ?> --</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('task_title') ?></label>
                    <input type="text" name="title" class="form-input" required maxlength="255">
                </div>
            </div>
            <div class="form-group" style="margin-bottom:12px;">
                <label class="form-label"><?= $lang('task_description') ?></label>
                <textarea name="description" class="form-input" rows="2"></textarea>
            </div>
            <div class="responsive-grid-3" style="margin-bottom:12px;">
                <div class="form-group">
                    <label class="form-label"><?= $lang('task_priority') ?></label>
                    <select name="priority" class="form-input">
                        <option value="low"><?= $lang('priority_low') ?></option>
                        <option value="normal" selected><?= $lang('priority_normal') ?></option>
                        <option value="high"><?= $lang('priority_high') ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('task_due_date') ?> *</label>
                    <input type="date" name="due_date" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('attachment') ?></label>
                    <input type="file" name="attachment" class="form-input" accept=".pdf,.txt,.xls,.xlsx">
                    <small style="color:var(--text-muted);">PDF, TXT, XLS/XLSX — max 3 MB</small>
                </div>
            </div>
            <div class="responsive-grid-2" style="margin-bottom:12px;">
                <div class="form-group">
                    <label class="form-label" style="display:flex; align-items:center; gap:6px;">
                        <input type="checkbox" name="is_billable" value="1" id="is_billable_cb" onchange="document.getElementById('task_price_group').style.display = this.checked ? 'block' : 'none';">
                        Zadanie płatne
                    </label>
                </div>
                <div class="form-group" id="task_price_group" style="display:none;">
                    <label class="form-label">Cena zadania (PLN)</label>
                    <input type="number" name="task_price" class="form-input" step="0.01" min="0" placeholder="0.00">
                </div>
            </div>
            <div style="margin-bottom:12px;">
                <button type="submit" class="btn btn-primary"><?= $lang('create') ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Filters -->
<div style="margin-bottom:16px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
    <form method="get" action="/office/tasks" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
        <select name="client_id" class="form-input" style="width:auto; min-width:180px;" onchange="this.form.submit()">
            <option value=""><?= $lang('all_clients') ?></option>
            <?php foreach ($clients as $c): ?>
                <option value="<?= $c['id'] ?>" <?= ($filterClientId ?? '') == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['company_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status" class="form-input" style="width:auto;" onchange="this.form.submit()">
            <option value=""><?= $lang('all_statuses') ?></option>
            <option value="open" <?= ($filterStatus ?? '') === 'open' ? 'selected' : '' ?>><?= $lang('task_open') ?></option>
            <option value="in_progress" <?= ($filterStatus ?? '') === 'in_progress' ? 'selected' : '' ?>><?= $lang('task_in_progress') ?></option>
            <option value="done" <?= ($filterStatus ?? '') === 'done' ? 'selected' : '' ?>><?= $lang('task_done') ?></option>
        </select>
    </form>
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
                    <th><?= $lang('client') ?></th>
                    <th><?= $lang('task_priority') ?></th>
                    <th><?= $lang('task_due_date') ?></th>
                    <th><?= $lang('task_status') ?></th>
                    <th>Płatne</th>
                    <th><?= $lang('attachment') ?></th>
                    <th><?= $lang('actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tasks as $task):
                    $isOverdue = $task['due_date'] && $task['status'] !== 'done' && $task['due_date'] < date('Y-m-d');
                ?>
                    <tr class="<?= $isOverdue ? 'task-overdue' : '' ?>">
                        <td>
                            <strong><?= htmlspecialchars($task['title']) ?></strong>
                            <?php if ($task['description']): ?>
                                <br><small style="color:var(--text-muted);"><?= htmlspecialchars(mb_substr($task['description'], 0, 80)) ?><?= mb_strlen($task['description'] ?? '') > 80 ? '...' : '' ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($task['client_name'] ?? '') ?></td>
                        <td><span class="badge priority-<?= $task['priority'] ?>"><?= $lang('priority_' . $task['priority']) ?></span></td>
                        <td class="<?= $isOverdue ? 'text-danger' : '' ?>">
                            <?= $task['due_date'] ? date('d.m.Y', strtotime($task['due_date'])) : '-' ?>
                            <?= $isOverdue ? ' (' . $lang('overdue') . ')' : '' ?>
                        </td>
                        <td><span class="badge status-<?= $task['status'] ?>"><?= $lang('task_' . $task['status']) ?></span></td>
                        <td>
                            <?php if (!empty($task['is_billable'])): ?>
                                <span class="badge status-open">Tak</span>
                                <?php if ($task['task_price'] !== null): ?>
                                    <br><small><?= number_format((float)$task['task_price'], 2, ',', ' ') ?> PLN</small>
                                <?php endif; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($task['attachment_path'])): ?>
                                <a href="/office/tasks/attachment/<?= $task['id'] ?>" class="btn btn-sm" title="<?= htmlspecialchars($task['attachment_name'] ?? '') ?>">&#128206; <?= htmlspecialchars(mb_substr($task['attachment_name'] ?? '', 0, 20)) ?></a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex; gap:4px; flex-wrap:wrap;">
                                <?php if ($task['status'] !== 'done'): ?>
                                    <form method="post" action="/office/tasks/<?= $task['id'] ?>/update" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="status" value="done">
                                        <button type="submit" class="btn btn-sm btn-success" title="<?= $lang('mark_done') ?>">&#10003;</button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" action="/office/tasks/<?= $task['id'] ?>/delete" style="display:inline;" onsubmit="return confirm('<?= $lang('confirm_delete') ?>');">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" title="<?= $lang('delete') ?>">&times;</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
<?php endif; ?>
