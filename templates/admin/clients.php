<?php
$f = $filters ?? ['search' => '', 'office_id' => '', 'status' => ''];
$curSort = $sort ?? 'company_name';
$curDir = $dir ?? 'asc';
$isGrouped = $groupMode ?? false;

// Build query string preserving current filters for sort links
$baseParams = array_filter([
    'search' => $f['search'],
    'office_id' => $f['office_id'],
    'status' => $f['status'],
    'group' => $isGrouped ? 'office' : '',
]);

$sortLink = function(string $col, string $label) use ($baseParams, $curSort, $curDir) {
    $newDir = ($curSort === $col && $curDir === 'asc') ? 'desc' : 'asc';
    $params = array_merge($baseParams, ['sort' => $col, 'dir' => $newDir]);
    $arrow = '';
    if ($curSort === $col) $arrow = $curDir === 'asc' ? ' &#9650;' : ' &#9660;';
    return '<a href="/admin/clients?' . htmlspecialchars(http_build_query($params)) . '" style="text-decoration:none;color:inherit;white-space:nowrap;">' . $label . $arrow . '</a>';
};
?>

<div class="section-header">
    <h1><?= $lang('clients') ?></h1>
    <div>
        <a href="/admin/clients/create" class="btn btn-primary"><?= $lang('add_client') ?></a>
        <a href="/admin/clients/bulk-import" class="btn btn-secondary"><?= $lang('bulk_import_clients') ?></a>
    </div>
</div>

