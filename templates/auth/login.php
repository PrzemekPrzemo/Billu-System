<!DOCTYPE html>
<html lang="<?= \App\Core\Language::getLocale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($branding['system_name'] ?? 'BiLLU') ?> - <?= $lang('login_button') ?></title>
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

        <p class="auth-subtitle" style="text-align:center;"><?= $lang('system_description') ?></p>

        <?php if (!empty($flash_error)): ?>
            <div class="alert alert-error"><?= $lang($flash_error) ?></div>
        <?php endif; ?>
        <?php if (!empty($flash_success)): ?>
            <div class="alert alert-success"><?= $lang($flash_success) ?></div>
        <?php endif; ?>

        <form method="POST" action="/login" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

            <div class="form-group">
                <label class="form-label"><?= $lang('login_type') ?></label>
                <div class="radio-group">
                    <label class="radio-label">
                        <input type="radio" name="login_type" value="client" checked onchange="updateLoginField()">
                        <?= $lang('client') ?>
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="login_type" value="office" onchange="updateLoginField()">
                        <?= $lang('office_accounting') ?> / <?= $lang('office_employee') ?>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" id="identifier-label"><?= $lang('nip') ?></label>
                <input type="text" name="identifier" id="identifier-input" class="form-input" required
                       placeholder="<?= $lang('nip_placeholder') ?>">
            </div>

            <div class="form-group">
                <label class="form-label"><?= $lang('password') ?></label>
                <input type="password" name="password" class="form-input" required>
            </div>

            <button type="submit" class="btn btn-primary btn-full"><?= $lang('login_button') ?></button>
        </form>

        <div class="auth-links">
            <a href="/forgot-password"><?= $lang('forgot_password') ?></a>
        </div>

        <div style="margin-top:16px; border-top:1px solid var(--gray-200, #e5e7eb); padding-top:12px;">
            <details style="font-size:12px; color:#6b7280;">
                <summary style="cursor:pointer; font-weight:500;"><?= $lang('cookie_info_title') ?></summary>
                <div style="margin-top:8px; line-height:1.6;">
                    <p><strong><?= $lang('cookie_session_only') ?></strong> (PHPSESSID)</p>
                    <ul style="margin:4px 0 4px 18px; padding:0;">
                        <li><?= $lang('cookie_purpose') ?></li>
                        <li><?= $lang('cookie_lifetime') ?></li>
                        <li><?= $lang('cookie_flags') ?></li>
                        <li><?= $lang('cookie_no_tracking') ?></li>
                    </ul>
                </div>
            </details>
        </div>
    </div>
</div>
<script>
function updateLoginField() {
    var val = document.querySelector('input[name="login_type"]:checked').value;
    var label = document.getElementById('identifier-label');
    var input = document.getElementById('identifier-input');
    if (val === 'office') {
        label.textContent = '<?= $lang('email') ?>';
        input.placeholder = '<?= $lang('email_placeholder') ?>';
        input.type = 'email';
    } else {
        label.textContent = '<?= $lang('nip') ?>';
        input.placeholder = '<?= $lang('nip_placeholder') ?>';
        input.type = 'text';
    }
}
</script>
</body>
</html>
