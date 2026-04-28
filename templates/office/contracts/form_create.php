<?php $csrf = \App\Core\Session::generateCsrfToken(); $flashError = \App\Core\Session::getFlash('error'); ?>
<div class="section-header">
    <h1><?= $lang('contracts_generate_form') ?></h1>
    <a href="/office/contracts/templates" class="btn btn-secondary">&larr; <?= $lang('back') ?></a>
</div>

<?php if ($flashError): ?><div class="alert alert-error"><?= htmlspecialchars($lang($flashError) ?: $flashError) ?></div><?php endif; ?>

<p class="text-muted" style="max-width:680px;">
    <?= $lang('contracts_generate_form_intro') ?>
    <strong><?= htmlspecialchars($template['name']) ?></strong>.
</p>

<form method="POST" action="/office/contracts/templates/<?= (int)$template['id'] ?>/issue"
      class="form-card" style="padding:24px;max-width:680px;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

    <div class="form-group">
        <label class="form-label"><?= $lang('contracts_select_client') ?></label>
        <select name="client_id" class="form-input">
            <option value=""><?= $lang('contracts_new_client_no_account') ?></option>
            <?php foreach ($clients as $c): ?>
                <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?> (<?= htmlspecialchars($c['nip']) ?>)</option>
            <?php endforeach; ?>
        </select>
        <small class="form-hint"><?= $lang('contracts_select_client_hint') ?></small>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label class="form-label"><?= $lang('contracts_recipient_name') ?></label>
            <input type="text" name="recipient_name" class="form-input" maxlength="255">
        </div>
        <div class="form-group">
            <label class="form-label"><?= $lang('contracts_recipient_email') ?></label>
            <input type="email" name="email" class="form-input" maxlength="255">
        </div>
    </div>

    <button type="submit" class="btn btn-primary"><?= $lang('contracts_issue_link') ?></button>
</form>
