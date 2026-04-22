<div class="section-header">
    <div>
        <div class="breadcrumb-path" style="font-size:13px;color:var(--text-muted);margin-bottom:4px;">
            <a href="/office/hr/settings"><?= $lang('hr_module') ?></a> &rsaquo;
            <a href="/office/hr/<?= $client['id'] ?>/employees"><?= htmlspecialchars($client['company_name']) ?></a> &rsaquo;
            <a href="/office/hr/<?= $client['id'] ?>/employees/<?= $employee['id'] ?>"><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></a> &rsaquo;
            <?= $lang('hr_eteczka') ?>
        </div>
        <h1>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            <?= $lang('hr_eteczka') ?> — <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?>
        </h1>
    </div>
    <a href="/office/hr/<?= $client['id'] ?>/employees/<?= $employee['id'] ?>" class="btn btn-secondary">
        <?= $lang('back') ?>
    </a>
</div>

<?php if ($flash_success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($flash_success) ?></div>
<?php endif; ?>
<?php if ($flash_error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($flash_error) ?></div>
<?php endif; ?>

<!-- Upload Form -->
<div class="card" style="margin-bottom:24px;">
    <div class="card-header"><strong><?= $lang('hr_doc_upload') ?></strong></div>
    <div class="card-body">
        <form method="POST" action="/office/hr/<?= $client['id'] ?>/employees/<?= $employee['id'] ?>/documents/upload" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:12px;align-items:end;">
                <div>
                    <label class="form-label"><?= $lang('hr_doc_category') ?> *</label>
                    <select name="category" class="form-control" required>
                        <option value="umowa"><?= $lang('hr_doc_cat_umowa') ?></option>
                        <option value="aneks"><?= $lang('hr_doc_cat_aneks') ?></option>
                        <option value="pit2"><?= $lang('hr_doc_cat_pit2') ?></option>
                        <option value="bhp"><?= $lang('hr_doc_cat_bhp') ?></option>
                        <option value="badanie"><?= $lang('hr_doc_cat_badanie') ?></option>
                        <option value="certyfikat"><?= $lang('hr_doc_cat_certyfikat') ?></option>
                        <option value="swiadectwo"><?= $lang('hr_doc_cat_swiadectwo') ?></option>
                        <option value="inne" selected><?= $lang('hr_doc_cat_inne') ?></option>
                    </select>
                </div>
                <div>
                    <label class="form-label"><?= $lang('hr_doc_expires') ?></label>
                    <input type="date" name="expiry_date" class="form-control">
                </div>
                <div>
                    <label class="form-label"><?= $lang('file') ?> * <small style="color:var(--text-muted)">(PDF, JPG, PNG, DOC max 10MB)</small></label>
                    <input type="file" name="document" class="form-control" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                </div>
                <div>
                    <button type="submit" class="btn btn-primary">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0018 9h-1.26A8 8 0 103 16.3"/></svg>
                        <?= $lang('upload') ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Document List -->
<div class="card">
    <div class="card-header">
        <strong><?= $lang('hr_documents') ?></strong>
        <span class="badge"><?= count($documents) ?></span>
    </div>
    <?php if (empty($documents)): ?>
        <div class="card-body" style="color:var(--text-muted);text-align:center;padding:32px;">
            <?= $lang('no_documents') ?>
        </div>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th><?= $lang('hr_doc_category') ?></th>
                    <th><?= $lang('name') ?></th>
                    <th><?= $lang('hr_doc_expires') ?></th>
                    <th><?= $lang('file_size') ?></th>
                    <th><?= $lang('uploaded_at') ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($documents as $doc): ?>
                    <?php
                        $expiry  = $doc['expiry_date'] ?? null;
                        $isExpired = $expiry && $expiry < date('Y-m-d');
                        $expiringSoon = $expiry && !$isExpired && $expiry <= date('Y-m-d', strtotime('+30 days'));
                        $expiryClass = $isExpired ? 'color:#dc2626;font-weight:bold;' : ($expiringSoon ? 'color:#d97706;' : '');
                    ?>
                    <tr>
                        <td>
                            <span class="badge badge-secondary"><?= htmlspecialchars($lang('hr_doc_cat_' . $doc['category'])) ?></span>
                        </td>
                        <td><?= htmlspecialchars($doc['original_name'] ?? '') ?></td>
                        <td style="<?= $expiryClass ?>">
                            <?php if ($expiry): ?>
                                <?= htmlspecialchars($expiry) ?>
                                <?php if ($isExpired): ?>
                                    <span class="badge badge-danger"><?= $lang('hr_doc_expired') ?></span>
                                <?php elseif ($expiringSoon): ?>
                                    <span class="badge badge-warning"><?= $lang('hr_doc_expires_soon') ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?= $doc['file_size'] ? number_format($doc['file_size'] / 1024, 1) . ' KB' : '—' ?></td>
                        <td><?= htmlspecialchars(substr($doc['uploaded_at'] ?? '', 0, 16)) ?></td>
                        <td style="text-align:right;white-space:nowrap;">
                            <a href="/office/hr/<?= $client['id'] ?>/employees/<?= $employee['id'] ?>/documents/<?= $doc['id'] ?>/download"
                               class="btn btn-sm btn-secondary">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="8 17 12 21 16 17"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.88 18.09A5 5 0 0018 9h-1.26A8 8 0 103 16.29"/></svg>
                                <?= $lang('download') ?>
                            </a>
                            <form method="POST" action="/office/hr/<?= $client['id'] ?>/employees/<?= $employee['id'] ?>/documents/<?= $doc['id'] ?>/delete"
                                  style="display:inline;" onsubmit="return confirm('<?= $lang('confirm_delete') ?>')">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>