<?php $csrf = \App\Core\Session::generateCsrfToken(); ?>
<!DOCTYPE html>
<html lang="<?= \App\Core\Language::getLocale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($template['name']) ?> &mdash; BiLLU</title>
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
    <link rel="stylesheet" href="<?= \App\Core\Asset::url('css/style.css') ?>">
</head>
<body>
<div class="auth-container" style="max-width:760px;margin:40px auto;padding:0 16px;">
    <div class="auth-card" style="padding:28px;">
        <h1 style="margin-top:0;"><?= htmlspecialchars($template['name']) ?></h1>
        <?php if (!empty($template['description'])): ?>
            <p class="text-muted"><?= nl2br(htmlspecialchars($template['description'])) ?></p>
        <?php endif; ?>

        <form method="POST" action="/contracts/form/submit">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

            <?php foreach ($fields as $f):
                $name  = $f['name'];
                $label = $f['label'] ?: $name;
                $req   = !empty($f['required']);
                $val   = htmlspecialchars((string) ($prefill[$name] ?? $f['default'] ?? ''));
                $reqAttr = $req ? 'required' : '';
            ?>
                <div class="form-group">
                    <label class="form-label"><?= htmlspecialchars($label) ?><?= $req ? ' *' : '' ?></label>
                    <?php if ($f['type'] === 'checkbox'): ?>
                        <label class="checkbox-label">
                            <input type="checkbox" name="fields[<?= htmlspecialchars($name) ?>]" value="1" <?= $reqAttr ?>>
                            <?= htmlspecialchars($label) ?>
                        </label>
                    <?php elseif ($f['type'] === 'select'): ?>
                        <input type="text" name="fields[<?= htmlspecialchars($name) ?>]" class="form-input" value="<?= $val ?>" <?= $reqAttr ?>>
                    <?php elseif ($f['type'] === 'signature'): ?>
                        <p class="text-muted" style="font-size:13px;">
                            <?= $lang('contracts_field_signature_hint') ?>
                        </p>
                    <?php else: ?>
                        <?php if (strlen($val) > 60 || preg_match('/(uwagi|notes|opis|description|tresc)/i', $name)): ?>
                            <textarea name="fields[<?= htmlspecialchars($name) ?>]" class="form-input" rows="3" maxlength="4096" <?= $reqAttr ?>><?= $val ?></textarea>
                        <?php else: ?>
                            <input type="text" name="fields[<?= htmlspecialchars($name) ?>]" class="form-input" maxlength="4096" value="<?= $val ?>" <?= $reqAttr ?>>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <p class="text-muted" style="font-size:12px;margin-top:14px;">
                <?= $lang('contracts_submit_disclaimer') ?>
            </p>

            <button type="submit" class="btn btn-primary btn-full"><?= $lang('contracts_submit_button') ?></button>
        </form>
    </div>
</div>
</body>
</html>
