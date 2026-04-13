<div class="section" style="margin-top:24px;">
    <h2><?= $lang('2fa_settings') ?></h2>

    <?php if (!empty($flash_success)): ?>
        <div class="alert alert-success"><?= $lang($flash_success) ?></div>
    <?php endif; ?>

    <?php if ($twoFactorEnabled ?? false): ?>
        <div class="alert alert-success" style="margin-bottom:16px;">
            <strong><?= $lang('2fa_active') ?></strong>
        </div>

        <form method="POST" action="/two-factor-disable" class="form-card" style="max-width:400px;">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <p style="margin-bottom:12px;"><?= $lang('2fa_disable_confirm') ?></p>
            <div class="form-group">
                <label class="form-label"><?= $lang('password') ?></label>
                <input type="password" name="password" class="form-input" required>
            </div>
            <button type="submit" class="btn btn-danger" onclick="return confirm('<?= $lang('2fa_disable') ?>?')"><?= $lang('2fa_disable') ?></button>
        </form>
    <?php elseif ($twoFactorAllowed ?? false): ?>
        <p style="margin-bottom:12px;"><?= $lang('2fa_inactive') ?></p>
        <a href="/two-factor-setup" class="btn btn-primary"><?= $lang('2fa_enable') ?></a>
    <?php else: ?>
        <p style="color:#888;"><?= $lang('2fa_inactive') ?></p>
    <?php endif; ?>
</div>
