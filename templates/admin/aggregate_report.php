<h1><?= $lang('aggregate_report') ?></h1>

<div class="form-card" style="margin-bottom: 20px;">
    <form method="POST" action="/admin/reports/aggregate">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div class="form-row">
            <div class="form-group">
                <label class="form-label"><?= $lang('select_clients') ?> *</label>
                <select name="client_ids[]" class="form-input" multiple size="6" required>
                    <?php foreach ($clients as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= in_array($c['id'], $filters['client_ids'] ?? []) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['company_name']) ?> (<?= $c['nip'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><?= $lang('date_from') ?></label>
                <input type="date" name="date_from" class="form-input" value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label"><?= $lang('date_to') ?></label>
                <input type="date" name="date_to" class="form-input" value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>">
            </div>
        </div>
        <div style="display:flex;gap:8px;">
            <button type="submit" class="btn btn-primary"><?= $lang('generate_report') ?></button>
            <button type="submit" name="format" value="pdf" class="btn btn-secondary"><?= $lang('download_pdf') ?></button>
        </div>
    </form>
</div>

<?php if ($results !== null): ?>
<div class="section">
    <table class="table">
        <thead>
            <tr>
                <th><?= $lang('client') ?></th>
                <th>NIP</th>
                <th><?= $lang('total') ?></th>
                <th><?= $lang('accepted') ?></th>
                <th><?= $lang('rejected') ?></th>
                <th><?= $lang('pending') ?></th>
                <th><?= $lang('gross_amount') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($results as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['client']['company_name']) ?></td>
                <td><?= htmlspecialchars($r['client']['nip']) ?></td>
                <td><?= $r['total'] ?></td>
                <td><span class="badge badge-success"><?= $r['accepted'] ?></span></td>
                <td><span class="badge badge-error"><?= $r['rejected'] ?></span></td>
                <td><span class="badge badge-warning"><?= $r['pending'] ?></span></td>
                <td class="text-right"><?= number_format($r['gross'], 2, ',', ' ') ?> PLN</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="table-footer">
                <td colspan="2"><strong><?= $lang('summary') ?></strong></td>
                <td><strong><?= $totals['accepted'] + $totals['rejected'] + $totals['pending'] ?></strong></td>
                <td><strong><?= $totals['accepted'] ?></strong></td>
                <td><strong><?= $totals['rejected'] ?></strong></td>
                <td><strong><?= $totals['pending'] ?></strong></td>
                <td class="text-right"><strong><?= number_format($totals['gross'], 2, ',', ' ') ?> PLN</strong></td>
            </tr>
        </tfoot>
    </table>
</div>
<?php endif; ?>
