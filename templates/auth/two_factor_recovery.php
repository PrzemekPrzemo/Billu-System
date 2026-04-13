<?php
    $isStandalone = !empty($standalone);
    $postRecoveryRedirect = \App\Core\Session::get('2fa_post_recovery_redirect');
    if ($postRecoveryRedirect) {
        \App\Core\Session::remove('2fa_post_recovery_redirect');
    }
?>
<?php if ($isStandalone): ?>
<!DOCTYPE html>
<html lang="<?= \App\Core\Language::getLocale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($branding['system_name'] ?? 'BiLLU') ?> - <?= $lang('2fa_recovery_codes') ?></title>
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
<?php endif; ?>

<div class="auth-container">
    <div class="auth-card" style="max-width:480px;">
        <h2 style="text-align:center;margin-bottom:8px;"><?= $lang('2fa_recovery_codes') ?></h2>

        <?php if (!empty($flash_success)): ?>
            <div class="alert alert-success"><?= $lang($flash_success) ?></div>
        <?php endif; ?>

        <div class="alert alert-warning" style="margin-bottom:16px;">
            <?= $lang('2fa_recovery_warning') ?>
        </div>

        <div style="background:#f5f5f5;border-radius:8px;padding:16px;margin:16px 0;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-family:monospace;font-size:1.1em;text-align:center;">
                <?php foreach ($codes as $code): ?>
                    <div style="padding:6px;background:#fff;border-radius:4px;border:1px solid #ddd;"><?= htmlspecialchars($code) ?></div>
                <?php endforeach; ?>
            </div>
        </div>

        <div style="text-align:center;margin:16px 0;">
            <button onclick="copyRecoveryCodes()" class="btn btn-secondary" style="margin-right:8px;"><?= $lang('2fa_copy_codes') ?></button>
            <button onclick="printRecoveryCodes()" class="btn btn-secondary"><?= $lang('2fa_print_codes') ?></button>
        </div>

        <p style="text-align:center;font-size:0.85em;color:#888;margin-bottom:16px;">
            <?= $lang('2fa_recovery_note') ?>
        </p>

        <div style="text-align:center;">
            <?php
                $dashUrl = $postRecoveryRedirect ?? '/login';
                if (!$postRecoveryRedirect && \App\Core\Auth::isLoggedIn()) {
                    $dashUrl = match(\App\Core\Auth::currentUserType()) {
                        'admin' => '/admin',
                        'client' => '/client',
                        'office' => '/office',
                        default => '/login',
                    };
                }
            ?>
            <a href="<?= htmlspecialchars($dashUrl) ?>" class="btn btn-primary"><?= $lang('2fa_continue') ?></a>
        </div>
    </div>
</div>

<script>
function copyRecoveryCodes() {
    var codes = <?= json_encode(implode("\n", $codes)) ?>;
    navigator.clipboard.writeText(codes).then(function() {
        alert(<?= json_encode($lang('2fa_codes_copied')) ?>);
    });
}
function printRecoveryCodes() {
    window.print();
}
</script>

<?php if ($isStandalone): ?>
</body>
</html>
<?php endif; ?>
