<h2>Pliki klienta: <?= htmlspecialchars($client['company_name']) ?></h2>

<p style="margin-bottom:16px;">
    <a href="/office/clients" class="btn btn-sm">&larr; Powrót do listy klientów</a>
    <a href="/office/clients/<?= (int)$client['id'] ?>/edit" class="btn btn-sm" style="margin-left:6px;">Edycja klienta</a>
    <a href="/office/clients/<?= (int)$client['id'] ?>/notes" class="btn btn-sm" style="margin-left:6px;">Notatki</a>
</p>

<?php
$categories = [
    'general' => ['label' => 'Ogólne', 'color' => '#6B7280'],
    'invoice' => ['label' => 'Faktura', 'color' => '#008F8F'],
    'contract' => ['label' => 'Umowa', 'color' => '#7C3AED'],
    'tax' => ['label' => 'Podatki', 'color' => '#DC2626'],
    'correspondence' => ['label' => 'Korespondencja', 'color' => '#D97706'],
    'other' => ['label' => 'Inne', 'color' => '#059669'],
];

function formatFileSize(int $bytes): string {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1, ',', ' ') . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1, ',', ' ') . ' KB';
    }
    return $bytes . ' B';
}

function getUploaderName(array $file): string {
    if ($file['uploaded_by_type'] === 'client') {
        return 'Klient';
    }
    if ($file['uploaded_by_type'] === 'office') {
        return 'Biuro';
    }
    if ($file['uploaded_by_type'] === 'employee') {
        $emp = \App\Models\OfficeEmployee::findById((int) $file['uploaded_by_id']);
        return $emp ? htmlspecialchars($emp['name']) : 'Pracownik';
    }
    return '—';
}
?>

<!-- Storage Stats -->
<div style="display:flex; flex-wrap:wrap; gap:16px; margin-bottom:20px;">
    <div class="form-card" style="padding:12px 20px; flex:0 0 auto;">
        <span style="color:var(--text-muted); font-size:13px;">Plików:</span>
        <strong style="margin-left:4px;"><?= (int)$stats['total_files'] ?></strong>
    </div>
    <div class="form-card" style="padding:12px 20px; flex:0 0 auto;">
        <span style="color:var(--text-muted); font-size:13px;">Rozmiar:</span>
        <strong style="margin-left:4px;"><?= formatFileSize((int)$stats['total_size']) ?></strong>
    </div>
</div>

<!-- Storage Path Configuration (collapsible) -->
<div class="form-card" style="padding:16px 20px; margin-bottom:20px;">
    <div style="display:flex; align-items:center; justify-content:space-between; cursor:pointer;"
         onclick="var el=document.getElementById('storagePath'); el.style.display = el.style.display==='none'?'block':'none';">
        <h3 style="margin:0; font-size:15px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle; margin-right:6px;">
                <circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/>
            </svg>
            Konfiguracja ścieżki plików
        </h3>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
    </div>
    <div id="storagePath" style="display:none; margin-top:12px;">
        <p style="color:var(--text-muted); font-size:13px; margin-bottom:8px;">
            Aktualna ścieżka: <code style="background:var(--gray-100); padding:2px 6px; border-radius:4px;"><?= htmlspecialchars($client['file_storage_path'] ?? '(domyślna: storage/client_files/)') ?></code>
        </p>
        <form method="POST" action="/office/clients/<?= (int)$client['id'] ?>/file-storage-path">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div style="display:flex; gap:8px; align-items:flex-end;">
                <div style="flex:1;">
                    <label class="form-label">Ścieżka do folderu</label>
                    <input type="text" name="file_storage_path" class="form-input"
                           value="<?= htmlspecialchars($client['file_storage_path'] ?? '') ?>"
                           placeholder="/mnt/shared/klienci/firma_xyz/">
                </div>
                <button type="submit" class="btn btn-primary">Zapisz</button>
            </div>
            <small style="color:var(--text-muted); display:block; margin-top:6px;">
                Pozostaw puste dla domyślnego folderu. Podaj ścieżkę bezwzględną do folderu udostępnionego (np. /mnt/shared/klienci/firma_xyz/).
            </small>
        </form>
    </div>
</div>

