<?php
/**
 * Master-admin activity log — full audit trail across all tenants.
 *
 * @var array  $rows
 * @var int    $total
 * @var int    $page
 * @var int    $pages
 * @var int    $page_size
 * @var array  $filters       — user_type / action / entity_type / date_from / date_to / keyword
 * @var array  $actor_names   — map "{type}:{id}" → display name
 * @var array  $facets        — ['user_types' => [...], 'entity_types' => [...]]
 */

$userTypeLabels = [
    'admin'           => 'Master admin',
    'office'          => 'Biuro (admin)',
    'employee'        => 'Pracownik biura',
    'client'          => 'Klient',
    'client_employee' => 'Pracownik klienta',
    'system'          => 'System / cron',
];

$buildQuery = function (array $extra = []) use ($filters): string {
    $merged = array_merge($filters, $extra);
    $merged = array_filter($merged, fn($v) => $v !== '' && $v !== null);
    return $merged ? '?' . http_build_query($merged) : '';
};

$actorLabel = function (array $row) use ($actor_names, $userTypeLabels): string {
    $type = (string) ($row['user_type'] ?? '');
    $id   = (int)    ($row['user_id']   ?? 0);
    $key  = $type . ':' . $id;
    $typeLabel = $userTypeLabels[$type] ?? ($type ?: '—');
    if (!empty($actor_names[$key])) {
        return $typeLabel . ': ' . $actor_names[$key];
    }
    return $id > 0 ? ($typeLabel . ' #' . $id) : $typeLabel;
};
?>
<h1>Logi działania</h1>
<p style="color:var(--gray-500); margin-bottom:16px;">
    Pełny audyt zdarzeń ze wszystkich kont biura i klientów. Zapisy przechowywane przez 37 dni.
</p>

<form method="GET" action="/admin/activity-log" class="form-card" style="margin-bottom:20px;">
    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Rola użytkownika</label>
            <select name="user_type" class="form-input">
                <option value="">— wszystkie —</option>
                <?php foreach (($facets['user_types'] ?? []) as $ut): ?>
                    <option value="<?= htmlspecialchars($ut, ENT_QUOTES) ?>"
                        <?= ($filters['user_type'] ?? '') === $ut ? 'selected' : '' ?>>
                        <?= htmlspecialchars($userTypeLabels[$ut] ?? $ut, ENT_QUOTES) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Typ obiektu</label>
            <select name="entity_type" class="form-input">
                <option value="">— wszystkie —</option>
                <?php foreach (($facets['entity_types'] ?? []) as $et): ?>
                    <option value="<?= htmlspecialchars($et, ENT_QUOTES) ?>"
                        <?= ($filters['entity_type'] ?? '') === $et ? 'selected' : '' ?>>
                        <?= htmlspecialchars($et, ENT_QUOTES) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Akcja (prefix)</label>
            <input type="text" name="action" class="form-input" maxlength="64"
                   placeholder="np. login, settings_updated"
                   value="<?= htmlspecialchars($filters['action'] ?? '', ENT_QUOTES) ?>">
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Data od</label>
            <input type="date" name="date_from" class="form-input"
                   value="<?= htmlspecialchars($filters['date_from'] ?? '', ENT_QUOTES) ?>">
        </div>

        <div class="form-group">
            <label class="form-label">Data do</label>
            <input type="date" name="date_to" class="form-input"
                   value="<?= htmlspecialchars($filters['date_to'] ?? '', ENT_QUOTES) ?>">
        </div>

        <div class="form-group">
            <label class="form-label">Słowo kluczowe (w szczegółach)</label>
            <input type="text" name="keyword" class="form-input" maxlength="128"
                   placeholder="np. NIP, nazwa firmy, ID"
                   value="<?= htmlspecialchars($filters['keyword'] ?? '', ENT_QUOTES) ?>">
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Filtruj</button>
        <a href="/admin/activity-log" class="btn btn-secondary">Wyczyść</a>
        <span style="color:var(--gray-500); margin-left:auto; align-self:center;">
            Wyniki: <strong><?= (int) $total ?></strong>
            <?php if ($pages > 1): ?>
                · Strona <strong><?= $page ?></strong> z <?= $pages ?>
            <?php endif; ?>
        </span>
    </div>
