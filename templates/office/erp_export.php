<h1><?= $lang('erp_export_title') ?></h1>
<p class="text-muted"><?= $lang('erp_export_desc') ?></p>

<div class="form-card" style="max-width:640px;margin:24px 0;padding:24px;">
    <form method="POST" action="/office/erp-export">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

        <div class="form-group">
            <label class="form-label"><?= $lang('client') ?></label>
            <select id="erp-client-select" class="form-input" required>
                <option value="">-- <?= $lang('erp_select_client') ?> --</option>
                <?php foreach ($clients as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?> (<?= htmlspecialchars($c['nip'] ?? '') ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label"><?= $lang('erp_select_batch') ?></label>
            <select name="batch_id" id="erp-batch-select" class="form-input" required disabled>
                <option value="">-- <?= $lang('erp_select_client_first') ?> --</option>
            </select>
            <span class="form-hint" id="erp-batch-hint"><?= $lang('erp_select_client_hint') ?></span>
        </div>

        <div class="form-group">
            <label class="form-label"><?= $lang('erp_format') ?></label>
            <select name="format" class="form-input" required id="erp-format-select">
                <option value="">-- <?= $lang('erp_select_format') ?> --</option>
                <optgroup label="<?= $lang('erp_group_csv') ?>">
                    <option value="comarch_optima"><?= $lang('erp_comarch_optima') ?></option>
                    <option value="sage"><?= $lang('erp_sage') ?></option>
                    <option value="insert_gt"><?= $lang('erp_insert_gt') ?></option>
                    <option value="rewizor"><?= $lang('erp_rewizor') ?></option>
                    <option value="universal_csv"><?= $lang('erp_universal_csv') ?></option>
                </optgroup>
                <optgroup label="<?= $lang('erp_group_xml') ?>">
                    <option value="enova"><?= $lang('erp_enova') ?></option>
                    <option value="wfirma"><?= $lang('erp_wfirma') ?></option>
                </optgroup>
                <optgroup label="<?= $lang('erp_group_jpk') ?>">
                    <option value="jpk_vat7"><?= $lang('erp_jpk_vat7') ?></option>
                    <option value="jpk_fa"><?= $lang('erp_jpk_fa') ?></option>
                </optgroup>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label"><?= $lang('erp_only_accepted') ?></label>
            <select name="only_accepted" class="form-input">
                <option value="1"><?= $lang('erp_only_accepted') ?></option>
                <option value="0"><?= $lang('erp_all_invoices') ?></option>
            </select>
        </div>

        <div id="erp-column-preview" style="display:none;margin:16px 0;padding:12px;background:var(--gray-50);border-radius:8px;">
            <strong><?= $lang('erp_preview_columns') ?>:</strong>
            <div id="erp-columns" style="margin-top:8px;font-family:monospace;font-size:0.85em;color:var(--gray-500);"></div>
        </div>

        <button type="submit" class="btn btn-primary" id="erp-submit-btn" disabled>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px;"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            <?= $lang('erp_download') ?>
        </button>
    </form>
</div>

<script>
(function() {
    // Batch data grouped by client_id
    var batchesByClient = {};
    <?php foreach ($batches as $b): ?>
    if (!batchesByClient[<?= (int)$b['client_id'] ?>]) batchesByClient[<?= (int)$b['client_id'] ?>] = [];
    batchesByClient[<?= (int)$b['client_id'] ?>].push({
        id: <?= (int)$b['id'] ?>,
        label: <?= json_encode(
            sprintf('%02d/%04d', $b['period_month'], $b['period_year'])
            . ' — ' . ($b['invoice_count'] ?? 0) . ' fakt.'
            . ($b['is_finalized'] ? ' ✓' : ' (w toku)')
        ) ?>
    });
    <?php endforeach; ?>

    var clientSel = document.getElementById('erp-client-select');
    var batchSel = document.getElementById('erp-batch-select');
    var batchHint = document.getElementById('erp-batch-hint');
    var submitBtn = document.getElementById('erp-submit-btn');

    clientSel.addEventListener('change', function() {
        var clientId = parseInt(this.value);
        batchSel.innerHTML = '';

        if (!clientId || !batchesByClient[clientId] || batchesByClient[clientId].length === 0) {
            batchSel.disabled = true;
            submitBtn.disabled = true;
            var opt = document.createElement('option');
            opt.value = '';
            opt.textContent = clientId ? '-- Brak paczek dla tego klienta --' : '-- <?= $lang('erp_select_client_first') ?> --';
            batchSel.appendChild(opt);
            batchHint.textContent = clientId ? 'Ten klient nie ma jeszcze paczek faktur.' : '<?= $lang('erp_select_client_hint') ?>';
            return;
        }

        batchSel.disabled = false;
        var defOpt = document.createElement('option');
        defOpt.value = '';
        defOpt.textContent = '-- <?= $lang('select_batch') ?> --';
        batchSel.appendChild(defOpt);

        batchesByClient[clientId].forEach(function(b) {
            var opt = document.createElement('option');
            opt.value = b.id;
            opt.textContent = b.label;
            batchSel.appendChild(opt);
        });

        batchHint.textContent = batchesByClient[clientId].length + ' paczek dostępnych.';
    });

    batchSel.addEventListener('change', function() {
        submitBtn.disabled = !this.value;
    });

    // Column preview
    var columns = {
        comarch_optima: ['Lp', 'Typ dokumentu', 'Numer faktury', 'Data wystawienia', 'Data sprzedaży', 'NIP sprzedawcy', 'Nazwa sprzedawcy', 'Adres', 'Kwota netto', 'Stawka VAT', 'Kwota VAT', 'Kwota brutto', 'Waluta', 'MPK'],
        sage: ['Numer faktury', 'Data wystawienia', 'NIP sprzedawcy', 'Nazwa sprzedawcy', 'Adres', 'Kwota netto', 'Kwota VAT', 'Kwota brutto', 'Forma płatności', 'Waluta'],
        insert_gt: ['Typ', 'NrDokumentu', 'DataWystawienia', 'DataOperacji', 'NIPKontrahenta', 'NazwaKontrahenta', 'AdresKontrahenta', 'OpisOperacji', 'KwotaNetto', 'StawkaVAT', 'KwotaVAT', 'KwotaBrutto', 'RodzajDokumentu'],
        rewizor: ['LpDekretacji', 'DataDokumentu', 'DataOperacji', 'TypDokumentu', 'NumerDokumentu', 'KontrahentNIP', 'KontrahentNazwa', 'OpisOperacji', 'KontoWn', 'KontoMa', 'Kwota', 'StawkaVAT'],
        universal_csv: ['Lp', 'TypDokumentu', 'NumerFaktury', 'DataWystawienia', 'DataSprzedazy', 'NIPSprzedawcy', 'NazwaSprzedawcy', 'AdresSprzedawcy', 'NIPNabywcy', 'NazwaNabywcy', 'KwotaNetto', 'StawkaVAT', 'KwotaVAT', 'KwotaBrutto', 'Waluta', 'NrKSeF', 'MPK', 'Status', 'Komentarz'],
        enova: ['XML: DokumentHandlowy → Kontrahent + Pozycje (per stawka VAT)'],
        wfirma: ['XML: Wydatki → Wydatek → Kontrahent + Pozycje (per stawka VAT)'],
        jpk_vat7: ['XML: JPK_V7M — Naglowek, Podmiot1, Deklaracja, ZakupWiersz, ZakupCtrl'],
        jpk_fa: ['XML: JPK_FA(4) — Naglowek, Podmiot1, Faktura (P_1..P_15), FakturaCtrl']
    };
    var formatSel = document.getElementById('erp-format-select');
    var preview = document.getElementById('erp-column-preview');
    var colsDiv = document.getElementById('erp-columns');

    formatSel.addEventListener('change', function() {
        var fmt = this.value;
        if (fmt && columns[fmt]) {
            colsDiv.textContent = columns[fmt].join(' | ');
            preview.style.display = 'block';
        } else {
            preview.style.display = 'none';
        }
    });
})();
</script>
