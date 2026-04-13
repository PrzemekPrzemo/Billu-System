<div class="section-header" style="margin-bottom:24px;">
    <h1><?= $lang('office_email_settings') ?></h1>
</div>

<?php $flashSuccess = \App\Core\Session::getFlash('success'); ?>
<?php if ($flashSuccess): ?>
    <div class="alert alert-success"><?= $lang($flashSuccess) ?></div>
<?php endif; ?>

<form method="POST" action="/office/email-settings" class="form-card">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

    <p style="color:var(--gray-500); margin-bottom:16px; font-size:13px;">
        <?= $lang('office_email_branding_description') ?>
    </p>

    <div class="form-row">
        <div class="form-group">
            <label class="form-label"><?= $lang('header_color') ?></label>
            <div style="display:flex; gap:8px; align-items:center;">
                <input type="color" name="header_color" id="header-color"
                       value="<?= htmlspecialchars($emailSettings['header_color'] ?? '#008F8F') ?>"
                       style="width:50px; height:38px; border:1px solid var(--gray-300); border-radius:6px; cursor:pointer;">
                <input type="text" id="header-color-text" class="form-input" style="width:100px;"
                       value="<?= htmlspecialchars($emailSettings['header_color'] ?? '#008F8F') ?>" readonly>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label"><?= $lang('logo_in_emails') ?></label>
            <label class="checkbox-label" style="margin-top:8px;">
                <input type="checkbox" name="logo_in_emails" value="1"
                       <?= ($emailSettings['logo_in_emails'] ?? 1) ? 'checked' : '' ?>>
                <?= $lang('show_logo_in_emails') ?>
            </label>
            <?php if (empty($office['logo_path'])): ?>
                <small class="form-hint" style="color:var(--warning);">
                    <?= $lang('no_logo_uploaded') ?>
                </small>
            <?php else: ?>
                <div style="margin-top:8px;">
                    <img src="<?= htmlspecialchars($office['logo_path']) ?>" alt="Logo" style="max-height:40px; max-width:180px; border-radius:4px;">
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label"><?= $lang('footer_text') ?></label>
        <textarea name="footer_text" class="wysiwyg-editor" rows="4"><?= htmlspecialchars($emailSettings['footer_text'] ?? '') ?></textarea>
        <small class="form-hint"><?= $lang('footer_text_hint') ?></small>
    </div>

    <!-- Preview -->
    <div style="margin-top:16px; margin-bottom:16px;">
        <h3 style="margin-bottom:8px;"><?= $lang('preview') ?></h3>
        <div id="email-preview" style="border:1px solid var(--gray-200); border-radius:8px; overflow:hidden; max-width:500px; box-shadow:0 2px 8px rgba(0,0,0,0.08);">
            <div id="preview-header" style="background:<?= htmlspecialchars($emailSettings['header_color'] ?? '#008F8F') ?>; color:white; padding:16px;">
                <?php if (!empty($office['logo_path']) && ($emailSettings['logo_in_emails'] ?? 1)): ?>
                    <img src="<?= htmlspecialchars($office['logo_path']) ?>" alt="Logo" style="max-height:30px; max-width:150px; margin-bottom:6px; display:block;">
                <?php endif; ?>
                <strong><?= $lang('sample_email_subject') ?></strong>
            </div>
            <div style="padding:16px; font-size:13px;">
                <p><?= $lang('sample_email_body') ?></p>
            </div>
            <div id="preview-footer" style="padding:8px; text-align:center; color:#9ca3af; font-size:11px; border-top:1px solid var(--gray-100);">
                <?= htmlspecialchars($emailSettings['footer_text'] ?? 'BiLLU Financial Solutions - System weryfikacji faktur') ?>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $lang('save') ?></button>
    </div>
</form>

<?php include __DIR__ . '/../_partials/wysiwyg.php'; ?>
<script>
document.getElementById('header-color').addEventListener('input', function() {
    document.getElementById('header-color-text').value = this.value;
    document.getElementById('preview-header').style.background = this.value;
});
</script>
