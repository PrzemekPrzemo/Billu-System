<h1><?= $lang($isEdit ? 'edit_invoice' : 'new_invoice') ?></h1>

<?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php
// Duplicate warning from session
$dupCandidates = \App\Core\Session::get('duplicate_candidates');
$dupFormData = \App\Core\Session::get('duplicate_form_data');
if ($dupCandidates): ?>
<div class="alert alert-warning" id="duplicate-warning-server" style="margin-bottom:20px;">
    <strong><?= $lang('duplicate_warning') ?></strong>
    <ul style="margin:8px 0; padding-left:20px;">
        <?php foreach ($dupCandidates as $dc): ?>
        <li><?= htmlspecialchars($dc['invoice_number'] ?? '') ?> — <?= number_format((float) ($dc['gross_amount'] ?? 0), 2, ',', ' ') ?> (<?= $dc['issue_date'] ?? '' ?>)</li>
        <?php endforeach; ?>
    </ul>
    <p style="margin-top:8px; font-size:13px;"><?= $lang('duplicate_warning_detail') ?></p>
</div>
<?php
    \App\Core\Session::set('duplicate_candidates', null);
    \App\Core\Session::set('duplicate_form_data', null);
endif; ?>

<div class="alert alert-warning" id="duplicate-warning-ajax" style="margin-bottom:20px; display:none;">
    <strong><?= $lang('duplicate_warning') ?></strong>
    <div id="duplicate-warning-list"></div>
</div>

