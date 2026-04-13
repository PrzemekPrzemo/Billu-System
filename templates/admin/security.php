<h1><?= $lang('security') ?></h1>

<div class="section">
    <h2><?= $lang('change_password') ?></h2>
    <div class="form-card" style="padding:16px; max-width:500px;">
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
