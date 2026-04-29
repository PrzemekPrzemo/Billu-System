<div class="section-header">
    <h1><?= $lang('batch_detail') ?>: <?= htmlspecialchars($batch['company_name']) ?> - <?= sprintf('%02d/%04d', $batch['period_month'], $batch['period_year']) ?></h1>
    <a href="/office/batches" class="btn btn-secondary"><?= $lang('back') ?></a>
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

<?php if (!$batch['is_finalized'] && $statusCounts['pending'] === 0 && count($invoices) > 0): ?>
<div class="alert alert-success" style="margin-bottom:12px;">
    <?= $lang('all_verified_waiting_deadline') . $batch['verification_deadline'] . '.' ?>
</div>
<?php elseif (!$batch['is_finalized']): ?>
<div class="alert alert-info" style="margin-bottom:12px;">
    <?= $lang('batch_open_until_deadline') . $batch['verification_deadline'] . ').' ?>
</div>
<?php endif; ?>

<div class="section">
    <h2><?= $lang('invoices') ?></h2>

    <form method="GET" action="/office/batches/<?= $batch['id'] ?>" class="form-card form-inline" style="margin-bottom: 16px;">
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
                <a href="/office/batches/<?= $batch['id'] ?>" class="btn btn-secondary"><?= $lang('clear_filters') ?></a>
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
                <th><?= $lang('comment') ?></th>
                <th><?= $lang('cost_center') ?></th>
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
                        <?php if (!empty($inv['whitelist_override_at'])): ?>
                            <br><span class="badge" style="background:#fef3c7;color:#92400e;font-size:10px;margin-top:3px;" title="<?= htmlspecialchars($inv['whitelist_override_reason'] ?? '') ?>">
                                <?= $lang('whitelist_override_badge') ?>
                            </span>
                        <?php endif; ?>
                    <?php elseif ($inv['status'] === 'rejected'): ?>
                        <span class="badge badge-error"><?= $lang('rejected') ?></span>
                    <?php else: ?>
                        <span class="badge badge-warning"><?= $lang('pending') ?></span>
                        <?php if (!empty($inv['whitelist_failed'])): ?>
                            <br><span class="badge" style="background:#fee2e2;color:#991b1b;font-size:10px;margin-top:3px;">
                                <?= $lang('whitelist_failed_badge') ?>
                            </span>
                            <button type="button"
                                    class="btn btn-sm btn-warning"
                                    style="margin-top:6px;font-size:11px;padding:4px 8px;"
                                    onclick="openWhitelistOverride(<?= (int)$inv['id'] ?>, <?= htmlspecialchars(json_encode($inv['seller_name'] . ' (' . $inv['seller_nip'] . ')'), ENT_QUOTES) ?>)">
                                <?= $lang('whitelist_override_button') ?>
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
                <td class="text-muted">
                    <?= htmlspecialchars($inv['comment'] ?? '') ?>
                    <?php $ccnt = $commentCounts[$inv['id']] ?? 0; ?>
                    <?php if ($ccnt > 0): ?>
                    <button type="button" class="btn-ghost comment-toggle" onclick="toggleComments(<?= $inv['id'] ?>)" title="<?= $lang('comments') ?>">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                        <span class="comment-count"><?= $ccnt ?></span>
                    </button>
                    <?php endif; ?>
                </td>
                <td class="text-muted"><?= htmlspecialchars($inv['cost_center'] ?? '') ?></td>
            </tr>
            <?php if (($commentCounts[$inv['id']] ?? 0) > 0): ?>
            <tr class="comment-row" id="comments-<?= $inv['id'] ?>" style="display:none;">
                <td colspan="11">
                    <div class="comment-thread" id="comment-thread-<?= $inv['id'] ?>">
                        <div class="comment-loading"><?= $lang('loading') ?>...</div>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
function toggleComments(invoiceId) {
    var row = document.getElementById('comments-' + invoiceId);
    if (!row) return;
    if (row.style.display === 'none') { row.style.display = ''; loadComments(invoiceId); }
    else { row.style.display = 'none'; }
}
function loadComments(invoiceId) {
    var thread = document.getElementById('comment-thread-' + invoiceId);
    fetch('/office/invoices/' + invoiceId + '/comments')
        .then(function(r) { return r.json(); })
        .then(function(comments) {
            var html = '';
            comments.forEach(function(c) {
                html += '<div class="comment-bubble comment-other"><div class="comment-meta"><strong>' + escHtml(c.user_name) + '</strong> <span class="text-muted">' + c.created_at + '</span></div><div class="comment-text">' + escHtml(c.message) + '</div></div>';
            });
            thread.innerHTML = html || '<div class="text-muted"><?= $lang('no_comments') ?></div>';
        });
}
function escHtml(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

function openWhitelistOverride(invoiceId, sellerLabel) {
    document.getElementById('wlo-form').action = '/office/invoices/' + invoiceId + '/whitelist-override';
    document.getElementById('wlo-seller').textContent = sellerLabel;
    document.getElementById('wlo-reason').value = '';
    document.getElementById('wlo-modal').style.display = 'flex';
    setTimeout(function(){ document.getElementById('wlo-reason').focus(); }, 50);
}
function closeWhitelistOverride() {
    document.getElementById('wlo-modal').style.display = 'none';
}
</script>

<!-- VAT whitelist override modal — accept on behalf of client with mandatory justification -->
<div id="wlo-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:1000;align-items:center;justify-content:center;padding:16px;">
    <form id="wlo-form" method="POST" action=""
          style="background:var(--white,#fff);border-radius:8px;max-width:560px;width:100%;padding:24px;box-shadow:0 10px 40px rgba(0,0,0,0.3);">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Session::generateCsrfToken()) ?>">

        <h2 style="margin-top:0;font-size:18px;"><?= $lang('whitelist_override_title') ?></h2>
        <p class="text-muted" style="font-size:13px;margin-bottom:8px;">
            <?= $lang('whitelist_override_intro') ?>
        </p>
        <p style="font-size:13px;margin:0 0 16px 0;">
            <?= $lang('whitelist_override_seller') ?>: <strong id="wlo-seller"></strong>
        </p>

        <div class="form-group">
            <label class="form-label" for="wlo-reason">
                <?= $lang('whitelist_override_reason_label') ?> *
            </label>
            <textarea id="wlo-reason" name="reason" class="form-input" rows="4" required
                      minlength="10" maxlength="1000"
                      placeholder="<?= htmlspecialchars($lang('whitelist_override_reason_placeholder')) ?>"></textarea>
            <small class="form-hint"><?= $lang('whitelist_override_reason_hint') ?></small>
        </div>

        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:18px;">
            <button type="button" class="btn btn-secondary" onclick="closeWhitelistOverride()"><?= $lang('cancel') ?></button>
            <button type="submit" class="btn btn-warning"><?= $lang('whitelist_override_confirm') ?></button>
        </div>
    </form>
</div>