<!-- Filters -->
<div class="form-card" style="padding:16px; margin-bottom:20px;">
    <form method="GET" action="/admin/clients" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
        <div class="form-group" style="margin:0; flex:1; min-width:180px;">
            <label class="form-label" style="font-size:12px;"><?= $lang('search') ?></label>
            <input type="text" name="search" class="form-input" value="<?= htmlspecialchars($f['search']) ?>" placeholder="<?= $lang('company_name') ?> / NIP">
        </div>
        <div class="form-group" style="margin:0; min-width:160px;">
            <label class="form-label" style="font-size:12px;"><?= $lang('office') ?></label>
            <select name="office_id" class="form-input">
                <option value=""><?= $lang('all') ?></option>
                <option value="0" <?= $f['office_id'] === '0' ? 'selected' : '' ?>>— <?= $lang('no_office') ?> —</option>
                <?php foreach ($offices as $o): ?>
                    <option value="<?= $o['id'] ?>" <?= $f['office_id'] === (string)$o['id'] ? 'selected' : '' ?>><?= htmlspecialchars($o['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0; min-width:120px;">
            <label class="form-label" style="font-size:12px;"><?= $lang('status') ?></label>
            <select name="status" class="form-input">
                <option value=""><?= $lang('all') ?></option>
                <option value="active" <?= $f['status'] === 'active' ? 'selected' : '' ?>><?= $lang('active') ?></option>
                <option value="inactive" <?= $f['status'] === 'inactive' ? 'selected' : '' ?>><?= $lang('inactive') ?></option>
            </select>
        </div>
        <?php if ($isGrouped): ?><input type="hidden" name="group" value="office"><?php endif; ?>
        <button type="submit" class="btn btn-primary"><?= $lang('search') ?></button>
        <?php if ($f['search'] || $f['office_id'] !== '' || $f['status']): ?>
            <a href="/admin/clients<?= $isGrouped ? '?group=office' : '' ?>" class="btn"><?= $lang('clear_filters') ?></a>
        <?php endif; ?>
        <a href="/admin/clients?<?= http_build_query(array_merge(array_filter(['search' => $f['search'], 'office_id' => $f['office_id'], 'status' => $f['status']]), $isGrouped ? [] : ['group' => 'office'])) ?>" class="btn <?= $isGrouped ? 'btn-primary' : '' ?>" style="margin-left:auto;" title="<?= $lang('group_by_office') ?>">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
            <?= $lang('group_by_office') ?>
        </a>
    </form>
</div>

<?php if ($isGrouped && !empty($grouped)): ?>
<!-- Grouped View -->
<?php foreach ($grouped as $officeName => $officeClients): ?>
<details open style="margin-bottom:16px;">
    <summary style="cursor:pointer; padding:12px 16px; background:var(--gray-50); border:1px solid var(--gray-200); border-radius:8px 8px 0 0; font-weight:700; font-size:15px; display:flex; align-items:center; gap:8px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
        <?= $officeName === '__none__' ? $lang('no_office') : htmlspecialchars($officeName) ?>
        <span class="badge badge-info" style="font-size:11px;"><?= count($officeClients) ?></span>
    </summary>
    <table class="table" style="border-top:none; border-radius:0 0 8px 8px;">
        <thead>
            <tr>
                <th>NIP</th>
                <th><?= $lang('company_name') ?></th>
                <th>Email</th>
                <th><?= $lang('status') ?></th>
                <th><?= $lang('created_at') ?></th>
                <th><?= $lang('actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($officeClients as $c): ?>
            <tr>
                <td><strong><?= htmlspecialchars($c['nip']) ?></strong></td>
                <td>
                    <?= htmlspecialchars($c['company_name']) ?>
                    <?php if (!empty($c['representative_name'])): ?>
                        <br><small class="text-muted"><?= htmlspecialchars($c['representative_name']) ?></small>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($c['email']) ?></td>
                <td>
                    <form method="POST" action="/admin/clients/<?= $c['id'] ?>/toggle-active" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <?php if ($c['is_active']): ?>
                            <button type="submit" class="badge badge-success" style="cursor:pointer; border:none; font:inherit;"
                                    data-confirm="<?= $lang('client_deactivate_confirm', ['name' => htmlspecialchars($c['company_name'])]) ?>">
                                <?= $lang('active') ?>
                            </button>
                        <?php else: ?>
                            <button type="submit" class="badge badge-error" style="cursor:pointer; border:none; font:inherit;"
                                    data-confirm="<?= $lang('client_activate_confirm', ['name' => htmlspecialchars($c['company_name'])]) ?>">
                                <?= $lang('inactive') ?>
                            </button>
                        <?php endif; ?>
                    </form>
                </td>
                <td><?= !empty($c['created_at']) ? date('Y-m-d', strtotime($c['created_at'])) : '-' ?></td>
                <td style="white-space:nowrap;">
                    <a href="/admin/clients/<?= $c['id'] ?>/edit" class="btn btn-sm"><?= $lang('edit') ?></a>
                    <a href="/admin/impersonate/client/<?= $c['id'] ?>" class="btn btn-xs btn-warning"><?= $lang('login_as') ?></a>
                    <form method="POST" action="/admin/clients/<?= $c['id'] ?>/delete" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <button type="submit" class="btn btn-xs btn-danger" data-confirm="<?= $lang('client_delete_confirm', ['name' => htmlspecialchars($c['company_name'])]) ?>"><?= $lang('delete') ?></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</details>
<?php endforeach; ?>
<?php require __DIR__ . '/../partials/pagination.php'; ?>

<?php else: ?>
<!-- Flat Table View -->
<table class="table">
    <thead>
        <tr>
            <th><?= $sortLink('nip', 'NIP') ?></th>
            <th><?= $sortLink('company_name', $lang('company_name')) ?></th>
            <th>Email</th>
            <th><?= $sortLink('office_name', $lang('office')) ?></th>
            <th><?= $sortLink('is_active', $lang('status')) ?></th>
            <th><?= $sortLink('created_at', $lang('created_at')) ?></th>
            <th><?= $lang('actions') ?></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($clients)): ?>
            <tr><td colspan="7" class="text-center text-muted"><?= $lang('no_clients') ?></td></tr>
        <?php endif; ?>
        <?php foreach ($clients as $c): ?>
        <tr>
            <td><strong><?= htmlspecialchars($c['nip']) ?></strong></td>
            <td>
                <?= htmlspecialchars($c['company_name']) ?>
                <?php if (!empty($c['representative_name'])): ?>
                    <br><small class="text-muted"><?= htmlspecialchars($c['representative_name']) ?></small>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($c['email']) ?></td>
            <td><?= htmlspecialchars($c['office_name'] ?? '-') ?></td>
            <td>
                <form method="POST" action="/admin/clients/<?= $c['id'] ?>/toggle-active" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <?php if ($c['is_active']): ?>
                        <button type="submit" class="badge badge-success" style="cursor:pointer; border:none; font:inherit;"
                                data-confirm="<?= $lang('client_deactivate_confirm', ['name' => htmlspecialchars($c['company_name'])]) ?>">
                            <?= $lang('active') ?>
                        </button>
                    <?php else: ?>
                        <button type="submit" class="badge badge-error" style="cursor:pointer; border:none; font:inherit;"
                                data-confirm="<?= $lang('client_activate_confirm', ['name' => htmlspecialchars($c['company_name'])]) ?>">
                            <?= $lang('inactive') ?>
                        </button>
                    <?php endif; ?>
                </form>
            </td>
            <td><?= !empty($c['created_at']) ? date('Y-m-d', strtotime($c['created_at'])) : '-' ?></td>
            <td style="white-space:nowrap;">
                <a href="/admin/clients/<?= $c['id'] ?>/edit" class="btn btn-sm"><?= $lang('edit') ?></a>
                <a href="/admin/impersonate/client/<?= $c['id'] ?>" class="btn btn-xs btn-warning"><?= $lang('login_as') ?></a>
                <form method="POST" action="/admin/clients/<?= $c['id'] ?>/delete" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <button type="submit" class="btn btn-xs btn-danger" data-confirm="<?= $lang('client_delete_confirm', ['name' => htmlspecialchars($c['company_name'])]) ?>"><?= $lang('delete') ?></button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php require __DIR__ . '/../partials/pagination.php'; ?>
<?php endif; ?>
