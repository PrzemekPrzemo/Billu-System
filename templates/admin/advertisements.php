<?php
/**
 * Admin: list of advertisement banners.
 * $ads — array from Advertisement::findAll()
 */
$placementLabels = \App\Models\Advertisement::PLACEMENTS;
$typeLabels      = \App\Models\Advertisement::TYPES;
?>
<div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px;">
    <h1 style="font-size:22px; font-weight:700;">Reklamy / Banery</h1>
    <a href="/admin/advertisements/create" class="btn btn-primary btn-sm">+ Nowa reklama</a>
</div>

<?php if (empty($ads)): ?>
    <div class="card">
        <div class="card-body" style="text-align:center; color:var(--gray-400); padding:40px;">
            Brak skonfigurowanych reklam. Kliknij <strong>+ Nowa reklama</strong>, aby dodać pierwszą.
        </div>
    </div>
<?php else: ?>
<div class="card">
    <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; font-size:13.5px;">
            <thead>
                <tr style="border-bottom:2px solid var(--gray-200); background:var(--gray-50);">
                    <th style="padding:10px 14px; text-align:left;">ID</th>
                    <th style="padding:10px 14px; text-align:left;">Tytuł</th>
                    <th style="padding:10px 14px; text-align:left;">Placement</th>
                    <th style="padding:10px 14px; text-align:left;">Typ</th>
                    <th style="padding:10px 14px; text-align:center;">Aktywna</th>
                    <th style="padding:10px 14px; text-align:left;">Ważna od / do</th>
                    <th style="padding:10px 14px; text-align:left;">Kolejność</th>
                    <th style="padding:10px 14px; text-align:left;">Akcje</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($ads as $ad): ?>
            <?php $isActive = (bool) $ad['is_active']; ?>
            <tr style="border-bottom:1px solid var(--gray-100); <?= !$isActive ? 'opacity:0.55;' : '' ?>">
                <td style="padding:10px 14px; color:var(--gray-400);"><?= $ad['id'] ?></td>
                <td style="padding:10px 14px; font-weight:600; max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                    <?= htmlspecialchars($ad['title']) ?>
                    <?php if (!empty($ad['link_url'])): ?>
                        <span style="font-weight:400; color:var(--gray-400); font-size:12px;"> &rarr; link</span>
                    <?php endif; ?>
                </td>
                <td style="padding:10px 14px;">
                    <span style="background:var(--gray-100); padding:2px 8px; border-radius:4px; font-size:12px;">
                        <?= htmlspecialchars($placementLabels[$ad['placement']] ?? $ad['placement']) ?>
                    </span>
                </td>
                <td style="padding:10px 14px;">
                    <?php
                    $typeBadge = [
                        'info'    => 'background:#e0f2fe; color:#075985;',
                        'promo'   => 'background:#f5f3ff; color:#5b21b6;',
                        'warning' => 'background:#fef3c7; color:#92400e;',
                        'success' => 'background:#ecfdf5; color:#065f46;',
                    ][$ad['type']] ?? '';
                    ?>
                    <span style="<?= $typeBadge ?> padding:2px 8px; border-radius:4px; font-size:12px;">
                        <?= htmlspecialchars($typeLabels[$ad['type']] ?? $ad['type']) ?>
                    </span>
                </td>
                <td style="padding:10px 14px; text-align:center;">
                    <button type="button"
                            class="btn btn-xs <?= $isActive ? 'btn-success' : 'btn-secondary' ?>"
                            onclick="adToggle(<?= (int)$ad['id'] ?>, this)"
                            data-csrf="<?= htmlspecialchars($csrf) ?>"
                            style="min-width:62px;">
                        <?= $isActive ? 'Tak' : 'Nie' ?>
                    </button>
                </td>
                <td style="padding:10px 14px; font-size:12px; color:var(--gray-500);">
                    <?php if ($ad['starts_at'] || $ad['ends_at']): ?>
                        <?= $ad['starts_at'] ? htmlspecialchars(substr($ad['starts_at'], 0, 10)) : '∞' ?>
                        &ndash;
                        <?= $ad['ends_at']   ? htmlspecialchars(substr($ad['ends_at'],   0, 10)) : '∞' ?>
                    <?php else: ?>
                        <span style="color:var(--gray-300);">zawsze</span>
                    <?php endif; ?>
                </td>
                <td style="padding:10px 14px; color:var(--gray-400);"><?= (int)$ad['sort_order'] ?></td>
                <td style="padding:10px 14px;">
                    <div style="display:flex; gap:6px;">
                        <a href="/admin/advertisements/<?= (int)$ad['id'] ?>/edit" class="btn btn-xs btn-secondary">Edytuj</a>
                        <form method="post" action="/admin/advertisements/<?= (int)$ad['id'] ?>/delete"
                              onsubmit="return confirm('Usunąć reklamę «<?= htmlspecialchars(addslashes($ad['title'])) ?>»?');"
                              style="display:inline;">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf) ?>">
                            <button type="submit" class="btn btn-xs btn-danger">Usuń</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
function adToggle(id, btn) {
    var csrf = btn.getAttribute('data-csrf');
    fetch('/admin/advertisements/' + id + '/toggle', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: '_token=' + encodeURIComponent(csrf)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.ok) {
            var row = btn.closest('tr');
            if (data.is_active) {
                btn.textContent = 'Tak';
                btn.className = 'btn btn-xs btn-success';
                row.style.opacity = '1';
            } else {
                btn.textContent = 'Nie';
                btn.className = 'btn btn-xs btn-secondary';
                row.style.opacity = '0.55';
            }
        }
    });
}
</script>
