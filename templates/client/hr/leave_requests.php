<div class="section-header">
    <div>
        <h1><?= $lang('hr_leave_requests') ?></h1>
        <?php if ($pendingCount > 0): ?>
            <span class="badge badge-warning" style="margin-left:8px;"><?= $pendingCount ?> oczekujących</span>
        <?php endif; ?>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="/client/hr/leaves/calendar" class="btn btn-secondary"><?= $lang('hr_leave_calendar') ?></a>
        <button class="btn btn-primary" onclick="document.getElementById('add-leave-modal').style.display='flex'">+ <?= $lang('hr_leave_create') ?></button>
    </div>
</div>

<?php include __DIR__ . '/../hr_nav.php'; ?>

<?php if ($flash_success): ?><div class="alert alert-success"><?= htmlspecialchars($flash_success) ?></div><?php endif; ?>
<?php if ($flash_error): ?><div class="alert alert-error"><?= htmlspecialchars($flash_error) ?></div><?php endif; ?>

<!-- Status filter -->
<div class="tabs" style="margin-bottom:16px;">
    <a href="?status=" class="tab <?= !$status ? 'active' : '' ?>">Wszystkie</a>
    <a href="?status=pending" class="tab <?= $status === 'pending' ? 'active' : '' ?>">Oczekujące <?php if ($pendingCount > 0): ?><span class="sidebar-badge"><?= $pendingCount ?></span><?php endif; ?></a>
    <a href="?status=approved" class="tab <?= $status === 'approved' ? 'active' : '' ?>">Zatwierdzone</a>
    <a href="?status=rejected" class="tab <?= $status === 'rejected' ? 'active' : '' ?>">Odrzucone</a>
</div>

<?php if (empty($requests)): ?>
<div class="empty-state"><p><?= $lang('hr_no_leave_requests') ?></p></div>
<?php else: ?>
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th><?= $lang('hr_leave_employee') ?></th>
                <th><?= $lang('hr_leave_type') ?></th>
                <th><?= $lang('hr_leave_date_from') ?></th>
                <th><?= $lang('hr_leave_date_to') ?></th>
                <th>Dni</th>
                <th><?= $lang('status') ?></th>
                <th><?= $lang('actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($requests as $r): ?>
            <tr>
                <td><strong><?= htmlspecialchars($r['employee_name']) ?></strong></td>
                <td><?= htmlspecialchars($r['leave_type_name']) ?></td>
                <td><?= htmlspecialchars($r['date_from']) ?></td>
                <td><?= htmlspecialchars($r['date_to']) ?></td>
                <td><?= (float)$r['days_count'] ?></td>
                <td>
                    <?php $cls = match($r['status']) { 'approved' => 'badge-success', 'rejected' => 'badge-danger', 'cancelled' => 'badge-default', default => 'badge-warning' }; ?>
                    <span class="badge <?= $cls ?>"><?= $lang('hr_leave_' . $r['status']) ?></span>
                    <?php if ($r['status'] === 'rejected' && $r['rejection_reason']): ?>
                        <div style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($r['rejection_reason']) ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($r['status'] === 'pending'): ?>
                    <div class="action-buttons" style="display:flex;gap:4px;flex-wrap:wrap;">
                        <form method="POST" action="/client/hr/leaves/<?= $r['id'] ?>/approve" class="inline-form">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <button type="submit" class="btn btn-xs btn-success">Zatwierdź</button>
                        </form>
                        <form method="POST" action="/client/hr/leaves/<?= $r['id'] ?>/reject" class="inline-form"
                              onsubmit="let r=prompt('Powód odrzucenia (opcjonalnie):');if(r!==null){this.querySelector('[name=rejection_reason]').value=r;return true;}return false;">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="rejection_reason" value="">
                            <button type="submit" class="btn btn-xs btn-danger">Odrzuć</button>
                        </form>
                        <form method="POST" action="/client/hr/leaves/<?= $r['id'] ?>/cancel" class="inline-form"
                              onsubmit="return confirm('<?= $lang('hr_leave_cancel_confirm') ?>');">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <button type="submit" class="btn btn-xs btn-secondary"><?= $lang('hr_leave_cancel') ?></button>
                        </form>
                    </div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Add Leave Request Modal -->
<div id="add-leave-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div class="card" style="width:100%;max-width:480px;margin:24px;">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <h3 style="margin:0;"><?= $lang('hr_leave_create') ?></h3>
            <button onclick="document.getElementById('add-leave-modal').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--text-muted);">&times;</button>
        </div>
        <div class="card-body">
            <form method="POST" action="/client/hr/leaves/create">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                <div class="form-group">
                    <label class="form-label"><?= $lang('hr_leave_employee') ?> *</label>
                    <select name="employee_id" class="form-control" required>
                        <option value="">— <?= $lang('hr_leave_employee') ?> —</option>
                        <?php foreach ($employees as $emp): ?>
                        <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label"><?= $lang('hr_leave_type') ?> *</label>
                    <select name="leave_type_id" class="form-control" required>
                        <option value="">— <?= $lang('hr_leave_type') ?> —</option>
                        <?php foreach ($leaveTypes as $lt): ?>
                        <option value="<?= $lt['id'] ?>"><?= htmlspecialchars($lt['name_pl']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label class="form-label"><?= $lang('hr_leave_date_from') ?> *</label>
                        <input type="date" name="date_from" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= $lang('hr_leave_date_to') ?> *</label>
                        <input type="date" name="date_to" class="form-control" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label"><?= $lang('hr_leave_notes') ?></label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="<?= $lang('hr_leave_notes') ?>"></textarea>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;"><?= $lang('hr_leave_create') ?></button>
            </form>
        </div>
    </div>
</div>
