<!DOCTYPE html>
<html lang="<?= \App\Core\Language::getLocale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($branding['system_name'] ?? 'BiLLU') ?> - <?= $lang('admin_login') ?></title>
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
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
        <div class="auth-logo" style="text-align:center;margin-bottom:16px;">
            <img src="<?= htmlspecialchars($branding['logo_path_login'] ?? $branding['logo_path'] ?? '/assets/img/logo.svg') ?>" alt="BiLLU" style="max-width:180px;height:auto;">
        </div>
        <p class="auth-subtitle" style="text-align:center;"><?= $lang('admin_login') ?></p>

        <?php if (!empty($flash_error)): ?>
            <div class="alert alert-error"><?= $lang($flash_error) ?></div>
        <?php endif; ?>
        <?php if (!empty($flash_success)): ?>
            <div class="alert alert-success"><?= $lang($flash_success) ?></div>
        <?php endif; ?>

        <form method="POST" action="/masterLogin" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

            <div class="form-group">
                <label class="form-label"><?= $lang('username') ?></label>
                <input type="text" name="username" class="form-input" required
                       placeholder="<?= $lang('username_placeholder') ?>">
            </div>

            <div class="form-group">
                <label class="form-label"><?= $lang('password') ?></label>
                <input type="password" name="password" class="form-input" required>
            </div>

            <button type="submit" class="btn btn-primary btn-full"><?= $lang('login_button') ?></button>
        </form>
    </div>
</div>
</body>
</html>
