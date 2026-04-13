<h1><?= $lang('import_contractors') ?></h1>

<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (!empty($importResult)): ?>
<div class="form-card" style="padding:20px; margin-bottom:20px;">
    <h3><?= $lang('import_results') ?></h3>
    <div style="display:flex; gap:16px; margin-bottom:12px; flex-wrap:wrap;">
        <div><span style="font-weight:700; color:var(--green-600);"><?= $importResult['success'] ?></span> <?= $lang('imported') ?></div>
        <div><span style="font-weight:700; color:var(--yellow-600);"><?= $importResult['skipped'] ?></span> <?= $lang('skipped_duplicates') ?></div>
        <?php if (!empty($importResult['errors'])): ?>
            <div><span style="font-weight:700; color:var(--red-600);"><?= count($importResult['errors']) ?></span> <?= $lang('errors') ?></div>
        <?php endif; ?>
    </div>
    <?php if (!empty($importResult['errors'])): ?>
        <details>
            <summary style="cursor:pointer; color:var(--red-600);"><?= $lang('show_errors') ?></summary>
            <ul style="margin-top:8px; font-size:13px;">
                <?php foreach ($importResult['errors'] as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </details>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="section">
    <div class="form-card" style="padding:20px;">
        <p style="margin-bottom:16px;"><?= $lang('import_contractors_desc') ?></p>

        <div style="margin-bottom:16px;">
            <a href="/client/contractors/import/template" class="btn btn-sm"><?= $lang('download_template') ?></a>
        </div>

        <form method="POST" action="/client/contractors/import" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="form-group">
                <label class="form-label"><?= $lang('select_file') ?> (CSV, XLS, XLSX)</label>
                <input type="file" name="file" class="form-input" accept=".csv,.xls,.xlsx" required>
            </div>
            <button type="submit" class="btn btn-primary"><?= $lang('import') ?></button>
            <a href="/client/contractors" class="btn" style="margin-left:8px;"><?= $lang('back') ?></a>
        </form>
    </div>
</div>
