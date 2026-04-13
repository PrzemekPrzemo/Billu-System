<h1><?= $lang('invoice_batches') ?></h1>

<table class="table">
    <thead>
        <tr>
            <th><?= $lang('client') ?></th>
            <th>NIP</th>
            <th><?= $lang('period') ?></th>
            <th><?= $lang('invoices') ?></th>
            <th><?= $lang('pending') ?></th>
            <th><?= $lang('deadline') ?></th>
            <th><?= $lang('status') ?></th>
            <th><?= $lang('actions') ?></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($batches)): ?>
            <tr><td colspan="8" class="text-center text-muted"><?= $lang('no_batches') ?></td></tr>
        <?php endif; ?>
        <?php foreach ($batches as $b): ?>
        <tr>
            <td><?= htmlspecialchars($b['company_name']) ?></td>
            <td><?= htmlspecialchars($b['nip']) ?></td>
            <td><?= sprintf('%02d/%04d', $b['period_month'], $b['period_year']) ?></td>
            <td><?= $b['invoice_count'] ?></td>
            <td>
                <?php if ($b['pending_count'] > 0): ?>
                    <span class="badge badge-warning"><?= $b['pending_count'] ?></span>
                <?php else: ?>
                    <span class="badge badge-success">0</span>
                <?php endif; ?>
            </td>
            <td><?= $b['verification_deadline'] ?></td>
            <td>
                <?php if ($b['is_finalized']): ?>
                    <span class="badge badge-success"><?= $lang('finalized') ?></span>
                <?php else: ?>
                    <span class="badge badge-warning"><?= $lang('active') ?></span>
                <?php endif; ?>
            </td>
            <td>
                <a href="/office/batches/<?= $b['id'] ?>" class="btn btn-sm"><?= $lang('details') ?></a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php require __DIR__ . '/../partials/pagination.php'; ?>
