<?php
$csrf = \App\Core\Session::generateCsrfToken();
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError   = \App\Core\Session::getFlash('error');
$appConfig    = require __DIR__ . '/../../../config/app.php';
$shareUrlAbs  = ($shareUrl ?? null)
    ?: ($form['status'] === 'pending'
        ? rtrim((string)($appConfig['url'] ?? ''), '/') . '/contracts/form/' . $form['token']
        : null);
?>
<div class="section-header">
    <h1><?= $lang('contracts_form_detail') ?> #<?= (int) $form['id'] ?></h1>
    <a href="/office/contracts/forms" class="btn btn-secondary">&larr; <?= $lang('back') ?></a>
</div>

<?php if ($flashSuccess): ?><div class="alert alert-success"><?= htmlspecialchars($lang($flashSuccess) ?: $flashSuccess) ?></div><?php endif; ?>
<?php if ($flashError):   ?><div class="alert alert-error"><?= htmlspecialchars($lang($flashError) ?: $flashError) ?></div><?php endif; ?>

<div class="form-card" style="padding:18px;margin-bottom:18px;">
    <dl style="display:grid;grid-template-columns:200px 1fr;gap:6px 16px;margin:0;">
        <dt><?= $lang('contracts_template') ?></dt><dd><?= htmlspecialchars($template['name'] ?? '?') ?></dd>
        <dt><?= $lang('status') ?></dt>
        <dd>
            <?php
            $cls = match ($form['status']) {
                'signed' => 'badge-success',
                'submitted','filled' => 'badge-warning',
                'pending' => 'badge-default',
                default => 'badge-error',
            };
            ?>
            <span class="badge <?= $cls ?>"><?= htmlspecialchars($lang('contracts_status_' . $form['status']) ?: $form['status']) ?></span>
        </dd>
        <dt><?= $lang('contracts_recipient') ?></dt>
        <dd><?= htmlspecialchars($form['recipient_name'] ?? '-') ?> <span class="text-muted">(<?= htmlspecialchars($form['recipient_email'] ?? '-') ?>)</span></dd>
        <?php if (!empty($client)): ?>
            <dt><?= $lang('contracts_linked_client') ?></dt>
            <dd><?= htmlspecialchars($client['company_name']) ?> (NIP: <?= htmlspecialchars($client['nip']) ?>)</dd>
        <?php endif; ?>
        <dt><?= $lang('contracts_created_at') ?></dt><dd><?= htmlspecialchars($form['created_at']) ?></dd>
        <dt><?= $lang('contracts_expires_at') ?></dt><dd><?= htmlspecialchars($form['expires_at']) ?></dd>
        <?php if (!empty($form['submitted_at'])): ?>
            <dt><?= $lang('contracts_submitted_at') ?></dt><dd><?= htmlspecialchars($form['submitted_at']) ?></dd>
        <?php endif; ?>
        <?php if (!empty($form['signed_at'])): ?>
            <dt><?= $lang('contracts_signed_at') ?></dt><dd><?= htmlspecialchars($form['signed_at']) ?></dd>
        <?php endif; ?>
    </dl>

    <?php if ($shareUrlAbs): ?>
        <hr style="margin:14px 0;border:none;border-top:1px solid var(--gray-200);">
        <div style="display:flex;gap:8px;align-items:center;">
            <input type="text" readonly value="<?= htmlspecialchars($shareUrlAbs) ?>" class="form-input" style="font-family:monospace;font-size:12px;flex:1;">
            <button type="button" class="btn btn-sm btn-secondary"
                    onclick="navigator.clipboard.writeText(this.previousElementSibling.value); this.textContent='✓'">
                <?= $lang('contracts_copy_link') ?>
            </button>
        </div>
        <small class="form-hint" style="margin-top:6px;display:block;"><?= $lang('contracts_send_link_hint') ?></small>
    <?php endif; ?>
</div>

<?php if (!empty($data)): ?>
<div class="form-card" style="padding:18px;margin-bottom:18px;">
    <h3 style="margin-top:0;"><?= $lang('contracts_filled_data') ?></h3>
    <dl style="display:grid;grid-template-columns:240px 1fr;gap:4px 16px;margin:0;font-size:13px;">
        <?php foreach ($data as $k => $v): ?>
            <dt><code><?= htmlspecialchars($k) ?></code></dt>
            <dd><?= htmlspecialchars(is_scalar($v) ? (string)$v : json_encode($v)) ?></dd>
        <?php endforeach; ?>
    </dl>
</div>
<?php endif; ?>

<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px;">
    <?php if (!empty($form['filled_pdf_path'])): ?>
        <a href="/office/contracts/forms/<?= (int)$form['id'] ?>/filled.pdf" class="btn btn-secondary"><?= $lang('contracts_download_filled') ?></a>
    <?php endif; ?>
    <?php if (!empty($form['signed_pdf_path'])): ?>
        <a href="/office/contracts/forms/<?= (int)$form['id'] ?>/signed.pdf" class="btn btn-primary"><?= $lang('contracts_download_signed') ?></a>
    <?php endif; ?>
    <?php if (!in_array($form['status'], ['signed','rejected','cancelled'], true)): ?>
        <form method="POST" action="/office/contracts/forms/<?= (int)$form['id'] ?>/cancel" style="display:inline;"
              onsubmit="return confirm('<?= htmlspecialchars($lang('contracts_cancel_confirm')) ?>');">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <button type="submit" class="btn btn-danger"><?= $lang('contracts_cancel_form') ?></button>
        </form>
    <?php endif; ?>
</div>

<h2><?= $lang('contracts_event_log') ?></h2>
<?php if (empty($events)): ?>
    <p class="text-muted"><?= $lang('contracts_no_events') ?></p>
<?php else: ?>
<div class="form-card" style="padding:0;">
    <table class="table" style="margin:0;">
        <thead><tr><th><?= $lang('contracts_received_at') ?></th><th><?= $lang('contracts_event_type') ?></th><th><?= $lang('contracts_signer') ?></th></tr></thead>
        <tbody>
            <?php foreach ($events as $e): ?>
                <tr>
                    <td class="text-muted"><?= htmlspecialchars($e['received_at']) ?></td>
                    <td><strong><?= htmlspecialchars($e['event_type']) ?></strong></td>
                    <td><?= htmlspecialchars($e['signer_email'] ?? '-') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
