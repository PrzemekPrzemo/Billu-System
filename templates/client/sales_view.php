<!-- Header -->
<div class="section-header" style="margin-bottom:16px;">
    <div>
        <h1 style="margin:0; font-size:22px;"><?= $lang('invoice_preview') ?></h1>
        <div style="color:var(--gray-500); font-size:14px; margin-top:4px;"><?= htmlspecialchars($invoice['invoice_number']) ?></div>
    </div>
    <a href="/client/sales" class="btn">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        <?= $lang('back') ?>
    </a>
</div>

<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Status & Actions Bar -->
<div class="form-card" style="padding:16px; margin-bottom:20px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <?php
            $statusClass = match($invoice['status']) {
                'draft' => 'badge-warning',
                'issued' => 'badge-success',
                'sent_ksef' => 'badge-info',
                'cancelled' => 'badge-error',
                default => '',
            };
            ?>
            <span class="badge <?= $statusClass ?>" style="font-size:13px; padding:5px 14px;"><?= $lang($invoice['status']) ?></span>
            <?php if (!empty($invoice['ksef_reference_number'])): ?>
                <span class="badge badge-info" style="font-size:12px; padding:5px 14px;">KSeF: <?= htmlspecialchars($invoice['ksef_reference_number']) ?></span>
            <?php endif; ?>
            <?php if ($invoice['invoice_type'] === 'FV_KOR'): ?>
                <span class="badge badge-warning" style="font-size:13px; padding:5px 14px;"><?= $lang('invoice_fv_kor') ?></span>
            <?php endif; ?>
            <?php if ($invoice['invoice_type'] === 'FP'): ?>
                <span class="badge" style="font-size:13px; padding:5px 14px; background:#f0f9ff; color:#0369a1; border:1px solid #bae6fd;">Proforma</span>
            <?php endif; ?>
            <?php if ($invoice['invoice_type'] === 'FV_ZAL'): ?>
                <span class="badge" style="font-size:13px; padding:5px 14px; background:#fefce8; color:#a16207; border:1px solid #fde68a;">Zaliczkowa</span>
            <?php endif; ?>
            <?php if ($invoice['invoice_type'] === 'FV_KON'): ?>
                <span class="badge" style="font-size:13px; padding:5px 14px; background:#f0fdf4; color:#166534; border:1px solid #86efac;">Koncowa</span>
            <?php endif; ?>
            <?php if ($invoice['ksef_status'] === 'pending'): ?>
                <span class="badge badge-warning" id="ksef-status-badge" style="font-size:13px; padding:5px 14px;">
                    <span class="ksef-spinner" style="width:12px; height:12px; margin-right:6px;"></span>KSeF: wysyłanie...
                </span>
            <?php endif; ?>
        </div>
        <div style="display:flex; gap:6px; flex-wrap:wrap;">
            <?php
            $ksefSt = $invoice['ksef_status'] ?? 'none';
            $sentToKsef = !empty($ksefSt) && !in_array($ksefSt, ['none', 'error']);
            $hasKsefNumber = !empty($invoice['ksef_reference_number']);
            $canEdit = $invoice['status'] === 'draft'
                || ($invoice['status'] === 'issued' && !$sentToKsef);
            $canSendKsef = $invoice['status'] === 'issued'
                && (empty($ksefSt) || $ksefSt === 'none' || $ksefSt === 'error')
                && ($invoice['invoice_type'] ?? 'FV') !== 'FP'; // Proforma cannot be sent to KSeF
            $canCorrect = $invoice['status'] !== 'draft'
                && $invoice['invoice_type'] !== 'FV_KOR'
                && $sentToKsef && $hasKsefNumber;
            ?>
            <?php if ($canEdit): ?>
                <a href="/client/sales/<?= $invoice['id'] ?>/edit" class="btn btn-sm">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    <?= $lang('edit') ?>
                </a>
            <?php endif; ?>
            <?php if ($invoice['status'] === 'draft'): ?>
                <form method="POST" action="/client/sales/<?= $invoice['id'] ?>/issue" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <button type="submit" class="btn btn-sm btn-primary"><?= $lang('issue_invoice') ?></button>
                </form>
                <form method="POST" action="/client/sales/<?= $invoice['id'] ?>/delete" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('<?= $lang('invoice_delete_confirm') ?>')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                    </button>
                </form>
            <?php endif; ?>
            <?php if ($canSendKsef): ?>
                <button type="button" class="btn btn-sm btn-primary" id="ksef-send-btn" onclick="sendToKsef(<?= $invoice['id'] ?>)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M22 2L11 13"/><path d="M22 2L15 22L11 13L2 9L22 2Z"/></svg>
                    <?= $lang('send_to_ksef') ?>
                </button>
            <?php endif; ?>
            <?php if ($invoice['status'] !== 'draft'): ?>
                <a href="/client/sales/<?= $invoice['id'] ?>/pdf" class="btn btn-sm">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    PDF
                </a>
                <?php if (!empty($canSendInvoices)): ?>
                <a href="/client/sales/<?= $invoice['id'] ?>/send-email" class="btn btn-sm">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
                    </svg>
                    <?= $lang('send_email') ?>
                </a>
                <?php endif; ?>
                <?php if ($hasKsefNumber && ($upoEnabled ?? true)): ?>
                <a href="/client/sales/<?= $invoice['id'] ?>/upo" class="btn btn-sm" title="Pobierz UPO z KSeF">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M9 15l2 2 4-4"/></svg>
                    <?= $lang('download_upo') ?>
                </a>
                <?php endif; ?>
            <?php endif; ?>
            <form method="POST" action="/client/sales/<?= $invoice['id'] ?>/duplicate" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <button type="submit" class="btn btn-sm">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                    <?= $lang('duplicate_invoice') ?>
                </button>
            </form>
            <?php if ($canCorrect): ?>
                <a href="/client/sales/<?= $invoice['id'] ?>/correction" class="btn btn-sm"><?= $lang('create_correction') ?></a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($sentToKsef || $hasKsefNumber || $ksefSt === 'error'): ?>
