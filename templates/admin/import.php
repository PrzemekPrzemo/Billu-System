<h1><?= $lang('import_invoices') ?></h1>

<?php if (!empty($importTemplates)): ?>
<div class="card" style="margin-bottom:20px;padding:16px;">
    <h3 style="margin-bottom:12px;"><?= $lang('import_templates') ?></h3>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php foreach ($importTemplates as $tpl): ?>
        <div style="padding:8px 12px;background:#f5f5f5;border-radius:6px;display:flex;align-items:center;gap:8px;">
            <span><?= htmlspecialchars($tpl['name']) ?></span>
            <small class="text-muted">(<?= htmlspecialchars($tpl['separator'] ?? ';') ?> / <?= htmlspecialchars($tpl['encoding'] ?? 'UTF-8') ?>)</small>
            <form method="POST" action="/admin/import/template-delete/<?= $tpl['id'] ?>" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <button type="submit" class="btn btn-danger btn-xs" onclick="return confirm('<?= $lang('confirm_delete') ?>')">&times;</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<details style="margin-bottom:16px;">
    <summary class="btn btn-secondary btn-sm"><?= $lang('import_template_save') ?></summary>
    <div class="card" style="padding:16px;margin-top:8px;">
        <form method="POST" action="/admin/import/template-save">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><?= $lang('template_name') ?></label>
                    <input type="text" name="template_name" class="form-input" required placeholder="<?= $lang('import_template_name_placeholder') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('erp_separator') ?></label>
                    <select name="separator" class="form-input">
                        <option value=";">; (<?= $lang('semicolon') ?>)</option>
                        <option value=",">, (<?= $lang('comma') ?>)</option>
                        <option value="&#9;">TAB</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><?= $lang('erp_encoding') ?></label>
                    <select name="encoding" class="form-input">
                        <option value="UTF-8">UTF-8</option>
                        <option value="Windows-1250">Windows-1250</option>
                        <option value="ISO-8859-2">ISO-8859-2</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('skip_rows') ?></label>
                    <input type="number" name="skip_rows" class="form-input" value="1" min="0">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label"><?= $lang('erp_column_mapping') ?> (JSON)</label>
                <textarea name="column_mapping" class="form-input" rows="3" placeholder='{"A":"seller_nip","B":"seller_name",...}'></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><?= $lang('save') ?></button>
        </form>
    </div>
</details>

