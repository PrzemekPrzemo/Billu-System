<?php
$categoryLabels = [
    'umowa' => 'Umowa', 'aneks' => 'Aneks', 'pit2' => 'PIT-2',
    'bhp' => 'BHP', 'badanie' => 'Badanie lekarskie', 'certyfikat' => 'Certyfikat',
    'swiadectwo' => 'Świadectwo', 'inne' => 'Inne',
];
// Client can upload only these categories
$uploadCategories = ['pit2' => 'PIT-2', 'bhp' => 'BHP', 'badanie' => 'Badanie lekarskie', 'certyfikat' => 'Certyfikat', 'inne' => 'Inne'];
?>

<div class="section-header">
    <div>
        <div class="breadcrumb-path" style="font-size:13px;color:var(--text-muted);margin-bottom:4px;">
            <a href="/client/hr/employees"><?= $lang('hr_employees') ?></a> &rsaquo;
            <a href="/client/hr/employees/<?= $employee['id'] ?>"><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></a> &rsaquo;
            Dokumenty
        </div>
        <h1>Dokumenty — <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></h1>
    </div>
    <div style="display:flex;gap:8px;">
        <button class="btn btn-primary" onclick="document.getElementById('upload-modal').style.display='flex'">+ Dodaj dokument</button>
        <a href="/client/hr/employees/<?= $employee['id'] ?>" class="btn btn-secondary"><?= $lang('back') ?></a>
    </div>
</div>

<?php include __DIR__ . '/../hr_nav.php'; ?>

<?php if ($flash_success): ?><div class="alert alert-success"><?= htmlspecialchars($flash_success) ?></div><?php endif; ?>
<?php if ($flash_error): ?><div class="alert alert-error"><?= htmlspecialchars($flash_error) ?></div><?php endif; ?>

<?php if (empty($documents)): ?>
<div class="empty-state"><p>Brak dokumentów dla tego pracownika.</p></div>
<?php else: ?>
<div class="card">
    <div class="card-body" style="padding:0;">
        <table class="table" style="margin:0;">
            <thead>
                <tr>
                    <th>Nazwa</th>
                    <th>Kategoria</th>
                    <th>Rozmiar</th>
                    <th>Data ważności</th>
                    <th>Data dodania</th>
                    <th>Pobierz</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($documents as $doc):
                    $isExpired = $doc['expiry_date'] && strtotime($doc['expiry_date']) < time();
                    $isExpiring = $doc['expiry_date'] && !$isExpired && strtotime($doc['expiry_date']) < strtotime('+30 days');
                ?>
                <tr style="<?= $isExpired ? 'background:#fff5f5;' : ($isExpiring ? 'background:#fffbeb;' : '') ?>">
                    <td><strong><?= htmlspecialchars($doc['original_name']) ?></strong></td>
                    <td><span class="badge badge-secondary"><?= $categoryLabels[$doc['category']] ?? $doc['category'] ?></span></td>
                    <td style="font-size:12px;color:var(--text-muted);">
                        <?= number_format($doc['file_size'] / 1024, 0) ?> KB
                    </td>
                    <td>
                        <?php if ($doc['expiry_date']): ?>
                            <span style="color:<?= $isExpired ? 'var(--danger)' : ($isExpiring ? 'var(--warning)' : 'var(--text)') ?>;font-weight:<?= $isExpired || $isExpiring ? '600' : '400' ?>;">
                                <?= $doc['expiry_date'] ?>
                            </span>
                            <?php if ($isExpired): ?><span class="badge badge-danger" style="font-size:10px;">wygasły</span><?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12px;color:var(--text-muted);"><?= date('Y-m-d', strtotime($doc['uploaded_at'])) ?></td>
                    <td>
                        <a href="/client/hr/employees/<?= $employee['id'] ?>/documents/<?= $doc['id'] ?>/download"
                           class="btn btn-xs btn-secondary" title="Pobierz">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        </a>
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
    <div class="card" style="width:100%;max-width:440px;margin:24px;">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <h3 style="margin:0;">Dodaj dokument</h3>
            <button onclick="document.getElementById('upload-modal').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--text-muted);">&times;</button>
        </div>
        <div class="card-body">
            <form method="POST" action="/client/hr/employees/<?= $employee['id'] ?>/documents/upload" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                <div class="form-group">
                    <label class="form-label">Kategoria *</label>
                    <select name="category" class="form-control" required>
                        <?php foreach ($uploadCategories as $val => $label): ?>
                        <option value="<?= $val ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Plik * <small style="color:var(--text-muted);">(PDF, JPG, PNG, DOC, DOCX, max 10 MB)</small></label>
                    <input type="file" name="document" class="form-control" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                </div>

                <div class="form-group">
                    <label class="form-label">Data ważności <small style="color:var(--text-muted);">(opcjonalnie)</small></label>
                    <input type="date" name="expiry_date" class="form-control">
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;">Prześlij</button>
            </form>
        </div>
    </div>
</div>
