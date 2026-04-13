<h1><?= $lang('scheduled_exports') ?></h1>

<?php if (!empty($_SESSION['success'])): ?>
    <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['error'])): ?>
    <div class="alert alert-error"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
<?php endif; ?>

<!-- Create new scheduled export -->
<div class="form-card" style="margin-bottom:20px;">
    <h3><?= $lang('scheduled_export_new') ?></h3>
    <form method="POST" action="/admin/scheduled-exports/create">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div class="form-row">
            <div class="form-group">
                <label class="form-label"><?= $lang('client') ?></label>
                <select name="client_id" class="form-input" required>
                    <option value=""><?= $lang('select_client') ?></option>
                    <?php foreach ($clients as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?> (<?= $c['nip'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><?= $lang('format') ?></label>
                <select name="format" class="form-input" required>
                    <option value="excel">Excel (XLS)</option>
                    <option value="pdf">PDF</option>
                    <option value="jpk_fa">JPK_FA (XML)</option>
                    <option value="jpk_vat7">JPK_VAT7</option>
                    <option value="comarch_optima">Comarch Optima</option>
                    <option value="sage">Sage Symfonia</option>
                    <option value="enova">enova365</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><?= $lang('frequency') ?></label>
                <select name="frequency" class="form-input">
                    <option value="monthly"><?= $lang('monthly') ?></option>
                    <option value="weekly"><?= $lang('weekly') ?></option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label"><?= $lang('day_of_month') ?></label>
                <input type="number" name="day_of_month" class="form-input" value="5" min="1" max="28">
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-input" required placeholder="email@example.com">
            </div>
            <div class="form-group" style="align-self:flex-end;">
                <label class="form-label">
                    <input type="checkbox" name="include_rejected" value="1"> <?= $lang('include_rejected') ?>
                </label>
            </div>
            <div class="form-group" style="align-self:flex-end;">
                <button type="submit" class="btn btn-primary"><?= $lang('create') ?></button>
            </div>
        </div>
    </form>
</div>

<!-- Existing scheduled exports -->
<?php if (!empty($exports)): ?>
<div class="section">
    <table class="table">
        <thead>
            <tr>
                <th><?= $lang('client') ?></th>
                <th><?= $lang('format') ?></th>
                <th><?= $lang('frequency') ?></th>
                <th>Email</th>
                <th><?= $lang('last_run') ?></th>
                <th><?= $lang('next_run') ?></th>
                <th><?= $lang('status') ?></th>
                <th><?= $lang('actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($exports as $e): ?>
            <tr>
                <td><?= htmlspecialchars($e['company_name']) ?> <small>(<?= $e['nip'] ?>)</small></td>
                <td><code><?= strtoupper($e['format']) ?></code></td>
                <td><?= $lang($e['frequency']) ?></td>
                <td><?= htmlspecialchars($e['email']) ?></td>
                <td><?= $e['last_run_at'] ? date('d.m.Y H:i', strtotime($e['last_run_at'])) : '<span class="text-muted">—</span>' ?></td>
                <td><?= $e['next_run_at'] ? date('d.m.Y H:i', strtotime($e['next_run_at'])) : '<span class="text-muted">—</span>' ?></td>
                <td>
                    <?php if ($e['is_active']): ?>
                        <span class="badge badge-success"><?= $lang('active') ?></span>
                    <?php else: ?>
                        <span class="badge badge-error"><?= $lang('inactive') ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <div style="display:flex;gap:4px;flex-wrap:wrap;">
                        <form method="POST" action="/admin/scheduled-exports/<?= $e['id'] ?>/run" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <button type="submit" class="btn btn-sm btn-primary" title="<?= $lang('run_now') ?>"><?= $lang('run_now') ?></button>
                        </form>
                        <form method="POST" action="/admin/scheduled-exports/<?= $e['id'] ?>/toggle" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <button type="submit" class="btn btn-sm"><?= $e['is_active'] ? $lang('deactivate') : $lang('activate') ?></button>
                        </form>
                        <form method="POST" action="/admin/scheduled-exports/<?= $e['id'] ?>/delete" style="display:inline;" onsubmit="return confirm('<?= $lang('confirm_delete') ?>');">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <button type="submit" class="btn btn-sm btn-danger"><?= $lang('delete') ?></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
<div class="empty-state">
    <p class="text-muted"><?= $lang('no_scheduled_exports') ?></p>
</div>
<?php endif; ?>
