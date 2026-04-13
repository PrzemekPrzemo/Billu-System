<div class="section-header" style="margin-bottom:24px;">
    <div>
        <h1><?= $lang('edit_template') ?></h1>
        <div style="color:var(--gray-500); font-size:14px; margin-top:4px;">
            <?= htmlspecialchars($emailTemplate['name']) ?>
            <code style="font-size:12px; background:var(--gray-100); padding:2px 6px; border-radius:4px; margin-left:8px;"><?= htmlspecialchars($emailTemplate['template_key']) ?></code>
        </div>
    </div>
    <a href="/admin/email-templates" class="btn"><?= $lang('back') ?></a>
</div>

<?php $flashSuccess = \App\Core\Session::getFlash('success'); ?>
<?php if ($flashSuccess): ?>
    <div class="alert alert-success"><?= $lang($flashSuccess) ?></div>
<?php endif; ?>

<?php if (!empty($emailTemplate['available_placeholders'])): ?>
<div class="form-card" style="margin-bottom:20px;">
    <h3 style="margin-bottom:8px;"><?= $lang('available_placeholders') ?></h3>
    <p style="font-size:13px; color:var(--gray-500); margin-bottom:8px;">
        <?= $lang('placeholders_hint') ?>
    </p>
    <div style="display:flex; flex-wrap:wrap; gap:6px;">
        <?php foreach (explode(',', $emailTemplate['available_placeholders']) as $ph): ?>
            <span class="badge badge-info" style="cursor:pointer; font-size:12px; padding:4px 10px;"
                  onclick="navigator.clipboard.writeText('{{<?= trim($ph) ?>}}'); this.style.opacity='0.5'; setTimeout(()=>this.style.opacity='1',300);">{{<?= trim($ph) ?>}}</span>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<form method="POST" action="/admin/email-templates/<?= urlencode($emailTemplate['template_key']) ?>" class="form-card">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

    <div class="responsive-grid-2" style="gap:24px;">
        <!-- Polish -->
        <div>
            <h3 style="margin-bottom:12px;">🇵🇱 Polski</h3>
            <div class="form-group">
                <label class="form-label"><?= $lang('subject') ?></label>
                <input type="text" name="subject_pl" class="form-input" value="<?= htmlspecialchars($emailTemplate['subject_pl']) ?>">
            </div>
            <div class="form-group">
                <label class="form-label"><?= $lang('body') ?></label>
                <textarea name="body_pl" class="wysiwyg-editor" rows="12"><?= htmlspecialchars($emailTemplate['body_pl']) ?></textarea>
            </div>
        </div>

        <!-- English -->
        <div>
            <h3 style="margin-bottom:12px;">🇬🇧 English</h3>
            <div class="form-group">
                <label class="form-label">Subject</label>
                <input type="text" name="subject_en" class="form-input" value="<?= htmlspecialchars($emailTemplate['subject_en']) ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Body</label>
                <textarea name="body_en" class="wysiwyg-editor" rows="12"><?= htmlspecialchars($emailTemplate['body_en']) ?></textarea>
            </div>
        </div>
    </div>

    <div class="form-actions" style="margin-top:16px;">
        <button type="submit" class="btn btn-primary"><?= $lang('save') ?></button>
        <a href="/admin/email-templates" class="btn btn-secondary"><?= $lang('cancel') ?></a>
    </div>
</form>

<?php include __DIR__ . '/../_partials/wysiwyg.php'; ?>
