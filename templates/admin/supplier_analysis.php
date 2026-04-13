<h1><?= $lang('supplier_analysis') ?></h1>

<div class="form-card" style="margin-bottom:20px;">
    <form method="GET" action="/admin/reports/suppliers">
        <div class="form-row">
            <div class="form-group">
                <label class="form-label"><?= $lang('client') ?></label>
                <select name="client_id" class="form-input" required>
                    <option value=""><?= $lang('select_client') ?></option>
                    <?php foreach ($clients as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($filters['client_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>>
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
            <div class="form-group" style="align-self:flex-end;">
                <button type="submit" class="btn btn-primary"><?= $lang('generate_report') ?></button>
            </div>
        </div>
    </form>
</div>

<?php if (!empty($suppliers) && $selectedClient): ?>
<div class="section">
    <h2><?= htmlspecialchars($selectedClient['company_name']) ?> — <?= $lang('supplier_analysis') ?></h2>

    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th><?= $lang('supplier_name') ?></th>
                <th>NIP</th>
                <th><?= $lang('total_invoices') ?></th>
                <th><?= $lang('net_amount') ?></th>
                <th><?= $lang('gross_amount') ?></th>
                <th><?= $lang('avg_invoice') ?></th>
                <th><?= $lang('trend_6m') ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php $rank = 0; ?>
            <?php foreach ($suppliers as $s): ?>
            <?php $rank++; ?>
            <tr>
                <td><strong><?= $rank ?></strong></td>
                <td>
                    <?= htmlspecialchars($s['seller_name']) ?>
                    <?php if ($s['anomaly']): ?>
                        <span title="<?= $lang('anomaly_detected') ?>" style="color:#dc2626;font-weight:bold;cursor:help;"> &#9888;</span>
                    <?php endif; ?>
                </td>
                <td><code><?= htmlspecialchars($s['seller_nip']) ?></code></td>
                <td class="text-center"><?= $s['invoice_count'] ?></td>
                <td class="text-right"><?= number_format((float) $s['total_net'], 2, ',', ' ') ?></td>
                <td class="text-right"><strong><?= number_format((float) $s['total_gross'], 2, ',', ' ') ?></strong></td>
                <td class="text-right"><?= number_format((float) $s['avg_gross'], 2, ',', ' ') ?></td>
                <td>
                    <?php if (!empty($s['trend'])): ?>
                    <?php
                        $maxTrend = max(array_column($s['trend'], 'total_gross')) ?: 1;
                    ?>
                    <div style="display:flex;align-items:flex-end;gap:2px;height:24px;">
                        <?php foreach ($s['trend'] as $t): ?>
                        <?php $h = ((float) $t['total_gross'] / $maxTrend) * 100; ?>
                        <div style="width:8px;background:var(--primary);border-radius:2px 2px 0 0;height:<?= round($h) ?>%;min-height:2px;"
                             title="<?= sprintf('%02d/%d', $t['month'], $t['year'] % 100) ?>: <?= number_format((float) $t['total_gross'], 2, ',', ' ') ?> PLN"></div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($s['anomaly']): ?>
                        <span class="badge badge-error"><?= $lang('anomaly') ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
