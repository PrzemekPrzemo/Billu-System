<div class="section-header" style="margin-bottom:16px;">
    <div>
        <h1><?= $lang('send_invoice_email') ?></h1>
        <div style="color:var(--gray-500); font-size:14px; margin-top:4px;"><?= htmlspecialchars($invoice['invoice_number']) ?></div>
    </div>
    <a href="/client/sales/<?= $invoice['id'] ?>" class="btn"><?= $lang('back') ?></a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= $lang($error) ?? htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST" action="/client/sales/<?= $invoice['id'] ?>/send-email" class="form-card">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

    <div class="form-row">
        <div class="form-group" style="flex:2;">
            <label class="form-label"><?= $lang('email_recipient') ?> *</label>
            <input type="email" name="to_email" class="form-input" required
                   value="<?= htmlspecialchars($contractorEmail) ?>"
                   placeholder="email@kontrahent.pl">
            <?php if (!empty($contractorName)): ?>
                <small class="form-hint"><?= htmlspecialchars($contractorName) ?></small>
            <?php endif; ?>
        </div>
        <div class="form-group" style="flex:1;">
            <label class="form-label"><?= $lang('email_language') ?></label>
            <select name="email_lang" id="email-lang-select" class="form-input">
                <option value="pl" <?= ($defaultLang ?? 'pl') === 'pl' ? 'selected' : '' ?>>&#127477;&#127473; Polski</option>
                <option value="en" <?= ($defaultLang ?? 'pl') === 'en' ? 'selected' : '' ?>>&#127468;&#127463; English</option>
            </select>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label"><?= $lang('subject') ?> *</label>
        <input type="text" name="subject" id="email-subject" class="form-input" required
               value="<?= htmlspecialchars($defaultSubject) ?>">
    </div>

    <div class="form-group">
        <label class="form-label"><?= $lang('body') ?></label>
        <textarea name="body" class="wysiwyg-editor" rows="10"><?= htmlspecialchars($defaultBody) ?></textarea>
    </div>

    <div class="form-card" style="background:var(--gray-50); padding:12px; margin-bottom:16px;">
        <div style="display:flex; align-items:center; gap:8px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--gray-500)" stroke-width="2">
                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>
            </svg>
            <span style="font-size:13px; color:var(--gray-600);">
                <?= $lang('attachment') ?>: <strong><?= htmlspecialchars($invoice['invoice_number']) ?>.pdf</strong>
            </span>
        </div>
    </div>

    <?php if (!empty($invoice['email_sent_at'])): ?>
    <div class="alert alert-info" style="margin-bottom:16px;">
        <?= $lang('invoice_already_sent') ?>
        <?= htmlspecialchars($invoice['email_sent_at']) ?>
        &rarr; <?= htmlspecialchars($invoice['email_sent_to']) ?>
    </div>
    <?php endif; ?>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;">
                <path d="M22 2L11 13"/><path d="M22 2L15 22L11 13L2 9L22 2Z"/>
            </svg>
            <?= $lang('send_email') ?>
        </button>
        <a href="/client/sales/<?= $invoice['id'] ?>" class="btn btn-secondary"><?= $lang('cancel') ?></a>
    </div>
</form>

<?php include __DIR__ . '/../_partials/wysiwyg.php'; ?>
<script>
(function() {
    var subjectPl = <?= json_encode($subjectPl ?? $defaultSubject) ?>;
    var subjectEn = <?= json_encode($subjectEn ?? $defaultSubject) ?>;
    var bodyPl = <?= json_encode($bodyPl ?? $defaultBody) ?>;
    var bodyEn = <?= json_encode($bodyEn ?? $defaultBody) ?>;

    document.getElementById('email-lang-select').addEventListener('change', function() {
        var lang = this.value;
        document.getElementById('email-subject').value = lang === 'en' ? subjectEn : subjectPl;
        // Update WYSIWYG body
        var editorBody = document.querySelector('.wysiwyg-body');
        var textarea = document.querySelector('textarea[name="body"]');
        if (editorBody) {
            editorBody.innerHTML = lang === 'en' ? bodyEn : bodyPl;
            if (textarea) textarea.value = editorBody.innerHTML;
        } else if (textarea) {
            textarea.value = lang === 'en' ? bodyEn : bodyPl;
        }
    });
})();
</script>
