<h2><?= $lang('tax_payments_title') ?></h2>

<!-- Year filter -->
<form method="get" action="/client/tax-payments" style="margin-bottom:20px;">
    <div class="form-group" style="margin-bottom:0;">
        <label class="form-label"><?= $lang('year') ?></label>
        <select name="year" class="form-input" style="max-width:200px;" onchange="this.form.submit()">
            <?php for ($y = (int)date('Y') + 1; $y >= 2023; $y--): ?>
                <option value="<?= $y ?>" <?= $filterYear == $y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
    </div>
</form>

<?php
$monthNames = [
    1 => 'Styczeń', 2 => 'Luty', 3 => 'Marzec', 4 => 'Kwiecień',
    5 => 'Maj', 6 => 'Czerwiec', 7 => 'Lipiec', 8 => 'Sierpień',
    9 => 'Wrzesień', 10 => 'Październik', 11 => 'Listopad', 12 => 'Grudzień',
];
$allowedStatuses = ['do_zaplaty', 'do_przeniesienia'];
$grid = $grid ?? [];
$hasData = !empty($grid);
?>

<?php if ($hasData): ?>
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th><?= $lang('month') ?></th>
                <?php foreach ($taxTypes as $type): ?>
                    <th style="text-align:center;"><?= htmlspecialchars($lang('tax_' . strtolower($type))) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php for ($m = 1; $m <= 12; $m++): ?>
            <tr>
                <td style="font-weight:600; white-space:nowrap;"><?= $monthNames[$m] ?></td>
                <?php foreach ($taxTypes as $type):
                    $cell = $grid[$m][$type] ?? null;
                    if ($cell):
                        $status = $cell['status'];
                        if (!in_array($status, $allowedStatuses, true)) { $status = 'do_zaplaty'; }
                        $cellClass = 'tax-' . htmlspecialchars($status);
                ?>
                    <td class="<?= $cellClass ?>" style="text-align:right; font-weight:600; font-size:15px;">
                        <?= number_format((float)$cell['amount'], 2, ',', ' ') ?> zł
                        <div style="font-size:11px; font-weight:normal; margin-top:2px;">
                            <?= htmlspecialchars($lang('tax_' . $status)) ?>
                        </div>
                    </td>
                <?php else: ?>
                    <td style="text-align:center; color:var(--gray-400);">—</td>
                <?php endif; ?>
                <?php endforeach; ?>
            </tr>
            <?php endfor; ?>
        </tbody>
    </table>
</div>

<!-- Legend -->
<div style="display:flex; flex-wrap:wrap; gap:16px; margin-top:16px; font-size:13px;">
    <span><span class="tax-legend-box tax-do-zaplaty"></span> <?= $lang('tax_do_zaplaty') ?></span>
    <span><span class="tax-legend-box tax-do-przeniesienia"></span> <?= $lang('tax_do_przeniesienia') ?></span>
</div>

<?php else: ?>
<div class="alert alert-info"><?= $lang('tax_no_data') ?></div>
<?php endif; ?>
