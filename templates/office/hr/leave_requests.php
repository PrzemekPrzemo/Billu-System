<div class="section-header">
    <div>
        <div class="breadcrumb-path" style="font-size:13px;color:var(--text-muted);margin-bottom:4px;">
            <a href="/office/hr/settings"><?= $lang('hr_module') ?></a> &rsaquo;
            <a href="/office/hr/<?= $clientId ?>/employees"><?= htmlspecialchars($client['company_name']) ?></a> &rsaquo;
            <?= $lang('hr_leaves') ?>
        </div>
        <h1><?= $lang('hr_leave_requests') ?></h1>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <a href="/office/hr/<?= $clientId ?>/employees" class="btn btn-secondary"><?= $lang('hr_employees') ?></a>
        <button class="btn btn-primary" onclick="document.getElementById('add-leave-modal').style.display='flex'">
            + <?= $lang('hr_add_leave_request') ?>
        </button>
    </div>
</div>

<?php include __DIR__ . '/../hr_nav.php'; ?>

<?php if ($flash_success): ?><div class="alert alert-success"><?= htmlspecialchars($flash_success) ?></div><?php endif; ?>
<?php if ($flash_error): ?><div class="alert alert-error"><?= htmlspecialchars($flash_error) ?></div><?php endif; ?>

<!-- Status filter tabs -->
<div class="tabs" style="margin-bottom:16px;">
    <a href="?status=" class="tab <?= !$status ? 'active' : '' ?>">Wszystkie</a>
    <a href="?status=pending" class="tab <?= $status === 'pending' ? 'active' : '' ?>">Oczekujące</a>
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
                <th><?= $lang('name') ?></th>
                <th>Typ urlopu</th>
                <th>Od</th><th>Do</th>
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
                </td>
                <td>
                    <?php if ($r['status'] === 'pending'): ?>
                    <div class="action-buttons">
                        <form method="POST" action="/office/hr/<?= $clientId ?>/leaves/<?= $r['id'] ?>/approve" class="inline-form">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <button type="submit" class="btn btn-xs btn-success">Zatwierdź</button>
                        </form>
                        <form method="POST" action="/office/hr/<?= $clientId ?>/leaves/<?= $r['id'] ?>/reject" class="inline-form">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="rejection_reason" value="">
                            <button type="submit" class="btn btn-xs btn-danger">Odrzuć</button>
                        </form>
                    </div>
                    <?php elseif ($r['status'] === 'approved' && $r['notes']): ?>
                        <span class="text-muted" style="font-size:12px;"><?= htmlspecialchars($r['notes']) ?></span>
                    <?php elseif ($r['status'] === 'rejected' && $r['rejection_reason']): ?>
                        <span class="text-muted" style="font-size:12px;">Powód: <?= htmlspecialchars($r['rejection_reason']) ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Add leave request modal -->
<div id="add-leave-modal" class="modal-overlay" style="display:none;" onclick="if(event.target===this)this.style.display='none'">
    <div class="modal-box" style="max-width:500px;">
        <div class="modal-header">
            <h3><?= $lang('hr_add_leave_request') ?></h3>
            <button onclick="document.getElementById('add-leave-modal').style.display='none'" class="modal-close">&times;</button>
        </div>
        <form method="POST" action="/office/hr/<?= $clientId ?>/leaves/create">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="form-group">
                <label class="form-label">Pracownik <span class="text-danger">*</span></label>
                <select name="employee_id" class="form-control" required>
                    <option value="">— wybierz —</option>
                    <?php
                    $empList = \App\Models\HrEmployee::findByClient($clientId, true);
                    foreach ($empList as $e): ?>
                    <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Typ urlopu <span class="text-danger">*</span></label>
                <select name="leave_type_id" class="form-control" required>
                    <?php foreach ($leaveTypes as $lt): ?>
                    <option value="<?= $lt['id'] ?>"><?= htmlspecialchars($lt['name_pl']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label">Od <span class="text-danger">*</span></label>
                    <input type="date" name="date_from" class="form-control" required value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Do <span class="text-danger">*</span></label>
                    <input type="date" name="date_to" class="form-control" required value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Uwagi</label>
                <textarea name="notes" class="form-control" rows="2"></textarea>
            </div>
            <div style="display:flex;gap:8px;margin-top:12px;">
                <button type="submit" class="btn btn-primary">Złóż wniosek</button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('add-leave-modal').style.display='none'">Anuluj</button>
            </div>
        </form>
    </div>
</div>