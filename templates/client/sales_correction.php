<h1><?= $lang('create_correction') ?></h1>

<div class="alert alert-warning" style="margin-bottom:20px;">
    <?= $lang('corrected_invoice') ?>: <strong><?= htmlspecialchars($originalInvoice['invoice_number']) ?></strong>
    (<?= htmlspecialchars($originalInvoice['issue_date']) ?>)
    <?php if (!empty($originalInvoice['ksef_reference_number'])): ?>
        &mdash; KSeF: <code style="font-size:12px;"><?= htmlspecialchars($originalInvoice['ksef_reference_number']) ?></code>
    <?php endif; ?>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php
$origItems = $originalInvoice['line_items'] ?? '[]';
if (is_string($origItems)) $origItems = json_decode($origItems, true) ?: [];
$origNet = (float) ($originalInvoice['net_amount'] ?? 0);
$origVat = (float) ($originalInvoice['vat_amount'] ?? 0);
$origGross = (float) ($originalInvoice['gross_amount'] ?? 0);
?>

<form method="POST" action="/client/sales/create" id="invoice-form">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
    <input type="hidden" name="invoice_type" value="FV_KOR">
    <input type="hidden" name="corrected_invoice_id" value="<?= $originalInvoice['id'] ?>">

    <!-- Correction reason & type -->
    <div class="section">
        <h2><?= $lang('correction_reason') ?></h2>
        <div class="form-card" style="padding:20px; max-width:none;">
            <div class="form-group">
                <label class="form-label"><?= $lang('correction_reason') ?> *</label>
                <textarea name="correction_reason" class="form-input" rows="3" required placeholder="np. Błędna ilość, korekta ceny, zmiana stawki VAT..."></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Typ korekty (TypKorekty w KSeF)</label>
                <select name="correction_type" class="form-input" style="max-width:500px;">
                    <option value="1" selected>1 — Wstecz: rozliczenie w okresie faktury oryginalnej (błędy, pomyłki)</option>
                    <option value="2">2 — Bieżąco: rozliczenie w okresie korekty (rabat, zwrot, nowe zdarzenie)</option>
                    <option value="3">3 — Mieszany: różne pozycje w różnych okresach</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Buyer -->
    <div class="section">
        <h2><?= $lang('buyer') ?></h2>
        <div class="form-card" style="padding:20px; max-width:none;">
            <input type="hidden" name="contractor_id" value="<?= htmlspecialchars($invoice['contractor_id'] ?? '') ?>">
            <div class="responsive-grid-2" style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                <div class="form-group">
                    <label class="form-label"><?= $lang('buyer_name') ?> *</label>
                    <input type="text" name="buyer_name" class="form-input" value="<?= htmlspecialchars($invoice['buyer_name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('buyer_nip') ?></label>
                    <input type="text" name="buyer_nip" class="form-input" value="<?= htmlspecialchars($invoice['buyer_nip'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label"><?= $lang('buyer_address') ?></label>
                <input type="text" name="buyer_address" class="form-input" value="<?= htmlspecialchars($invoice['buyer_address'] ?? '') ?>">
            </div>
        </div>
    </div>

    <!-- Dates -->
    <div class="section">
        <h2><?= $lang('date') ?></h2>
        <div class="form-card" style="padding:20px; max-width:none;">
            <div class="responsive-grid-3" style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:16px;">
                <div class="form-group">
                    <label class="form-label"><?= $lang('issue_date') ?> *</label>
                    <input type="date" name="issue_date" class="form-input" value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d', strtotime('-1 day')) ?>" max="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('sale_date') ?> *</label>
                    <input type="date" name="sale_date" class="form-input" value="<?= htmlspecialchars($invoice['sale_date'] ?? date('Y-m-d')) ?>" max="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('due_date') ?></label>
                    <input type="date" name="due_date" class="form-input" value="<?= htmlspecialchars($invoice['due_date'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- STAN PRZED KOREKTĄ (readonly) -->
    <div class="section">
        <h2 style="color:var(--gray-500);">Stan przed korektą <span style="font-weight:400; font-size:14px;">(dane z faktury oryginalnej)</span></h2>
        <div class="form-card" style="padding:20px; max-width:none; overflow-x:auto; background:var(--gray-50); opacity:0.9;">
            <table class="data-table" style="width:100%; table-layout:auto;">
                <thead>
                    <tr>
                        <th style="width:3%;">Lp.</th>
                        <th style="width:35%;"><?= $lang('item_name') ?></th>
                        <th style="width:7%;"><?= $lang('quantity') ?></th>
                        <th style="width:7%;"><?= $lang('unit') ?></th>
                        <th style="width:10%;"><?= $lang('unit_price') ?></th>
                        <th style="width:8%;"><?= $lang('vat_rate') ?></th>
                        <th style="width:10%;"><?= $lang('net_amount') ?></th>
                        <th style="width:8%;"><?= $lang('vat_amount') ?></th>
                        <th style="width:10%;"><?= $lang('gross_amount') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($origItems as $i => $item):
                        $net = round(($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0), 2);
                        $vatRate = is_numeric($item['vat_rate'] ?? '') ? (int)$item['vat_rate'] : 0;
                        $vat = round($net * $vatRate / 100, 2);
                    ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($item['name'] ?? '') ?></td>
                        <td style="text-align:right;"><?= htmlspecialchars($item['quantity'] ?? 1) ?></td>
                        <td><?= htmlspecialchars($item['unit'] ?? 'szt.') ?></td>
                        <td style="text-align:right;"><?= number_format((float)($item['unit_price'] ?? 0), 2, ',', ' ') ?></td>
                        <td style="text-align:center;"><?= htmlspecialchars($item['vat_rate'] ?? '23') ?>%</td>
                        <td style="text-align:right;"><?= number_format($net, 2, ',', ' ') ?></td>
                        <td style="text-align:right;"><?= number_format($vat, 2, ',', ' ') ?></td>
                        <td style="text-align:right; font-weight:600;"><?= number_format($net + $vat, 2, ',', ' ') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="font-weight:700; background:var(--gray-100);">
                        <td colspan="6" style="text-align:right;"><?= $lang('total') ?>:</td>
                        <td style="text-align:right;"><?= number_format($origNet, 2, ',', ' ') ?></td>
                        <td style="text-align:right;"><?= number_format($origVat, 2, ',', ' ') ?></td>
                        <td style="text-align:right;"><?= number_format($origGross, 2, ',', ' ') ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- STAN PO KOREKCIE (editable) -->
    <div class="section">
        <h2 style="color:var(--primary);">Stan po korekcie <span style="font-weight:400; font-size:14px;">(edytuj pozycje)</span></h2>
        <div class="form-card" style="padding:20px; max-width:none; overflow-x:auto;">
            <table class="data-table" id="line-items-table" style="width:100%; table-layout:auto;">
                <thead>
                    <tr>
                        <th style="width:3%;">Lp.</th>
                        <th style="width:35%;"><?= $lang('item_name') ?></th>
                        <th style="width:7%;"><?= $lang('quantity') ?></th>
                        <th style="width:7%;"><?= $lang('unit') ?></th>
                        <th style="width:10%;"><?= $lang('unit_price') ?></th>
                        <th style="width:8%;"><?= $lang('vat_rate') ?></th>
                        <th style="width:10%;"><?= $lang('net_amount') ?></th>
                        <th style="width:8%;"><?= $lang('vat_amount') ?></th>
                        <th style="width:10%;"><?= $lang('gross_amount') ?></th>
                        <th style="width:3%;"></th>
                    </tr>
                </thead>
                <tbody id="line-items-body"></tbody>
                <tfoot>
                    <tr style="font-weight:700; background:var(--gray-50);">
                        <td colspan="6" style="text-align:right;"><?= $lang('total') ?>:</td>
                        <td id="total-net" style="text-align:right;">0,00</td>
                        <td id="total-vat" style="text-align:right;">0,00</td>
                        <td id="total-gross" style="text-align:right;">0,00</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
            <button type="button" id="add-line-btn" class="btn" style="margin-top:12px;"><?= $lang('add_line') ?></button>
        </div>
    </div>

    <!-- PODSUMOWANIE RÓŻNIC -->
    <div class="section">
        <h2>Podsumowanie korekty</h2>
        <div class="form-card" style="padding:20px; max-width:none;">
            <table class="data-table" style="width:100%; max-width:600px; table-layout:auto;">
                <thead>
                    <tr>
                        <th></th>
                        <th style="text-align:right;"><?= $lang('net_amount') ?></th>
                        <th style="text-align:right;"><?= $lang('vat_amount') ?></th>
                        <th style="text-align:right;"><?= $lang('gross_amount') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="font-weight:600;">Przed korektą</td>
                        <td id="sum-orig-net" style="text-align:right;"><?= number_format($origNet, 2, ',', ' ') ?></td>
                        <td id="sum-orig-vat" style="text-align:right;"><?= number_format($origVat, 2, ',', ' ') ?></td>
                        <td id="sum-orig-gross" style="text-align:right;"><?= number_format($origGross, 2, ',', ' ') ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight:600;">Po korekcie</td>
                        <td id="sum-new-net" style="text-align:right;">0,00</td>
                        <td id="sum-new-vat" style="text-align:right;">0,00</td>
                        <td id="sum-new-gross" style="text-align:right;">0,00</td>
                    </tr>
                    <tr style="font-weight:700; border-top:2px solid var(--gray-300);">
                        <td>Różnica</td>
                        <td id="sum-diff-net" style="text-align:right;">0,00</td>
                        <td id="sum-diff-vat" style="text-align:right;">0,00</td>
                        <td id="sum-diff-gross" style="text-align:right;">0,00</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Payment -->
    <div class="section">
        <h2><?= $lang('payment_info') ?></h2>
        <div class="form-card" style="padding:20px; max-width:none;">
            <div class="responsive-grid-2" style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                <div class="form-group">
                    <label class="form-label"><?= $lang('payment_method') ?></label>
                    <select name="payment_method" class="form-input">
                        <?php
                        $methods = ['przelew' => 'payment_transfer', 'gotowka' => 'payment_cash', 'karta' => 'payment_card', 'kompensata' => 'payment_compensation', 'barter' => 'payment_barter'];
                        foreach ($methods as $val => $key): ?>
                            <option value="<?= $val ?>" <?= ($invoice['payment_method'] ?? 'przelew') === $val ? 'selected' : '' ?>><?= $lang($key) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('bank_account') ?></label>
                    <select name="bank_account_id" class="form-input">
                        <option value=""><?= $lang('select_bank_account') ?></option>
                        <?php foreach ($bankAccounts as $ba): ?>
                            <option value="<?= $ba['id'] ?>" <?= ($invoice['bank_account_id'] ?? '') == $ba['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ba['account_name'] ?: $ba['bank_name']) ?> (<?= $ba['currency'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="form-group" style="margin-top:16px;">
        <label class="form-label"><?= $lang('notes') ?></label>
        <textarea name="notes" class="form-input" rows="2"><?= htmlspecialchars($invoice['notes'] ?? '') ?></textarea>
    </div>

    <div style="display:flex; gap:12px; margin-top:20px;">
        <button type="submit" name="action" value="draft" class="btn"><?= $lang('save_draft') ?></button>
        <button type="submit" name="action" value="issue" class="btn btn-primary"><?= $lang('issue_invoice') ?></button>
        <a href="/client/sales/<?= $originalInvoice['id'] ?>" class="btn"><?= $lang('cancel') ?></a>
    </div>
</form>

<script>
(function() {
    const existingItems = <?= json_encode($lineItems ?? []) ?>;
    const origNet = <?= $origNet ?>;
    const origVat = <?= $origVat ?>;
    const origGross = <?= $origGross ?>;
    let lineIndex = 0;

    function addLine(data = {}) {
        const idx = lineIndex++;
        const tbody = document.getElementById('line-items-body');
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${idx + 1}</td>
            <td><input type="text" name="items[${idx}][name]" class="form-input" value="${esc(data.name || '')}" required style="width:100%;"></td>
            <td><input type="number" name="items[${idx}][quantity]" class="form-input line-qty" value="${data.quantity || 1}" min="0.01" step="0.01" required style="width:80px;"></td>
            <td><select name="items[${idx}][unit]" class="form-input" style="width:80px;">${['szt.','godz.','usł.','m2','kg','km','kpl.'].map(u => `<option value="${u}" ${(data.unit||'szt.')===u?'selected':''}>${u}</option>`).join('')}</select></td>
            <td><input type="number" name="items[${idx}][unit_price]" class="form-input line-price" value="${data.unit_price || ''}" min="0" step="0.01" required style="width:120px;"></td>
            <td><select name="items[${idx}][vat_rate]" class="form-input line-vat" style="width:90px;">${['23','8','5','0','zw','np'].map(r => `<option value="${r}" ${(data.vat_rate||'23')===r?'selected':''}>${r}${!isNaN(r)?'%':''}</option>`).join('')}</select></td>
            <td class="line-net" style="text-align:right; font-weight:600;">0,00</td>
            <td class="line-vat-amount" style="text-align:right;">0,00</td>
            <td class="line-gross" style="text-align:right; font-weight:600;">0,00</td>
            <td><button type="button" class="btn btn-sm btn-danger remove-line">&times;</button></td>
        `;
        tbody.appendChild(tr);
        tr.querySelector('.remove-line').addEventListener('click', () => { tr.remove(); recalculate(); });
        tr.querySelector('.line-qty').addEventListener('input', recalculate);
        tr.querySelector('.line-price').addEventListener('input', recalculate);
        tr.querySelector('.line-vat').addEventListener('change', recalculate);
        recalculate();
    }

    function recalculate() {
        let totalNet = 0, totalVat = 0, totalGross = 0;
        document.querySelectorAll('#line-items-body tr').forEach(tr => {
            const qty = parseFloat(tr.querySelector('.line-qty')?.value || 0);
            const price = parseFloat(tr.querySelector('.line-price')?.value || 0);
            const vatRate = tr.querySelector('.line-vat')?.value || '23';
            const net = Math.round(qty * price * 100) / 100;
            let vatPercent = !isNaN(parseInt(vatRate)) ? parseInt(vatRate) / 100 : 0;
            const vat = Math.round(net * vatPercent * 100) / 100;
            tr.querySelector('.line-net').textContent = fmt(net);
            tr.querySelector('.line-vat-amount').textContent = fmt(vat);
            tr.querySelector('.line-gross').textContent = fmt(net + vat);
            totalNet += net; totalVat += vat; totalGross += net + vat;
        });
        document.getElementById('total-net').textContent = fmt(totalNet);
        document.getElementById('total-vat').textContent = fmt(totalVat);
        document.getElementById('total-gross').textContent = fmt(totalGross);

        // Update summary
        document.getElementById('sum-new-net').textContent = fmt(totalNet);
        document.getElementById('sum-new-vat').textContent = fmt(totalVat);
        document.getElementById('sum-new-gross').textContent = fmt(totalGross);

        const diffNet = totalNet - origNet;
        const diffVat = totalVat - origVat;
        const diffGross = totalGross - origGross;
        setDiff('sum-diff-net', diffNet);
        setDiff('sum-diff-vat', diffVat);
        setDiff('sum-diff-gross', diffGross);
    }

    function setDiff(id, val) {
        const el = document.getElementById(id);
        const prefix = val > 0 ? '+' : '';
        el.textContent = prefix + fmt(val);
        el.style.color = val < 0 ? 'var(--red-500)' : (val > 0 ? 'var(--green-600)' : '');
    }

    function fmt(n) { return n.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ' '); }
    function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML.replace(/"/g, '&quot;'); }

    document.getElementById('add-line-btn').addEventListener('click', () => addLine());
    existingItems.forEach(item => addLine(item));
    if (existingItems.length === 0) addLine();
})();
</script>

<style>
@media (max-width: 768px) {
    div[style*="grid-template-columns"] { grid-template-columns: 1fr !important; }
}
</style>