<div class="form-card" style="padding:14px 18px; margin-bottom:16px; display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
    <div style="display:flex; align-items:center; gap:8px;">
        <strong style="font-size:13px; color:var(--gray-500);">Status KSeF:</strong>
        <?php if ($ksefSt === 'accepted'): ?>
            <span class="badge badge-success">Zaakceptowana</span>
        <?php elseif ($ksefSt === 'sent' || $ksefSt === 'pending'): ?>
            <span class="badge badge-info">Wysłana</span>
        <?php elseif ($ksefSt === 'error' || $ksefSt === 'rejected'): ?>
            <span class="badge badge-error">Odrzucona</span>
        <?php endif; ?>
    </div>
    <?php if ($hasKsefNumber): ?>
    <div style="font-size:13px;">
        <strong style="color:var(--gray-500);">Nr KSeF:</strong>
        <code style="font-size:12px; background:var(--gray-50); padding:2px 8px; border-radius:4px; border:1px solid var(--gray-200);"><?= htmlspecialchars($invoice['ksef_reference_number']) ?></code>
    </div>
    <?php endif; ?>
    <?php if ($ksefSt === 'error' && !empty($invoice['ksef_error'])): ?>
    <div style="font-size:13px; color:var(--danger);">
        <?= htmlspecialchars($invoice['ksef_error']) ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (!empty($invoice['email_sent_at'])): ?>
<div class="alert alert-info" style="margin-bottom:16px; display:flex; align-items:center; gap:8px;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
    </svg>
    <?= $lang('invoice_email_sent_info') ?>:
    <strong><?= htmlspecialchars($invoice['email_sent_to']) ?></strong>
    (<?= htmlspecialchars($invoice['email_sent_at']) ?>)
</div>
<?php endif; ?>

<?php if (($invoice['invoice_type'] ?? 'FV') === 'FP'): ?>
<div class="alert alert-warning" style="margin-bottom:16px;">
    <strong>Faktura proforma</strong> nie jest dokumentem ksiegowym i nie zostala wyslana do KSeF.
</div>
<?php endif; ?>

<?php if (($invoice['invoice_type'] ?? 'FV') === 'FV_ZAL'): ?>
<div class="alert alert-info" style="margin-bottom:16px;">
    <strong>Faktura zaliczkowa</strong> dokumentuje otrzymana zaliczke.
    <?php if (!empty($invoice['advance_amount'])): ?>
        Kwota zaliczki: <strong><?= number_format((float)$invoice['advance_amount'], 2, ',', ' ') ?> <?= htmlspecialchars($invoice['currency'] ?? 'PLN') ?></strong>
    <?php endif; ?>
</div>
<?php if (!empty($invoice['advance_order_description'])): ?>
<div class="form-card" style="padding:12px 16px; margin-bottom:16px; background:var(--gray-50); border:1px solid var(--gray-200); border-radius:8px;">
    <div style="font-weight:600; margin-bottom:4px; font-size:13px; color:var(--gray-500);">Opis zamowienia / umowy:</div>
    <div style="font-size:14px;"><?= nl2br(htmlspecialchars($invoice['advance_order_description'])) ?></div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php if (($invoice['invoice_type'] ?? 'FV') === 'FV_KON'): ?>
<div class="alert alert-info" style="margin-bottom:16px;">
    <strong>Faktura koncowa</strong> rozlicza wczesniej wystawione faktury zaliczkowe.
</div>
<?php
    $relatedAdvanceIds = $invoice['related_advance_ids'] ?? null;
    if (is_string($relatedAdvanceIds)) $relatedAdvanceIds = json_decode($relatedAdvanceIds, true) ?: [];
    if (!empty($relatedAdvanceIds)):
