<!DOCTYPE html>
<html lang="<?= \App\Core\Language::getLocale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($branding['system_name'] ?? 'BiLLU') ?> - <?= $lang('forgot_password') ?></title>
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
        <h1 class="auth-title"><?= $lang('forgot_password') ?></h1>

        <?php if (!empty($flash_error)): ?>
            <div class="alert alert-error"><?= $lang($flash_error) ?></div>
        <?php endif; ?>
        <?php if (!empty($flash_success)): ?>
            <div class="alert alert-success"><?= $lang($flash_success) ?></div>
        <?php endif; ?>

        <form method="POST" action="/forgot-password" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

            <div class="form-group">
                <label class="form-label"><?= $lang('login_type') ?></label>
                <div class="radio-group">
                    <label class="radio-label">
                        <input type="radio" name="user_type" value="client" checked>
                        <?= $lang('client') ?>
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="user_type" value="office">
                        <?= $lang('office_accounting') ?>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label"><?= $lang('nip') ?></label>
                <input type="text" name="nip" class="form-input" required
                       placeholder="<?= $lang('nip_placeholder') ?>">
            </div>

            <button type="submit" class="btn btn-primary btn-full"><?= $lang('send_reset_link') ?></button>
        </form>

        <div class="auth-links">
            <a href="/login"><?= $lang('back_to_login') ?></a>
        </div>
    </div>
</div>
</body>
</html>
