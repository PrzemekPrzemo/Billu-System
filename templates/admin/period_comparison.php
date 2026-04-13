<h1><?= $lang('period_comparison') ?></h1>

<div class="form-card" style="margin-bottom:20px;">
    <form method="GET" action="/admin/reports/comparison">
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
                <label class="form-label"><?= $lang('months') ?></label>
                <select name="months" class="form-input">
                    <?php foreach ([6, 12, 18, 24] as $m): ?>
                        <option value="<?= $m ?>" <?= ($filters['months'] ?? 12) == $m ? 'selected' : '' ?>><?= $m ?> <?= $lang('months_label') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="align-self:flex-end;">
                <button type="submit" class="btn btn-primary"><?= $lang('generate_report') ?></button>
            </div>
        </div>
    </form>
</div>

<?php if (!empty($data) && $selectedClient): ?>
<div class="section">
    <h2><?= htmlspecialchars($selectedClient['company_name']) ?> — <?= $lang('period_comparison') ?></h2>

    <!-- Bar chart -->
    <div style="margin:20px 0;overflow-x:auto;">
        <?php
            $maxGross = max(array_column($data, 'gross_total')) ?: 1;
        ?>
        <div style="display:flex;align-items:flex-end;gap:6px;height:200px;min-width:<?= count($data) * 50 ?>px;padding-bottom:24px;position:relative;">
            <?php foreach ($data as $d): ?>
            <?php $pct = ($d['gross_total'] / $maxGross) * 100; ?>
            <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%;">
                <div style="width:100%;max-width:40px;background:var(--primary);border-radius:4px 4px 0 0;height:<?= round($pct) ?>%;min-height:4px;transition:height 0.3s;" title="<?= number_format((float)$d['gross_total'], 2, ',', ' ') ?> PLN"></div>
                <small style="font-size:0.7em;margin-top:4px;white-space:nowrap;"><?= sprintf('%02d/%d', $d['month'], $d['year'] % 100) ?></small>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Data table -->
    <table class="table">
        <thead>
            <tr>
                <th><?= $lang('period') ?></th>
                <th><?= $lang('total_invoices') ?></th>
                <th><?= $lang('accepted') ?></th>
                <th><?= $lang('rejected') ?></th>
                <th><?= $lang('net_amount') ?></th>
                <th>VAT</th>
                <th><?= $lang('gross_amount') ?></th>
                <th><?= $lang('change') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php $prevGross = null; ?>
            <?php foreach ($data as $d): ?>
            <?php
                $gross = (float) $d['gross_total'];
                $delta = null;
                if ($prevGross !== null && $prevGross > 0) {
                    $delta = (($gross - $prevGross) / $prevGross) * 100;
                }
                $prevGross = $gross;
            ?>
            <tr>
                <td><strong><?= sprintf('%02d/%04d', $d['month'], $d['year']) ?></strong></td>
                <td class="text-center"><?= $d['total_count'] ?></td>
                <td class="text-center"><span class="badge badge-success"><?= $d['accepted'] ?></span></td>
                <td class="text-center"><span class="badge badge-error"><?= $d['rejected'] ?></span></td>
                <td class="text-right"><?= number_format((float) $d['net_total'], 2, ',', ' ') ?></td>
                <td class="text-right"><?= number_format((float) $d['vat_total'], 2, ',', ' ') ?></td>
                <td class="text-right"><strong><?= number_format($gross, 2, ',', ' ') ?></strong></td>
                <td class="text-center">
                    <?php if ($delta !== null): ?>
                        <?php if ($delta > 0): ?>
                            <span style="color:#dc2626;">&#9650; <?= number_format($delta, 1) ?>%</span>
                        <?php elseif ($delta < 0): ?>
                            <span style="color:#22c55e;">&#9660; <?= number_format(abs($delta), 1) ?>%</span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
