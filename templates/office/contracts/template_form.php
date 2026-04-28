<?php
$csrf = \App\Core\Session::generateCsrfToken();
$isEdit = !empty($template);
$flashError = \App\Core\Session::getFlash('error');
?>
<div class="section-header">
    <h1><?= $isEdit ? $lang('contracts_edit_template') : $lang('contracts_upload_template') ?></h1>
    <a href="/office/contracts/templates" class="btn btn-secondary">&larr; <?= $lang('back') ?></a>
</div>

<?php if ($flashError): ?><div class="alert alert-error"><?= htmlspecialchars($lang($flashError) ?: $flashError) ?></div><?php endif; ?>

<form method="POST" enctype="multipart/form-data"
      action="<?= $isEdit ? '/office/contracts/templates/' . (int)$template['id'] . '/update' : '/office/contracts/templates/upload' ?>"
      class="form-card" style="padding:24px;max-width:780px;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

    <div class="form-group">
        <label class="form-label"><?= $lang('contracts_template_name') ?> *</label>
        <input type="text" name="name" class="form-input" required maxlength="255"
               value="<?= htmlspecialchars($template['name'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label class="form-label"><?= $lang('contracts_template_description') ?></label>
        <textarea name="description" class="form-input" rows="3"><?= htmlspecialchars($template['description'] ?? '') ?></textarea>
    </div>

    <?php if (!$isEdit): ?>
        <div class="form-group">
            <label class="form-label"><?= $lang('contracts_pdf_file') ?> *</label>
            <input type="file" name="pdf" class="form-input" accept="application/pdf" required>
            <small class="form-hint"><?= $lang('contracts_pdf_hint') ?></small>
        </div>
    <?php else: ?>
        <div class="form-group">
            <label class="form-label"><?= $lang('contracts_uploaded_file') ?></label>
            <p class="text-muted">
                <?= htmlspecialchars($template['original_filename']) ?>
                &middot; <a href="/office/contracts/templates/<?= (int)$template['id'] ?>/preview" target="_blank"><?= $lang('contracts_preview') ?></a>
            </p>
        </div>

        <h3 style="margin-top:18px;font-size:14px;"><?= $lang('contracts_detected_fields') ?></h3>
        <?php if (empty($fields)): ?>
            <p class="text-muted"><?= $lang('contracts_no_fields_detected') ?></p>
        <?php else: ?>
            <table class="table" style="margin-bottom:18px;">
                <thead>
                    <tr><th><?= $lang('contracts_field_name') ?></th><th><?= $lang('contracts_field_type') ?></th><th><?= $lang('contracts_field_label') ?></th><th><?= $lang('required') ?></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($fields as $f): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($f['name']) ?></code></td>
                        <td><?= htmlspecialchars($f['type']) ?></td>
                        <td><?= htmlspecialchars($f['label']) ?></td>
                        <td><?= !empty($f['required']) ? 'TAK' : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h3 style="margin-top:18px;font-size:14px;"><?= $lang('contracts_signers') ?></h3>
        <p class="text-muted" style="font-size:12px;margin-bottom:8px;">
            <?= $lang('contracts_signers_hint') ?>
        </p>
        <textarea name="signers_json" class="form-input" rows="6" style="font-family:monospace;font-size:12px;"
                  spellcheck="false"><?= htmlspecialchars(json_encode($signers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></textarea>

        <label class="checkbox-label" style="margin-top:14px;display:block;">
            <input type="checkbox" name="is_active" value="1" <?= !empty($template['is_active']) ? 'checked' : '' ?>>
            <?= $lang('active') ?>
        </label>
    <?php endif; ?>

    <div style="margin-top:18px;">
        <button type="submit" class="btn btn-primary"><?= $lang('save') ?></button>
        <a href="/office/contracts/templates" class="btn btn-secondary"><?= $lang('cancel') ?></a>
    </div>
</form>
