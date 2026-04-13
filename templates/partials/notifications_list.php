<h1><?= $lang('notifications') ?></h1>

<?php
$panelPrefix = \App\Core\Auth::isAdmin() ? '/admin' : (\App\Core\Auth::isOffice() ? '/office' : '/client');
$unreadCount = count(array_filter($notifications, fn($n) => !$n['is_read']));
?>

<?php if ($unreadCount > 0): ?>
<form method="POST" action="<?= $panelPrefix ?>/notifications/read" style="margin-bottom: 16px;">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
    <button type="submit" class="btn btn-secondary btn-sm"><?= $lang('mark_all_read') ?></button>
</form>
<?php endif; ?>

<?php if (empty($notifications)): ?>
    <p class="text-muted"><?= $lang('no_notifications') ?></p>
<?php else: ?>
<div class="notifications-list">
    <?php foreach ($notifications as $n): ?>
    <div class="notif-item <?= $n['is_read'] ? 'notif-read' : 'notif-unread' ?>">
        <div class="notif-header">
            <span class="notif-type badge badge-<?= $n['type'] === 'error' ? 'error' : ($n['type'] === 'warning' ? 'warning' : ($n['type'] === 'success' ? 'success' : 'info')) ?>">
                <?= htmlspecialchars($n['type']) ?>
            </span>
            <span class="notif-date text-muted"><?= $n['created_at'] ?></span>
        </div>
        <div class="notif-title"><?= htmlspecialchars($n['title']) ?></div>
        <?php if ($n['message']): ?>
            <div class="notif-message text-muted"><?= htmlspecialchars($n['message']) ?></div>
        <?php endif; ?>
        <div class="notif-actions">
            <?php if ($n['link']): ?>
                <a href="<?= htmlspecialchars($n['link']) ?>" class="btn btn-xs btn-primary"><?= $lang('view') ?></a>
            <?php endif; ?>
            <?php if (!$n['is_read']): ?>
            <form method="POST" action="<?= $panelPrefix ?>/notifications/read" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="id" value="<?= $n['id'] ?>">
                <button type="submit" class="btn btn-xs btn-secondary"><?= $lang('mark_read') ?></button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
