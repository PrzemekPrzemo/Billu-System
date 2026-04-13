<h2><?= $lang('notification_preferences') ?></h2>

<div class="card">
    <div class="card-body">
        <form method="post" action="/office/messages/preferences">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

            <div class="form-group" style="margin-bottom:16px;">
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="checkbox" name="notify_new_thread" value="1" <?= $prefs['notify_new_thread'] ? 'checked' : '' ?>>
                    <?= $lang('notify_new_thread') ?>
                </label>
                <small style="color:var(--text-muted);"><?= $lang('notify_new_thread_desc') ?></small>
            </div>

            <div class="form-group" style="margin-bottom:16px;">
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="checkbox" name="notify_new_reply" value="1" <?= $prefs['notify_new_reply'] ? 'checked' : '' ?>>
                    <?= $lang('notify_new_reply') ?>
                </label>
                <small style="color:var(--text-muted);"><?= $lang('notify_new_reply_desc') ?></small>
            </div>

            <div class="form-group" style="margin-bottom:16px;">
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="checkbox" name="notify_email" value="1" <?= $prefs['notify_email'] ? 'checked' : '' ?>>
                    <?= $lang('notify_email') ?>
                </label>
                <small style="color:var(--text-muted);"><?= $lang('notify_email_desc') ?></small>
            </div>

            <button type="submit" class="btn btn-primary"><?= $lang('save') ?></button>
            <a href="/office/messages" class="btn btn-sm" style="margin-left:8px;">&larr; <?= $lang('back_to_messages') ?></a>
        </form>
    </div>
</div>
