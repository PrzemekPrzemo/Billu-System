<?php $flashSuccess = \App\Core\Session::getFlash('success'); $flashError = \App\Core\Session::getFlash('error'); ?>
<div class="section-header">
    <h1><?= $lang('contracts_module') ?></h1>
    <a href="/office/contracts/templates/upload" class="btn btn-primary">+ <?= $lang('contracts_upload_template') ?></a>
</div>

<?php if ($flashSuccess): ?><div class="alert alert-success"><?= htmlspecialchars($lang($flashSuccess) ?: $flashSuccess) ?></div><?php endif; ?>
<?php if ($flashError): ?><div class="alert alert-error"><?= htmlspecialchars($lang($flashError) ?: $flashError) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:24px;">
    <div class="form-card" style="padding:16px;">
        <div style="font-size:11px;color:var(--gray-500);text-transform:uppercase;"><?= $lang('contracts_pending') ?></div>
        <div style="font-size:28px;font-weight:600;"><?= (int) $countsByStatus['pending'] ?></div>
    </div>
    <div class="form-card" style="padding:16px;">
        <div style="font-size:11px;color:var(--gray-500);text-transform:uppercase;"><?= $lang('contracts_filled') ?></div>
        <div style="font-size:28px;font-weight:600;"><?= (int) $countsByStatus['filled'] + (int) $countsByStatus['submitted'] ?></div>
    </div>
    <div class="form-card" style="padding:16px;">
        <div style="font-size:11px;color:var(--gray-500);text-transform:uppercase;"><?= $lang('contracts_signed') ?></div>
        <div style="font-size:28px;font-weight:600;color:var(--success);"><?= (int) $countsByStatus['signed'] ?></div>
    </div>
    <div class="form-card" style="padding:16px;">
        <div style="font-size:11px;color:var(--gray-500);text-transform:uppercase;"><?= $lang('contracts_templates_active') ?></div>
        <div style="font-size:28px;font-weight:600;"><?= count($templates) ?></div>
    </div>
</div>

<h2><?= $lang('contracts_templates') ?></h2>
<?php if (empty($templates)): ?>
    <div class="empty-state form-card" style="padding:24px;text-align:center;">
        <p><?= $lang('contracts_no_templates') ?></p>
        <a href="/office/contracts/templates/upload" class="btn btn-primary"><?= $lang('contracts_upload_first_template') ?></a>
    </div>
<?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;">
        <?php foreach ($templates as $t): ?>
        <div class="form-card" style="padding:16px;">
            <div style="font-weight:600;margin-bottom:6px;"><?= htmlspecialchars($t['name']) ?></div>
            <?php if (!empty($t['description'])): ?>
                <div class="text-muted" style="font-size:12px;margin-bottom:10px;"><?= htmlspecialchars($t['description']) ?></div>
            <?php endif; ?>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="/office/contracts/templates/<?= (int)$t['id'] ?>/issue" class="btn btn-sm btn-primary"><?= $lang('contracts_generate_form') ?></a>
                <a href="/office/contracts/templates/<?= (int)$t['id'] ?>/edit" class="btn btn-sm btn-secondary"><?= $lang('edit') ?></a>
                <a href="/office/contracts/templates/<?= (int)$t['id'] ?>/preview" target="_blank" class="btn btn-sm btn-secondary"><?= $lang('contracts_preview') ?></a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<h2 style="margin-top:32px;"><?= $lang('contracts_recent_forms') ?></h2>
<?php if (empty($recent)): ?>
    <p class="text-muted"><?= $lang('contracts_no_forms') ?></p>
<?php else: ?>
    <div class="form-card" style="padding:0;">
        <table class="table" style="margin:0;">
            <thead>
                <tr>
                    <th>#</th><th><?= $lang('contracts_recipient') ?></th><th><?= $lang('status') ?></th>
                    <th><?= $lang('contracts_created_at') ?></th><th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $r): ?>
                <tr>
                    <td class="text-muted"><?= (int) $r['id'] ?></td>
                    <td><?= htmlspecialchars($r['recipient_name'] ?? $r['recipient_email'] ?? '-') ?></td>
                    <td>
                        <?php
                        $cls = match ($r['status']) {
                            'signed'    => 'badge-success',
                            'submitted', 'filled' => 'badge-warning',
                            'pending'   => 'badge-default',
                            default     => 'badge-error',
                        };
                        ?>
                        <span class="badge <?= $cls ?>"><?= htmlspecialchars($lang('contracts_status_' . $r['status']) ?: $r['status']) ?></span>
                    </td>
                    <td class="text-muted"><?= htmlspecialchars($r['created_at']) ?></td>
                    <td><a href="/office/contracts/forms/<?= (int)$r['id'] ?>" class="btn btn-sm btn-secondary"><?= $lang('details') ?></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p style="margin-top:12px;"><a href="/office/contracts/forms"><?= $lang('contracts_all_forms') ?> &rarr;</a></p>
<?php endif; ?>
