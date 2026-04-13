<div class="section-header" style="margin-bottom:24px;">
    <h1><?= $lang('email_templates') ?></h1>
</div>

<?php $flashSuccess = \App\Core\Session::getFlash('success'); ?>
<?php if ($flashSuccess): ?>
    <div class="alert alert-success"><?= $lang($flashSuccess) ?></div>
<?php endif; ?>

<p style="color:var(--gray-500); margin-bottom:20px; max-width:700px;">
    <?= $lang('email_templates_description') ?>
</p>

<?php if (empty($templates)): ?>
    <div class="form-card" style="text-align:center; padding:40px; color:var(--gray-400);">
        <?= $lang('no_email_templates') ?>
    </div>
<?php else: ?>
<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(340px, 1fr)); gap:16px;">
    <?php
    $templateIcons = [
        'new_invoices_notification' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
        'deadline_reminder' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        'password_reset' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>',
        'initial_credentials' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        'certificate_expiry' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
        'password_expiry' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    ];
    ?>
    <?php foreach ($templates as $t): ?>
    <div class="form-card" style="padding:20px; display:flex; flex-direction:column; gap:12px;">
        <div style="display:flex; align-items:center; gap:10px;">
            <div style="width:40px; height:40px; border-radius:10px; background:var(--gray-100); display:flex; align-items:center; justify-content:center; color:var(--primary); flex-shrink:0;">
                <?= $templateIcons[$t['template_key']] ?? '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>' ?>
            </div>
            <div>
                <strong style="font-size:15px;"><?= htmlspecialchars($t['name']) ?></strong>
                <div style="font-size:12px; color:var(--gray-500);"><?= htmlspecialchars($t['template_key']) ?></div>
            </div>
        </div>
        <?php if (!empty($t['available_placeholders'])): ?>
        <div style="display:flex; flex-wrap:wrap; gap:4px;">
            <?php foreach (explode(',', $t['available_placeholders']) as $ph): ?>
                <span class="badge" style="font-size:10px; padding:2px 6px;">{{<?= trim($ph) ?>}}</span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div style="margin-top:auto;">
            <a href="/admin/email-templates/<?= urlencode($t['template_key']) ?>" class="btn btn-sm btn-primary" style="width:100%; text-align:center;">
                <?= $lang('edit') ?>
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
