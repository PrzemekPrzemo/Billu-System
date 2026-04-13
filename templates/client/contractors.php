<div class="section-header">
    <h1><?= $lang('contractors') ?></h1>
    <div style="display:flex; gap:8px;">
        <a href="/client/contractors/import" class="btn"><?= $lang('import') ?> CSV/XLSX</a>
        <a href="/client/contractors/create" class="btn btn-primary"><?= $lang('new_contractor') ?></a>
    </div>
</div>

<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Search -->
<div class="form-card" style="padding:12px 16px; margin-bottom:20px;">
    <form method="GET" action="/client/contractors" id="contractors-search-form" style="display:flex; gap:8px; align-items:center;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--gray-400)" stroke-width="2" style="flex-shrink:0;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" name="q" id="contractors-search-input" class="form-input" style="border:none; box-shadow:none; padding:6px 8px;" placeholder="<?= $lang('search_contractors') ?>..." value="<?= htmlspecialchars($search ?? '') ?>">
        <noscript><button type="submit" class="btn btn-primary btn-sm"><?= $lang('search') ?></button></noscript>
        <button type="submit" class="btn btn-primary btn-sm" id="contractors-search-btn" style="display:none;"><?= $lang('search') ?></button>
        <?php if (!empty($search)): ?>
            <a href="/client/contractors" class="btn btn-sm"><?= $lang('clear_filters') ?></a>
        <?php endif; ?>
    </form>
</div>

<?php if (empty($contractors)): ?>
    <div class="empty-state">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--gray-300)" stroke-width="1.5" style="margin-bottom:16px;">
            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>
        </svg>
        <p style="margin-bottom:16px;"><?= $lang('no_contractors') ?></p>
        <a href="/client/contractors/create" class="btn btn-primary"><?= $lang('new_contractor') ?></a>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:40px;">#</th>
                    <th>Nazwa</th>
                    <th><?= $lang('nip') ?></th>
                    <th class="hide-mobile">Adres</th>
                    <th class="hide-mobile"><?= $lang('email') ?></th>
                    <th class="hide-mobile"><?= $lang('phone') ?></th>
                    <th style="width:100px;"><?= $lang('actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contractors as $i => $c):
                    $initials = mb_strtoupper(mb_substr($c['company_name'], 0, 2));
                    $address = trim(implode(', ', array_filter([
                        $c['address_street'] ?? '',
                        trim(($c['address_postal'] ?? '') . ' ' . ($c['address_city'] ?? ''))
                    ])));
                ?>
                <tr>
                    <td style="color:var(--gray-400);"><?= $i + 1 ?></td>
                    <td>
                        <?php $displayName = !empty($c['short_name']) ? $c['short_name'] : $c['company_name']; ?>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="width:36px; height:36px; border-radius:50%; background:var(--primary); color:white; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:600; flex-shrink:0;"><?= htmlspecialchars($initials) ?></div>
                            <div style="min-width:0;">
                                <div style="font-weight:600;" class="text-truncate-lg" title="<?= htmlspecialchars($c['company_name']) ?>"><?= htmlspecialchars($displayName) ?></div>
                                <?php if (!empty($c['contact_person'])): ?>
                                    <div style="font-size:12px; color:var(--gray-500);"><?= htmlspecialchars($c['contact_person']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td><code style="font-size:13px;"><?= htmlspecialchars($c['nip'] ?? '-') ?></code></td>
                    <td class="hide-mobile" style="font-size:13px; color:var(--gray-500);">
                        <span class="text-truncate" title="<?= htmlspecialchars($address) ?>"><?= htmlspecialchars($address ?: '-') ?></span>
                    </td>
                    <td class="hide-mobile">
                        <?php if (!empty($c['email'])): ?>
                            <a href="mailto:<?= htmlspecialchars($c['email']) ?>" style="font-size:13px;" class="text-truncate-sm" title="<?= htmlspecialchars($c['email']) ?>"><?= htmlspecialchars($c['email']) ?></a>
                        <?php else: ?>
                            <span style="color:var(--gray-400);">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="hide-mobile" style="font-size:13px;"><?= htmlspecialchars($c['phone'] ?? '-') ?></td>
                    <td>
                        <div style="display:flex; gap:4px;">
                            <a href="/client/contractors/<?= $c['id'] ?>/edit" class="btn btn-sm" title="<?= $lang('edit') ?>">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                            </a>
                            <form method="POST" action="/client/contractors/<?= $c['id'] ?>/delete" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('<?= $lang('contractor_delete_confirm') ?>')" title="<?= $lang('delete') ?>">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<script>
// Live contractor search with debounce
(function() {
    var searchInput = document.getElementById('contractors-search-input');
    var searchForm = document.getElementById('contractors-search-form');
    if (!searchInput || !searchForm) return;

    var debounceTimer = null;

    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function() {
            searchForm.submit();
        }, 300);
    });
})();
</script>
