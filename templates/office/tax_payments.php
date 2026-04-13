<h2><?= $lang('tax_payments_title') ?></h2>

<?php if (!empty($success)): ?>
<div class="alert alert-success"><?= $lang($success) ?></div>
<?php endif; ?>

<!-- Filters -->
<form method="get" action="/office/tax-payments" style="margin-bottom:20px;">
    <div class="responsive-grid-2">
        <div class="form-group" style="margin-bottom:0;">
            <label class="form-label"><?= $lang('select_client') ?></label>
            <select name="client_id" class="form-input" onchange="this.form.submit()">
                <option value="">-- <?= $lang('select_client') ?> --</option>
                <?php foreach ($clients as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= $filterClientId == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['company_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <label class="form-label"><?= $lang('year') ?></label>
            <select name="year" class="form-input" onchange="this.form.submit()">
                <?php for ($y = (int)date('Y') + 1; $y >= 2023; $y--): ?>
                    <option value="<?= $y ?>" <?= $filterYear == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
    </div>
</form>

<?php if ($filterClientId): ?>

<?php
$monthNames = [
    1 => 'Styczeń', 2 => 'Luty', 3 => 'Marzec', 4 => 'Kwiecień',
    5 => 'Maj', 6 => 'Czerwiec', 7 => 'Lipiec', 8 => 'Sierpień',
    9 => 'Wrzesień', 10 => 'Październik', 11 => 'Listopad', 12 => 'Grudzień',
];
$allowedStatuses = ['do_zaplaty', 'do_przeniesienia'];
?>

<form method="post" action="/office/tax-payments/save">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="client_id" value="<?= (int) $filterClientId ?>">
    <input type="hidden" name="year" value="<?= (int) $filterYear ?>">

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th><?= $lang('month') ?></th>
                    <?php foreach ($taxTypes as $type): ?>
                        <th colspan="2" style="text-align:center;"><?= htmlspecialchars($lang('tax_' . strtolower($type))) ?></th>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <th></th>
                    <?php foreach ($taxTypes as $type): ?>
                        <th style="font-weight:normal; font-size:12px;"><?= $lang('tax_amount') ?></th>
                        <th style="font-weight:normal; font-size:12px;"><?= $lang('tax_status') ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <tr>
                    <td style="font-weight:600; white-space:nowrap;"><?= $monthNames[$m] ?></td>
                    <?php foreach ($taxTypes as $type):
                        $cell = $grid[$m][$type] ?? null;
                        $amount = $cell['amount'] ?? '';
                        $status = $cell['status'] ?? 'do_zaplaty';
                        if (!in_array($status, $allowedStatuses, true)) { $status = 'do_zaplaty'; }
                        $cellClass = $amount !== '' ? 'tax-' . htmlspecialchars($status) : '';
                        $safeType = htmlspecialchars($type);
                    ?>
                        <td class="<?= $cellClass ?>" id="cell-<?= $m ?>-<?= $safeType ?>">
                            <input type="number" step="0.01" min="0"
                                   name="tax[<?= $m ?>][<?= $safeType ?>][amount]"
                                   value="<?= htmlspecialchars($amount) ?>"
                                   class="form-input tax-amount-input"
                                   placeholder="0.00"
                                   oninput="updateCellColor(<?= $m ?>, '<?= $safeType ?>')">
                        </td>
                        <td class="<?= $cellClass ?>" id="cell-status-<?= $m ?>-<?= $safeType ?>">
                            <select name="tax[<?= $m ?>][<?= $safeType ?>][status]"
                                    class="form-input tax-status-select"
                                    onchange="updateCellColor(<?= $m ?>, '<?= $safeType ?>')">
                                <option value="do_zaplaty" <?= $status === 'do_zaplaty' ? 'selected' : '' ?>>
                                    <?= $lang('tax_do_zaplaty') ?>
                                </option>
                                <option value="do_przeniesienia" <?= $status === 'do_przeniesienia' ? 'selected' : '' ?>>
                                    <?= $lang('tax_do_przeniesienia') ?>
                                </option>
                            </select>
                        </td>
                    <?php endforeach; ?>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px; display:flex; flex-wrap:wrap; gap:12px; align-items:center;">
        <button type="submit" class="btn btn-primary"><?= $lang('save') ?></button>
        <div style="display:flex; flex-wrap:wrap; gap:16px; font-size:13px;">
            <span><span class="tax-legend-box tax-do-zaplaty"></span> <?= $lang('tax_do_zaplaty') ?></span>
            <span><span class="tax-legend-box tax-do-przeniesienia"></span> <?= $lang('tax_do_przeniesienia') ?></span>
        </div>
    </div>
</form>

<script>
function updateCellColor(month, type) {
    var amountInput = document.querySelector('input[name="tax[' + month + '][' + type + '][amount]"]');
    var statusSelect = document.querySelector('select[name="tax[' + month + '][' + type + '][status]"]');
    var cellAmount = document.getElementById('cell-' + month + '-' + type);
    var cellStatus = document.getElementById('cell-status-' + month + '-' + type);

    var amount = amountInput.value.trim();
    var status = statusSelect.value;

    cellAmount.className = amount !== '' ? 'tax-' + status : '';
    cellStatus.className = amount !== '' ? 'tax-' + status : '';
}
</script>

<?php else: ?>
<div class="alert alert-info"><?= $lang('tax_select_client_year') ?></div>
<?php endif; ?>
