<?php $typeLabels = \App\Models\HrCompanyDocument::TYPE_LABELS; ?>

<div class="section-header">
    <div>
        <div class="breadcrumb-path" style="font-size:13px;color:var(--text-muted);margin-bottom:4px;">
            <a href="/office/hr/settings"><?= $lang('hr_module') ?></a> &rsaquo;
            <a href="/office/hr/<?= $clientId ?>/employees"><?= htmlspecialchars($client['company_name']) ?></a> &rsaquo;
            Dokumenty firmowe
        </div>
        <h1>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg>
            Dokumenty firmowe — <?= htmlspecialchars($client['company_name']) ?>
        </h1>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('upload-modal').style.display='flex'">+ Dodaj dokument</button>
</div>

<?php include __DIR__ . '/../hr_nav.php'; ?>

<?php if ($flash_success): ?><div class="alert alert-success"><?= htmlspecialchars($flash_success) ?></div><?php endif; ?>
<?php if ($flash_error): ?><div class="alert alert-error"><?= htmlspecialchars($flash_error) ?></div><?php endif; ?>

<?php if (empty($documents)): ?>
<div class="empty-state">
    <p>Brak dokumentów firmowych. Dodaj regulaminy, układy zbiorowe i inne dokumenty.</p>
    <button class="btn btn-primary" onclick="document.getElementById('upload-modal').style.display='flex'">Dodaj dokument</button>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body" style="padding:0;">
        <table class="table" style="margin:0;font-size:13px;">
            <thead>
                <tr>
                    <th>Tytuł</th>
                    <th>Typ</th>
                    <th>Rozmiar</th>
                    <th>Obowiązuje od</th>
                    <th>Obowiązuje do</th>
                    <th>Dodano</th>
                    <th><?= $lang('actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($documents as $doc): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($doc['title']) ?></strong></td>
                    <td><span class="badge badge-info"><?= $typeLabels[$doc['document_type']] ?? $doc['document_type'] ?></span></td>
                    <td style="font-size:12px;color:var(--text-muted);"><?= number_format($doc['file_size'] / 1024, 0) ?> KB</td>
                    <td><?= $doc['valid_from'] ?? '—' ?></td>
                    <td><?= $doc['valid_until'] ?? '—' ?></td>
                    <td style="font-size:12px;color:var(--text-muted);"><?= date('Y-m-d', strtotime($doc['created_at'])) ?></td>
                    <td>
                        <div style="display:flex;gap:4px;">
                            <a href="/office/hr/<?= $clientId ?>/company-docs/<?= $doc['id'] ?>/download"
                               class="btn btn-xs btn-secondary" title="Pobierz">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            </a>
                            <form method="POST" action="/office/hr/<?= $clientId ?>/company-docs/<?= $doc['id'] ?>/delete"
                                  onsubmit="return confirm('Usunąć dokument?');" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <button type="submit" class="btn btn-xs btn-danger" title="Usuń">&times;</button>
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

<!-- Upload Modal -->
<div id="upload-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div class="card" style="width:100%;max-width:480px;margin:24px;">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <h3 style="margin:0;">Dodaj dokument firmowy</h3>
            <button onclick="document.getElementById('upload-modal').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--text-muted);">&times;</button>
        </div>
        <div class="card-body">
            <form method="POST" action="/office/hr/<?= $clientId ?>/company-docs/upload" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                <div class="form-group">
                    <label class="form-label">Typ dokumentu *</label>
                    <select name="document_type" class="form-control" required>
                        <?php foreach ($typeLabels as $val => $label): ?>
                        <option value="<?= $val ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Tytuł</label>
                    <input type="text" name="title" class="form-control" placeholder="Nazwa dokumentu">
                </div>

                <div class="form-group">
                    <label class="form-label">Plik * <small style="color:var(--text-muted);">(PDF, DOC, DOCX, max 20 MB)</small></label>
                    <input type="file" name="document" class="form-control" required accept=".pdf,.doc,.docx">
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label class="form-label">Obowiązuje od</label>
                        <input type="date" name="valid_from" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Obowiązuje do</label>
                        <input type="date" name="valid_until" class="form-control">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;">Prześlij</button>
            </form>
        </div>
    </div>
</div>