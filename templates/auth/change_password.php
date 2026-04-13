<div class="auth-container">
    <div class="auth-card">
        <h1 class="auth-title"><?= $lang('change_password') ?></h1>
        <p class="auth-subtitle"><?= $lang('change_password_subtitle') ?></p>

        <form method="POST" action="/change-password" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

            <div class="form-group">
                <label class="form-label"><?= $lang('current_password') ?></label>
                <input type="password" name="current_password" class="form-input" required>
            </div>

            <div class="form-group">
                <label class="form-label"><?= $lang('new_password') ?></label>
                <input type="password" name="new_password" class="form-input" required minlength="12">
                <small class="form-hint"><?= $lang('password_requirements') ?></small>
            </div>

            <div class="form-group">
                <label class="form-label"><?= $lang('confirm_password') ?></label>
                <input type="password" name="confirm_password" class="form-input" required>
            </div>

            <button type="submit" class="btn btn-primary btn-full"><?= $lang('change_password_button') ?></button>
        </form>
    </div>
</div>
