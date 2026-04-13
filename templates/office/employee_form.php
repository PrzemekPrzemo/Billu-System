<div class="section-header">
    <h1><?= $employee ? ($lang('edit_employee')) : ($lang('add_employee')) ?></h1>
    <a href="/office/employees" class="btn btn-secondary"><?= $lang('back') ?></a>
</div>

<form method="POST" action="<?= $employee ? '/office/employees/' . $employee['id'] . '/update' : '/office/employees/create' ?>" class="form-card">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

    <div class="form-row">
        <div class="form-group">
            <label class="form-label"><?= $lang('employee_name') ?> *</label>
            <input type="text" name="name" class="form-input" required
                   value="<?= htmlspecialchars($employee['name'] ?? '') ?>"
                   placeholder="Jan Kowalski">
        </div>

        <div class="form-group">
            <label class="form-label"><?= $lang('position') ?></label>
            <input type="text" name="position" class="form-input"
                   value="<?= htmlspecialchars($employee['position'] ?? '') ?>"
                   placeholder="Ksiegowa">
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label class="form-label"><?= $lang('email') ?></label>
            <input type="email" name="email" class="form-input"
                   value="<?= htmlspecialchars($employee['email'] ?? '') ?>"
                   placeholder="jan@biuro.pl">
        </div>

        <div class="form-group">
            <label class="form-label"><?= $lang('phone') ?></label>
            <input type="text" name="phone" class="form-input"
                   value="<?= htmlspecialchars($employee['phone'] ?? '') ?>"
                   placeholder="+48 123 456 789">
        </div>
    </div>

    <!-- Password for login -->
    <div class="form-row">
        <div class="form-group">
            <label class="form-label"><?= $employee ? ($lang('new_password')) : ($lang('password')) ?><?= $employee ? '' : ' *' ?></label>
            <input type="password" name="<?= $employee ? 'new_password' : 'password' ?>" class="form-input"
                   <?= $employee ? '' : 'required' ?> minlength="12"
                   placeholder="<?= $employee ? ($lang('leave_empty_no_change')) : 'Min. 12 znakow' ?>">
            <?php if (!$employee): ?>
                <small class="form-hint"><?= $lang('password_requirements') ?></small>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label class="form-label"><?= $lang('confirm_password') ?></label>
            <input type="password" name="<?= $employee ? 'new_password_confirm' : 'password_confirm' ?>" class="form-input"
                   <?= $employee ? '' : 'required' ?> minlength="12"
                   placeholder="<?= $lang('confirm_password') ?>">
        </div>
    </div>

    <div class="form-group">
        <label class="checkbox-label">
            <input type="checkbox" name="force_password_change" value="1" <?= (!$employee || ($employee['force_password_change'] ?? 0)) ? 'checked' : '' ?>>
            <?= $lang('force_password_change') ?>
        </label>
    </div>

    <?php if ($employee): ?>
    <div class="form-group">
        <label class="checkbox-label">
            <input type="checkbox" name="is_active" value="1" <?= $employee['is_active'] ? 'checked' : '' ?>>
            <?= $lang('employee_active') ?>
        </label>
    </div>
    <?php if (!empty($employee['last_login_at'])): ?>
        <div class="form-group">
            <small class="text-muted"><?= $lang('last_login') ?>: <?= htmlspecialchars($employee['last_login_at']) ?></small>
        </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($clients)): ?>
    <div class="form-group">
        <label class="form-label"><?= $lang('assigned_clients') ?></label>
        <small class="form-hint" style="margin-bottom:8px;"><?= $lang('assigned_clients_hint') ?></small>
        <div class="client-checkboxes">
            <?php foreach ($clients as $c): ?>
            <label>
                <input type="checkbox" name="client_ids[]" value="<?= $c['id'] ?>"
                    <?= in_array($c['id'], $assignedClientIds) ? 'checked' : '' ?>>
                <span><?= htmlspecialchars($c['company_name']) ?></span>
                <span class="text-muted" style="font-size:12px;">(NIP: <?= htmlspecialchars($c['nip']) ?>)</span>
            </label>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $lang('save') ?></button>
        <a href="/office/employees" class="btn btn-secondary"><?= $lang('cancel') ?></a>
    </div>
</form>
