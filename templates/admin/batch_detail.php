<div class="section-header">
    <h1><?= $lang('batch_detail') ?>: <?= htmlspecialchars($batch['company_name']) ?> - <?= sprintf('%02d/%04d', $batch['period_month'], $batch['period_year']) ?></h1>
    <a href="/admin/batches" class="btn btn-secondary"><?= $lang('back') ?></a>
</div>

<div class="stats-grid">
    <?php
    $statusCounts = ['pending' => 0, 'accepted' => 0, 'rejected' => 0];
    foreach ($stats as $s) {
        $statusCounts[$s['status']] = $s['cnt'];
    }
    ?>
    <div class="stat-card">
        <div class="stat-value"><?= count($invoices) ?></div>
        <div class="stat-label"><?= $lang('total') ?></div>
    </div>
    <div class="stat-card stat-warning">
        <div class="stat-value"><?= $statusCounts['pending'] ?></div>
        <div class="stat-label"><?= $lang('pending') ?></div>
    </div>
    <div class="stat-card stat-success">
        <div class="stat-value"><?= $statusCounts['accepted'] ?></div>
        <div class="stat-label"><?= $lang('accepted') ?></div>
    </div>
    <div class="stat-card stat-error">
        <div class="stat-value"><?= $statusCounts['rejected'] ?></div>
        <div class="stat-label"><?= $lang('rejected') ?></div>
    </div>
</div>

<div class="batch-info">
    <p><strong><?= $lang('deadline') ?>:</strong> <?= $batch['verification_deadline'] ?></p>
    <p><strong><?= $lang('status') ?>:</strong>
        <?php if ($batch['is_finalized']): ?>
            <span class="badge badge-success"><?= $lang('finalized') ?> (<?= $batch['finalized_at'] ?>)</span>
        <?php else: ?>
            <span class="badge badge-warning"><?= $lang('active') ?></span>
        <?php endif; ?>
    </p>
</div>

<?php
$deadlinePassed = strtotime($batch['verification_deadline']) <= strtotime(date('Y-m-d'));
$allVerified = $statusCounts['pending'] === 0 && count($invoices) > 0;
?>

<?php if (!$batch['is_finalized'] && !$deadlinePassed): ?>
    <div class="alert alert-info" style="margin-bottom:12px;">
        <?= $lang('batch_open_until_deadline') . $batch['verification_deadline'] . '). Można dodawać nowe faktury.' ?>
    </div>
    <?php if ($allVerified): ?>
        <div class="alert alert-success" style="margin-bottom:12px;">
            <?= $lang('all_invoices_verified_waiting') ?>
        </div>
    <?php endif; ?>
    <form method="POST" action="/admin/batches/<?= $batch['id'] ?>/finalize" class="inline-form">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <button type="submit" class="btn btn-warning" onclick="return confirm('<?= $lang('confirm_finalize_before_deadline') ?>')"><?= $lang('force_finalize') ?></button>
    </form>
<?php elseif (!$batch['is_finalized'] && $deadlinePassed && $allVerified): ?>
    <form method="POST" action="/admin/batches/<?= $batch['id'] ?>/finalize" class="inline-form">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <button type="submit" class="btn btn-primary" onclick="return confirm('<?= $lang('confirm_finalize') ?>')"><?= $lang('finalize_batch') ?></button>
    </form>
<?php elseif (!$batch['is_finalized'] && $deadlinePassed): ?>
    <form method="POST" action="/admin/batches/<?= $batch['id'] ?>/finalize" class="inline-form">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <button type="submit" class="btn btn-warning" onclick="return confirm('<?= $lang('confirm_finalize_with_pending') ?>')"><?= $lang('force_finalize') ?></button>
    </form>
<?php endif; ?>

