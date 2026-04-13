<div class="section-header">
    <h1><?= $lang('employees') ?></h1>
    <a href="/office/employees/create" class="btn btn-primary">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <?= $lang('add_employee') ?>
    </a>
</div>

<?php if (empty($employees)): ?>
<div class="empty-state">
    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:12px;opacity:0.4;"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
    <p><?= $lang('no_employees') ?></p>
</div>
<?php else: ?>
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th><?= $lang('name') ?></th>
                <th><?= $lang('position') ?></th>
                <th><?= $lang('email') ?></th>
                <th><?= $lang('phone') ?></th>
                <th><?= $lang('assigned_clients') ?></th>
                <th><?= $lang('status') ?></th>
                <th><?= $lang('actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($employees as $emp): ?>
            <tr>
                <td><strong><?= htmlspecialchars($emp['name']) ?></strong></td>
                <td class="text-muted"><?= htmlspecialchars($emp['position'] ?? '-') ?></td>
                <td><?= htmlspecialchars($emp['email'] ?? '-') ?></td>
                <td><?= htmlspecialchars($emp['phone'] ?? '-') ?></td>
                <td>
                    <span class="badge badge-info"><?= (int)($emp['client_count'] ?? 0) ?></span>
                </td>
                <td>
                    <?php if ($emp['is_active']): ?>
                        <span class="badge badge-success"><?= $lang('active') ?></span>
                    <?php else: ?>
                        <span class="badge badge-default"><?= $lang('inactive') ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="action-buttons">
                        <a href="/office/employees/<?= $emp['id'] ?>/edit" class="btn btn-xs"><?= $lang('edit') ?></a>
                        <form method="POST" action="/office/employees/<?= $emp['id'] ?>/delete" class="inline-form">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <button type="submit" class="btn btn-xs btn-danger" onclick="return confirm('<?= $lang('confirm_delete') ?>')"><?= $lang('delete') ?></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
