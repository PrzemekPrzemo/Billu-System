<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <div>
        <h1><?= $lang('modules_management') ?></h1>
        <p style="color:var(--gray-500);margin-top:4px;">
            <?= $lang('modules_for_client') ?>: <strong><?= htmlspecialchars($client['company_name'] ?? $client['name'] ?? '') ?></strong>
            (NIP: <?= htmlspecialchars($client['nip'] ?? '') ?>)
        </p>
    </div>
    <a href="/admin/clients/<?= $client['id'] ?>/edit" class="btn btn-secondary">&larr; <?= $lang('back_to_edit') ?></a>
</div>

<form method="POST" action="/admin/clients/<?= $client['id'] ?>/modules" class="form-card">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

    <div style="display:flex;gap:8px;margin-bottom:20px;">
        <button type="button" class="btn btn-sm btn-secondary" onclick="toggleAll(true)"><?= $lang('select_all_modules') ?></button>
        <button type="button" class="btn btn-sm btn-secondary" onclick="toggleAll(false)"><?= $lang('deselect_all_modules') ?></button>
    </div>

    <?php
    $categories = [
        'core' => $lang('module_cat_core'),
        'tax' => $lang('module_cat_tax'),
        'communication' => $lang('module_cat_communication'),
        'reporting' => $lang('module_cat_reporting'),
        'tools' => $lang('module_cat_tools'),
        'hr' => $lang('module_cat_hr'),
        'system' => $lang('module_cat_system'),
    ];

    $grouped = [];
    foreach ($modules as $m) {
        $cat = $m['category'] ?? 'general';
        $grouped[$cat][] = $m;
    }
    ?>

    <?php foreach ($categories as $catSlug => $catName): ?>
        <?php if (!empty($grouped[$catSlug])): ?>
        <div style="margin-bottom:24px;">
            <h3 style="font-size:14px;text-transform:uppercase;letter-spacing:0.05em;color:var(--gray-500);margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid var(--gray-200);">
                <?= $catName ?>
            </h3>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:12px;">
                <?php foreach ($grouped[$catSlug] as $m):
                    $isSystem = !empty($m['is_system']);
                    $officeEnabled = !empty($m['is_enabled_for_office']);
                    $clientEnabled = !empty($m['is_enabled_for_client']);
                    $effectiveEnabled = $officeEnabled && $clientEnabled;
                    $blockedByOffice = !$officeEnabled;
                ?>
                <label class="module-card" style="display:flex;align-items:center;gap:12px;padding:14px 16px;border-radius:var(--radius-md,8px);border:1px solid var(--gray-200);cursor:<?= ($isSystem || $blockedByOffice) ? 'default' : 'pointer' ?>;background:var(--gray-50);transition:border-color 0.15s;<?= $effectiveEnabled ? 'border-color:var(--primary);' : '' ?><?= $blockedByOffice ? 'opacity:0.5;' : '' ?>">
                    <div style="flex-shrink:0;">
                        <input type="checkbox"
                               name="modules[]"
                               value="<?= htmlspecialchars($m['slug']) ?>"
                               class="module-toggle"
                               <?= $clientEnabled ? 'checked' : '' ?>
                               <?= ($isSystem || $blockedByOffice) ? 'disabled' : '' ?>
                               style="width:18px;height:18px;accent-color:var(--primary);">
                        <?php if ($isSystem): ?>
                        <input type="hidden" name="modules[]" value="<?= htmlspecialchars($m['slug']) ?>">
                        <?php endif; ?>
                        <?php if ($blockedByOffice && !$isSystem): ?>
                        <input type="hidden" name="modules[]" value="<?= htmlspecialchars($m['slug']) ?>">
                        <?php endif; ?>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;font-size:14px;display:flex;align-items:center;gap:6px;">
                            <i class="<?= htmlspecialchars($m['icon'] ?? 'fas fa-puzzle-piece') ?>" style="color:var(--primary);width:18px;text-align:center;"></i>
                            <?= htmlspecialchars($m['name']) ?>
                            <?php if ($isSystem): ?>
                            <span style="font-size:10px;background:var(--gray-200);color:var(--gray-600);padding:2px 6px;border-radius:4px;font-weight:500;">SYSTEM</span>
                            <?php endif; ?>
                            <?php if ($blockedByOffice && !$isSystem): ?>
                            <span style="font-size:10px;background:var(--error, #dc2626);color:#fff;padding:2px 6px;border-radius:4px;font-weight:500;"><?= $lang('blocked_by_office') ?></span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:12px;color:var(--gray-500);margin-top:2px;">
                            <?= htmlspecialchars($m['description'] ?? '') ?>
                        </div>
                    </div>
                    <div style="flex-shrink:0;font-size:11px;font-weight:600;<?= $effectiveEnabled ? 'color:var(--success);' : 'color:var(--gray-400);' ?>">
                        <?= $effectiveEnabled ? $lang('module_enabled') : $lang('module_disabled') ?>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <div class="form-actions" style="margin-top:24px;">
        <button type="submit" class="btn btn-primary"><?= $lang('save') ?></button>
        <a href="/admin/clients/<?= $client['id'] ?>/edit" class="btn btn-secondary"><?= $lang('cancel') ?></a>
    </div>
</form>

<script>
function toggleAll(state) {
    document.querySelectorAll('.module-toggle:not([disabled])').forEach(function(cb) {
        cb.checked = state;
    });
}
</script>
