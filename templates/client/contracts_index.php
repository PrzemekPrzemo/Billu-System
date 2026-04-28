<div class="section-header">
    <h1><?= $lang('contracts_my') ?></h1>
</div>

<?php if (empty($forms)): ?>
    <div class="empty-state form-card" style="padding:24px;text-align:center;">
        <p><?= $lang('contracts_my_empty') ?></p>
    </div>
<?php else: ?>
<div class="form-card" style="padding:0;">
    <table class="table" style="margin:0;">
        <thead>
            <tr>
                <th>#</th><th><?= $lang('status') ?></th>
                <th><?= $lang('contracts_created_at') ?></th>
                <th><?= $lang('contracts_signed_at') ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($forms as $f): ?>
            <tr>
                <td class="text-muted"><?= (int) $f['id'] ?></td>
                <td>
                    <?php
                    $cls = match ($f['status']) {
                        'signed'    => 'badge-success',
                        'submitted','filled' => 'badge-warning',
                        'pending'   => 'badge-default',
                        default     => 'badge-error',
                    };
                    ?>
                    <span class="badge <?= $cls ?>"><?= htmlspecialchars($lang('contracts_status_' . $f['status']) ?: $f['status']) ?></span>
                </td>
                <td class="text-muted"><?= htmlspecialchars($f['created_at']) ?></td>
                <td class="text-muted"><?= htmlspecialchars($f['signed_at'] ?? '-') ?></td>
                <td>
                    <?php if ($f['status'] === 'pending'): ?>
                        <a href="/contracts/form/<?= htmlspecialchars($f['token']) ?>" class="btn btn-sm btn-primary"><?= $lang('contracts_fill_now') ?></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