<form method="POST" action="/admin/import" enctype="multipart/form-data" class="form-card">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

    <div class="form-row">
        <div class="form-group">
            <label class="form-label"><?= $lang('client') ?> *</label>
            <select name="client_id" class="form-input" required>
                <option value=""><?= $lang('select_client') ?></option>
                <?php foreach ($clients as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?> (<?= $c['nip'] ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label class="form-label"><?= $lang('month') ?> *</label>
            <select name="month" class="form-input" required>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $m == date('n') ? 'selected' : '' ?>><?= sprintf('%02d', $m) ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label"><?= $lang('year') ?> *</label>
            <select name="year" class="form-input" required>
                <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                    <option value="<?= $y ?>" <?= $y == date('Y') ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label"><?= $lang('file') ?> *</label>
        <input type="file" name="file" class="form-input" required accept=".xls,.xlsx,.csv,.txt">
        <small class="form-hint"><?= $lang('import_file_hint') ?></small>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $lang('import_button') ?></button>
    </div>
</form>

<?php
$importResult = \App\Core\Session::get('import_result');
\App\Core\Session::remove('import_result');
if ($importResult): ?>
<div class="section">
    <h2><?= $lang('import_results') ?></h2>
    <div class="result-card">
        <p><strong><?= $lang('total_rows') ?>:</strong> <?= $importResult['total'] ?></p>
        <p><strong><?= $lang('imported_success') ?>:</strong> <span class="text-success"><?= $importResult['success'] ?></span></p>
        <?php if (!empty($importResult['errors'])): ?>
            <p><strong><?= $lang('errors') ?>:</strong></p>
            <ul class="error-list">
                <?php foreach ($importResult['errors'] as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($ksefConfigured)): ?>
<div class="section">
    <h2><?= $lang('ksef_import') ?></h2>
    <p class="text-muted"><?= $lang('ksef_import_desc') ?></p>
    <form method="POST" action="/admin/import/ksef" class="form-card" id="ksef-import-form">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

        <div class="form-group">
            <label class="form-label"><?= $lang('client') ?> *</label>
            <select name="client_id" class="form-input" required>
                <option value=""><?= $lang('select_client') ?></option>
                <?php foreach ($clients as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?> (<?= $c['nip'] ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label"><?= $lang('month') ?> *</label>
                <select name="month" class="form-input" required>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $m == date('n') ? 'selected' : '' ?>><?= sprintf('%02d', $m) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><?= $lang('year') ?> *</label>
                <select name="year" class="form-input" required>
                    <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                        <option value="<?= $y ?>" <?= $y == date('Y') ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary" id="ksef-import-btn"><?= $lang('import_from_ksef') ?></button>
        </div>

        <div id="ksef-import-progress" style="display:none; margin-top:12px;">
            <div class="alert alert-info">
                <span id="ksef-import-status-text"><?= $lang('ksef_import_starting') ?></span>
                <span class="ksef-spinner" style="display:inline-block; width:16px; height:16px; border:2px solid rgba(0,0,0,0.2); border-top-color:#008F8F; border-radius:50%; animation:spin 0.8s linear infinite; vertical-align:middle; margin-left:8px;"></span>
            </div>
        </div>
        <div id="ksef-import-result" style="display:none; margin-top:12px;"></div>
    </form>

    <?php
    $pendingJobId = \App\Core\Session::get('ksef_import_job_id');
    if ($pendingJobId) { \App\Core\Session::remove('ksef_import_job_id'); }
    ?>
    <script>
    (function() {
        var jobId = <?= json_encode($pendingJobId ?? '') ?>;
        var statusUrl = '/admin/import/ksef-status';
        var form = document.getElementById('ksef-import-form');
        var btn = document.getElementById('ksef-import-btn');
        var progress = document.getElementById('ksef-import-progress');
        var statusText = document.getElementById('ksef-import-status-text');
        var resultDiv = document.getElementById('ksef-import-result');

        if (jobId) { startPolling(jobId); }

        form.addEventListener('submit', function() {
            btn.disabled = true;
            progress.style.display = 'block';
            resultDiv.style.display = 'none';
            statusText.textContent = '<?= $lang('ksef_import_starting') ?>';
        });

        function startPolling(id) {
            btn.disabled = true;
            progress.style.display = 'block';
            resultDiv.style.display = 'none';
            poll(id);
        }

        function poll(id) {
            fetch(statusUrl + '?job_id=' + encodeURIComponent(id))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.status === 'running' || data.status === 'queued') {
                        statusText.textContent = data.message || '<?= $lang('ksef_import_running') ?>';
                        setTimeout(function() { poll(id); }, 2000);
                    } else {
                        progress.style.display = 'none';
                        btn.disabled = false;
                        showResult(data);
                    }
                })
                .catch(function() {
                    progress.style.display = 'none';
                    btn.disabled = false;
                    resultDiv.style.display = 'block';
                    resultDiv.innerHTML = '<div class="alert alert-error"><?= $lang('ksef_import_poll_error') ?></div>';
                });
        }

        function showResult(data) {
            resultDiv.style.display = 'block';
            var r = data.result || {};
            if (data.status === 'error') {
                resultDiv.innerHTML = '<div class="alert alert-error">' + escHtml(data.message) + '</div>';
            } else if (r.success > 0) {
                resultDiv.innerHTML = '<div class="alert alert-success">' + escHtml(data.message) + '</div>';
            } else if (r.total === 0) {
                resultDiv.innerHTML = '<div class="alert alert-warning">' + escHtml(data.message) + '</div>';
            } else {
                var html = '<div class="alert alert-warning">' + escHtml(data.message);
                if (r.errors && r.errors.length) {
                    html += '<ul style="margin-top:8px;">';
                    r.errors.forEach(function(e) { html += '<li>' + escHtml(e) + '</li>'; });
                    html += '</ul>';
                }
                html += '</div>';
                resultDiv.innerHTML = html;
            }
        }

        function escHtml(s) { var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }
    })();
    </script>
</div>
<?php endif; ?>

<div class="section">
    <h2><?= $lang('import_format_info') ?></h2>
    <div class="info-card">
        <p><?= $lang('import_format_desc') ?></p>
        <table class="table table-compact">
            <thead>
                <tr><th><?= $lang('column') ?></th><th><?= $lang('description') ?></th><th><?= $lang('required') ?></th></tr>
            </thead>
            <tbody>
                <tr><td>A</td><td>NIP <?= $lang('seller') ?></td><td>*</td></tr>
                <tr><td>B</td><td><?= $lang('seller_name') ?></td><td>*</td></tr>
                <tr><td>C</td><td><?= $lang('seller_address') ?></td><td></td></tr>
                <tr><td>D</td><td><?= $lang('seller_contact') ?></td><td></td></tr>
                <tr><td>E</td><td>NIP <?= $lang('buyer') ?></td><td>*</td></tr>
                <tr><td>F</td><td><?= $lang('buyer_name') ?></td><td>*</td></tr>
                <tr><td>G</td><td><?= $lang('buyer_address') ?></td><td></td></tr>
                <tr><td>H</td><td><?= $lang('invoice_number') ?></td><td>*</td></tr>
                <tr><td>I</td><td><?= $lang('issue_date') ?></td><td>*</td></tr>
                <tr><td>J</td><td><?= $lang('sale_date') ?></td><td></td></tr>
                <tr><td>K</td><td><?= $lang('currency') ?></td><td></td></tr>
                <tr><td>L</td><td><?= $lang('net_amount') ?></td><td>*</td></tr>
                <tr><td>M</td><td><?= $lang('vat_amount') ?></td><td>*</td></tr>
                <tr><td>N</td><td><?= $lang('gross_amount') ?></td><td>*</td></tr>
                <tr><td>O</td><td><?= $lang('line_items') ?></td><td></td></tr>
                <tr><td>P</td><td><?= $lang('vat_details') ?></td><td></td></tr>
            </tbody>
        </table>
    </div>
</div>