?>
<div class="form-card" style="padding:16px; margin-bottom:16px;">
    <div style="font-weight:600; margin-bottom:8px; font-size:14px;">Powiazane faktury zaliczkowe:</div>
    <table class="data-table" style="width:100%; font-size:13px;">
        <thead>
            <tr><th>Numer</th><th>Data</th><th style="text-align:right;">Kwota brutto</th><th style="text-align:right;">Zaliczka</th></tr>
        </thead>
        <tbody>
            <?php
            $advanceTotal = 0;
            foreach ($relatedAdvanceIds as $advId):
                $advInv = \App\Models\IssuedInvoice::findById((int)$advId);
                if (!$advInv) continue;
                $advAmount = (float)($advInv['advance_amount'] ?? $advInv['gross_amount']);
                $advanceTotal += $advAmount;
            ?>
            <tr>
                <td><a href="/client/sales/<?= $advInv['id'] ?>" style="color:var(--primary); font-weight:600;"><?= htmlspecialchars($advInv['invoice_number']) ?></a></td>
                <td><?= htmlspecialchars($advInv['issue_date']) ?></td>
                <td style="text-align:right;"><?= number_format((float)$advInv['gross_amount'], 2, ',', ' ') ?></td>
                <td style="text-align:right;"><?= number_format($advAmount, 2, ',', ' ') ?></td>
            </tr>
            <?php endforeach; ?>
            <tr style="font-weight:700; border-top:2px solid var(--gray-300);">
                <td colspan="3" style="text-align:right;">Suma zaliczek:</td>
                <td style="text-align:right;"><?= number_format($advanceTotal, 2, ',', ' ') ?> <?= htmlspecialchars($invoice['currency'] ?? 'PLN') ?></td>
            </tr>
            <tr style="font-weight:700;">
                <td colspan="3" style="text-align:right;">Pozostalo do zaplaty:</td>
                <td style="text-align:right; color:var(--primary);"><?= number_format((float)$invoice['gross_amount'] - $advanceTotal, 2, ',', ' ') ?> <?= htmlspecialchars($invoice['currency'] ?? 'PLN') ?></td>
            </tr>
        </tbody>
    </table>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- Invoice Preview Card - Professional Layout -->
