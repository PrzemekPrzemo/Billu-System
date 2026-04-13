<h1><?= $lang('bulk_import_clients') ?></h1>

<p class="text-muted"><?= $lang('bulk_import_desc') ?></p>

<form method="POST" action="/admin/clients/bulk-import" enctype="multipart/form-data" class="form-card">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

    <div class="form-group">
        <label class="form-label"><?= $lang('office') ?></label>
        <select name="office_id" class="form-input">
            <option value="">-</option>
            <?php foreach ($offices as $o): ?>
                <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label class="form-label"><?= $lang('file') ?> *</label>
        <input type="file" name="file" class="form-input" required accept=".txt,.csv">
    </div>

    <div class="info-card">
        <p><strong><?= $lang('bulk_import_format') ?>:</strong></p>
        <p>NIP;Company Name;Representative;Email;Report Email</p>
        <small class="form-hint"><?= $lang('bulk_import_format') ?> - tab or semicolon separated</small>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $lang('import_button') ?></button>
        <a href="/admin/clients" class="btn btn-secondary"><?= $lang('cancel') ?></a>
    </div>
</form>

<?php
$bulkResult = \App\Core\Session::get('bulk_import_result');
\App\Core\Session::remove('bulk_import_result');
if ($bulkResult): ?>
<div class="section">
    <h2><?= $lang('import_results') ?></h2>
    <div class="result-card">
        <p><strong><?= $lang('total_rows') ?>:</strong> <?= $bulkResult['total'] ?></p>
        <p><strong><?= $lang('imported_success') ?>:</strong> <span class="text-success"><?= $bulkResult['success'] ?></span></p>
        <?php if (!empty($bulkResult['errors'])): ?>
            <p><strong><?= $lang('errors') ?>:</strong></p>
            <ul class="error-list">
                <?php foreach ($bulkResult['errors'] as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if (!empty($bulkResult['passwords'])): ?>
        <h3><?= $lang('generated_passwords') ?></h3>
        <table class="table table-compact">
            <thead>
                <tr>
                    <th>NIP</th>
                    <th><?= $lang('company_name') ?></th>
                    <th><?= $lang('password') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bulkResult['passwords'] as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['nip']) ?></td>
                    <td><?= htmlspecialchars($p['company_name']) ?></td>
                    <td><code><?= htmlspecialchars($p['password']) ?></code></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
