<h1><?= $lang('accept_privacy_policy') ?></h1>

<div class="privacy-policy-content">
    <?= \App\Models\Setting::get('privacy_policy_text') ?>
</div>

<form method="POST" action="/accept-privacy" class="auth-form">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

    <div class="form-group">
        <label class="checkbox-label">
            <input type="checkbox" name="accept_privacy" value="1" required>
            <?= $lang('i_accept_privacy') ?>
        </label>
    </div>

    <button type="submit" class="btn btn-primary btn-full"><?= $lang('accept_button') ?></button>
</form>
