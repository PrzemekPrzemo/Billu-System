<div class="section-header">
    <h1><?= $lang('contracts_all_forms') ?></h1>
    <a href="/office/contracts/templates" class="btn btn-secondary"><?= $lang('contracts_templates') ?></a>
</div>

<form method="GET" style="margin-bottom:16px;">
    <select name="status" class="form-input" style="display:inline-block;width:auto;" onchange="this.form.submit()">
        <option value=""><?= $lang('contracts_all_statuses') ?></option>
        <?php foreach (['pending','filled','submitted','signed','rejected','expired','cancelled'] as $s): ?>
            <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>>
                <?= htmlspecialchars($lang('contracts_status_' . $s) ?: $s) ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>

<?php if (empty($forms)): ?>
    <p class="text-muted"><?= $lang('contracts_no_forms') ?></p>
<?php else: ?>
<div class="form-card" style="padding:0;">
    <table class="table" style="margin:0;">
        <thead>
            <tr>
                <th>#</th><th><?= $lang('contracts_recipient') ?></th><th><?= $lang('status') ?></th>
                <th><?= $lang('contracts_created_at') ?></th><th><?= $lang('contracts_expires_at') ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($forms as $f): ?>
            <tr>
                <td class="text-muted"><?= (int) $f['id'] ?></td>
                <td><?= htmlspecialchars($f['recipient_name'] ?? $f['recipient_email'] ?? '-') ?></td>
                <td>
                    <?php
                    $cls = match ($f['status']) {
                        'signed'    => 'badge-success',
                        'submitted', 'filled' => 'badge-warning',
                        'pending'   => 'badge-default',
                        default     => 'badge-error',
                    };
                    ?>
                    <span class="badge <?= $cls ?>"><?= htmlspecialchars($lang('contracts_status_' . $f['status']) ?: $f['status']) ?></span>
                </td>
                <td class="text-muted"><?= htmlspecialchars($f['created_at']) ?></td>
                <td class="text-muted"><?= htmlspecialchars($f['expires_at']) ?></td>
                <td><a href="/office/contracts/forms/<?= (int)$f['id'] ?>" class="btn btn-sm btn-secondary"><?= $lang('details') ?></a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
$totalPages = (int) ceil(($total ?? 0) / max(1, $perPage));
if ($totalPages > 1): ?>
    <div style="margin-top:12px;">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="?page=<?= $p ?><?= $statusFilter ? '&status=' . htmlspecialchars($statusFilter) : '' ?>"
               class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
<?php endif; ?>
<?php endif; ?>
