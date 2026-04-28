<?php $csrf = \App\Core\Session::generateCsrfToken(); ?>
<div class="section-header" style="display:flex;justify-content:space-between;align-items:center;">
    <h1><?= $lang('contracts_templates') ?></h1>
    <a href="/office/contracts/templates/upload" class="btn btn-primary">+ <?= $lang('contracts_upload_template') ?></a>
</div>

<?php if (empty($templates)): ?>
    <div class="empty-state form-card" style="padding:24px;text-align:center;">
        <p><?= $lang('contracts_no_templates') ?></p>
    </div>
<?php else: ?>
<div class="form-card" style="padding:0;">
    <table class="table" style="margin:0;">
        <thead>
            <tr>
                <th><?= $lang('contracts_template_name') ?></th>
                <th><?= $lang('contracts_fields_count') ?></th>
                <th><?= $lang('status') ?></th>
                <th><?= $lang('contracts_created_at') ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($templates as $t):
                $fieldCount = count(\App\Models\ContractTemplate::decodeFields($t));
            ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($t['name']) ?></strong>
                    <?php if (!empty($t['description'])): ?>
                        <div class="text-muted" style="font-size:12px;"><?= htmlspecialchars(mb_substr($t['description'], 0, 100)) ?></div>
                    <?php endif; ?>
                </td>
                <td class="text-muted"><?= $fieldCount ?></td>
                <td>
                    <?php if (!empty($t['is_active'])): ?>
                        <span class="badge badge-success"><?= $lang('active') ?></span>
                    <?php else: ?>
                        <span class="badge badge-default"><?= $lang('inactive') ?></span>
                    <?php endif; ?>
                </td>
                <td class="text-muted"><?= htmlspecialchars($t['created_at']) ?></td>
                <td style="white-space:nowrap;">
                    <a href="/office/contracts/templates/<?= (int)$t['id'] ?>/issue" class="btn btn-sm btn-primary"><?= $lang('contracts_generate_form') ?></a>
                    <a href="/office/contracts/templates/<?= (int)$t['id'] ?>/edit" class="btn btn-sm btn-secondary"><?= $lang('edit') ?></a>
                    <a href="/office/contracts/templates/<?= (int)$t['id'] ?>/preview" target="_blank" class="btn btn-sm btn-secondary"><?= $lang('contracts_preview') ?></a>
                    <?php if (!empty($t['is_active'])): ?>
                        <form method="POST" action="/office/contracts/templates/<?= (int)$t['id'] ?>/delete" style="display:inline;"
                              onsubmit="return confirm('<?= htmlspecialchars($lang('contracts_deactivate_confirm')) ?>');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                            <button type="submit" class="btn btn-sm btn-danger"><?= $lang('contracts_deactivate') ?></button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