<form method="POST" action="<?= $isEdit ? '/client/sales/' . $invoice['id'] . '/update' : '/client/sales/create' ?>" id="invoice-form">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
    <input type="hidden" name="duplicate_acknowledged" id="duplicate-acknowledged" value="<?= $dupCandidates ? '1' : '' ?>">

    <!-- Invoice Type Selector -->
    <?php if (!$isEdit || ($invoice['status'] ?? '') === 'draft'): ?>
    <div class="section">
        <h2>Rodzaj dokumentu</h2>
        <div class="form-card" style="padding:20px; max-width:none;">
            <div class="form-group" style="max-width:400px;">
                <label class="form-label">Typ faktury *</label>
                <select name="invoice_type" id="invoice-type-select" class="form-input">
                    <?php
                    $invoiceTypes = [
                        'FV' => 'Faktura VAT',
                        'FP' => 'Faktura Proforma',
                        'FV_ZAL' => 'Faktura Zaliczkowa',
                        'FV_KON' => 'Faktura Koncowa do zaliczki',
                    ];
                    $selectedType = $invoice['invoice_type'] ?? 'FV';
                    foreach ($invoiceTypes as $val => $label): ?>
                        <option value="<?= $val ?>" <?= $selectedType === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Info banners for each type -->
            <div id="invoice-type-info-FP" class="alert alert-warning" style="margin-top:12px; display:none;">
                <strong>Faktura proforma</strong> nie jest dokumentem ksiegowym. Nie zostanie wyslana do KSeF.
            </div>
            <div id="invoice-type-info-FV_ZAL" class="alert alert-info" style="margin-top:12px; display:none;">
                <strong>Faktura zaliczkowa</strong> dokumentuje otrzymana zaliczke na poczet przyszlej dostawy lub uslugi.
            </div>
            <div id="invoice-type-info-FV_KON" class="alert alert-info" style="margin-top:12px; display:none;">
                <strong>Faktura koncowa</strong> rozlicza wczesniej wystawione faktury zaliczkowe. Wybierz powiazane faktury zaliczkowe ponizej.
            </div>
        </div>
    </div>
    <?php else: ?>
    <input type="hidden" name="invoice_type" value="<?= htmlspecialchars($invoice['invoice_type'] ?? 'FV') ?>">
    <?php endif; ?>

    <!-- Advance Invoice Fields (FV_ZAL) -->
    <div class="section" id="advance-fields-section" style="display:none;">
        <h2>Dane zaliczki</h2>
        <div class="form-card" style="padding:20px; max-width:none;">
            <div class="responsive-grid-2" style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                <div class="form-group">
                    <label class="form-label">Kwota zaliczki brutto *</label>
                    <input type="number" name="advance_amount" id="advance-amount" class="form-input" step="0.01" min="0" value="<?= htmlspecialchars($invoice['advance_amount'] ?? '') ?>" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label class="form-label">Opis zamowienia / umowy</label>
                    <textarea name="advance_order_description" class="form-input" rows="3" placeholder="Opis zamowienia lub umowy, na poczet ktorej wplacono zaliczke"><?= htmlspecialchars($invoice['advance_order_description'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- Final Invoice: Related Advance Invoices (FV_KON) -->
    <div class="section" id="final-invoice-section" style="display:none;">
        <h2>Powiazane faktury zaliczkowe</h2>
        <div class="form-card" style="padding:20px; max-width:none;">
            <p style="color:var(--gray-500); font-size:13px; margin-bottom:12px;">Wybierz faktury zaliczkowe, ktore maja byc rozliczone ta faktura koncowa.</p>
            <div id="advance-invoices-list" style="margin-bottom:12px;">
                <p style="color:var(--gray-400); font-size:13px;">Wybierz kontrahenta, aby zaladowac liste faktur zaliczkowych.</p>
            </div>
            <input type="hidden" name="related_advance_ids" id="related-advance-ids" value="<?= htmlspecialchars(is_string($invoice['related_advance_ids'] ?? '') ? ($invoice['related_advance_ids'] ?? '') : json_encode($invoice['related_advance_ids'] ?? [])) ?>">
            <div id="advance-totals" style="display:none; padding:12px 16px; background:var(--gray-50); border:1px solid var(--gray-200); border-radius:8px; margin-top:12px;">
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; font-size:14px;">
                    <div>
                        <span style="color:var(--gray-500);">Suma zaliczek brutto:</span>
                        <strong id="advance-total-amount" style="display:block; font-size:16px;">0,00</strong>
                    </div>
                    <div>
                        <span style="color:var(--gray-500);">Kwota faktury brutto:</span>
                        <strong id="invoice-gross-amount" style="display:block; font-size:16px;">0,00</strong>
                    </div>
                    <div>
                        <span style="color:var(--gray-500);">Pozostalo do zaplaty:</span>
                        <strong id="remaining-amount" style="display:block; font-size:16px; color:var(--primary);">0,00</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contractor / Buyer -->
    <div class="section">
        <h2><?= $lang('buyer') ?></h2>
        <div class="form-card" style="padding:20px; max-width:none;">
            <div class="form-group" style="max-width:400px; position:relative;">
                <label class="form-label"><?= $lang('select_contractor') ?></label>
                <input type="text" id="contractor-search" class="form-input" placeholder="<?= $lang('search_contractors') ?>" autocomplete="off">
                <input type="hidden" name="contractor_id" id="contractor-id" value="<?= htmlspecialchars($invoice['contractor_id'] ?? '') ?>">
                <div id="contractor-results" style="display:none; position:absolute; top:100%; left:0; right:0; z-index:100; background:var(--bg-primary); border:1px solid var(--gray-300); border-radius:6px; max-height:200px; overflow-y:auto; box-shadow:0 4px 12px rgba(0,0,0,0.15);"></div>
            </div>

            <div class="responsive-grid-2" style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                <div class="form-group">
                    <label class="form-label"><?= $lang('buyer_name') ?> *</label>
                    <input type="text" name="buyer_name" id="buyer-name" class="form-input" value="<?= htmlspecialchars($invoice['buyer_name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('buyer_nip') ?></label>
                    <input type="text" name="buyer_nip" id="buyer-nip" class="form-input" value="<?= htmlspecialchars($invoice['buyer_nip'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label"><?= $lang('buyer_address') ?></label>
                <input type="text" name="buyer_address" id="buyer-address" class="form-input" value="<?= htmlspecialchars($invoice['buyer_address'] ?? '') ?>">
            </div>

            <!-- New contractor inline -->
            <div style="margin-top:12px; border-top:1px solid var(--gray-200); padding-top:12px;">
                <button type="button" id="toggle-new-contractor" class="btn btn-sm" style="margin-bottom:12px;">+ <?= $lang('new_contractor') ?></button>
                <div id="new-contractor-section" style="display:none;">
                    <div style="display:flex; gap:8px; align-items:flex-end; margin-bottom:12px;">
                        <div class="form-group" style="margin-bottom:0; flex:1; max-width:250px;">
                            <label class="form-label">NIP</label>
                            <input type="text" id="new-contractor-nip" class="form-input" placeholder="0000000000" maxlength="10">
                        </div>
                        <button type="button" id="gus-lookup-btn" class="btn btn-sm"><?= $lang('gus_lookup') ?></button>
                        <span id="gus-lookup-status" style="font-size:13px; color:var(--gray-500);"></span>
                    </div>
                    <div class="form-group">
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                            <input type="checkbox" name="save_contractor" value="1">
                            <span><?= $lang('save_as_contractor') ?></span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payer (Podmiot3) -->
    <div class="section">
        <div style="display:flex; align-items:center; gap:12px; cursor:pointer;" id="payer-toggle">
            <h2 style="margin:0;">Płatnik (jeśli inny niż nabywca)</h2>
            <span id="payer-toggle-icon" style="font-size:18px; color:var(--gray-500);">&#9654;</span>
        </div>
        <?php
            $payerData = $invoice['payer_data'] ?? '';
            if (is_string($payerData) && $payerData !== '') {
                $payerData = json_decode($payerData, true) ?: [];
            }
            if (!is_array($payerData)) $payerData = [];
            $payerExpanded = !empty($payerData);
        ?>
        <div class="form-card" id="payer-section" style="padding:20px; max-width:none; margin-top:12px; <?= $payerExpanded ? '' : 'display:none;' ?>">
            <div class="responsive-grid-2" style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                <div class="form-group">
                    <label class="form-label">Nazwa płatnika</label>
                    <input type="text" name="payer_name" class="form-input" value="<?= htmlspecialchars($payerData['payer_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">NIP płatnika</label>
                    <input type="text" name="payer_nip" class="form-input" value="<?= htmlspecialchars($payerData['payer_nip'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Adres płatnika</label>
                <input type="text" name="payer_address" class="form-input" value="<?= htmlspecialchars($payerData['payer_address'] ?? '') ?>">
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
                    <input type="date" name="issue_date" class="form-input" value="<?= htmlspecialchars($invoice['issue_date'] ?? date('Y-m-d')) ?>" min="<?= date('Y-m-d', strtotime('-1 day')) ?>" max="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('sale_date') ?> *</label>
                    <input type="date" name="sale_date" class="form-input" value="<?= htmlspecialchars($invoice['sale_date'] ?? date('Y-m-d')) ?>" max="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('due_date') ?> *</label>
                    <input type="date" name="due_date" id="due-date" class="form-input" value="<?= htmlspecialchars($invoice['due_date'] ?? '') ?>" required>
                </div>
            </div>
        </div>
    </div>

    <!-- Line Items -->
    <div class="section">
        <h2><?= $lang('line_items') ?></h2>
        <div class="form-card" style="padding:20px; max-width:none; overflow-x:auto;">
            <table class="data-table" id="line-items-table" style="width:100%; table-layout:auto;">
                <thead>
                    <tr>
                        <th style="width:3%;">Lp.</th>
                        <th style="width:28%;"><?= $lang('item_name') ?></th>
                        <th style="width:7%;"><?= $lang('quantity') ?></th>
                        <th style="width:7%;"><?= $lang('unit') ?></th>
                        <th style="width:10%;"><?= $lang('unit_price') ?></th>
                        <th style="width:8%;"><?= $lang('vat_rate') ?></th>
                        <th style="width:8%;">GTU</th>
                        <th style="width:10%;"><?= $lang('net_amount') ?></th>
                        <th style="width:8%;"><?= $lang('vat_amount') ?></th>
                        <th style="width:10%;"><?= $lang('gross_amount') ?></th>
                        <th style="width:3%;"></th>
                    </tr>
                </thead>
                <tbody id="line-items-body">
                    <!-- Rows added by JS -->
                </tbody>
                <tfoot>
                    <tr style="font-weight:700; background:var(--gray-50);">
                        <td colspan="7" style="text-align:right;"><?= $lang('total') ?>:</td>
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

    <!-- VAT Summary -->
    <div class="section">
        <h2><?= $lang('vat_summary') ?></h2>
        <div class="form-card" style="padding:20px; max-width:none;">
            <table class="data-table" id="vat-summary-table" style="max-width:100%;">
                <thead>
                    <tr>
                        <th><?= $lang('vat_rate') ?></th>
                        <th style="text-align:right;"><?= $lang('net_amount') ?></th>
                        <th style="text-align:right;"><?= $lang('vat_amount') ?></th>
                        <th style="text-align:right;"><?= $lang('gross_amount') ?></th>
                    </tr>
                </thead>
                <tbody id="vat-summary-body">
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
                    <select name="payment_method" id="payment-method-select" class="form-input">
                        <?php
                        $methods = ['przelew' => 'payment_transfer', 'gotowka' => 'payment_cash', 'karta' => 'payment_card', 'kompensata' => 'payment_compensation', 'barter' => 'payment_barter'];
                        $selectedMethod = $invoice['payment_method'] ?? $profile['default_payment_method'] ?? 'przelew';
                        foreach ($methods as $val => $key): ?>
                            <option value="<?= $val ?>" <?= $selectedMethod === $val ? 'selected' : '' ?>><?= $lang($key) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" id="cash-payment-status" style="display:none;">
                    <label class="form-label">Status płatności *</label>
                    <select name="cash_payment_status" id="cash-payment-status-select" class="form-input">
                        <option value="paid">Opłacona</option>
                        <option value="to_pay">Do zapłaty</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('invoice_currency') ?></label>
                    <div id="quick-rates" style="font-size:12px; color:var(--gray-500); margin-bottom:4px; display:none;"></div>
                    <select name="currency" class="form-input" id="currency-select">
                        <?php $selectedCurrency = $invoice['currency'] ?? 'PLN'; ?>
                        <option value="PLN" <?= $selectedCurrency === 'PLN' ? 'selected' : '' ?>>PLN</option>
                        <option value="EUR" <?= $selectedCurrency === 'EUR' ? 'selected' : '' ?>>EUR</option>
                        <option value="USD" <?= $selectedCurrency === 'USD' ? 'selected' : '' ?>>USD</option>
                    </select>
                </div>
                <div class="form-group" id="mpp-group" style="display:flex; align-items:center; padding-top:24px;">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="checkbox" name="is_split_payment" value="1" id="mpp-checkbox" <?= !empty($invoice['is_split_payment']) ? 'checked' : '' ?>>
                        <span>Mechanizm podzielonej płatności (MPP)</span>
                    </label>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('bank_account') ?></label>
                    <select name="bank_account_id" class="form-input" id="bank-account-select">
                        <option value=""><?= $lang('select_bank_account') ?></option>
                        <?php foreach ($bankAccounts as $ba): ?>
                            <option value="<?= $ba['id'] ?>"
                                data-number="<?= htmlspecialchars($ba['account_number']) ?>"
                                data-bank="<?= htmlspecialchars($ba['bank_name']) ?>"
                                data-currency="<?= htmlspecialchars($ba['currency'] ?? 'PLN') ?>"
                                data-default-receiving="<?= !empty($ba['is_default_receiving']) ? '1' : '0' ?>"
                                <?= (($invoice['bank_account_id'] ?? '') == $ba['id'] || (!empty($ba['is_default_receiving']) && empty($invoice['bank_account_id']))) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ba['account_name'] ?: $ba['bank_name']) ?> (<?= htmlspecialchars($ba['currency']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="no-currency-account-warning" class="alert alert-warning" style="margin-top:8px; padding:8px 12px; font-size:13px; display:none;">
                        <?= $lang('no_account_for_currency') ?> — <a href="/client/company" style="font-weight:600;"><?= $lang('add_currency_account') ?></a>
                    </div>
                    <?php if (empty($bankAccounts)): ?>
                    <div class="alert alert-warning" style="margin-top:8px; padding:8px 12px; font-size:13px;">
                        Brak rachunków bankowych — <a href="/client/company" style="font-weight:600;">dodaj konto w panelu klienta</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Notes -->
    <div class="section">
        <h2><?= $lang('notes') ?></h2>
        <div class="form-card" style="padding:20px; max-width:none;">
            <div class="form-group">
                <label class="form-label"><?= $lang('notes') ?></label>
                <textarea name="notes" class="form-input" rows="3"><?= htmlspecialchars($invoice['notes'] ?? $profile['invoice_notes'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label"><?= $lang('notes') ?> (<?= $lang('optional') ?> - wewnętrzne)</label>
                <textarea name="internal_notes" class="form-input" rows="2"><?= htmlspecialchars($invoice['internal_notes'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <!-- Exchange Rate (NBP) -->
    <div class="section" id="exchange-rate-section" style="display:none;">
        <h2><?= $lang('exchange_rate_section') ?></h2>
        <div class="form-card" style="padding:20px; max-width:none;">
            <div id="exchange-rate-info">
                <p id="exchange-rate-loading" style="color:var(--gray-500); margin:0;">
                    <?= $lang('exchange_rate_loading') ?>
                </p>
                <div id="exchange-rate-data" style="display:none;">
                    <table style="border-collapse:collapse; width:100%; max-width:500px;">
                        <tr>
                            <td style="padding:6px 16px 6px 0; font-weight:600;"><?= $lang('invoice_currency') ?>:</td>
                            <td id="er-currency" style="padding:6px 0;"></td>
                        </tr>
                        <tr>
                            <td style="padding:6px 16px 6px 0; font-weight:600;"><?= $lang('exchange_rate_label') ?>:</td>
                            <td id="er-rate" style="padding:6px 0; font-size:1.1em; font-weight:700;"></td>
                        </tr>
                        <tr>
                            <td style="padding:6px 16px 6px 0; font-weight:600;"><?= $lang('exchange_rate_date_label') ?>:</td>
                            <td id="er-date" style="padding:6px 0;"></td>
                        </tr>
                        <tr>
                            <td style="padding:6px 16px 6px 0; font-weight:600;"><?= $lang('exchange_rate_table_label') ?>:</td>
                            <td id="er-table" style="padding:6px 0;"></td>
                        </tr>
                        <tr style="border-top:1px solid var(--gray-200);">
                            <td style="padding:10px 16px 6px 0; font-weight:600;"><?= $lang('exchange_rate_conversion') ?>:</td>
                            <td id="er-conversion" style="padding:10px 0 6px 0; font-weight:600;"></td>
                        </tr>
                    </table>
                    <div id="pln-summary" style="margin-top:12px; padding:10px 14px; background:var(--gray-50); border-radius:6px; border:1px solid var(--gray-200); display:none;">
                        <div style="font-size:12px; color:var(--gray-500); margin-bottom:4px;"><?= $lang('pln_equivalent') ?? 'Rownowartość w PLN' ?>:</div>
                        <table style="width:100%; max-width:300px; font-size:13px;">
                            <tr><td><?= $lang('net_amount') ?>:</td><td id="pln-net" style="text-align:right; font-weight:600;"></td></tr>
                            <tr><td>VAT:</td><td id="pln-vat" style="text-align:right; font-weight:600; color:var(--danger);"></td></tr>
                            <tr style="border-top:1px solid var(--gray-200);"><td><?= $lang('gross_amount') ?>:</td><td id="pln-gross" style="text-align:right; font-weight:700;"></td></tr>
                        </table>
                    </div>
                </div>
                <div id="exchange-rate-error" class="alert alert-warning" style="display:none; margin-top:8px; padding:8px 12px; font-size:13px;">
                    <?= $lang('exchange_rate_error') ?>
                </div>
            </div>
            <input type="hidden" name="exchange_rate" id="exchange-rate-value" value="<?= htmlspecialchars($invoice['exchange_rate'] ?? '') ?>">
            <input type="hidden" name="exchange_rate_date" id="exchange-rate-date-value" value="<?= htmlspecialchars($invoice['exchange_rate_date'] ?? '') ?>">
            <input type="hidden" name="exchange_rate_table" id="exchange-rate-table-value" value="<?= htmlspecialchars($invoice['exchange_rate_table'] ?? '') ?>">
        </div>
    </div>

    <!-- Actions -->
    <div style="display:flex; gap:12px; margin-top:20px;">
        <button type="submit" name="action" value="draft" class="btn"><?= $lang('save_draft') ?></button>
        <button type="submit" name="action" value="issue" class="btn btn-primary"><?= $lang('issue_invoice') ?></button>
        <a href="/client/sales" class="btn"><?= $lang('cancel') ?></a>
    </div>
</form>

<script>
(function() {
    const services = <?= json_encode($services ?? []) ?>;
    const existingItems = <?= json_encode($lineItems ?? []) ?>;
    const defaultPaymentDays = <?= (int)($profile['default_payment_days'] ?? 14) ?>;
    const sellerNip = '<?= preg_replace('/[^0-9]/', '', $client['nip'] ?? '') ?>';

    let lineIndex = 0;

    // Block self-invoicing and missing bank account on form submit
    document.getElementById('invoice-form').addEventListener('submit', function(e) {
        var buyerNip = (document.getElementById('buyer-nip').value || '').replace(/[^0-9]/g, '');
        if (buyerNip && sellerNip && buyerNip === sellerNip) {
            e.preventDefault();
            alert('Nie można wystawić faktury na samego siebie — NIP nabywcy jest taki sam jak NIP sprzedawcy.');
            document.getElementById('buyer-nip').focus();
            return false;
        }
        // Check bank account when payment method is bank transfer
        var paymentMethod = document.querySelector('[name="payment_method"]');
        var bankAccount = document.getElementById('bank-account-select');
        if (paymentMethod && bankAccount && paymentMethod.value === 'przelew' && !bankAccount.value) {
            e.preventDefault();
            alert('Brak wskazanego rachunku bankowego — uzupełnij w panelu klienta.');
            bankAccount.focus();
            return false;
        }
    });

    // Set default due date if not set
    const dueDateInput = document.getElementById('due-date');
    if (!dueDateInput.value) {
        const d = new Date();
        d.setDate(d.getDate() + defaultPaymentDays);
        dueDateInput.value = d.toISOString().split('T')[0];
    }

    function addLine(data = {}) {
        const idx = lineIndex++;
        const tbody = document.getElementById('line-items-body');
        const tr = document.createElement('tr');
        tr.dataset.idx = idx;
        tr.innerHTML = `
            <td>${idx + 1}</td>
            <td>
                <input type="text" name="items[${idx}][name]" class="form-input" value="${esc(data.name || '')}" required style="width:100%;" list="services-list">
            </td>
            <td>
                <input type="number" name="items[${idx}][quantity]" class="form-input line-qty" value="${data.quantity || 1}" min="0.01" step="0.01" required style="width:80px;">
            </td>
            <td>
                <select name="items[${idx}][unit]" class="form-input" style="width:80px;">
                    ${['szt.','godz.','usł.','m2','kg','km','kpl.'].map(u => `<option value="${u}" ${(data.unit||'szt.')===u?'selected':''}>${u}</option>`).join('')}
                </select>
            </td>
            <td>
                <input type="number" name="items[${idx}][unit_price]" class="form-input line-price" value="${data.unit_price || ''}" min="0" step="0.01" required style="width:120px;">
            </td>
            <td>
                <select name="items[${idx}][vat_rate]" class="form-input line-vat" style="width:90px;">
                    ${['23','8','5','0','zw','np'].map(r => `<option value="${r}" ${(data.vat_rate||'23')===r?'selected':''}>${r}${!isNaN(r)?'%':''}</option>`).join('')}
                </select>
            </td>
            <td>
                <select name="items[${idx}][gtu]" class="form-input" style="width:100px;">
                    ${['','GTU_01','GTU_02','GTU_03','GTU_04','GTU_05','GTU_06','GTU_07','GTU_08','GTU_09','GTU_10','GTU_11','GTU_12','GTU_13'].map(g => `<option value="${g}" ${(data.gtu||'')===g?'selected':''}>${g||'(brak)'}</option>`).join('')}
                </select>
            </td>
            <td class="line-net" style="text-align:right; font-weight:600;">0,00</td>
            <td class="line-vat-amount" style="text-align:right;">0,00</td>
            <td class="line-gross" style="text-align:right; font-weight:600;">0,00</td>
            <td>
                <button type="button" class="btn btn-sm btn-danger remove-line" title="${<?= json_encode($lang('remove_line')) ?>}">&times;</button>
            </td>
        `;
        tbody.appendChild(tr);

        tr.querySelector('.remove-line').addEventListener('click', () => { tr.remove(); recalculate(); renumber(); });
        tr.querySelector('.line-qty').addEventListener('input', recalculate);
        tr.querySelector('.line-price').addEventListener('input', recalculate);
        tr.querySelector('.line-vat').addEventListener('change', recalculate);

        recalculate();
    }

    function recalculate() {
        let totalNet = 0, totalVat = 0, totalGross = 0;
        const vatSummary = {};

        document.querySelectorAll('#line-items-body tr').forEach(tr => {
            const qty = parseFloat(tr.querySelector('.line-qty')?.value || 0);
            const price = parseFloat(tr.querySelector('.line-price')?.value || 0);
            const vatRate = tr.querySelector('.line-vat')?.value || '23';

            const net = Math.round(qty * price * 100) / 100;
            let vatPercent = 0;
            if (!isNaN(parseInt(vatRate))) vatPercent = parseInt(vatRate) / 100;
            const vat = Math.round(net * vatPercent * 100) / 100;
            const gross = net + vat;

            tr.querySelector('.line-net').textContent = fmt(net);
            tr.querySelector('.line-vat-amount').textContent = fmt(vat);
            tr.querySelector('.line-gross').textContent = fmt(gross);

            totalNet += net;
            totalVat += vat;
            totalGross += gross;

            const rateKey = vatRate;
            if (!vatSummary[rateKey]) vatSummary[rateKey] = {net: 0, vat: 0, gross: 0};
            vatSummary[rateKey].net += net;
            vatSummary[rateKey].vat += vat;
            vatSummary[rateKey].gross += gross;
        });

        document.getElementById('total-net').textContent = fmt(totalNet);
        document.getElementById('total-vat').textContent = fmt(totalVat);
        document.getElementById('total-gross').textContent = fmt(totalGross);

        // Update PLN equivalent summary
        const erValue = parseFloat(document.getElementById('exchange-rate-value')?.value || '0');
        const plnSummary = document.getElementById('pln-summary');
        if (erValue > 0 && plnSummary) {
            document.getElementById('pln-net').textContent = fmt(Math.round(totalNet * erValue * 100) / 100) + ' PLN';
            document.getElementById('pln-vat').textContent = fmt(Math.round(totalVat * erValue * 100) / 100) + ' PLN';
            document.getElementById('pln-gross').textContent = fmt(Math.round(totalGross * erValue * 100) / 100) + ' PLN';
            plnSummary.style.display = 'block';
        } else if (plnSummary) {
            plnSummary.style.display = 'none';
        }

        // VAT summary table
        const vsBody = document.getElementById('vat-summary-body');
        vsBody.innerHTML = '';
        Object.keys(vatSummary).sort().forEach(rate => {
            const vs = vatSummary[rate];
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${rate}${!isNaN(rate)?'%':''}</td>
                <td style="text-align:right;">${fmt(vs.net)}</td>
                <td style="text-align:right;">${fmt(vs.vat)}</td>
                <td style="text-align:right;">${fmt(vs.gross)}</td>
            `;
            vsBody.appendChild(row);
        });
    }

    function renumber() {
        document.querySelectorAll('#line-items-body tr').forEach((tr, i) => {
            tr.querySelector('td:first-child').textContent = i + 1;
        });
    }

    function fmt(n) {
        return n.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML.replace(/"/g, '&quot;');
    }

    // Services datalist
    if (services.length > 0) {
        const dl = document.createElement('datalist');
        dl.id = 'services-list';
        services.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.name;
            opt.dataset.price = s.default_price || '';
            opt.dataset.unit = s.unit || 'szt.';
            opt.dataset.vat = s.vat_rate || '23';
            dl.appendChild(opt);
        });
        document.body.appendChild(dl);
    }

    // Contractor autocomplete
    let searchTimeout;
    const searchInput = document.getElementById('contractor-search');
    const resultsDiv = document.getElementById('contractor-results');

    searchInput?.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const q = this.value.trim();
        if (q.length < 2) { resultsDiv.style.display = 'none'; return; }

        searchTimeout = setTimeout(() => {
            fetch('/client/contractors/search?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(data => {
                    resultsDiv.innerHTML = '';
                    if (data.length === 0) {
                        resultsDiv.style.display = 'none';
                        return;
                    }
                    data.forEach(c => {
                        const div = document.createElement('div');
                        div.style.cssText = 'padding:8px 12px; cursor:pointer; border-bottom:1px solid var(--gray-200);';
                        div.textContent = c.company_name + (c.nip ? ' (NIP: ' + c.nip + ')' : '');
                        div.addEventListener('mouseenter', () => div.style.background = 'var(--gray-100)');
                        div.addEventListener('mouseleave', () => div.style.background = '');
                        div.addEventListener('click', () => {
                            const cIdEl = document.getElementById('contractor-id');
                            cIdEl.value = c.id;
                            document.getElementById('buyer-name').value = c.company_name;
                            document.getElementById('buyer-nip').value = c.nip || '';
                            const addr = [c.address_street, c.address_postal, c.address_city].filter(Boolean).join(', ');
                            document.getElementById('buyer-address').value = addr;
                            searchInput.value = c.company_name;
                            resultsDiv.style.display = 'none';
                            cIdEl.dispatchEvent(new Event('change'));
                        });
                        resultsDiv.appendChild(div);
                    });
                    resultsDiv.style.display = 'block';
                });
        }, 300);
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('#contractor-search') && !e.target.closest('#contractor-results')) {
            resultsDiv.style.display = 'none';
        }
    });

    // New contractor toggle + GUS lookup
    document.getElementById('toggle-new-contractor')?.addEventListener('click', function() {
        const sec = document.getElementById('new-contractor-section');
        sec.style.display = sec.style.display === 'none' ? 'block' : 'none';
    });

    document.getElementById('gus-lookup-btn')?.addEventListener('click', function() {
        const nip = document.getElementById('new-contractor-nip').value.replace(/[\s-]/g, '');
        const status = document.getElementById('gus-lookup-status');
        if (!/^\d{10}$/.test(nip)) { status.textContent = 'NIP musi mieć 10 cyfr'; status.style.color = 'var(--red-500)'; return; }
        status.textContent = 'Pobieranie...'; status.style.color = 'var(--gray-500)';
        this.disabled = true;
        fetch('/client/contractors/gus-lookup?nip=' + encodeURIComponent(nip))
        .then(r => r.json())
        .then(data => {
            this.disabled = false;
            if (data.error) { status.textContent = data.error; status.style.color = 'var(--red-500)'; return; }
            document.getElementById('buyer-name').value = data.company_name || '';
            document.getElementById('buyer-nip').value = data.nip || nip;
            const addr = [data.street, data.postal, data.city].filter(Boolean).join(', ');
            document.getElementById('buyer-address').value = addr;
            document.getElementById('contractor-id').value = '';
            var src = data.source === 'ceidg' ? ' (CEIDG)' : (data.source === 'gus' ? ' (GUS)' : '');
            status.textContent = 'OK' + src; status.style.color = 'var(--green-500)';
        })
        .catch(() => { this.disabled = false; status.textContent = 'Błąd połączenia'; status.style.color = 'var(--red-500)'; });
    });

    // Add line button
    document.getElementById('add-line-btn').addEventListener('click', () => addLine());

    // Init existing items or add one empty line
    if (existingItems.length > 0) {
        existingItems.forEach(item => addLine(item));
    } else {
        addLine();
    }

    // Payer (Podmiot3) toggle
    const payerToggle = document.getElementById('payer-toggle');
    const payerSection = document.getElementById('payer-section');
    const payerIcon = document.getElementById('payer-toggle-icon');
    if (payerToggle && payerSection) {
        if (payerSection.style.display !== 'none') {
            payerIcon.innerHTML = '&#9660;';
        }
        payerToggle.addEventListener('click', function() {
            if (payerSection.style.display === 'none') {
                payerSection.style.display = '';
                payerIcon.innerHTML = '&#9660;';
            } else {
                payerSection.style.display = 'none';
                payerIcon.innerHTML = '&#9654;';
            }
        });
    }
})();
</script>

<script>
// AJAX duplicate detection (F2)
(function() {
    let debounceTimer = null;
    const warningEl = document.getElementById('duplicate-warning-ajax');
    const listEl = document.getElementById('duplicate-warning-list');
    const ackInput = document.getElementById('duplicate-acknowledged');

    function checkDuplicate() {
        const form = document.getElementById('invoice-form');
        if (!form) return;
        const invoiceNumber = (form.querySelector('[name="invoice_number"]') || {}).value || '';
        const buyerNip = (form.querySelector('[name="buyer_nip"]') || {}).value || '';
        const grossEl = form.querySelector('[name="gross_amount"]');
        const grossAmount = grossEl ? grossEl.value : '0';

        if (!invoiceNumber || invoiceNumber.indexOf('DRAFT-') === 0) {
            warningEl.style.display = 'none';
            return;
        }

        const csrf = form.querySelector('[name="csrf_token"]').value;
        const excludeId = form.querySelector('[name="exclude_id"]') ? form.querySelector('[name="exclude_id"]').value : '';

        fetch('/client/sales/check-duplicate', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'csrf_token=' + encodeURIComponent(csrf)
                + '&invoice_number=' + encodeURIComponent(invoiceNumber)
                + '&buyer_nip=' + encodeURIComponent(buyerNip)
                + '&gross_amount=' + encodeURIComponent(grossAmount)
                + '&exclude_id=' + encodeURIComponent(excludeId)
        })
        .then(r => r.json())
        .then(data => {
            if (data.is_duplicate && data.candidates && data.candidates.length > 0) {
                let html = '<ul style="margin:8px 0; padding-left:20px;">';
                data.candidates.forEach(c => {
                    html += '<li>' + (c.invoice_number||'') + ' — ' + (c.gross_amount||'0') + ' (' + (c.issue_date||'') + ')</li>';
                });
                html += '</ul>';
                html += '<label style="display:flex;align-items:center;gap:6px;margin-top:8px;font-size:13px;cursor:pointer;">';
                html += '<input type="checkbox" onchange="document.getElementById(\'duplicate-acknowledged\').value=this.checked?\'1\':\'\'">';
                html += ' <?= $lang('duplicate_acknowledged') ?></label>';
                listEl.innerHTML = html;
                warningEl.style.display = 'block';
                if (ackInput) ackInput.value = '';
            } else {
                warningEl.style.display = 'none';
            }
        })
        .catch(() => {});
    }

    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('invoice-form');
        if (!form) return;
        ['buyer_nip'].forEach(name => {
            const el = form.querySelector('[name="' + name + '"]');
            if (el) el.addEventListener('blur', function() {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(checkDuplicate, 300);
            });
        });
    });
})();
</script>

<script>
// Currency-based bank account filtering
(function() {
    const currencySelect = document.getElementById('currency-select');
    const bankSelect = document.getElementById('bank-account-select');
    const warningEl = document.getElementById('no-currency-account-warning');
    if (!currencySelect || !bankSelect) return;

    function filterBankAccounts() {
        const currency = currencySelect.value;
        const options = bankSelect.querySelectorAll('option[data-currency]');
        let hasMatch = false;
        let currentSelected = bankSelect.value;
        let currentOptionVisible = false;

        options.forEach(opt => {
            const optCurrency = opt.dataset.currency || 'PLN';
            if (optCurrency === currency) {
                opt.style.display = '';
                opt.disabled = false;
                hasMatch = true;
                if (opt.value === currentSelected) currentOptionVisible = true;
            } else {
                opt.style.display = 'none';
                opt.disabled = true;
            }
        });

        // Auto-select: if current selection is hidden, pick best match
        if (!currentOptionVisible) {
            let defaultOpt = null;
            let firstOpt = null;
            options.forEach(opt => {
                if (opt.disabled || opt.style.display === 'none') return;
                if (!firstOpt) firstOpt = opt;
                if (opt.dataset.defaultReceiving === '1') defaultOpt = opt;
            });
            bankSelect.value = (defaultOpt || firstOpt || {value: ''}).value;
        }

        // Show/hide warning
        if (warningEl) {
            warningEl.style.display = (currency !== 'PLN' && !hasMatch) ? 'block' : 'none';
        }
    }

    currencySelect.addEventListener('change', filterBankAccounts);
    // Run on page load
    filterBankAccounts();
})();
</script>

<script>
// NBP Exchange Rate fetching for foreign currency invoices
(function() {
    const currencySelect = document.getElementById('currency-select');
    const saleDateInput = document.querySelector('input[name="sale_date"]');
    const section = document.getElementById('exchange-rate-section');
    const loadingEl = document.getElementById('exchange-rate-loading');
    const dataEl = document.getElementById('exchange-rate-data');
    const errorEl = document.getElementById('exchange-rate-error');
    const hiddenRate = document.getElementById('exchange-rate-value');
    const hiddenDate = document.getElementById('exchange-rate-date-value');
    const hiddenTable = document.getElementById('exchange-rate-table-value');

    if (!currencySelect || !saleDateInput || !section) return;

    const NBP_API = 'https://api.nbp.pl/api/exchangerates/rates/a';
    const MAX_LOOKBACK = 10;

    function formatDate(d) {
        return d.toISOString().split('T')[0];
    }

    function subtractDay(dateStr) {
        var d = new Date(dateStr + 'T12:00:00');
        d.setDate(d.getDate() - 1);
        return formatDate(d);
    }

    function getNetTotal() {
        var el = document.getElementById('total-net');
        if (el) {
            var text = el.textContent.replace(/\s/g, '').replace(',', '.');
            var val = parseFloat(text);
            return isNaN(val) ? 0 : val;
        }
        return 0;
    }

    function clearSection() {
        section.style.display = 'none';
        loadingEl.style.display = 'none';
        dataEl.style.display = 'none';
        errorEl.style.display = 'none';
        hiddenRate.value = '';
        hiddenDate.value = '';
        hiddenTable.value = '';
    }

    function showError() {
        loadingEl.style.display = 'none';
        dataEl.style.display = 'none';
        errorEl.style.display = 'block';
    }

    function showData(currency, rate, date, table) {
        loadingEl.style.display = 'none';
        errorEl.style.display = 'none';
        dataEl.style.display = 'block';

        document.getElementById('er-currency').textContent = '1 ' + currency;
        document.getElementById('er-rate').textContent = rate.toFixed(4) + ' PLN';
        document.getElementById('er-date').textContent = date;
        document.getElementById('er-table').textContent = table;

        hiddenRate.value = rate.toFixed(6);
        hiddenDate.value = date;
        hiddenTable.value = table;

        updateConversion(currency, rate);
        // Trigger recalculate to update PLN summary
        if (typeof recalculate === 'function') recalculate();
    }

    function updateConversion(currency, rate) {
        var net = getNetTotal();
        var convEl = document.getElementById('er-conversion');
        if (net > 0) {
            var plnAmount = (net * rate).toFixed(2);
            convEl.textContent = net.toFixed(2) + ' ' + currency + ' \u00d7 ' + rate.toFixed(4) + ' = ' + plnAmount + ' PLN';
        } else {
            convEl.textContent = '1 ' + currency + ' = ' + rate.toFixed(4) + ' PLN';
        }
    }

    async function fetchNbpRate(currency, dateStr, attempt) {
        if (attempt >= MAX_LOOKBACK) return null;

        var url = NBP_API + '/' + encodeURIComponent(currency) + '/' + dateStr + '/?format=json';
        try {
            var resp = await fetch(url);
            if (resp.status === 404) {
                // No rate for this date (weekend/holiday) — try previous day
                return fetchNbpRate(currency, subtractDay(dateStr), attempt + 1);
            }
            if (!resp.ok) return null;

            var data = await resp.json();
            if (data && data.rates && data.rates[0]) {
                return {
                    rate: data.rates[0].mid,
                    date: data.rates[0].effectiveDate || dateStr,
                    table: data.rates[0].no || ''
                };
            }
        } catch (e) {
            // Network error
        }
        return null;
    }

    var fetchController = 0;

    async function updateExchangeRate() {
        var currency = currencySelect.value;
        var saleDate = saleDateInput.value;

        if (currency === 'PLN' || !saleDate) {
            clearSection();
            return;
        }

        section.style.display = '';
        loadingEl.style.display = 'block';
        dataEl.style.display = 'none';
        errorEl.style.display = 'none';

        // Day before sale_date per art. 31a VAT
        var startDate = subtractDay(saleDate);
        var myFetch = ++fetchController;

        var result = await fetchNbpRate(currency, startDate, 0);

        // Ignore stale responses
        if (myFetch !== fetchController) return;

        if (result) {
            showData(currency, result.rate, result.date, result.table);
        } else {
            showError();
            hiddenRate.value = '';
            hiddenDate.value = '';
            hiddenTable.value = '';
        }
    }

    currencySelect.addEventListener('change', updateExchangeRate);
    saleDateInput.addEventListener('change', updateExchangeRate);

    // Update conversion when line items change (observe totals)
    var totalObserver = new MutationObserver(function() {
        var currency = currencySelect.value;
        var rate = parseFloat(hiddenRate.value);
        if (currency !== 'PLN' && rate > 0) {
            updateConversion(currency, rate);
        }
    });
    var totalEl = document.getElementById('total-net');
    if (totalEl) {
        totalObserver.observe(totalEl, { childList: true, characterData: true, subtree: true });
    }

    // Run on page load if editing an existing foreign currency invoice
    if (currencySelect.value !== 'PLN') {
        updateExchangeRate();
    }

    // Quick rates preview — fetch EUR/USD/GBP rates on page load
    (async function loadQuickRates() {
        var quickRatesEl = document.getElementById('quick-rates');
        if (!quickRatesEl) return;

        var currencies = ['EUR', 'USD', 'GBP'];
        var today = new Date();
        // Start from yesterday (NBP publishes around 12:00, so today's rate may not exist yet)
        var startDate = new Date(today);
        startDate.setDate(startDate.getDate() - 1);

        var parts = [];
        for (var i = 0; i < currencies.length; i++) {
            var cur = currencies[i];
            try {
                var result = await fetchNbpRate(cur, startDate, 0);
                if (result) {
                    parts.push('<strong>' + cur + '</strong>: ' + result.rate.toFixed(4).replace('.', ',') + ' PLN');
                }
            } catch(e) { /* ignore */ }
        }

        if (parts.length > 0) {
            quickRatesEl.innerHTML = parts.join(' &nbsp;|&nbsp; ');
            quickRatesEl.style.display = 'block';
        }
    })();
})();
</script>

<style>
@media (max-width: 768px) {
    div[style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }
    #exchange-rate-section .form-card {
        padding: 16px;
    }
    #exchange-rate-section table {
        font-size: 14px;
    }
    #exchange-rate-section table td {
        padding: 5px 8px 5px 0 !important;
        white-space: normal !important;
        word-break: break-word;
    }
    #exchange-rate-section #er-rate {
        font-size: 1em !important;
    }
}
@media (max-width: 480px) {
    #exchange-rate-section .form-card {
        padding: 12px;
    }
    #exchange-rate-section table {
        font-size: 13px;
    }
    #exchange-rate-section table td {
        padding: 4px 6px 4px 0 !important;
    }
    #exchange-rate-section #er-rate {
        font-size: 0.95em !important;
    }
    #exchange-rate-section .alert {
        font-size: 12px;
        padding: 6px 8px !important;
    }
}
</style>

<script>
// Cash payment status toggle (FV gotówkowa)
(function() {
    var paymentMethodSelect = document.getElementById('payment-method-select');
    var cashStatusDiv = document.getElementById('cash-payment-status');
    var cashStatusSelect = document.getElementById('cash-payment-status-select');
    var mppGroup = document.getElementById('mpp-group');
    var mppCheckbox = document.getElementById('mpp-checkbox');

    if (!paymentMethodSelect || !cashStatusDiv || !cashStatusSelect) return;

    var dueDateInput = document.getElementById('due-date');
    var issueDateInput = document.querySelector('input[name="issue_date"]');

    function syncDueDateIfCashPaid() {
        if (paymentMethodSelect.value === 'gotowka' && cashStatusSelect.value === 'paid' && dueDateInput && issueDateInput) {
            dueDateInput.value = issueDateInput.value;
        }
    }

    function toggleCashStatus() {
        var isCash = paymentMethodSelect.value === 'gotowka';
        if (isCash) {
            cashStatusDiv.style.display = '';
            cashStatusSelect.required = true;
            // MPP niedostępne dla gotówki
            if (mppGroup) mppGroup.style.display = 'none';
            if (mppCheckbox) { mppCheckbox.checked = false; mppCheckbox.disabled = true; }
            syncDueDateIfCashPaid();
        } else {
            cashStatusDiv.style.display = 'none';
            cashStatusSelect.required = false;
            if (mppGroup) mppGroup.style.display = 'flex';
            if (mppCheckbox) mppCheckbox.disabled = false;
        }
    }

    paymentMethodSelect.addEventListener('change', toggleCashStatus);
    cashStatusSelect.addEventListener('change', syncDueDateIfCashPaid);
    if (issueDateInput) issueDateInput.addEventListener('change', syncDueDateIfCashPaid);
    // Run on page load in case payment method is already set to cash
    toggleCashStatus();
})();
</script>

<script>
// Invoice type switching — show/hide conditional sections
(function() {
    const typeSelect = document.getElementById('invoice-type-select');
    if (!typeSelect) return;

    const advanceSection = document.getElementById('advance-fields-section');
    const finalSection = document.getElementById('final-invoice-section');
    const infoBanners = {
        FP: document.getElementById('invoice-type-info-FP'),
        FV_ZAL: document.getElementById('invoice-type-info-FV_ZAL'),
        FV_KON: document.getElementById('invoice-type-info-FV_KON'),
    };

    function updateInvoiceType() {
        const type = typeSelect.value;

        // Hide all info banners
        Object.values(infoBanners).forEach(el => { if (el) el.style.display = 'none'; });
        // Show relevant banner
        if (infoBanners[type]) infoBanners[type].style.display = 'block';

        // Show/hide advance fields
        if (advanceSection) {
            advanceSection.style.display = (type === 'FV_ZAL') ? '' : 'none';
        }

        // Show/hide final invoice section
        if (finalSection) {
            finalSection.style.display = (type === 'FV_KON') ? '' : 'none';
            if (type === 'FV_KON') {
                loadAdvanceInvoices();
            }
        }
    }

    // Load advance invoices for FV_KON
    let advanceInvoicesLoaded = false;
    function loadAdvanceInvoices() {
        const contractorId = document.getElementById('contractor-id')?.value;
        const listDiv = document.getElementById('advance-invoices-list');
        if (!listDiv) return;

        if (!contractorId) {
            listDiv.innerHTML = '<p style="color:var(--gray-400); font-size:13px;">Wybierz kontrahenta, aby zaladowac liste faktur zaliczkowych.</p>';
            return;
        }

        listDiv.innerHTML = '<p style="color:var(--gray-500); font-size:13px;">Ladowanie faktur zaliczkowych...</p>';

        function escapeHtml(str) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }

        const csrf = document.querySelector('[name="csrf_token"]').value;
        fetch('/client/sales/advance-invoices?contractor_id=' + encodeURIComponent(contractorId))
            .then(r => r.json())
            .then(data => {
                if (!data || data.length === 0) {
                    listDiv.innerHTML = '<p style="color:var(--gray-400); font-size:13px;">Brak faktur zaliczkowych dla tego kontrahenta.</p>';
                    return;
                }

                // Parse existing related IDs
                let existingIds = [];
                try {
                    const raw = document.getElementById('related-advance-ids').value;
                    if (raw) existingIds = JSON.parse(raw);
                } catch(e) {}

                let html = '<table class="data-table" style="width:100%; font-size:13px;">';
                html += '<thead><tr><th style="width:30px;"></th><th>Numer</th><th>Data</th><th>Kontrahent</th><th style="text-align:right;">Kwota brutto</th><th style="text-align:right;">Zaliczka</th></tr></thead><tbody>';
                data.forEach(inv => {
                    const checked = existingIds.includes(inv.id) ? 'checked' : '';
                    html += '<tr>';
                    html += '<td><input type="checkbox" class="advance-invoice-cb" value="' + inv.id + '" data-gross="' + (inv.advance_amount || inv.gross_amount) + '" ' + checked + '></td>';
                    html += '<td>' + escapeHtml(inv.invoice_number || '') + '</td>';
                    html += '<td>' + escapeHtml(inv.issue_date || '') + '</td>';
                    html += '<td>' + escapeHtml(inv.buyer_name || '') + '</td>';
                    html += '<td style="text-align:right;">' + parseFloat(inv.gross_amount || 0).toFixed(2).replace('.', ',') + '</td>';
                    html += '<td style="text-align:right;">' + parseFloat(inv.advance_amount || inv.gross_amount || 0).toFixed(2).replace('.', ',') + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table>';
                listDiv.innerHTML = html;

                // Bind checkboxes
                listDiv.querySelectorAll('.advance-invoice-cb').forEach(cb => {
                    cb.addEventListener('change', updateAdvanceTotals);
                });
                updateAdvanceTotals();
            })
            .catch(() => {
                listDiv.innerHTML = '<p style="color:var(--danger); font-size:13px;">Blad ladowania faktur zaliczkowych.</p>';
            });
    }

    function updateAdvanceTotals() {
        const checkboxes = document.querySelectorAll('.advance-invoice-cb');
        const totalsDiv = document.getElementById('advance-totals');
        if (!totalsDiv) return;

        let selectedIds = [];
        let totalAdvance = 0;

        checkboxes.forEach(cb => {
            if (cb.checked) {
                selectedIds.push(parseInt(cb.value));
                totalAdvance += parseFloat(cb.dataset.gross || 0);
            }
        });

        // Update hidden field
        document.getElementById('related-advance-ids').value = JSON.stringify(selectedIds);

        if (selectedIds.length > 0) {
            totalsDiv.style.display = '';
            document.getElementById('advance-total-amount').textContent = totalAdvance.toFixed(2).replace('.', ',');

            // Get current invoice gross
            const grossText = document.getElementById('total-gross')?.textContent || '0';
            const invoiceGross = parseFloat(grossText.replace(/\s/g, '').replace(',', '.')) || 0;
            document.getElementById('invoice-gross-amount').textContent = invoiceGross.toFixed(2).replace('.', ',');

            const remaining = invoiceGross - totalAdvance;
            const remainingEl = document.getElementById('remaining-amount');
            remainingEl.textContent = remaining.toFixed(2).replace('.', ',');
            remainingEl.style.color = remaining < 0 ? 'var(--danger)' : 'var(--primary)';
        } else {
            totalsDiv.style.display = 'none';
        }
    }

    // Observe gross total changes
    const grossTotalEl = document.getElementById('total-gross');
    if (grossTotalEl) {
        new MutationObserver(updateAdvanceTotals).observe(grossTotalEl, { childList: true, characterData: true, subtree: true });
    }

    typeSelect.addEventListener('change', updateInvoiceType);

    // Reload advance invoices when contractor changes
    const contractorIdField = document.getElementById('contractor-id');
    if (contractorIdField) {
        contractorIdField.addEventListener('change', function() {
            if (typeSelect.value === 'FV_KON') {
                loadAdvanceInvoices();
            }
        });
    }

    // Run on page load
    updateInvoiceType();
})();
</script>