<!-- Upload Form -->
<div class="form-card" style="padding:20px; margin-bottom:20px;">
    <h3 style="margin-top:0; margin-bottom:12px;">Wyślij plik</h3>
    <form method="POST" action="/office/clients/<?= (int)$client['id'] ?>/files/upload" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

        <div style="display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end;">
            <div style="flex:1; min-width:200px;">
                <label class="form-label">Plik</label>
                <input type="file" name="file" class="form-input" required
                       accept=".pdf,.jpg,.jpeg,.png,.txt,.xls,.xlsx,.doc,.docx,.csv,.xml,.zip">
            </div>
            <div style="min-width:160px;">
                <label class="form-label">Kategoria</label>
                <select name="category" class="form-input">
                    <?php foreach ($categories as $key => $cat): ?>
                        <option value="<?= $key ?>"><?= htmlspecialchars($cat['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex:1; min-width:200px;">
                <label class="form-label">Opis <small style="color:var(--text-muted);">(opcjonalnie)</small></label>
                <textarea name="description" class="form-input" rows="1" maxlength="500" placeholder="Krótki opis pliku..."></textarea>
            </div>
            <div>
                <button type="submit" class="btn btn-primary">Wyślij</button>
            </div>
        </div>
        <small style="color:var(--text-muted); display:block; margin-top:8px;">
            Max 10 MB. Dozwolone formaty: PDF, JPG, PNG, TXT, XLS, XLSX, DOC, DOCX, CSV, XML, ZIP
        </small>
    </form>
</div>

<!-- Category Filter -->
<div class="form-card" style="padding:12px 16px; margin-bottom:20px;">
    <div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
        <span style="font-weight:600; margin-right:8px;">Filtruj:</span>
        <a href="/office/clients/<?= (int)$client['id'] ?>/files"
           class="btn btn-sm <?= $currentCategory === null ? 'btn-primary' : '' ?>"
           style="<?= $currentCategory === null ? '' : 'background:var(--gray-100); color:var(--gray-700);' ?>">
            Wszystkie
        </a>
        <?php foreach ($categories as $key => $cat): ?>
            <a href="/office/clients/<?= (int)$client['id'] ?>/files?category=<?= $key ?>"
               class="btn btn-sm <?= $currentCategory === $key ? 'btn-primary' : '' ?>"
               style="<?= $currentCategory === $key ? '' : 'background:var(--gray-100); color:var(--gray-700);' ?>">
                <?= htmlspecialchars($cat['label']) ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Files Table -->
<?php if (empty($files)): ?>
    <div class="alert alert-info">Brak plików<?= $currentCategory ? ' w wybranej kategorii' : '' ?>.</div>
<?php else: ?>
    <div class="card">
        <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Nazwa pliku</th>
                    <th>Kategoria</th>
                    <th>Rozmiar</th>
                    <th>Dodano przez</th>
                    <th>Data</th>
                    <th>Akcje</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($files as $f): ?>
                    <?php $cat = $categories[$f['category']] ?? $categories['general']; ?>
                    <tr>
                        <td>
                            <a href="/office/files/<?= (int)$f['id'] ?>/download" style="text-decoration:none;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle; margin-right:4px;">
                                    <path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/>
                                </svg>
                                <?= htmlspecialchars($f['original_filename']) ?>
                            </a>
                            <?php if (!empty($f['description'])): ?>
                                <br><small style="color:var(--text-muted);"><?= htmlspecialchars($f['description']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="display:inline-block; padding:2px 8px; border-radius:12px; font-size:12px; font-weight:600; color:#fff; background:<?= htmlspecialchars($cat['color']) ?>;">
                                <?= htmlspecialchars($cat['label']) ?>
                            </span>
                        </td>
                        <td style="white-space:nowrap;"><?= formatFileSize((int)$f['file_size']) ?></td>
                        <td><?= getUploaderName($f) ?></td>
                        <td style="white-space:nowrap;"><?= date('d.m.Y H:i', strtotime($f['created_at'])) ?></td>
                        <td style="white-space:nowrap;">
                            <a href="/office/files/<?= (int)$f['id'] ?>/download" class="btn btn-sm btn-primary" title="Pobierz">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;">
                                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                                </svg>
                            </a>
                            <form method="POST" action="/office/files/<?= (int)$f['id'] ?>/delete" style="display:inline;"
                                  onsubmit="return confirm('Czy na pewno chcesz usunąć ten plik?');">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <button type="submit" class="btn btn-sm btn-danger" title="Usuń">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;">
                                        <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                                    </svg>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
<?php endif; ?>
