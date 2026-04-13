<h1><?= $lang('security') ?></h1>

<div class="section">
    <h2><?= $lang('change_password') ?></h2>
    <div class="form-card" style="padding:16px; max-width:500px;">

        <?php if (isset($passwordDaysLeft) && $passwordDaysLeft <= 14): ?>
        <div class="alert <?= $passwordDaysLeft === 0 ? 'alert-error' : 'alert-warning' ?>" style="margin-bottom:16px;">
            <?php if ($passwordDaysLeft === 0): ?>
                <?= $lang('password_expires_today') ?>
            <?php else: ?>
                <?= sprintf($lang('password_expires_in'), $passwordDaysLeft) ?>
            <?php endif; ?>
            <?= $lang('password_change_recommended') ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($passwordChangedAt)): ?>
        <div style="margin-bottom:16px;font-size:13px;color:var(--gray-500);">
            <div><strong><?= $lang('password_last_changed') ?>:</strong> <?= htmlspecialchars($passwordChangedAt) ?></div>
            <div><strong><?= $lang('password_expiry_date') ?>:</strong> <?= htmlspecialchars($passwordExpiryDate) ?>
                (<?= $passwordDaysLeft ?> <?= $lang('days_left') ?>)</div>
        </div>
        <?php endif; ?>

        <form method="POST" action="/change-password">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

            <div class="form-group">
                <label class="form-label"><?= $lang('current_password') ?></label>
                <input type="password" name="current_password" class="form-input" required autocomplete="current-password">
            </div>

            <div class="form-group">
                <label class="form-label"><?= $lang('new_password') ?></label>
                <input type="password" name="new_password" class="form-input" required minlength="12" autocomplete="new-password">
                <small class="form-hint"><?= $lang('password_requirements') ?></small>
            </div>

            <div class="form-group">
                <label class="form-label"><?= $lang('confirm_password') ?></label>
                <input type="password" name="confirm_password" class="form-input" required autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-primary"><?= $lang('change_password_button') ?></button>
        </form>
    </div>
</div>

<div class="section">
    <h2><?= $lang('2fa_settings') ?></h2>
    <div class="form-card" style="padding:16px; max-width:500px;">
        <?php include __DIR__ . '/../partials/two_factor_profile.php'; ?>
    </div>
</div>
