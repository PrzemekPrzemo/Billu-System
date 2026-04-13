<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:8px;">
    <h2 style="margin:0;"><?= htmlspecialchars($thread['subject'] ?? '(brak tematu)') ?></h2>
    <a href="/client/messages" class="btn btn-sm">&larr; <?= $lang('back_to_messages') ?></a>
</div>

<?php if ($thread['invoice_id']): ?>
<div style="margin-bottom:12px; color:var(--text-muted); font-size:13px;">
    <?= $lang('related_invoice') ?>: #<?= (int) $thread['invoice_id'] ?>
</div>
<?php endif; ?>

<div class="message-thread">
    <?php foreach ($messages as $m): ?>
        <div class="message-bubble <?= $m['sender_type'] === 'client' ? 'msg-from-client' : 'msg-from-office' ?>">
            <div class="message-header">
                <strong><?= htmlspecialchars($m['sender_name'] ?? '') ?></strong>
                <span class="message-time"><?= date('d.m.Y H:i', strtotime($m['created_at'])) ?></span>
            </div>
            <div class="message-body"><?= nl2br(htmlspecialchars($m['body'])) ?></div>
            <?php if (!empty($m['attachment_name'])): ?>
            <div style="margin-top:8px; padding-top:6px; border-top:1px solid var(--gray-200);">
                <a href="/client/messages/attachment/<?= $m['id'] ?>" style="display:inline-flex; align-items:center; gap:4px; font-size:13px; color:var(--primary);">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/></svg>
                    <?= htmlspecialchars($m['attachment_name']) ?>
                </a>
            </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<div class="card" style="margin-top:16px;">
    <div class="card-body">
        <form method="post" action="/client/messages/<?= $thread['id'] ?>/reply" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="form-group" style="margin-bottom:12px;">
                <label class="form-label"><?= $lang('reply') ?></label>
                <textarea name="body" class="form-input" rows="3" required placeholder="<?= htmlspecialchars($lang('write_reply')) ?>..."></textarea>
            </div>
            <div class="form-group" style="margin-bottom:12px;">
                <label class="form-label"><?= $lang('attachment') ?></label>
                <input type="file" name="attachment" class="form-input" accept=".pdf,.txt,.xls,.xlsx">
                <small style="color:var(--text-muted);">PDF, TXT, XLS/XLSX — max 3 MB</small>
            </div>
            <button type="submit" class="btn btn-primary"><?= $lang('send_message') ?></button>
        </form>
    </div>
</div>