<div class="section">
    <h2><?= $lang('invoices') ?></h2>

    <form method="GET" action="/admin/batches/<?= $batch['id'] ?>" class="form-card form-inline" style="margin-bottom: 16px;">
        <div class="form-row">
            <div class="form-group">
                <label class="form-label"><?= $lang('status') ?></label>
                <select name="status" class="form-input">
                    <option value=""><?= $lang('all_statuses') ?></option>
                    <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>><?= $lang('pending') ?></option>
                    <option value="accepted" <?= ($filters['status'] ?? '') === 'accepted' ? 'selected' : '' ?>><?= $lang('accepted') ?></option>
                    <option value="rejected" <?= ($filters['status'] ?? '') === 'rejected' ? 'selected' : '' ?>><?= $lang('rejected') ?></option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><?= $lang('search') ?></label>
                <input type="text" name="search" class="form-input" placeholder="<?= $lang('search_placeholder') ?>" value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary"><?= $lang('filter') ?></button>
                <a href="/admin/batches/<?= $batch['id'] ?>" class="btn btn-secondary"><?= $lang('clear_filters') ?></a>
            </div>
        </div>
    </form>

    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th><?= $lang('invoice_number') ?></th>
                <th><?= $lang('issue_date') ?></th>
                <th><?= $lang('seller') ?></th>
                <th>NIP</th>
                <th><?= $lang('net_amount') ?></th>
                <th>VAT</th>
                <th><?= $lang('gross_amount') ?></th>
                <th><?= $lang('status') ?></th>
                <th><?= $lang('cost_center') ?> (MPK)</th>
                <th><?= $lang('comment') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($invoices as $i => $inv): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><strong><?= htmlspecialchars($inv['invoice_number']) ?></strong>
                    <?php if (($inv['invoice_type'] ?? 'VAT') === 'KOR'): ?>
                        <span class="badge" style="background:#fef3c7;color:#92400e;font-size:10px;padding:1px 6px;margin-left:4px;">KOREKTA</span>
                    <?php endif; ?>
                    <?php if (!empty($inv['ksef_reference_number'])): ?>
                        <br><small style="color:var(--info); font-size:11px;" title="<?= htmlspecialchars($inv['ksef_reference_number']) ?>">KSeF: <?= htmlspecialchars($inv['ksef_reference_number']) ?></small>
                    <?php endif; ?>
                    <?php if (!empty($inv['corrected_invoice_number'])): ?>
                        <br><small style="color:var(--gray-500); font-size:11px;">Koryguje: <?= htmlspecialchars($inv['corrected_invoice_number']) ?></small>
                    <?php endif; ?>
                </td>
                <td><?= $inv['issue_date'] ?></td>
                <td><?= htmlspecialchars($inv['seller_name']) ?></td>
                <td><?= htmlspecialchars($inv['seller_nip']) ?></td>
                <td class="text-right"><?= number_format((float)$inv['net_amount'], 2, ',', ' ') ?> <?= $inv['currency'] ?></td>
                <td class="text-right"><?= number_format((float)$inv['vat_amount'], 2, ',', ' ') ?></td>
                <td class="text-right"><?= number_format((float)$inv['gross_amount'], 2, ',', ' ') ?></td>
                <td>
                    <?php if ($inv['status'] === 'accepted'): ?>
                        <span class="badge badge-success"><?= $lang('accepted') ?></span>
                        <?= $inv['verified_by_auto'] ? '<small>(auto)</small>' : '' ?>
                    <?php elseif ($inv['status'] === 'rejected'): ?>
                        <span class="badge badge-error"><?= $lang('rejected') ?></span>
                    <?php else: ?>
                        <span class="badge badge-warning"><?= $lang('pending') ?></span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($inv['cost_center'] ?? '') ?></td>
                <td class="text-muted">
                    <?= htmlspecialchars($inv['comment'] ?? '') ?>
                    <?php $ccnt = $commentCounts[$inv['id']] ?? 0; ?>
                    <button type="button" class="btn-ghost comment-toggle" onclick="toggleComments(<?= $inv['id'] ?>)" title="<?= $lang('comments') ?>">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                        <?php if ($ccnt > 0): ?><span class="comment-count"><?= $ccnt ?></span><?php endif; ?>
                    </button>
                </td>
            </tr>
            <tr class="comment-row" id="comments-<?= $inv['id'] ?>" style="display:none;">
                <td colspan="11">
                    <div class="comment-thread" id="comment-thread-<?= $inv['id'] ?>">
                        <div class="comment-loading"><?= $lang('loading') ?>...</div>
                    </div>
                    <form class="comment-form" onsubmit="submitComment(event, <?= $inv['id'] ?>)">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
                        <div style="display:flex;gap:8px;margin-top:8px;">
                            <input type="text" name="message" class="form-input" placeholder="<?= $lang('comment_placeholder') ?>" required style="flex:1;">
                            <button type="submit" class="btn btn-primary btn-xs"><?= $lang('comment_send') ?></button>
                        </div>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
function toggleComments(invoiceId) {
    var row = document.getElementById('comments-' + invoiceId);
    if (row.style.display === 'none') { row.style.display = ''; loadComments(invoiceId); }
    else { row.style.display = 'none'; }
}
function loadComments(invoiceId) {
    var thread = document.getElementById('comment-thread-' + invoiceId);
    thread.innerHTML = '<div class="comment-loading"><?= $lang('loading') ?>...</div>';
    fetch('/admin/invoices/' + invoiceId + '/comments')
        .then(function(r) { return r.json(); })
        .then(function(comments) {
            if (comments.length === 0) { thread.innerHTML = '<div class="text-muted" style="padding:8px;font-size:0.85em;"><?= $lang('no_comments') ?></div>'; return; }
            var html = '';
            comments.forEach(function(c) {
                var cls = c.user_type === 'admin' ? 'comment-mine' : 'comment-other';
                html += '<div class="comment-bubble ' + cls + '"><div class="comment-meta"><strong>' + escHtml(c.user_name) + '</strong> <span class="text-muted">' + c.created_at + '</span></div><div class="comment-text">' + escHtml(c.message) + '</div></div>';
            });
            thread.innerHTML = html;
        });
}
function submitComment(event, invoiceId) {
    event.preventDefault();
    var form = event.target;
    fetch('/admin/invoices/comment', {method: 'POST', body: new FormData(form)})
        .then(function() { form.querySelector('input[name="message"]').value = ''; loadComments(invoiceId); });
}
function escHtml(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
</script>
