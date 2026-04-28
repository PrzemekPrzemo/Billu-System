<!DOCTYPE html>
<html lang="<?= \App\Core\Language::getLocale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $lang('contracts_thanks_title') ?> &mdash; BiLLU</title>
    <link rel="stylesheet" href="<?= \App\Core\Asset::url('css/style.css') ?>">
</head>
<body>
<div class="auth-container" style="max-width:560px;margin:80px auto;padding:0 16px;text-align:center;">
    <div class="auth-card" style="padding:36px;">
        <div style="font-size:48px;margin-bottom:12px;">✓</div>
        <h1 style="margin-top:0;"><?= $lang('contracts_thanks_title') ?></h1>
        <p class="text-muted">
            <?= $lang('contracts_thanks_body') ?>
        </p>
    </div>
</div>
</body>
</html>