<?php
$isCorrection = $invoice['invoice_type'] === 'FV_KOR';
$docTitle = match($invoice['invoice_type'] ?? 'FV') {
    'FV_KOR' => 'FAKTURA KORYGUJACA',
    'FP' => 'FAKTURA PROFORMA',
    'FV_ZAL' => 'FAKTURA ZALICZKOWA',
    'FV_KON' => 'FAKTURA KONCOWA',
    default => 'FAKTURA VAT',
};
?>
<div class="form-card inv-preview" id="invoice-preview">

    <!-- Document Title -->
    <div class="inv-title-bar">
        <div class="inv-title"><?= $docTitle ?></div>
        <div class="inv-number"><?= htmlspecialchars($invoice['invoice_number']) ?></div>
    </div>

    <!-- Separator -->
    <div class="inv-separator"></div>

    <!-- Dates Row -->
    <div class="inv-dates-row">
        <div class="inv-date-box">
            <span class="inv-date-label"><?= $lang('issue_date') ?></span>
            <span class="inv-date-value"><?= htmlspecialchars($invoice['issue_date']) ?></span>
        </div>
        <?php if (!empty($invoice['sale_date']) && $invoice['sale_date'] !== $invoice['issue_date']): ?>
        <div class="inv-date-box">
            <span class="inv-date-label"><?= $lang('sale_date') ?></span>
            <span class="inv-date-value"><?= htmlspecialchars($invoice['sale_date']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($invoice['due_date'])): ?>
        <div class="inv-date-box">
            <span class="inv-date-label"><?= $lang('due_date') ?></span>
            <span class="inv-date-value"><?= htmlspecialchars($invoice['due_date']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($invoice['ksef_reference_number'])): ?>
        <div class="inv-date-box" style="flex:2;">
            <span class="inv-date-label">Numer KSeF</span>
            <span class="inv-date-value" style="font-size:12px; font-family:monospace;"><?= htmlspecialchars($invoice['ksef_reference_number']) ?></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Seller / Buyer Cards -->
    <div class="inv-parties">
        <div class="inv-party-card">
            <div class="inv-party-label"><?= $lang('seller') ?></div>
            <div class="inv-party-name"><?= htmlspecialchars($invoice['seller_name']) ?></div>
            <div class="inv-party-detail">NIP: <?= htmlspecialchars($invoice['seller_nip']) ?></div>
            <?php if (!empty($invoice['seller_address'])): ?>
                <div class="inv-party-detail"><?= htmlspecialchars($invoice['seller_address']) ?></div>
            <?php endif; ?>
        </div>
        <div class="inv-party-card">
            <div class="inv-party-label"><?= $lang('buyer') ?></div>
            <div class="inv-party-name"><?= htmlspecialchars($invoice['buyer_name']) ?></div>
            <?php if (!empty($invoice['buyer_nip'])): ?>
                <div class="inv-party-detail">NIP: <?= htmlspecialchars($invoice['buyer_nip']) ?></div>
            <?php endif; ?>
            <?php if (!empty($invoice['buyer_address'])): ?>
                <div class="inv-party-detail"><?= htmlspecialchars($invoice['buyer_address']) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Line Items -->
    <?php
    $lineItems = $invoice['line_items'] ?? '[]';
    if (is_string($lineItems)) $lineItems = json_decode($lineItems, true) ?: [];
    ?>
    <?php if (!empty($lineItems)): ?>
    <div class="table-responsive" style="margin-bottom:24px;">
        <table class="inv-items-table">
            <thead>
                <tr>
                    <th style="width:40px;">Lp.</th>
                    <th><?= $lang('item_name') ?></th>
                    <th class="text-center"><?= $lang('quantity') ?></th>
                    <th class="text-center"><?= $lang('unit') ?></th>
                    <th class="text-right"><?= $lang('unit_price') ?></th>
                    <th class="text-center"><?= $lang('vat_rate') ?></th>
                    <th class="text-right"><?= $lang('net_amount') ?></th>
                    <th class="text-right"><?= $lang('vat_amount') ?></th>
                    <th class="text-right"><?= $lang('gross_amount') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lineItems as $i => $item): ?>
                <tr>
                    <td class="text-center"><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($item['name'] ?? '') ?></td>
                    <td class="text-center"><?= $item['quantity'] ?? 1 ?></td>
                    <td class="text-center"><?= htmlspecialchars($item['unit'] ?? 'szt.') ?></td>
                    <td class="text-right tabnum"><?= number_format((float)($item['unit_price'] ?? 0), 2, ',', ' ') ?></td>
                    <td class="text-center"><?= htmlspecialchars($item['vat_rate'] ?? '23') ?>%</td>
                    <td class="text-right tabnum"><?= number_format((float)($item['net'] ?? 0), 2, ',', ' ') ?></td>
                    <td class="text-right tabnum"><?= number_format((float)($item['vat'] ?? 0), 2, ',', ' ') ?></td>
                    <td class="text-right tabnum" style="font-weight:600;"><?= number_format((float)($item['gross'] ?? 0), 2, ',', ' ') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- VAT Summary + Totals -->
    <?php
    $vatDetails = $invoice['vat_details'] ?? '[]';
    if (is_string($vatDetails)) $vatDetails = json_decode($vatDetails, true) ?: [];
    ?>
    <div class="inv-totals-wrapper">
        <table class="inv-vat-table">
            <thead>
                <tr>
                    <th><?= $lang('vat_rate') ?></th>
                    <th class="text-right"><?= $lang('net_amount') ?></th>
                    <th class="text-right"><?= $lang('vat_amount') ?></th>
                    <th class="text-right"><?= $lang('gross_amount') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vatDetails as $vd):
                    $vdNet = (float)($vd['net'] ?? 0);
                    $vdVat = (float)($vd['vat'] ?? 0);
                ?>
                <tr>
                    <td class="text-center"><?= htmlspecialchars($vd['rate'] ?? '') ?>%</td>
                    <td class="text-right tabnum"><?= number_format($vdNet, 2, ',', ' ') ?></td>
                    <td class="text-right tabnum"><?= number_format($vdVat, 2, ',', ' ') ?></td>
                    <td class="text-right tabnum"><?= number_format($vdNet + $vdVat, 2, ',', ' ') ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="inv-vat-total-row">
                    <td><strong><?= $lang('total') ?></strong></td>
                    <td class="text-right tabnum"><strong><?= number_format((float)$invoice['net_amount'], 2, ',', ' ') ?></strong></td>
                    <td class="text-right tabnum"><strong><?= number_format((float)$invoice['vat_amount'], 2, ',', ' ') ?></strong></td>
                    <td class="text-right tabnum"><strong><?= number_format((float)$invoice['gross_amount'], 2, ',', ' ') ?></strong></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Exchange Rate Info (foreign currency invoices) -->
    <?php if (($invoice['currency'] ?? 'PLN') !== 'PLN' && !empty($invoice['exchange_rate'])): ?>
    <div class="form-card" style="padding:12px 16px; margin:12px 0; background:var(--gray-50); border:1px solid var(--gray-200); border-radius:8px;">
        <div style="font-weight:600; margin-bottom:6px; font-size:14px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            <?= $lang('exchange_rate_info') ?>
        </div>
        <div style="font-size:14px;">
            <?= $lang('nbp_rate') ?>: 1 <?= htmlspecialchars($invoice['currency']) ?> = <?= number_format((float)$invoice['exchange_rate'], 4, ',', ' ') ?> PLN
            <?php if (!empty($invoice['exchange_rate_table'])): ?>
                (<?= $lang('table') ?>: <?= htmlspecialchars($invoice['exchange_rate_table']) ?>
                <?php if (!empty($invoice['exchange_rate_date'])): ?>
                    <?= $lang('from_date') ?> <?= htmlspecialchars($invoice['exchange_rate_date']) ?>
                <?php endif; ?>)
            <?php endif; ?>
        </div>
        <?php if (!empty($invoice['vat_amount_pln'])): ?>
        <div style="font-size:14px; margin-top:4px;">
            <?= $lang('vat_in_pln') ?>: <strong><?= number_format((float)$invoice['vat_amount_pln'], 2, ',', ' ') ?> PLN</strong>
        </div>
        <?php endif; ?>
        <?php if (!empty($invoice['net_amount_pln'])): ?>
        <div style="font-size:14px; margin-top:2px;">
            <?= $lang('net_in_pln') ?>: <?= number_format((float)$invoice['net_amount_pln'], 2, ',', ' ') ?> PLN
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Grand Total -->
    <div class="inv-grand-total">
        <span class="inv-grand-total-label"><?= $lang('gross_amount') ?>:</span>
        <span class="inv-grand-total-amount"><?= number_format((float)$invoice['gross_amount'], 2, ',', ' ') ?> <?= htmlspecialchars($invoice['currency'] ?? 'PLN') ?></span>
        <?php if (($invoice['currency'] ?? 'PLN') !== 'PLN' && !empty($invoice['vat_amount_pln'])): ?>
        <div style="font-size:13px; color:var(--gray-500); margin-top:4px;">
            <?= $lang('including_vat') ?>: <?= number_format((float)$invoice['vat_amount_pln'], 2, ',', ' ') ?> PLN
        </div>
        <?php endif; ?>
    </div>

    <!-- Payment Info -->
    <div class="inv-payment-box">
        <div class="inv-payment-col">
            <div class="inv-payment-label"><?= $lang('payment_method') ?></div>
            <div class="inv-payment-value">
                <?php
                $methodKeys = ['przelew' => 'payment_transfer', 'gotowka' => 'payment_cash', 'karta' => 'payment_card', 'kompensata' => 'payment_compensation', 'barter' => 'payment_barter'];
                echo $lang($methodKeys[$invoice['payment_method']] ?? 'payment_transfer');
                ?>
            </div>
        </div>
        <?php if (!empty($invoice['bank_account_number'])): ?>
        <div class="inv-payment-col">
            <div class="inv-payment-label"><?= $lang('bank_account') ?></div>
            <div class="inv-payment-value"><?= htmlspecialchars($invoice['bank_name'] ?? '') ?></div>
            <div style="font-family:monospace; font-size:13px; margin-top:2px; letter-spacing:0.5px;"><?= htmlspecialchars($invoice['bank_account_number']) ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Notes -->
    <?php if (!empty($invoice['notes'])): ?>
    <div class="inv-notes-box">
        <div class="inv-notes-label"><?= $lang('notes') ?></div>
        <div><?= nl2br(htmlspecialchars($invoice['notes'])) ?></div>
    </div>
    <?php endif; ?>

    <!-- Correction info — before/after comparison -->
    <?php if ($isCorrection): ?>
        <?php
        $originalItems = $invoice['original_line_items'] ?? null;
        if (is_string($originalItems)) $originalItems = json_decode($originalItems, true) ?: [];
        ?>
        <?php if (!empty($originalItems)): ?>
        <!-- Stan przed korektą -->
        <div style="margin:0 30px 8px; padding:10px 18px; background:#fef2f2; border:1px solid #fca5a5; border-radius:8px 8px 0 0;">
            <div style="font-size:11px; text-transform:uppercase; color:#991b1b; font-weight:700; letter-spacing:0.5px;">Stan przed korektą</div>
        </div>
        <div class="table-responsive" style="margin:0 30px 16px;">
            <table class="inv-items-table" style="margin:0; width:100%;">
                <thead>
                    <tr>
                        <th style="width:40px;">Lp.</th>
                        <th><?= $lang('item_name') ?></th>
                        <th class="text-center"><?= $lang('quantity') ?></th>
                        <th class="text-center"><?= $lang('unit') ?></th>
                        <th class="text-right"><?= $lang('unit_price') ?></th>
                        <th class="text-center"><?= $lang('vat_rate') ?></th>
                        <th class="text-right"><?= $lang('net_amount') ?></th>
                        <th class="text-right"><?= $lang('vat_amount') ?></th>
                        <th class="text-right"><?= $lang('gross_amount') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($originalItems as $i => $item): ?>
                    <tr style="background:#fef2f2;">
                        <td class="text-center"><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($item['name'] ?? '') ?></td>
                        <td class="text-center"><?= $item['quantity'] ?? 1 ?></td>
                        <td class="text-center"><?= htmlspecialchars($item['unit'] ?? 'szt.') ?></td>
                        <td class="text-right tabnum"><?= number_format((float)($item['unit_price'] ?? 0), 2, ',', ' ') ?></td>
                        <td class="text-center"><?= htmlspecialchars($item['vat_rate'] ?? '23') ?>%</td>
                        <td class="text-right tabnum"><?= number_format((float)($item['net'] ?? 0), 2, ',', ' ') ?></td>
                        <td class="text-right tabnum"><?= number_format((float)($item['vat'] ?? 0), 2, ',', ' ') ?></td>
                        <td class="text-right tabnum" style="font-weight:600;"><?= number_format((float)($item['gross'] ?? 0), 2, ',', ' ') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="background:#fef2f2; font-weight:700; border-top:2px solid #fca5a5;">
                        <td colspan="6" class="text-right">Razem przed:</td>
                        <td class="text-right tabnum"><?= number_format((float)($invoice['original_net_amount'] ?? 0), 2, ',', ' ') ?></td>
                        <td class="text-right tabnum"><?= number_format((float)($invoice['original_vat_amount'] ?? 0), 2, ',', ' ') ?></td>
                        <td class="text-right tabnum"><?= number_format((float)($invoice['original_gross_amount'] ?? 0), 2, ',', ' ') ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Stan po korekcie -->
        <div style="margin:0 30px 8px; padding:10px 18px; background:#f0fdf4; border:1px solid #86efac; border-radius:8px 8px 0 0;">
            <div style="font-size:11px; text-transform:uppercase; color:#166534; font-weight:700; letter-spacing:0.5px;">Stan po korekcie</div>
        </div>
        <div class="table-responsive" style="margin:0 30px 16px;">
            <table class="inv-items-table" style="margin:0; width:100%;">
                <thead>
                    <tr>
                        <th style="width:40px;">Lp.</th>
                        <th><?= $lang('item_name') ?></th>
                        <th class="text-center"><?= $lang('quantity') ?></th>
                        <th class="text-center"><?= $lang('unit') ?></th>
                        <th class="text-right"><?= $lang('unit_price') ?></th>
                        <th class="text-center"><?= $lang('vat_rate') ?></th>
                        <th class="text-right"><?= $lang('net_amount') ?></th>
                        <th class="text-right"><?= $lang('vat_amount') ?></th>
                        <th class="text-right"><?= $lang('gross_amount') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lineItems as $i => $item): ?>
                    <tr style="background:#f0fdf4;">
                        <td class="text-center"><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($item['name'] ?? '') ?></td>
                        <td class="text-center"><?= $item['quantity'] ?? 1 ?></td>
                        <td class="text-center"><?= htmlspecialchars($item['unit'] ?? 'szt.') ?></td>
                        <td class="text-right tabnum"><?= number_format((float)($item['unit_price'] ?? 0), 2, ',', ' ') ?></td>
                        <td class="text-center"><?= htmlspecialchars($item['vat_rate'] ?? '23') ?>%</td>
                        <td class="text-right tabnum"><?= number_format((float)($item['net'] ?? 0), 2, ',', ' ') ?></td>
                        <td class="text-right tabnum"><?= number_format((float)($item['vat'] ?? 0), 2, ',', ' ') ?></td>
                        <td class="text-right tabnum" style="font-weight:600;"><?= number_format((float)($item['gross'] ?? 0), 2, ',', ' ') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="background:#f0fdf4; font-weight:700; border-top:2px solid #86efac;">
                        <td colspan="6" class="text-right">Razem po:</td>
                        <td class="text-right tabnum"><?= number_format((float)$invoice['net_amount'], 2, ',', ' ') ?></td>
                        <td class="text-right tabnum"><?= number_format((float)$invoice['vat_amount'], 2, ',', ' ') ?></td>
                        <td class="text-right tabnum"><?= number_format((float)$invoice['gross_amount'], 2, ',', ' ') ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Różnica -->
        <?php
        $origNet = (float)($invoice['original_net_amount'] ?? 0);
        $origVat = (float)($invoice['original_vat_amount'] ?? 0);
        $origGross = (float)($invoice['original_gross_amount'] ?? 0);
        $diffNet = (float)$invoice['net_amount'] - $origNet;
        $diffVat = (float)$invoice['vat_amount'] - $origVat;
        $diffGross = (float)$invoice['gross_amount'] - $origGross;
        $diffColor = $diffGross < 0 ? '#dc2626' : ($diffGross > 0 ? '#16a34a' : 'var(--gray-700)');
        ?>
        <div style="margin:0 30px 20px; padding:12px 18px; background:#fef9c3; border:1px solid #fde68a; border-radius:8px; display:flex; justify-content:flex-end; gap:24px; font-weight:700; font-size:14px;">
            <span style="color:#92400e;">RÓŻNICA:</span>
            <span style="color:<?= $diffColor ?>;">Netto: <?= ($diffNet >= 0 ? '+' : '') . number_format($diffNet, 2, ',', ' ') ?></span>
            <span style="color:<?= $diffColor ?>;">VAT: <?= ($diffVat >= 0 ? '+' : '') . number_format($diffVat, 2, ',', ' ') ?></span>
            <span style="color:<?= $diffColor ?>;">Brutto: <?= ($diffGross >= 0 ? '+' : '') . number_format($diffGross, 2, ',', ' ') ?></span>
        </div>
        <?php endif; ?>

        <?php if (!empty($invoice['correction_reason'])): ?>
        <div class="inv-correction-box">
            <div class="inv-notes-label"><?= $lang('correction_reason') ?></div>
            <div><?= htmlspecialchars($invoice['correction_reason']) ?></div>
            <?php if (!empty($invoice['corrected_invoice_id'])): ?>
                <div style="margin-top:6px; font-size:13px;">
                    <?= $lang('corrected_invoice') ?>:
                    <a href="/client/sales/<?= $invoice['corrected_invoice_id'] ?>" style="color:var(--primary); font-weight:600;">#<?= $invoice['corrected_invoice_id'] ?></a>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- KSeF Error -->
    <?php if (!empty($invoice['ksef_error'])): ?>
    <div class="alert alert-error" style="margin-top:12px;">
        <strong>KSeF:</strong> <?= htmlspecialchars($invoice['ksef_error']) ?>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <div class="inv-footer">
        Wygenerowano w BiLLU &middot; <?= date('Y-m-d H:i') ?>
    </div>
