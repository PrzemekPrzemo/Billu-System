<h1><?= $lang('duplicates_report') ?></h1>

<?php $flashSuccess = \App\Core\Session::getFlash('success'); ?>
<?php if ($flashSuccess): ?>
    <div class="alert alert-success"><?= $lang($flashSuccess) ?></div>
<?php endif; ?>
<?php $flashError = \App\Core\Session::getFlash('error'); ?>
<?php if ($flashError): ?>
    <div class="alert alert-error"><?= $lang($flashError) ?></div>
<?php endif; ?>

<div class="form-card" style="padding:20px; margin-bottom:20px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <form method="GET" action="/admin/duplicates" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
            <div class="form-group" style="margin:0;">
                <label class="form-label"><?= $lang('status') ?></label>
                <select name="status" class="form-input" style="width:auto;">
                    <option value=""><?= $lang('all') ?></option>
                    <option value="pending" <?= ($selectedStatus ?? '') === 'pending' ? 'selected' : '' ?>><?= $lang('duplicate_status_pending') ?></option>
                    <option value="dismissed" <?= ($selectedStatus ?? '') === 'dismissed' ? 'selected' : '' ?>><?= $lang('duplicate_status_dismissed') ?></option>
                    <option value="confirmed" <?= ($selectedStatus ?? '') === 'confirmed' ? 'selected' : '' ?>><?= $lang('duplicate_status_confirmed') ?></option>
                </select>
            </div>
            <button type="submit" class="btn btn-secondary"><?= $lang('filter') ?></button>
        </form>

        <form method="POST" action="/admin/duplicates/scan" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <button type="submit" class="btn btn-primary"><?= $lang('scan_for_duplicates') ?></button>
        </form>
    </div>
</div>

<?php if (!empty($scanResult)): ?>
<div class="alert alert-info" style="margin-bottom:20px;">
    <?= sprintf($lang('duplicates_scan_complete'), $scanResult['new_duplicates']) ?>
    (<?= $lang('checked') ?>: <?= $scanResult['total_checked'] ?>, <?= $lang('clients') ?>: <?= $scanResult['clients_scanned'] ?>)
</div>
<?php endif; ?>

<div class="form-card" style="padding:20px;">
    <?php if (empty($candidates)): ?>
        <p style="color:var(--gray-500); text-align:center; padding:40px 0;"><?= $lang('no_duplicates_found') ?></p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?= $lang('type') ?></th>
                        <th><?= $lang('invoice_number') ?></th>
                        <th>NIP</th>
                        <th><?= $lang('amount') ?></th>
                        <th><?= $lang('date') ?></th>
                        <th><?= $lang('client') ?></th>
                        <th><?= $lang('match') ?></th>
                        <th><?= $lang('status') ?></th>
                        <th><?= $lang('actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($candidates as $dc): ?>
                    <tr style="<?= $dc['status'] !== 'pending' ? 'opacity:0.6;' : '' ?>">
                        <td>
                            <span class="badge <?= $dc['invoice_type'] === 'purchase' ? 'badge-info' : 'badge-success' ?>">
                                <?= $dc['invoice_type'] === 'purchase' ? $lang('purchase') : $lang('sales') ?>
                            </span>
                        </td>
                        <td><strong><?= htmlspecialchars($dc['invoice_number'] ?? '') ?></strong></td>
                        <td><?= htmlspecialchars($dc['nip'] ?? '') ?></td>
                        <td><?= number_format((float) ($dc['gross_amount'] ?? 0), 2, ',', ' ') ?></td>
                        <td><?= $dc['issue_date'] ?? '' ?></td>
                        <td><?= htmlspecialchars($dc['client_name'] ?? '') ?></td>
                        <td>
                            <span class="badge <?= ($dc['match_score'] ?? 100) >= 100 ? 'badge-danger' : 'badge-warning' ?>">
                                <?= ($dc['match_score'] ?? 100) >= 100 ? $lang('match_exact') : $lang('match_fuzzy') ?>
                            </span>
                        </td>
                        <td><?= $lang('duplicate_status_' . ($dc['status'] ?? 'pending')) ?></td>
                        <td style="white-space:nowrap;">
                            <?php if ($dc['status'] === 'pending'): ?>
                                <form method="POST" action="/admin/duplicates/<?= $dc['id'] ?>/review" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="status" value="dismissed">
                                    <button type="submit" class="btn btn-sm btn-secondary"><?= $lang('dismiss_duplicate') ?></button>
                                </form>
                                <form method="POST" action="/admin/duplicates/<?= $dc['id'] ?>/review" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="status" value="confirmed">
                                    <button type="submit" class="btn btn-sm btn-danger"><?= $lang('confirm_duplicate') ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
