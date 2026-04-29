<!DOCTYPE html>
<html lang="<?= \App\Core\Language::getLocale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $lang('contracts_invalid_title') ?> &mdash; BiLLU</title>
    <link rel="stylesheet" href="<?= \App\Core\Asset::url('css/style.css') ?>">
</head>
<body>
<div class="auth-container" style="max-width:560px;margin:80px auto;padding:0 16px;text-align:center;">
    <div class="auth-card" style="padding:36px;">
        <h1 style="margin-top:0;"><?= $lang('contracts_invalid_title') ?></h1>
        <p class="text-muted">
            <?php
            echo htmlspecialchars($lang('contracts_invalid_reason_' . ($reason ?? 'unknown')) ?: $lang('contracts_invalid_unknown'));
            ?>
        </p>
        <?php if (!empty($detail)): ?>
            <p class="text-muted" style="font-size:12px;"><?= htmlspecialchars($detail) ?></p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
