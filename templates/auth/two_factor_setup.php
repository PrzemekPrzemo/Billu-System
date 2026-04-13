<!DOCTYPE html>
<html lang="<?= \App\Core\Language::getLocale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($branding['system_name'] ?? 'BiLLU') ?> - <?= $lang('2fa_setup') ?></title>
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
        .qr-container { text-align: center; margin: 20px 0; }
        .qr-container img { max-width: 250px; height: auto; }
        .secret-key { background: #f5f5f5; padding: 12px; border-radius: 8px; text-align: center;
                       font-family: monospace; font-size: 1.1em; letter-spacing: 0.15em; word-break: break-all;
                       margin: 12px 0; }
        .setup-steps { margin: 16px 0; }
        .setup-steps li { margin-bottom: 8px; line-height: 1.5; }
    </style>
</head>
<body>
<div class="auth-container">
    <div class="auth-card" style="max-width:480px;">
        <div class="auth-logo" style="text-align:center;margin-bottom:16px;">
            <img src="<?= htmlspecialchars($branding['logo_path_login'] ?? $branding['logo_path'] ?? '/assets/img/logo.svg') ?>" alt="BiLLU" style="max-width:150px;height:auto;">
        </div>

        <h2 style="text-align:center;margin-bottom:8px;"><?= $lang('2fa_setup') ?></h2>

        <?php if ($isForced ?? false): ?>
            <div class="alert alert-warning" style="margin-bottom:16px;"><?= $lang('2fa_setup_required') ?></div>
        <?php endif; ?>

        <p style="margin-bottom:12px;"><?= $lang('2fa_setup_instructions') ?></p>

        <ol class="setup-steps">
            <li><?= $lang('2fa_step1') ?></li>
            <li><?= $lang('2fa_step2') ?></li>
            <li><?= $lang('2fa_step3') ?></li>
        </ol>

        <?php if (!empty($flash_error)): ?>
            <div class="alert alert-error"><?= $lang($flash_error) ?></div>
        <?php endif; ?>

        <div class="qr-container">
            <img src="<?= $qrSvg ?>" alt="QR Code">
        </div>

        <p style="text-align:center;font-size:0.85em;color:#888;margin-bottom:4px;"><?= $lang('2fa_manual_entry') ?></p>
        <div class="secret-key"><?= htmlspecialchars($secret) ?></div>

        <form method="POST" action="/two-factor-setup" class="auth-form" style="margin-top:20px;">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

            <div class="form-group">
                <label class="form-label"><?= $lang('2fa_verification_code') ?></label>
                <input type="text" name="code" class="form-input" required
                       autocomplete="one-time-code" inputmode="numeric"
                       pattern="[0-9]{6}" maxlength="6"
                       placeholder="000000"
                       style="text-align:center;font-size:1.5em;letter-spacing:0.3em;">
                <small class="form-hint"><?= $lang('2fa_enter_code_from_app') ?></small>
            </div>

            <button type="submit" class="btn btn-primary btn-full"><?= $lang('2fa_activate') ?></button>
        </form>

        <?php if (!($isForced ?? false)): ?>
        <div class="auth-links">
            <a href="/login"><?= $lang('cancel') ?></a>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
