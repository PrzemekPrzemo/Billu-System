<!DOCTYPE html>
<html lang="<?= \App\Core\Language::getLocale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($branding['system_name'] ?? 'BiLLU') ?> - <?= $lang('account_blocked_title') ?></title>
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
            <img src="<?= htmlspecialchars($branding['logo_path_login'] ?? $branding['logo_path'] ?? '/assets/img/logo.svg') ?>" alt="BiLLU" style="max-width:220px;height:auto;">
        </div>

        <div class="alert alert-error" style="text-align:center; font-size:1.1em; padding:20px;">
            <strong><?= $lang('account_blocked_title') ?></strong>
        </div>

        <?php if ($accountType === 'client'): ?>
            <p style="text-align:center; margin:16px 0;">
                <?= $lang('account_blocked_client_msg') ?>
            </p>
            <?php if (!empty($officeName)): ?>
            <div style="background:var(--primary-dark, #0B2430); color:#fff; border-radius:8px; padding:20px; margin:16px 0;">
                <h3 style="margin:0 0 12px 0; font-size:1em;"><?= $lang('account_blocked_office_contact') ?></h3>
                <p style="margin:4px 0;"><strong><?= htmlspecialchars($officeName) ?></strong></p>
                <?php if (!empty($officeEmail)): ?>
                <p style="margin:4px 0;">
                    <a href="mailto:<?= htmlspecialchars($officeEmail) ?>" style="color:#008F8F;">
                        <?= htmlspecialchars($officeEmail) ?>
                    </a>
                </p>
                <?php endif; ?>
                <?php if (!empty($officePhone)): ?>
                <p style="margin:4px 0;">
                    <a href="tel:<?= htmlspecialchars($officePhone) ?>" style="color:#008F8F;">
                        <?= htmlspecialchars($officePhone) ?>
                    </a>
                </p>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <p style="text-align:center; color:#888;">
                <?= $lang('account_blocked_contact_admin') ?>
            </p>
            <?php endif; ?>

        <?php elseif ($accountType === 'office'): ?>
            <p style="text-align:center; margin:16px 0;">
                <?= $lang('account_blocked_office_msg') ?>
            </p>
            <p style="text-align:center; color:#e74c3c; font-weight:bold; margin:12px 0;">
                <?= $lang('account_blocked_office_clients_msg') ?>
            </p>
            <div style="background:var(--primary-dark, #0B2430); color:#fff; border-radius:8px; padding:20px; margin:16px 0; text-align:center;">
                <p style="margin:0;"><?= $lang('account_blocked_contact_sales') ?></p>
            </div>
        <?php endif; ?>

        <div style="text-align:center; margin-top:20px;">
            <a href="/login" class="btn btn-secondary"><?= $lang('back_to_login') ?></a>
        </div>
    </div>
</div>
</body>
</html>
