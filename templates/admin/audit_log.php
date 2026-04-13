<div class="section-header">
    <h1><?= $lang('audit_log') ?></h1>
    <a href="/admin/audit-log/export?<?= http_build_query($filters ?? []) ?>" class="btn btn-secondary"><?= $lang('export_csv') ?></a>
</div>

<form method="GET" action="/admin/audit-log" class="form-card form-inline">
    <div class="form-row">
        <div class="form-group">
            <label class="form-label"><?= $lang('action') ?></label>
            <select name="action" class="form-input">
                <option value="">--</option>
                <option value="login" <?= ($filters['action'] ?? '') === 'login' ? 'selected' : '' ?>>Login</option>
                <option value="logout" <?= ($filters['action'] ?? '') === 'logout' ? 'selected' : '' ?>>Logout</option>
                <option value="create" <?= ($filters['action'] ?? '') === 'create' ? 'selected' : '' ?>>Create</option>
                <option value="update" <?= ($filters['action'] ?? '') === 'update' ? 'selected' : '' ?>>Update</option>
                <option value="delete" <?= ($filters['action'] ?? '') === 'delete' ? 'selected' : '' ?>>Delete</option>
                <option value="import" <?= ($filters['action'] ?? '') === 'import' ? 'selected' : '' ?>>Import</option>
                <option value="verify" <?= ($filters['action'] ?? '') === 'verify' ? 'selected' : '' ?>>Verify</option>
                <option value="impersonate" <?= ($filters['action'] ?? '') === 'impersonate' ? 'selected' : '' ?>>Impersonate</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label"><?= $lang('date_from') ?></label>
            <input type="date" name="date_from" class="form-input"
                   value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label class="form-label"><?= $lang('date_to') ?></label>
            <input type="date" name="date_to" class="form-input"
                   value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>">
        </div>

        <div class="form-group">
            <button type="submit" class="btn btn-primary"><?= $lang('filter') ?></button>
        </div>
    </div>
</form>

<div class="section">
    <table class="table">
        <thead>
            <tr>
                <th><?= $lang('date') ?></th>
                <th><?= $lang('user_type') ?></th>
                <th><?= $lang('user_id') ?></th>
                <th><?= $lang('action') ?></th>
                <th><?= $lang('details') ?></th>
                <th><?= $lang('ip_address') ?></th>
                <th><?= $lang('entity') ?></th>
                <th><?= $lang('entity') ?> ID</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr><td colspan="8" class="text-center text-muted"><?= $lang('no_results') ?></td></tr>
            <?php endif; ?>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td><?= $log['created_at'] ?></td>
                <td><span class="badge badge-<?= $log['user_type'] === 'admin' ? 'info' : 'default' ?>"><?= htmlspecialchars($log['user_type']) ?></span></td>
                <td><?= $log['user_id'] ?></td>
                <td><?= htmlspecialchars($log['action']) ?></td>
                <td class="text-muted"><?= htmlspecialchars(mb_substr($log['details'] ?? '', 0, 120)) ?></td>
                <td><?= htmlspecialchars($log['ip_address'] ?? '') ?></td>
                <td><?= htmlspecialchars($log['entity_type'] ?? '') ?></td>
                <td><?= $log['entity_id'] ?? '' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php require __DIR__ . '/../partials/pagination.php'; ?>
</div>

<div class="section">
    <h2><?= $lang('login_history') ?></h2>
    <table class="table">
        <thead>
            <tr>
                <th><?= $lang('date') ?></th>
                <th><?= $lang('user_type') ?></th>
                <th><?= $lang('user_id') ?></th>
                <th><?= $lang('ip_address') ?></th>
                <th><?= $lang('user_agent') ?></th>
                <th><?= $lang('status') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($loginHistory)): ?>
                <tr><td colspan="6" class="text-center text-muted"><?= $lang('no_results') ?></td></tr>
            <?php endif; ?>
            <?php foreach ($loginHistory as $entry): ?>
            <tr>
                <td><?= $entry['created_at'] ?></td>
                <td><span class="badge badge-<?= $entry['user_type'] === 'admin' ? 'info' : 'default' ?>"><?= htmlspecialchars($entry['user_type']) ?></span></td>
                <td><?= $entry['user_id'] ?></td>
                <td><?= htmlspecialchars($entry['ip_address'] ?? '') ?></td>
                <td class="text-muted"><?= htmlspecialchars(mb_substr($entry['user_agent'] ?? '', 0, 80)) ?></td>
                <td>
                    <?php if ($entry['success']): ?>
                        <span class="badge badge-success"><?= $lang('success') ?></span>
                    <?php else: ?>
                        <span class="badge badge-error"><?= $lang('failed') ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
