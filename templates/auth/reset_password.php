<!DOCTYPE html>
<html lang="<?= \App\Core\Language::getLocale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($branding['system_name'] ?? 'BiLLU') ?> - <?= $lang('reset_password') ?></title>
    <meta name="theme-color" content="<?= htmlspecialchars($branding['primary_color'] ?? '#008F8F') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Roboto+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        :root {
            --primary: <?= htmlspecialchars($branding['primary_color'] ?? '#008F8F') ?>;
            --primary-dark: <?= htmlspecialchars($branding['secondary_color'] ?? '#0B2430') ?>;
            --accent: <?= htmlspecialchars($branding['accent_color'] ?? '#882D61') ?>;
        }
    </style>
</head>
<body>
<div class="auth-container">
    <div class="auth-card">
        <h1 class="auth-title"><?= $lang('reset_password') ?></h1>

        <?php if (!empty($flash_error)): ?>
            <div class="alert alert-error"><?= $lang($flash_error) ?></div>
        <?php endif; ?>
        <?php if (!empty($flash_success)): ?>
            <div class="alert alert-success"><?= $lang($flash_success) ?></div>
        <?php endif; ?>

        <form method="POST" action="/reset-password" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

            <div class="form-group">
                <label class="form-label"><?= $lang('new_password') ?></label>
                <input type="password" name="new_password" class="form-input" required>
            </div>

            <div class="form-group">
                <label class="form-label"><?= $lang('confirm_password') ?></label>
                <input type="password" name="confirm_password" class="form-input" required>
            </div>

            <p class="form-hint"><?= $lang('password_requirements') ?></p>

            <button type="submit" class="btn btn-primary btn-full"><?= $lang('reset_password_button') ?></button>
        </form>

        <div class="auth-links">
            <a href="/login"><?= $lang('back_to_login') ?></a>
        </div>
    </div>
</div>
</body>
</html>
