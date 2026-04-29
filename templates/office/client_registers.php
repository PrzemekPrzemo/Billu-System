<?php
/**
 * @var array $client
 * @var array|null $contractor
 * @var array $notes        ClientExternalNote rows, newest first
 * @var string $target_type 'client' | 'contractor'
 * @var int    $target_id
 * @var bool   $is_office_admin
 */
$csrf = \App\Core\Session::generateCsrfToken();
$isContractor = ($target_type === 'contractor');
$backUrl = $isContractor
    ? "/office/clients/{$client['id']}"
    : "/office/clients";
$refreshUrl = $isContractor
    ? "/office/clients/{$client['id']}/contractors/{$target_id}/registers/refresh"
    : "/office/clients/{$client['id']}/registers/refresh";
$crbrUrl = "/office/clients/{$client['id']}/crbr/refresh";

// Group notes by source for the "latest per source" header.
$bySource = [];
foreach ($notes as $n) {
    $bySource[$n['source']] ??= $n; // first encounter is newest
}
$sourceLabels = [
    'gus'   => 'GUS',
    'krs'   => 'KRS',
    'ceidg' => 'CEIDG',
    'crbr'  => 'CRBR',
    'eus'   => 'e-US',
    'manual'=> 'Notatka biura',
];
?>
<h1>
    Dane z rejestrów:
    <?= htmlspecialchars($isContractor
        ? ($contractor['company_name'] ?? '—')
        : ($client['company_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?>
</h1>
<p><a href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm">&larr; Powrót</a></p>

<div class="card" style="margin-bottom:20px;">
    <div class="card-header">Pobierz / odśwież dane</div>
    <div class="card-body" style="display:flex; gap:12px; flex-wrap:wrap;">
        <form method="POST" action="<?= htmlspecialchars($refreshUrl, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn btn-primary">
                Odśwież z GUS / KRS / CEIDG
            </button>
        </form>
        <?php if (!$isContractor && $is_office_admin): ?>
        <form method="POST" action="<?= htmlspecialchars($crbrUrl, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn btn-secondary"
                    title="Beneficjenci rzeczywiści — dostępne tylko dla administratora biura">
                Pobierz CRBR (beneficjenci rzeczywiści)
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($notes)): ?>
    <div class="card">
        <div class="card-body">
            <p>Brak zapisanych notatek z rejestrów. Kliknij "Odśwież" aby pobrać dane.</p>
        </div>
    </div>
<?php else: ?>
    <div class="card" style="margin-bottom:20px;">
        <div class="card-header">Najnowsze dane (per rejestr)</div>
        <div class="card-body">
            <?php foreach ($bySource as $src => $note): ?>
                <details style="margin-bottom:12px; border:1px solid var(--gray-200); border-radius:6px;">
                    <summary style="padding:10px 14px; cursor:pointer; background:var(--gray-50); display:flex; justify-content:space-between; align-items:center;">
                        <strong><?= htmlspecialchars($sourceLabels[$src] ?? $src, ENT_QUOTES, 'UTF-8') ?></strong>
                        <small style="color:var(--gray-500);">
                            <?= htmlspecialchars($note['fetched_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </small>
                    </summary>
                    <div style="padding:14px;">
                        <?= /* formatted_html is pre-rendered + escaped at format-time */ $note['formatted_html'] ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Pełna historia (<?= count($notes) ?>)</div>
        <div class="card-body" style="padding:0;">
            <table class="table" style="margin:0;">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Rejestr</th>
                        <th>Klucz</th>
                        <th>Pobrał</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notes as $n): ?>
                        <tr>
                            <td><?= htmlspecialchars($n['fetched_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <span class="badge badge-<?= htmlspecialchars($n['source'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($sourceLabels[$n['source']] ?? $n['source'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td><code><?= htmlspecialchars($n['source_ref'] ?? '', ENT_QUOTES, 'UTF-8') ?></code></td>
                            <td>
                                <small>
                                    <?= htmlspecialchars(($n['fetched_by_type'] ?? '') . ' #' . ($n['fetched_by_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