</form>

<?php if (empty($rows)): ?>
    <div class="card"><div class="card-body">
        <p>Brak zdarzeń pasujących do filtrów.</p>
    </div></div>
<?php else: ?>

<div class="card">
    <div class="card-body" style="padding:0;">
        <table class="table" style="margin:0;">
            <thead>
                <tr>
                    <th style="white-space:nowrap;">Czas</th>
                    <th>Użytkownik</th>
                    <th>Akcja</th>
                    <th>Obiekt</th>
                    <th class="hide-mobile">Szczegóły</th>
                    <th class="hide-mobile">IP</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td style="white-space:nowrap; font-size:13px; color:var(--gray-600);">
                        <?= htmlspecialchars(substr((string) ($r['created_at'] ?? ''), 0, 19), ENT_QUOTES) ?>
                    </td>
                    <td><?= htmlspecialchars($actorLabel($r), ENT_QUOTES) ?></td>
                    <td><code style="font-size:12px;"><?= htmlspecialchars((string) ($r['action'] ?? ''), ENT_QUOTES) ?></code></td>
                    <td>
                        <?php if (!empty($r['entity_type'])): ?>
                            <small><?= htmlspecialchars($r['entity_type'], ENT_QUOTES) ?>
                                <?= !empty($r['entity_id']) ? '#' . (int) $r['entity_id'] : '' ?></small>
                        <?php else: ?>
                            <small style="color:var(--gray-400);">—</small>
                        <?php endif; ?>
                    </td>
                    <td class="hide-mobile" style="max-width:380px; font-size:13px;">
                        <?php
                        $details = (string) ($r['details'] ?? '');
                        $hasJson = !empty($r['old_values']) || !empty($r['new_values']);
                        ?>
                        <?php if ($details !== ''): ?>
                            <span title="<?= htmlspecialchars($details, ENT_QUOTES) ?>">
                                <?= htmlspecialchars(mb_strlen($details) > 100 ? mb_substr($details, 0, 100) . '…' : $details, ENT_QUOTES) ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($hasJson): ?>
                            <details style="margin-top:4px;">
                                <summary style="cursor:pointer; color:var(--primary); font-size:12px;">JSON</summary>
                                <?php if (!empty($r['old_values'])): ?>
                                    <strong style="font-size:11px;">old_values:</strong>
                                    <pre style="font-size:11px; white-space:pre-wrap; background:var(--gray-50); padding:6px; border-radius:4px; max-height:200px; overflow:auto;"><?= htmlspecialchars($r['old_values'], ENT_QUOTES) ?></pre>
                                <?php endif; ?>
                                <?php if (!empty($r['new_values'])): ?>
                                    <strong style="font-size:11px;">new_values:</strong>
                                    <pre style="font-size:11px; white-space:pre-wrap; background:var(--gray-50); padding:6px; border-radius:4px; max-height:200px; overflow:auto;"><?= htmlspecialchars($r['new_values'], ENT_QUOTES) ?></pre>
                                <?php endif; ?>
                            </details>
                        <?php endif; ?>
                        <?php if (!empty($r['impersonated_by'])): ?>
                            <span class="badge badge-warning" style="font-size:10px;">impersonacja przez #<?= (int) $r['impersonated_by'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="hide-mobile" style="font-size:11px; color:var(--gray-500); white-space:nowrap;">
                        <?= htmlspecialchars((string) ($r['ip_address'] ?? ''), ENT_QUOTES) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($pages > 1): ?>
<nav style="display:flex; gap:6px; margin-top:16px; align-items:center; flex-wrap:wrap;">
    <?php if ($page > 1): ?>
        <a href="/admin/activity-log<?= $buildQuery(['page' => $page - 1]) ?>" class="btn btn-sm">&larr; Poprzednia</a>
    <?php endif; ?>
    <span style="color:var(--gray-500);">Strona <?= $page ?> z <?= $pages ?></span>
    <?php if ($page < $pages): ?>
        <a href="/admin/activity-log<?= $buildQuery(['page' => $page + 1]) ?>" class="btn btn-sm">Następna &rarr;</a>
    <?php endif; ?>
</nav>
<?php endif; ?>

<?php endif; // empty rows ?>
