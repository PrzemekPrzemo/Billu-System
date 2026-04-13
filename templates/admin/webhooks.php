<h1><?= $lang('webhooks') ?></h1>

<div class="form-card" style="margin-bottom: 20px;">
    <h3><?= $lang('add_webhook') ?></h3>
    <form method="POST" action="/admin/webhooks/create">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div class="form-row">
            <div class="form-group">
                <label class="form-label"><?= $lang('webhook_url') ?> *</label>
                <input type="url" name="url" class="form-input" required placeholder="https://example.com/webhook">
            </div>
            <div class="form-group">
                <label class="form-label"><?= $lang('client') ?> (<?= $lang('optional') ?>)</label>
                <select name="client_id" class="form-input">
                    <option value=""><?= $lang('all_clients') ?></option>
                    <?php foreach ($clients as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?> (<?= $c['nip'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><?= $lang('webhook_events') ?></label>
                <select name="events" class="form-input">
                    <option value="all">all</option>
                    <option value="batch.created">batch.created</option>
                    <option value="batch.finalized">batch.finalized</option>
                    <option value="invoice.verified">invoice.verified</option>
                    <option value="import.completed">import.completed</option>
                </select>
            </div>
        </div>
        <button type="submit" class="btn btn-primary"><?= $lang('add_webhook') ?></button>
    </form>
</div>

<?php if (empty($webhooks)): ?>
    <p class="text-muted"><?= $lang('no_results') ?></p>
<?php else: ?>
<table class="table">
    <thead>
        <tr>
            <th>URL</th>
            <th><?= $lang('client') ?></th>
            <th><?= $lang('webhook_events') ?></th>
            <th><?= $lang('status') ?></th>
            <th><?= $lang('last_triggered') ?></th>
            <th><?= $lang('actions') ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($webhooks as $wh): ?>
        <tr>
            <td><code style="font-size:12px;"><?= htmlspecialchars($wh['url']) ?></code></td>
            <td><?= $wh['company_name'] ? htmlspecialchars($wh['company_name']) : $lang('all_clients') ?></td>
            <td><span class="badge badge-info"><?= htmlspecialchars($wh['events']) ?></span></td>
            <td>
                <?php if ($wh['is_active']): ?>
                    <span class="badge badge-success"><?= $lang('active') ?></span>
                <?php else: ?>
                    <span class="badge badge-error"><?= $lang('inactive') ?></span>
                <?php endif; ?>
                <?php if ($wh['last_status_code']): ?>
                    <small class="text-muted">(HTTP <?= $wh['last_status_code'] ?>)</small>
                <?php endif; ?>
            </td>
            <td><?= $wh['last_triggered_at'] ?: $lang('never') ?></td>
            <td>
                <form method="POST" action="/admin/webhooks/<?= $wh['id'] ?>/toggle" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <button type="submit" class="btn btn-xs btn-secondary"><?= $wh['is_active'] ? 'OFF' : 'ON' ?></button>
                </form>
                <form method="POST" action="/admin/webhooks/<?= $wh['id'] ?>/delete" style="display:inline;" onsubmit="return confirm('<?= $lang('confirm_delete') ?>')">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <button type="submit" class="btn btn-xs btn-danger"><?= $lang('delete') ?></button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
