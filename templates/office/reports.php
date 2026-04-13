<h1><?= $lang('reports') ?></h1>

<div class="section" style="margin-bottom:16px;">
    <form method="GET" action="/office/reports" style="display:flex;gap:12px;align-items:flex-end;">
        <div class="form-group" style="margin-bottom:0;">
            <label class="form-label"><?= $lang('client') ?></label>
            <select name="client_id" class="form-input">
                <option value=""><?= $lang('all_clients') ?></option>
                <?php foreach ($clients as $c): ?>
                <option value="<?= $c['id'] ?>" <?= ($selectedClient ?? null) == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['company_name']) ?> (<?= $c['nip'] ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><?= $lang('filter') ?></button>
    </form>
</div>

<?php if (empty($reports)): ?>
    <div class="empty-state">
        <p><?= $lang('no_reports') ?></p>
    </div>
<?php else: ?>
<table class="table">
    <thead>
        <tr>
            <th><?= $lang('client') ?></th>
            <th><?= $lang('period') ?></th>
            <th><?= $lang('type') ?></th>
            <th><?= $lang('created_at') ?></th>
            <th><?= $lang('actions') ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($reports as $r): ?>
        <tr>
            <td><?= htmlspecialchars($r['company_name']) ?> (<?= $r['nip'] ?>)</td>
            <td><?= sprintf('%02d/%04d', $r['period_month'], $r['period_year']) ?></td>
            <td>
                <?php if ($r['report_type'] === 'accepted'): ?>
                    <span class="badge badge-success"><?= $lang('accepted') ?></span>
                <?php else: ?>
                    <span class="badge badge-error"><?= $lang('rejected') ?></span>
                <?php endif; ?>
                <?php if (!empty($r['cost_center_name'])): ?>
                    <span class="badge"><?= htmlspecialchars($r['cost_center_name']) ?></span>
                <?php endif; ?>
            </td>
            <td><?= $r['created_at'] ?></td>
            <td class="action-buttons">
                <?php if ($r['pdf_path']): ?>
                    <a href="/office/reports/<?= $r['id'] ?>/download?type=pdf" class="btn btn-sm">PDF</a>
                <?php endif; ?>
                <?php if ($r['xls_path']): ?>
                    <a href="/office/reports/<?= $r['id'] ?>/download?type=xls" class="btn btn-sm">XLSX</a>
                <?php endif; ?>
                <?php if (!empty($r['xml_path'])): ?>
                    <a href="/office/reports/<?= $r['id'] ?>/download?type=xml" class="btn btn-sm"><?= $lang('download_jpk') ?></a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
