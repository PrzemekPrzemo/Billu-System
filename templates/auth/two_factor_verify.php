<!DOCTYPE html>
<html lang="<?= \App\Core\Language::getLocale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($branding['system_name'] ?? 'BiLLU') ?> - <?= $lang('2fa_verification') ?></title>
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

        <h2 style="text-align:center;margin-bottom:8px;"><?= $lang('2fa_verification') ?></h2>
        <p style="text-align:center;color:#666;margin-bottom:20px;"><?= $lang('2fa_enter_code') ?></p>

        <?php if (!empty($flash_error)): ?>
            <div class="alert alert-error"><?= $lang($flash_error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/two-factor-verify" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

            <div class="form-group">
                <label class="form-label"><?= $lang('2fa_code') ?></label>
                <input type="text" name="code" class="form-input" required
                       autocomplete="one-time-code" inputmode="numeric"
                       pattern="[0-9A-Za-z]*" maxlength="8"
                       placeholder="000000" autofocus
                       style="text-align:center;font-size:1.5em;letter-spacing:0.3em;">
            </div>

            <div class="form-group" style="display:flex;align-items:flex-start;gap:8px;font-size:0.9em;">
                <input type="checkbox" name="trust_device" id="trust_device" value="1"
                       style="margin-top:3px;width:16px;height:16px;accent-color:var(--primary);flex-shrink:0;">
                <label for="trust_device" style="cursor:pointer;line-height:1.4;">
                    <?= $lang('2fa_trust_device') ?>
                    <span style="display:block;color:#888;font-size:0.85em;margin-top:2px;">
                        <?= $lang('2fa_trust_device_hint') ?>
                    </span>
                </label>
            </div>

            <button type="submit" class="btn btn-primary btn-full"><?= $lang('2fa_verify') ?></button>
        </form>

        <p style="text-align:center;margin-top:16px;font-size:0.85em;color:#888;">
            <?= $lang('2fa_recovery_hint') ?>
        </p>

        <div class="auth-links">
            <a href="/login"><?= $lang('back_to_login') ?></a>
        </div>
    </div>
</div>
</body>
</html>
