<h2><?= $lang('messages') ?></h2>

<?php if (empty($threads)): ?>
    <div class="alert alert-info"><?= $lang('no_messages') ?></div>
<?php else: ?>
    <div class="card" style="margin-bottom:20px;">
        <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th><?= $lang('subject') ?></th>
                    <th><?= $lang('sender') ?></th>
                    <th><?= $lang('replies') ?></th>
                    <th><?= $lang('date') ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($threads as $t): ?>
                    <tr class="<?= !$t['is_read_by_client'] ? 'msg-unread' : '' ?>">
                        <td>
                            <a href="/client/messages/<?= $t['id'] ?>">
                                <?php if (!$t['is_read_by_client']): ?><strong><?php endif; ?>
                                <?= htmlspecialchars($t['subject'] ?? '(brak tematu)') ?>
                                <?php if (!$t['is_read_by_client']): ?></strong><?php endif; ?>
                            </a>
                            <?php if ($t['invoice_id']): ?>
                                <span class="badge badge-sm">FV</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($t['sender_name'] ?? '') ?></td>
                        <td><?= (int) ($t['reply_count'] ?? 0) ?></td>
                        <td><?= date('d.m.Y H:i', strtotime($t['last_reply_at'] ?? $t['created_at'])) ?></td>
                        <td><a href="/client/messages/<?= $t['id'] ?>" class="btn btn-sm btn-primary"><?= $lang('view') ?></a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
<?php endif; ?>

<div style="margin-bottom:12px;">
    <button type="button" class="btn btn-primary" onclick="document.getElementById('newMessageForm').style.display = document.getElementById('newMessageForm').style.display === 'none' ? 'block' : 'none';">
        <?= $lang('new_message') ?>
    </button>
    <a href="/client/messages/preferences" class="btn btn-sm" style="margin-left:8px;"><?= $lang('notification_preferences') ?></a>
</div>

<div id="newMessageForm" style="display:none;">
    <div class="card">
        <div class="card-header">
            <span><?= $lang('new_message') ?></span>
        </div>
        <div class="card-body">
            <form method="post" action="/client/messages/create" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label"><?= $lang('subject') ?></label>
                    <input type="text" name="subject" class="form-input" required maxlength="255" placeholder="<?= htmlspecialchars($lang('message_subject_placeholder')) ?>">
                </div>
                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label"><?= $lang('message_body') ?></label>
                    <textarea name="body" class="form-input" rows="3" required placeholder="<?= htmlspecialchars($lang('write_message')) ?>..."></textarea>
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
</div>