</div>

<style>
/* Invoice preview professional styling */
.inv-preview {
    padding: 0 !important;
    max-width: none;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 4px 12px rgba(0,0,0,0.04);
    border: 1px solid var(--gray-200, #e5e7eb);
    overflow: hidden;
}

.inv-title-bar {
    background: var(--primary, #2563eb);
    color: #fff;
    padding: 24px 30px 20px;
}
.inv-title {
    font-size: 22px;
    font-weight: 700;
    letter-spacing: 1px;
    margin-bottom: 4px;
}
.inv-number {
    font-size: 15px;
    opacity: 0.85;
    font-weight: 500;
}

.inv-separator {
    height: 3px;
    background: linear-gradient(90deg, var(--primary, #2563eb) 0%, var(--primary, #2563eb) 40%, transparent 100%);
}

/* Dates */
.inv-dates-row {
    display: flex;
    gap: 16px;
    padding: 20px 30px;
    flex-wrap: wrap;
    background: var(--gray-50, #f9fafb);
    border-bottom: 1px solid var(--gray-200, #e5e7eb);
}
.inv-date-box {
    flex: 1;
    min-width: 120px;
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.inv-date-label {
    font-size: 11px;
    text-transform: uppercase;
    color: var(--gray-500, #6b7280);
    font-weight: 600;
    letter-spacing: 0.5px;
}
.inv-date-value {
    font-size: 14px;
    font-weight: 600;
    color: var(--gray-800, #1f2937);
}

/* Parties */
.inv-parties {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    padding: 24px 30px;
}
.inv-party-card {
    padding: 16px 20px;
    border: 1px solid var(--gray-200, #e5e7eb);
    border-radius: 8px;
    background: #fff;
}
.inv-party-label {
    font-size: 11px;
    text-transform: uppercase;
    color: var(--gray-500, #6b7280);
    font-weight: 600;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
    padding-bottom: 6px;
    border-bottom: 1px solid var(--gray-100, #f3f4f6);
}
.inv-party-name {
    font-size: 15px;
    font-weight: 700;
    color: var(--gray-900, #111827);
    margin-bottom: 4px;
}
.inv-party-detail {
    font-size: 13px;
    color: var(--gray-600, #4b5563);
    line-height: 1.5;
}

/* Line items table */
.inv-items-table {
    width: 100%;
    border-collapse: collapse;
    margin: 0 30px;
    width: calc(100% - 60px);
}
.inv-items-table thead th {
    background: var(--gray-100, #f3f4f6);
    color: var(--gray-700, #374151);
    font-size: 12px;
    font-weight: 600;
    padding: 10px 12px;
    border-bottom: 2px solid var(--gray-300, #d1d5db);
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.inv-items-table tbody td {
    padding: 10px 12px;
    font-size: 13px;
    border-bottom: 1px solid var(--gray-100, #f3f4f6);
    color: var(--gray-700, #374151);
}
.inv-items-table tbody tr:nth-child(even) {
    background: var(--gray-50, #f9fafb);
}
.inv-items-table tbody tr:hover {
    background: #eff6ff;
}

.text-center { text-align: center; }
.text-right { text-align: right; }
.tabnum { font-variant-numeric: tabular-nums; }

/* VAT summary */
.inv-totals-wrapper {
    display: flex;
    justify-content: flex-end;
    padding: 0 30px 20px;
}
.inv-vat-table {
    border-collapse: collapse;
    min-width: 380px;
}
.inv-vat-table thead th {
    background: var(--gray-100, #f3f4f6);
    color: var(--gray-700, #374151);
    font-size: 11px;
    font-weight: 600;
    padding: 8px 14px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    border-bottom: 1px solid var(--gray-300, #d1d5db);
}
.inv-vat-table tbody td {
    padding: 6px 14px;
    font-size: 13px;
    border-bottom: 1px solid var(--gray-100, #f3f4f6);
    color: var(--gray-700, #374151);
}
.inv-vat-total-row {
    border-top: 2px solid var(--gray-400, #9ca3af);
}
.inv-vat-total-row td {
    padding-top: 10px !important;
    font-size: 14px !important;
}

/* Grand total */
.inv-grand-total {
    margin: 0 30px 24px;
    padding: 16px 24px;
    background: var(--primary, #2563eb);
    color: #fff;
    border-radius: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.inv-grand-total-label {
    font-size: 14px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.inv-grand-total-amount {
    font-size: 22px;
    font-weight: 700;
    letter-spacing: 0.5px;
}

/* Payment box */
.inv-payment-box {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin: 0 30px 20px;
    padding: 16px 20px;
    background: var(--gray-50, #f9fafb);
    border: 1px solid var(--gray-200, #e5e7eb);
    border-radius: 8px;
}
.inv-payment-label {
    font-size: 11px;
    text-transform: uppercase;
    color: var(--gray-500, #6b7280);
    font-weight: 600;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}
.inv-payment-value {
    font-size: 14px;
    font-weight: 600;
    color: var(--gray-800, #1f2937);
}

/* Notes */
.inv-notes-box {
    margin: 0 30px 16px;
    padding: 14px 18px;
    background: var(--gray-50, #f9fafb);
    border: 1px solid var(--gray-200, #e5e7eb);
    border-radius: 8px;
    font-size: 13px;
    color: var(--gray-700, #374151);
}
.inv-notes-label {
    font-size: 11px;
    text-transform: uppercase;
    color: var(--gray-500, #6b7280);
    font-weight: 600;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
}

/* Correction box */
.inv-correction-box {
    margin: 0 30px 16px;
    padding: 14px 18px;
    background: #fef3c7;
    border: 1px solid #fde68a;
    border-radius: 8px;
    font-size: 13px;
    color: #92400e;
}

/* Footer */
.inv-footer {
    text-align: center;
    padding: 14px 30px;
    background: var(--gray-50, #f9fafb);
    border-top: 1px solid var(--gray-200, #e5e7eb);
    font-size: 12px;
    color: var(--gray-400, #9ca3af);
}

/* Badge styles */
.badge-info { background: var(--blue-100, #dbeafe); color: var(--blue-700, #1d4ed8); }
.badge-warning { background: var(--yellow-100, #fef3c7); color: var(--yellow-700, #a16207); }
.badge-error { background: var(--red-100, #fee2e2); color: var(--red-700, #b91c1c); }

/* Responsive */
@media (max-width: 768px) {
    .inv-parties, .inv-payment-box { grid-template-columns: 1fr; }
    .inv-dates-row { flex-direction: column; }
    .inv-title-bar, .inv-dates-row { padding-left: 16px; padding-right: 16px; }
    .inv-items-table { margin: 0 16px; width: calc(100% - 32px); }
    .inv-totals-wrapper, .inv-grand-total, .inv-payment-box, .inv-notes-box, .inv-correction-box { margin-left: 16px; margin-right: 16px; }
    .inv-vat-table { min-width: auto; width: 100%; }
    .inv-grand-total { flex-direction: column; gap: 4px; text-align: center; }
    .inv-grand-total-amount { font-size: 20px; }
}

/* Print-friendly */
@media print {
    .section-header, .form-card:first-of-type { display: none !important; }
    .inv-preview { box-shadow: none; border: none; }
    .inv-title-bar { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .inv-grand-total { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>

<script>
function sendToKsef(invoiceId) {
    var btn = document.getElementById('ksef-send-btn');
    if (btn) { btn.disabled = true; btn.textContent = 'Wysylanie...'; }

    var formData = new FormData();
    formData.append('csrf_token', '<?= htmlspecialchars(\App\Core\Session::get('csrf_token') ?? '') ?>');

    fetch('/client/sales/' + invoiceId + '/send-ksef', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) {
                alert('Blad: ' + data.error);
                if (btn) { btn.disabled = false; btn.textContent = '<?= $lang('send_to_ksef') ?>'; }
                return;
            }
            // Store job in global tracker and start polling
            if (window._ksefTracker) {
                window._ksefTracker.addJob(data.job_id, '<?= htmlspecialchars($invoice['invoice_number'] ?? '') ?>');
            } else {
                pollSingleJob(data.job_id);
            }
        })
        .catch(function(err) {
            alert('Blad polaczenia: ' + err.message);
            if (btn) { btn.disabled = false; btn.textContent = '<?= $lang('send_to_ksef') ?>'; }
        });
}

function pollSingleJob(jobId) {
    var badge = document.getElementById('ksef-status-badge');
    if (!badge) {
        badge = document.createElement('span');
        badge.id = 'ksef-status-badge';
        badge.className = 'badge badge-warning';
        badge.style.cssText = 'font-size:14px; padding:6px 16px;';
        var btns = document.querySelector('div[style*="display:flex"][style*="gap:8px"]');
        if (btns) btns.appendChild(badge);
    }
    badge.textContent = 'KSeF: wysylanie...';

    var pollCount = 0;
    var interval = setInterval(function() {
        if (++pollCount > 90) {
            clearInterval(interval);
            badge.textContent = 'KSeF: timeout';
            badge.className = 'badge badge-error';
            return;
        }
        fetch('/client/ksef-send-status?job_id=' + jobId)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.status === 'running') {
                    badge.textContent = 'KSeF: ' + (data.message || 'przetwarzanie...');
                } else if (data.status === 'completed') {
                    clearInterval(interval);
                    badge.textContent = 'KSeF: wyslano!';
                    badge.className = 'badge badge-info';
                    setTimeout(function() { window.location.reload(); }, 1500);
                } else if (data.status === 'error') {
                    clearInterval(interval);
                    badge.textContent = 'KSeF: ' + (data.message || 'blad');
                    badge.className = 'badge badge-error';
                    var btn = document.getElementById('ksef-send-btn');
                    if (btn) { btn.disabled = false; btn.textContent = '<?= $lang('send_to_ksef') ?>'; }
                }
            })
            .catch(function() {});
    }, 2000);
}
</script>
