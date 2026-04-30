<div class="section-header">
    <h1><?= $lang('invoices') ?> - <?= sprintf('%02d/%04d', $batch['period_month'], $batch['period_year']) ?></h1>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <?php if (!empty($ksefEnabled) && !$batch['is_finalized']): ?>
        <button type="button" class="btn btn-primary" id="ksef-import-batch-btn" onclick="importKsefForBatch()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            <span id="ksef-import-batch-label"><?= $lang('download_from_ksef') ?></span>
        </button>
        <?php endif; ?>
        <a href="/client" class="btn btn-secondary"><?= $lang('back') ?></a>
    </div>
</div>

<div class="batch-info">
    <p><strong><?= $lang('deadline') ?>:</strong> <?= $batch['verification_deadline'] ?></p>
    <p><strong><?= $lang('status') ?>:</strong>
        <?php if ($batch['is_finalized']): ?>
            <span class="badge badge-success"><?= $lang('finalized') ?></span>
        <?php else: ?>
            <span class="badge badge-warning"><?= $lang('in_progress') ?></span>
        <?php endif; ?>
    </p>
</div>

<?php
$statusCounts = ['pending' => 0, 'accepted' => 0, 'rejected' => 0];
foreach ($stats as $s) {
    $statusCounts[$s['status']] = $s['cnt'];
}
$pendingInvoices = array_filter($invoices, fn($inv) => $inv['status'] === 'pending');
?>

<?php if (!$batch['is_finalized'] && $statusCounts['pending'] === 0 && count($invoices) > 0): ?>
<div class="alert alert-success" style="margin-bottom:12px;">
    <?= $lang('all_verified_waiting_deadline') . $batch['verification_deadline'] . '.' ?>
</div>
<?php endif; ?>

<form method="GET" action="/client/invoices/<?= $batch['id'] ?>" class="form-card form-inline" style="margin-bottom: 16px;">
    <div class="form-row">
        <div class="form-group">
            <label class="form-label"><?= $lang('status') ?></label>
            <select name="status" class="form-input">
                <option value=""><?= $lang('all_statuses') ?></option>
                <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>><?= $lang('pending') ?></option>
                <option value="accepted" <?= ($filters['status'] ?? '') === 'accepted' ? 'selected' : '' ?>><?= $lang('accepted') ?></option>
                <option value="rejected" <?= ($filters['status'] ?? '') === 'rejected' ? 'selected' : '' ?>><?= $lang('rejected') ?></option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label"><?= $lang('search') ?></label>
            <input type="text" name="search" class="form-input" placeholder="<?= $lang('search_placeholder') ?>" value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
        </div>
        <div class="form-group">
            <button type="submit" class="btn btn-primary"><?= $lang('filter') ?></button>
            <a href="/client/invoices/<?= $batch['id'] ?>" class="btn btn-secondary"><?= $lang('clear_filters') ?></a>
            <button type="button" class="btn" onclick="ksefBackfillPurchase()" title="Uzupełnij brakujące numery KSeF z API">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M1 4v6h6"/><path d="M3.51 15a9 9 0 105.64-12.36L3 9"/></svg>
                Uzupełnij nr KSeF
            </button>
            <button type="button" class="btn" onclick="whitelistRecheck()" title="Sprawdź rachunki bankowe na białej liście VAT" style="background:#fef2f2;border-color:#dc2626;color:#dc2626;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Weryfikuj białą listę
            </button>
        </div>
    </div>
</form>

<?php if (!$batch['is_finalized'] && count($pendingInvoices) > 0): ?>
<div class="bulk-actions">
    <form method="POST" action="/client/invoices/bulk" id="bulk-form">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="batch_id" value="<?= $batch['id'] ?>">
        <input type="hidden" name="bulk_action" id="bulk-action-field" value="">
        <input type="hidden" name="bulk_comment" id="bulk-comment-field" value="">
        <div class="bulk-bar">
            <button type="button" class="btn btn-success btn-sm" onclick="submitBulkAccept()"><?= $lang('accept_selected') ?></button>
            <button type="button" class="btn btn-danger btn-sm" onclick="showBulkRejectModal()"><?= $lang('reject_selected') ?></button>
        </div>
<?php endif; ?>

<?php if (!empty($client['has_cost_centers']) && !empty($costCenters)): ?>
<div class="bulk-mpk-bar" id="bulk-mpk-bar" style="display:none;padding:8px 12px;background:#e3f2fd;border-radius:6px;margin:8px 0;align-items:center;gap:8px;">
    <form method="POST" action="/client/invoices/bulk-mpk" id="bulk-mpk-form" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="batch_id" value="<?= $batch['id'] ?>">
        <span id="mpk-selected-count" style="font-weight:600;"><?= $lang('selected') ?>: 0</span>
        <select name="cost_center_id" class="form-input" style="width:auto;min-width:200px;" required>
            <option value="">-- MPK --</option>
            <?php foreach ($costCenters as $cc): ?>
                <option value="<?= $cc['id'] ?>"><?= htmlspecialchars($cc['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary btn-sm"><?= $lang('bulk_mpk_assign') ?></button>
    </form>
</div>
<?php endif; ?>

<table class="table" id="invoices-table">
    <thead>
        <tr>
            <th style="width:30px; text-align:center;"><input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)" title="Zaznacz wszystkie"></th>
            <th>#</th>
            <th><?= $lang('invoice_number') ?></th>
            <th><?= $lang('issue_date') ?></th>
            <th><?= $lang('seller') ?></th>
            <th class="hide-mobile">NIP</th>
            <th><?= $lang('net_amount') ?></th>
            <th class="hide-mobile">VAT</th>
            <th><?= $lang('gross_amount') ?></th>
            <th><?= $lang('status') ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($invoices as $i => $inv): ?>
        <?php
        $isPaid = (int) ($inv['is_paid'] ?? 0);
        $rowClass = ($inv['status'] === 'accepted' && $isPaid === 0) ? 'status-accepted-unpaid'
            : (($inv['status'] === 'accepted' && $isPaid === 2) ? 'status-accepted-transfer' : 'status-' . $inv['status']);
        ?>
        <tr class="invoice-row <?= $rowClass ?>" style="cursor:pointer;" onclick="onRowClick(event, <?= $inv['id'] ?>)" data-id="<?= $inv['id'] ?>">
                <td style="text-align:center;">
                    <input type="checkbox" class="row-check" value="<?= $inv['id'] ?>" data-status="<?= $inv['status'] ?>" onchange="updateSelections()">
                </td>
            <td><?= $i + 1 ?></td>
            <td>
                <strong><?= htmlspecialchars($inv['invoice_number']) ?></strong>
                <?php if (($inv['invoice_type'] ?? 'VAT') === 'KOR'): ?>
                    <span class="badge" style="background:#fef3c7;color:#92400e;font-size:10px;padding:1px 6px;margin-left:4px;">KOREKTA</span>
                <?php endif; ?>
                <?php if (!empty($inv['ksef_reference_number'])): ?>
                    <br><small style="color:var(--info); font-size:11px;" title="<?= htmlspecialchars($inv['ksef_reference_number']) ?>">KSeF: <?= htmlspecialchars($inv['ksef_reference_number']) ?></small>
                <?php endif; ?>
                <?php if (!empty($inv['corrected_invoice_number'])): ?>
                    <br><small style="color:var(--gray-500); font-size:11px;">Koryguje: <?= htmlspecialchars($inv['corrected_invoice_number']) ?></small>
                <?php endif; ?>
                <?php $ccnt = $commentCounts[$inv['id']] ?? 0; if ($ccnt > 0): ?>
                    <br><small style="color:var(--gray-500); font-size:11px;">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-1px;"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                        <?= $ccnt ?>
                    </small>
                <?php endif; ?>
            </td>
            <td><?= $inv['issue_date'] ?></td>
            <td><span class="text-truncate" title="<?= htmlspecialchars($inv['seller_name']) ?>"><?= htmlspecialchars($inv['seller_name']) ?></span></td>
            <td class="hide-mobile"><?= htmlspecialchars($inv['seller_nip']) ?></td>
            <td class="text-right"><?= number_format((float)$inv['net_amount'], 2, ',', ' ') ?> <?= $inv['currency'] ?></td>
            <td class="text-right hide-mobile"><?= number_format((float)$inv['vat_amount'], 2, ',', ' ') ?></td>
            <td class="text-right"><strong><?= number_format((float)$inv['gross_amount'], 2, ',', ' ') ?></strong></td>
            <td id="status-cell-<?= $inv['id'] ?>">
                <?php if ($inv['status'] === 'accepted'): ?>
                    <?php if ($isPaid === 2): ?>
                        <span class="badge" style="background:#dbeafe;color:#2563eb;"><?= $lang('transfer_in_progress') ?></span>
                        <button class="btn btn-sm btn-success" style="margin-top:4px;font-size:11px;padding:2px 8px;" onclick="event.stopPropagation(); markAsPaid(<?= $inv['id'] ?>, this)">Opłacona</button>
                    <?php elseif ($isPaid === 0): ?>
                        <span class="badge badge-warning"><?= $lang('not_paid') ?></span>
                        <button class="btn btn-sm btn-success" style="margin-top:4px;font-size:11px;padding:2px 8px;" onclick="event.stopPropagation(); markAsPaid(<?= $inv['id'] ?>, this)">Opłacona</button>
                    <?php else: ?>
                        <span class="badge badge-success"><?= $lang('accepted') ?></span>
                    <?php endif; ?>
                    <?php if (!empty($inv['cost_center'])): ?>
                        <br><small class="text-muted">MPK: <?= htmlspecialchars($inv['cost_center']) ?></small>
                    <?php endif; ?>
                <?php elseif ($inv['status'] === 'rejected'): ?>
                    <span class="badge badge-error"><?= $lang('rejected') ?></span>
                    <?php if (!empty($inv['comment'])): ?>
                        <br><small class="text-muted"><?= htmlspecialchars($inv['comment']) ?></small>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="badge badge-warning"><?= $lang('pending') ?></span>
                    <?php if (!empty($inv['whitelist_failed'])): ?>
                        <br><span class="badge badge-whitelist-fail" style="background:#dc2626;color:#fff;font-size:10px;margin-top:2px;">BRAK NA BIAŁEJ LIŚCIE VAT</span>
                    <?php endif; ?>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr class="table-footer">
            <td colspan="6" class="text-right"><strong><?= $lang('total') ?>:</strong></td>
            <td class="text-right"><strong><?= number_format(array_sum(array_column($invoices, 'net_amount')), 2, ',', ' ') ?></strong></td>
            <td class="text-right"><strong><?= number_format(array_sum(array_column($invoices, 'vat_amount')), 2, ',', ' ') ?></strong></td>
            <td class="text-right"><strong><?= number_format(array_sum(array_column($invoices, 'gross_amount')), 2, ',', ' ') ?></strong></td>
            <td></td>
        </tr>
    </tfoot>
</table>

<?php if (!$batch['is_finalized'] && count($pendingInvoices) > 0): ?>
    </form>
</div>
<?php endif; ?>

<?php if ($batch['is_finalized']): ?>
<div class="section">
    <h3><?= $lang('download_reports') ?></h3>
    <div class="action-buttons">
        <a href="/client/reports/rejected/<?= $batch['id'] ?>?type=pdf" class="btn btn-secondary"><?= $lang('download_rejected_pdf') ?></a>
        <a href="/client/reports/rejected/<?= $batch['id'] ?>?type=xls" class="btn btn-secondary"><?= $lang('download_rejected_xls') ?></a>
    </div>
</div>
<?php endif; ?>

<!-- PDF Export Bar -->
<?php if (!empty($invoices)): ?>
<div class="form-card" style="padding:12px 16px; margin-bottom:20px;">
    <div class="export-bar-inner" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
        <span style="font-weight:600; font-size:14px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px; margin-right:4px;"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Eksport PDF <small id="pdf-selection-info" style="font-weight:400; color:var(--gray-500);"></small>
        </span>
        <div style="display:flex; gap:6px; flex-wrap:wrap;">
            <form method="POST" action="/client/invoices/bulk-pdf" style="display:inline;" id="pdf-bulk-vertical-form" onsubmit="updatePdfIds()">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="layout" value="vertical">
                <input type="hidden" name="invoice_ids" id="pdf-bulk-vertical-ids" value="<?= htmlspecialchars(json_encode(array_column($invoices, 'id'))) ?>">
                <input type="hidden" name="return_url" value="/client/invoices/<?= $batch['id'] ?>">
                <button type="submit" class="btn btn-sm">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    PDF - pełna wizualizacja
                </button>
            </form>
            <form method="POST" action="/client/invoices/bulk-pdf" style="display:inline;" id="pdf-bulk-horizontal-form" onsubmit="updatePdfIds()">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="layout" value="horizontal">
                <input type="hidden" name="invoice_ids" id="pdf-bulk-horizontal-ids" value="<?= htmlspecialchars(json_encode(array_column($invoices, 'id'))) ?>">
                <input type="hidden" name="return_url" value="/client/invoices/<?= $batch['id'] ?>">
                <button type="submit" class="btn btn-sm">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                    PDF - zestawienie tabelaryczne
                </button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
// Bank export button — show if there are unpaid accepted invoices
$unpaidAccepted = array_filter($invoices, fn($inv) => $inv['status'] === 'accepted' && (int)($inv['is_paid'] ?? 0) !== 1);
if (!empty($unpaidAccepted)):
?>
<div class="form-card" style="padding:12px 16px; margin-bottom:20px;">
    <div class="export-bar-inner" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
        <span style="font-weight:600; font-size:14px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px; margin-right:4px;"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
            Eksport przelewów bankowych
        </span>
        <a href="/client/bank-export/<?= $batch['id'] ?>" class="btn btn-sm btn-primary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px; vertical-align:-2px;"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Generuj plik Elixir-O (<?= count($unpaidAccepted) ?> nieopłaconych)
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Invoice Detail Modal -->
<div id="invoice-detail-modal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width:800px;max-height:90vh;overflow-y:auto;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <h3 id="detail-title" style="margin:0;"></h3>
            <div style="display:flex;gap:6px;align-items:center;">
                <button type="button" class="btn btn-sm" id="detail-preview-btn" onclick="openVisualization()" style="min-width:auto;padding:4px 10px;" title="Podgląd faktury">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    Podgląd
                </button>
                <button type="button" class="btn btn-sm" id="detail-pdf-btn" onclick="downloadInvoicePdf()" style="min-width:auto;padding:4px 10px;" title="Pobierz PDF">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    PDF
                </button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeDetailModal()" style="min-width:auto;padding:4px 10px;">&times;</button>
            </div>
        </div>

        <div id="detail-ksef" style="display:none;margin-bottom:8px;">
            <small style="color:var(--info);">KSeF: <span id="detail-ksef-number"></span></small>
        </div>

        <div id="detail-correction-info" style="display:none;margin-bottom:8px;padding:8px 12px;background:#fef3c7;border:1px solid #fde68a;border-radius:6px;font-size:0.9em;">
            <strong style="color:#92400e;">FAKTURA KORYGUJĄCA</strong>
            <div id="detail-corrected-invoice" style="margin-top:4px;color:#92400e;"></div>
            <div id="detail-correction-reason" style="margin-top:4px;color:#92400e;"></div>
        </div>

        <div class="detail-dates-grid" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:12px;font-size:0.9em;">
            <div><strong><?= $lang('issue_date_label') ?>:</strong><br><span id="detail-issue-date"></span></div>
            <div><strong><?= $lang('sale_date_label') ?>:</strong><br><span id="detail-sale-date"></span></div>
            <div><strong><?= $lang('due_date_label') ?>:</strong><br><span id="detail-due-date"></span></div>
        </div>

        <div class="detail-parties-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;font-size:0.9em;">
            <div style="background:var(--gray-50);padding:8px;border-radius:6px;">
                <strong>Sprzedawca</strong><br>
                <span id="detail-seller-name"></span><br>
                <small>NIP: <span id="detail-seller-nip"></span></small><br>
                <small id="detail-seller-address"></small>
            </div>
            <div style="background:var(--gray-50);padding:8px;border-radius:6px;">
                <strong>Nabywca</strong><br>
                <span id="detail-buyer-name"></span><br>
                <small>NIP: <span id="detail-buyer-nip"></span></small><br>
                <small id="detail-buyer-address"></small>
            </div>
        </div>

        <div class="detail-amounts-grid" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:12px;text-align:center;">
            <div style="background:var(--gray-50);padding:8px;border-radius:6px;">
                <small>Netto</small><br><strong id="detail-net"></strong>
            </div>
            <div style="background:var(--gray-50);padding:8px;border-radius:6px;">
                <small>VAT</small><br><strong id="detail-vat"></strong>
            </div>
            <div style="background:var(--gray-50);padding:8px;border-radius:6px;">
                <small>Brutto</small><br><strong id="detail-gross"></strong>
            </div>
        </div>

        <div id="detail-line-items-section" style="margin-bottom:12px;">
            <strong><?= $lang('invoice_line_items') ?>:</strong>
            <table class="table" style="font-size:0.85em;margin-top:4px;">
                <thead><tr><th>#</th><th>Nazwa</th><th>Ilość</th><th>Jedn.</th><th>Cena netto</th><th>Wartość netto</th><th>Stawka VAT</th></tr></thead>
                <tbody id="detail-line-items"></tbody>
            </table>
        </div>

        <div id="detail-notes-section" class="info-box-warning" style="display:none;margin-bottom:12px;padding:8px;border-radius:6px;font-size:0.9em;">
            <strong>Uwagi:</strong> <span id="detail-notes"></span>
        </div>

        <div style="margin-bottom:12px;">
            <strong>Status:</strong> <span id="detail-status-badge"></span>
            <span id="detail-mpk-info" style="display:none;"><br><small class="text-muted">MPK: <span id="detail-mpk-name"></span></small></span>
            <span id="detail-reject-reason" style="display:none;"><br><small class="text-muted">Powód odrzucenia: <span id="detail-reject-text"></span></small></span>
        </div>

        <div id="detail-actions" style="display:none;margin-bottom:12px;padding:12px;background:var(--gray-50);border-radius:6px;">
            <div id="detail-mpk-group" style="display:none;margin-bottom:8px;">
                <label class="form-label"><?= $lang('cost_center') ?></label>
                <select id="detail-cost-center" class="form-input" style="width:auto;min-width:200px;">
                    <option value="">-- <?= $lang('select_cost_center') ?> --</option>
                    <?php if (!empty($costCenters)): foreach ($costCenters as $cc): ?>
                        <option value="<?= $cc['id'] ?>"><?= htmlspecialchars($cc['name']) ?></option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:8px;">
                <label class="form-label"><?= $lang('comment') ?></label>
                <textarea id="detail-comment" class="form-input" rows="2" placeholder="Komentarz (wymagany przy odrzuceniu)"></textarea>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button type="button" class="btn btn-success" onclick="detailAccept()">Zaakceptuj</button>
                <button type="button" class="btn btn-danger" onclick="detailReject()">Odrzuć</button>
            </div>
        </div>

        <div id="detail-paid-actions" style="display:none;margin-bottom:12px;padding:12px;background:var(--gray-50);border-radius:6px;">
            <div style="display:flex;gap:8px;align-items:center;">
                <button type="button" class="btn btn-success" id="detail-paid-btn" onclick="detailTogglePaid()"><?= $lang('mark_as_paid') ?></button>
                <small style="color:var(--gray-500);">Opłacone faktury nie pojawią się w eksporcie przelewów bankowych</small>
            </div>
        </div>

        <div style="margin-bottom:8px;">
            <strong>Komentarze:</strong>
            <div id="detail-comments-thread" style="max-height:200px;overflow-y:auto;margin:8px 0;"></div>
            <form onsubmit="detailSubmitComment(event); return false;" style="display:flex;gap:6px;">
                <input type="text" id="detail-comment-input" class="form-input" placeholder="Dodaj komentarz..." style="flex:1;">
                <button type="submit" class="btn btn-primary btn-sm">Wyślij</button>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Reject Modal -->
<div id="bulk-reject-modal" class="modal" style="display:none;">
    <div class="modal-content">
        <h3><?= $lang('reject_selected') ?></h3>
        <p id="bulk-reject-count"></p>
        <div class="form-group">
            <label class="form-label"><?= $lang('rejection_comment') ?></label>
            <textarea id="bulk-reject-comment" class="form-input" rows="3" placeholder="<?= $lang('rejection_comment_placeholder') ?>" required></textarea>
        </div>
        <div class="form-actions">
            <button type="button" class="btn btn-danger" onclick="confirmBulkReject()"><?= $lang('confirm_reject') ?></button>
            <button type="button" class="btn btn-secondary" onclick="closeBulkRejectModal()"><?= $lang('cancel') ?></button>
        </div>
    </div>
</div>

<script>
var allInvoiceIds = <?= json_encode(array_column($invoices, 'id')) ?>;
var hasCostCenters = <?= json_encode(!empty($client['has_cost_centers']) && !empty($costCenters)) ?>;

function toggleSelectAll(el) {
    document.querySelectorAll('.row-check').forEach(function(cb) { cb.checked = el.checked; });
    updateSelections();
}

function updateSelections() {
    // Update PDF selection info
    var allChecked = document.querySelectorAll('.row-check:checked');
    var info = document.getElementById('pdf-selection-info');
    if (info) {
        if (allChecked.length > 0 && allChecked.length < allInvoiceIds.length) {
            info.textContent = '(zaznaczono ' + allChecked.length + ' z ' + allInvoiceIds.length + ')';
        } else {
            info.textContent = '';
        }
    }
    // Sync pending checkboxes to bulk form
    updateBulkMpkBar();
}

function updatePdfIds() {
    var checked = document.querySelectorAll('.row-check:checked');
    var ids;
    if (checked.length > 0) {
        ids = Array.from(checked).map(function(cb) { return parseInt(cb.value); });
    } else {
        ids = allInvoiceIds;
    }
    var json = JSON.stringify(ids);
    document.getElementById('pdf-bulk-vertical-ids').value = json;
    document.getElementById('pdf-bulk-horizontal-ids').value = json;
}

function ksefBackfillPurchase() {
    if (!confirm('Uzupełnić brakujące numery KSeF dla faktur zakupowych?\n\nSystem odpyta API KSeF i dopasuje numery po numerze faktury i NIP sprzedawcy.')) return;

    var btn = event.target.closest('button');
    var origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span style="display:inline-flex;align-items:center;gap:4px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="spin"><path d="M1 4v6h6"/><path d="M3.51 15a9 9 0 105.64-12.36L3 9"/></svg> Trwa wyszukiwanie...</span>';

    var formData = new FormData();
    formData.append('csrf_token', '<?= htmlspecialchars(\App\Core\Session::get('csrf_token') ?? '') ?>');

    fetch('/client/invoices/ksef-backfill', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled = false;
            btn.innerHTML = origText;
            if (data.error) {
                alert('Błąd: ' + data.error);
                return;
            }
            var msg = data.message || '';
            if (data.errors && data.errors.length > 0) {
                msg += '\n\nBłędy:\n' + data.errors.join('\n');
            }
            alert(msg);
            if (data.recovered > 0) {
                window.location.reload();
            }
        })
        .catch(function(err) {
            btn.disabled = false;
            btn.innerHTML = origText;
            alert('Błąd połączenia: ' + err.message);
        });
}

function whitelistRecheck() {
    if (!confirm('Sprawdzić rachunki bankowe na białej liście VAT dla wszystkich faktur w tej paczce?')) return;

    var btn = event.target.closest('button');
    var origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span style="display:inline-flex;align-items:center;gap:4px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="spin"><path d="M1 4v6h6"/><path d="M3.51 15a9 9 0 105.64-12.36L3 9"/></svg> Sprawdzanie...</span>';

    var formData = new FormData();
    formData.append('csrf_token', '<?= htmlspecialchars($csrf) ?>');
    formData.append('batch_id', '<?= $batch['id'] ?>');

    fetch('/client/invoices/whitelist-recheck', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled = false;
            btn.innerHTML = origText;
            if (data.error) {
                alert('Błąd: ' + data.error);
                return;
            }
            alert(data.message || 'Gotowe');
            if (data.failed > 0 || data.checked > 0) {
                window.location.reload();
            }
        })
        .catch(function(err) {
            btn.disabled = false;
            btn.innerHTML = origText;
            alert('Błąd połączenia: ' + err.message);
        });
}

function getCheckedPending() {
    return document.querySelectorAll('.row-check:checked[data-status="pending"]');
}

function syncBulkFormIds() {
    var form = document.getElementById('bulk-form');
    if (!form) return;
    form.querySelectorAll('input[name="invoice_ids[]"]').forEach(function(el) { el.remove(); });
    getCheckedPending().forEach(function(cb) {
        var hidden = document.createElement('input');
        hidden.type = 'hidden'; hidden.name = 'invoice_ids[]'; hidden.value = cb.value;
        form.appendChild(hidden);
    });
}

function submitBulkAccept() {
    var checked = getCheckedPending();
    if (checked.length === 0) { alert('<?= $lang('select_invoices_first') ?>'); return; }
    if (hasCostCenters) {
        alert('<?= $lang('bulk_accept_use_mpk') ?>');
        return;
    }
    syncBulkFormIds();
    document.getElementById('bulk-action-field').value = 'accept';
    document.getElementById('bulk-comment-field').value = '';
    document.getElementById('bulk-form').submit();
}

function showBulkRejectModal() {
    var checked = getCheckedPending();
    if (checked.length === 0) { alert('<?= $lang('select_invoices_first') ?>'); return; }
    document.getElementById('bulk-reject-count').textContent = '<?= $lang('selected') ?>: ' + checked.length;
    document.getElementById('bulk-reject-comment').value = '';
    document.getElementById('bulk-reject-modal').style.display = 'flex';
}
function closeBulkRejectModal() {
    document.getElementById('bulk-reject-modal').style.display = 'none';
}
function confirmBulkReject() {
    var comment = document.getElementById('bulk-reject-comment').value.trim();
    if (!comment) { alert('<?= $lang('comment_required_for_reject') ?>'); return; }
    syncBulkFormIds();
    document.getElementById('bulk-action-field').value = 'reject';
    document.getElementById('bulk-comment-field').value = comment;
    document.getElementById('bulk-form').submit();
}

function onRowClick(event, invoiceId) {
    if (event.target.type === 'checkbox' || event.target.closest('label.checkbox-label')) return;
    openInvoiceDetail(invoiceId);
}

var currentInvoiceId = null;
var currentInvoiceData = null;

function openInvoiceDetail(id) {
    currentInvoiceId = id;
    fetch('/client/invoices/detail?invoice_id=' + id)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) { alert(data.error); return; }
            currentInvoiceData = data;

            document.getElementById('detail-title').textContent = data.invoice_number;

            // KSeF
            if (data.ksef_reference_number) {
                document.getElementById('detail-ksef').style.display = '';
                document.getElementById('detail-ksef-number').textContent = data.ksef_reference_number;
            } else {
                document.getElementById('detail-ksef').style.display = 'none';
            }

            // Correction info
            var corrInfo = document.getElementById('detail-correction-info');
            if (data.invoice_type === 'KOR') {
                corrInfo.style.display = '';
                var corrInvEl = document.getElementById('detail-corrected-invoice');
                var parts = [];
                if (data.corrected_invoice_number) parts.push('Koryguje: ' + data.corrected_invoice_number);
                if (data.corrected_invoice_date) parts.push('z dnia ' + data.corrected_invoice_date);
                if (data.corrected_ksef_number) parts.push('(KSeF: ' + data.corrected_ksef_number + ')');
                corrInvEl.textContent = parts.join(' ');
                var reasonEl = document.getElementById('detail-correction-reason');
                reasonEl.textContent = data.correction_reason ? 'Powód: ' + data.correction_reason : '';
                reasonEl.style.display = data.correction_reason ? '' : 'none';
            } else {
                corrInfo.style.display = 'none';
            }

            // Dates
            document.getElementById('detail-issue-date').textContent = data.issue_date || '-';
            document.getElementById('detail-sale-date').textContent = data.sale_date || '-';
            document.getElementById('detail-due-date').textContent = data.due_date || '-';

            // Seller / Buyer
            document.getElementById('detail-seller-name').textContent = data.seller_name || '';
            document.getElementById('detail-seller-nip').textContent = data.seller_nip || '';
            document.getElementById('detail-seller-address').textContent = data.seller_address || '';
            document.getElementById('detail-buyer-name').textContent = data.buyer_name || '';
            document.getElementById('detail-buyer-nip').textContent = data.buyer_nip || '';
            document.getElementById('detail-buyer-address').textContent = data.buyer_address || '';

            // Amounts
            var cur = data.currency || 'PLN';
            document.getElementById('detail-net').textContent = formatMoney(data.net_amount) + ' ' + cur;
            document.getElementById('detail-vat').textContent = formatMoney(data.vat_amount) + ' ' + cur;
            document.getElementById('detail-gross').textContent = formatMoney(data.gross_amount) + ' ' + cur;

            // Line items
            var tbody = document.getElementById('detail-line-items');
            tbody.innerHTML = '';
            var items = data.line_items || [];
            if (items.length > 0) {
                document.getElementById('detail-line-items-section').style.display = '';
                items.forEach(function(item, idx) {
                    var tr = document.createElement('tr');
                    tr.innerHTML = '<td>' + (idx+1) + '</td>'
                        + '<td>' + escHtml(item.name || item.nazwa || '') + '</td>'
                        + '<td class="text-right">' + escHtml(item.quantity || item.ilosc || '') + '</td>'
                        + '<td>' + escHtml(item.unit || item.jednostka || '') + '</td>'
                        + '<td class="text-right">' + formatMoney(item.unit_price || item.cena_netto || '') + '</td>'
                        + '<td class="text-right">' + formatMoney(item.net_value || item.wartosc_netto || '') + '</td>'
                        + '<td>' + escHtml(item.vat_rate || item.stawka_vat || '') + '</td>';
                    tbody.appendChild(tr);
                });
            } else {
                document.getElementById('detail-line-items-section').style.display = 'none';
            }

            // Notes
            if (data.notes || data.description) {
                document.getElementById('detail-notes-section').style.display = '';
                document.getElementById('detail-notes').textContent = data.notes || data.description;
            } else {
                document.getElementById('detail-notes-section').style.display = 'none';
            }

            // Status
            var statusHtml = '';
            if (data.status === 'accepted' && data.is_paid == 2) statusHtml = '<span class="badge" style="background:#dbeafe;color:#2563eb;"><?= $lang('transfer_in_progress') ?></span>';
            else if (data.status === 'accepted' && data.is_paid == 0) statusHtml = '<span class="badge badge-warning"><?= $lang('not_paid') ?></span>';
            else if (data.status === 'accepted') statusHtml = '<span class="badge badge-success"><?= $lang('accepted') ?></span>';
            else if (data.status === 'rejected') statusHtml = '<span class="badge badge-error"><?= $lang('rejected') ?></span>';
            else {
                statusHtml = '<span class="badge badge-warning"><?= $lang('pending') ?></span>';
                if (data.whitelist_failed) {
                    statusHtml += '<br><span class="badge badge-whitelist-fail" style="background:#dc2626;color:#fff;font-size:10px;margin-top:2px;">BRAK NA BIAŁEJ LIŚCIE VAT</span>';
                }
            }
            document.getElementById('detail-status-badge').innerHTML = statusHtml;

            // MPK / reject reason
            if (data.cost_center) {
                document.getElementById('detail-mpk-info').style.display = '';
                document.getElementById('detail-mpk-name').textContent = data.cost_center;
            } else {
                document.getElementById('detail-mpk-info').style.display = 'none';
            }
            if (data.status === 'rejected' && data.comment) {
                document.getElementById('detail-reject-reason').style.display = '';
                document.getElementById('detail-reject-text').textContent = data.comment;
            } else {
                document.getElementById('detail-reject-reason').style.display = 'none';
            }

            // Actions section
            var actionsEl = document.getElementById('detail-actions');
            var paidActionsEl = document.getElementById('detail-paid-actions');
            paidActionsEl.style.display = 'none';

            if (data.status === 'pending' && !data.is_finalized) {
                actionsEl.style.display = '';
                // MPK
                if (data.cost_centers && data.cost_centers.length > 0) {
                    document.getElementById('detail-mpk-group').style.display = '';
                } else {
                    document.getElementById('detail-mpk-group').style.display = 'none';
                }
                // Comment field
                document.getElementById('detail-comment').value = '';
            } else {
                actionsEl.style.display = 'none';
            }

            // Show paid button for accepted unpaid invoices
            if (data.status === 'accepted' && data.is_paid != 1) {
                var paidBtn = document.getElementById('detail-paid-btn');
                paidBtn.disabled = false;
                paidBtn.textContent = 'Oznacz jako opłacona';
                paidActionsEl.style.display = '';
            } else if (data.status === 'accepted' && data.is_paid == 1) {
                var paidBtn = document.getElementById('detail-paid-btn');
                paidBtn.disabled = true;
                paidBtn.textContent = 'Opłacona ✓';
                paidActionsEl.style.display = '';
            }

            // Load comments
            detailLoadComments(id);

            document.getElementById('invoice-detail-modal').style.display = 'flex';
        })
        .catch(function(err) { alert('Błąd: ' + err.message); });
}

function closeDetailModal() {
    document.getElementById('invoice-detail-modal').style.display = 'none';
    currentInvoiceId = null;
    currentInvoiceData = null;
}

function openVisualization() {
    if (!currentInvoiceId) return;
    window.open('/client/invoices/visualization?invoice_id=' + currentInvoiceId, '_blank');
}

function downloadInvoicePdf() {
    if (!currentInvoiceId) return;
    window.open('/client/invoices/pdf?id=' + currentInvoiceId, '_blank');
}

function formatMoney(val) {
    var n = parseFloat(val);
    if (isNaN(n)) return val || '-';
    return n.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

function detailAccept() {
    if (hasCostCenters) {
        var cc = document.getElementById('detail-cost-center').value;
        if (!cc) { alert('<?= $lang('select_cost_center') ?>'); return; }
    }
    var formData = new FormData();
    formData.append('csrf_token', '<?= htmlspecialchars($csrf) ?>');
    formData.append('invoice_id', currentInvoiceId);
    formData.append('action', 'accept');
    if (hasCostCenters) formData.append('cost_center_id', document.getElementById('detail-cost-center').value);
    var comment = document.getElementById('detail-comment').value.trim();
    if (comment) formData.append('comment', comment);

    fetch('/client/invoices/verify', {
        method: 'POST', body: formData,
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    }).then(function(r) { return r.json().then(function(d) { return {ok: r.ok, data: d}; }); })
    .then(function(res) {
        if (res.ok && res.data.success) {
            updateStatusCell(currentInvoiceId, 'accepted', res.data.cost_center || '', '');
            document.getElementById('detail-status-badge').innerHTML = '<span class="badge badge-success"><?= $lang('accepted') ?></span>';
            document.getElementById('detail-actions').style.display = 'none';
            if (res.data.all_verified) window.location.reload();
        } else {
            alert(res.data.error || 'Błąd');
        }
    });
}

function detailReject() {
    var comment = document.getElementById('detail-comment').value.trim();
    if (!comment) { alert('<?= $lang('comment_required_for_reject') ?>'); return; }

    var formData = new FormData();
    formData.append('csrf_token', '<?= htmlspecialchars($csrf) ?>');
    formData.append('invoice_id', currentInvoiceId);
    formData.append('action', 'reject');
    formData.append('comment', comment);
    if (hasCostCenters) {
        var cc = document.getElementById('detail-cost-center').value;
        if (cc) formData.append('cost_center_id', cc);
    }

    fetch('/client/invoices/verify', {
        method: 'POST', body: formData,
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    }).then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            updateStatusCell(currentInvoiceId, 'rejected', '', comment);
            document.getElementById('detail-status-badge').innerHTML = '<span class="badge badge-error"><?= $lang('rejected') ?></span>';
            document.getElementById('detail-actions').style.display = 'none';
            document.getElementById('detail-reject-reason').style.display = '';
            document.getElementById('detail-reject-text').textContent = comment;
            if (data.all_verified) window.location.reload();
        } else {
            alert(data.error || 'Błąd');
        }
    });
}

function detailTogglePaid() {
    var btn = document.getElementById('detail-paid-btn');
    var invoiceId = currentInvoiceId;
    var formData = new FormData();
    formData.append('invoice_id', invoiceId);
    formData.append('csrf_token', '<?= htmlspecialchars($csrf) ?>');
    fetch('/client/invoices/toggle-paid', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success && data.is_paid == 1) {
                btn.disabled = true;
                btn.textContent = 'Opłacona ✓';
                // Update list row: change to green + accepted badge
                var cell = document.getElementById('status-cell-' + invoiceId);
                if (cell) {
                    var mpkHtml = '';
                    var mpkSmall = cell.querySelector('.text-muted');
                    if (mpkSmall) mpkHtml = '<br>' + mpkSmall.outerHTML;
                    cell.innerHTML = '<span class="badge badge-success"><?= $lang('accepted') ?></span>' + mpkHtml;
                }
                var row = cell ? cell.closest('tr') : null;
                if (row) {
                    row.className = row.className.replace(/status-[\w-]+/, 'status-accepted');
                }
            }
        });
}

function markAsPaid(invoiceId, btn) {
    var formData = new FormData();
    formData.append('invoice_id', invoiceId);
    formData.append('csrf_token', '<?= htmlspecialchars($csrf) ?>');
    btn.disabled = true;
    btn.textContent = '...';
    fetch('/client/invoices/toggle-paid', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success && data.is_paid == 1) {
                // Update status cell
                var cell = document.getElementById('status-cell-' + invoiceId);
                if (cell) {
                    var mpkSmall = cell.querySelector('.text-muted');
                    var mpkHtml = mpkSmall ? '<br>' + mpkSmall.outerHTML : '';
                    cell.innerHTML = '<span class="badge badge-success"><?= $lang('accepted') ?></span>' + mpkHtml;
                }
                // Update row color
                var row = cell ? cell.closest('tr') : null;
                if (row) {
                    row.className = row.className.replace(/status-[\w-]+/, 'status-accepted');
                }
            } else {
                btn.disabled = false;
                btn.textContent = 'Opłacona';
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.textContent = 'Opłacona';
        });
}

function updateStatusCell(invoiceId, status, costCenter, comment) {
    var cell = document.getElementById('status-cell-' + invoiceId);
    if (!cell) return;
    var html = '';
    var rowClass = 'status-' + status;
    if (status === 'accepted') {
        // Newly accepted invoices are unpaid by default
        html = '<span class="badge badge-warning"><?= $lang('not_paid') ?></span>';
        html += '<button class="btn btn-sm btn-success" style="margin-top:4px;font-size:11px;padding:2px 8px;" onclick="event.stopPropagation(); markAsPaid(' + invoiceId + ', this)">Opłacona</button>';
        rowClass = 'status-accepted-unpaid';
        if (costCenter) html += '<br><small class="text-muted">MPK: ' + escHtml(costCenter) + '</small>';
    } else if (status === 'rejected') {
        html = '<span class="badge badge-error"><?= $lang('rejected') ?></span>';
        if (comment) html += '<br><small class="text-muted">' + escHtml(comment) + '</small>';
    }
    cell.innerHTML = html;
    // Update row class
    var row = cell.closest('tr');
    if (row) {
        row.className = row.className.replace(/status-[\w-]+/, rowClass);
    }
}

function detailLoadComments(invoiceId) {
    var thread = document.getElementById('detail-comments-thread');
    thread.innerHTML = '<div class="text-muted" style="padding:8px;font-size:0.85em;"><?= $lang('loading') ?>...</div>';

    fetch('/client/invoices/comments?invoice_id=' + invoiceId)
        .then(function(r) { return r.json(); })
        .then(function(comments) {
            if (comments.length === 0) {
                thread.innerHTML = '<div class="text-muted" style="padding:8px;font-size:0.85em;"><?= $lang('no_comments') ?></div>';
                return;
            }
            var html = '';
            comments.forEach(function(c) {
                var typeClass = c.user_type === 'client' ? 'comment-mine' : 'comment-other';
                html += '<div class="comment-bubble ' + typeClass + '">';
                html += '<div class="comment-meta"><strong>' + escHtml(c.user_name) + '</strong> <span class="text-muted">' + c.created_at + '</span></div>';
                html += '<div class="comment-text">' + escHtml(c.message) + '</div>';
                html += '</div>';
            });
            thread.innerHTML = html;
        });
}

function detailSubmitComment(event) {
    event.preventDefault();
    var input = document.getElementById('detail-comment-input');
    var message = input.value.trim();
    if (!message) return;

    var formData = new FormData();
    formData.append('csrf_token', '<?= htmlspecialchars($csrf) ?>');
    formData.append('invoice_id', currentInvoiceId);
    formData.append('message', message);

    fetch('/client/invoices/comment', {method: 'POST', body: formData})
        .then(function() {
            input.value = '';
            detailLoadComments(currentInvoiceId);
        });
}

// Close modal on backdrop click
document.addEventListener('click', function(e) {
    var modal = document.getElementById('invoice-detail-modal');
    if (e.target === modal) closeDetailModal();
});

// Row check listeners are handled via onchange="updateSelections()" inline

function updateBulkMpkBar() {
    var bar = document.getElementById('bulk-mpk-bar');
    var form = document.getElementById('bulk-mpk-form');
    if (!bar) return;

    var checked = getCheckedPending();
    var count = checked.length;
    var countEl = document.getElementById('mpk-selected-count');
    if (countEl) countEl.textContent = '<?= $lang('selected') ?>: ' + count;

    bar.style.display = count > 0 ? 'flex' : 'none';

    // Sync checked IDs to the MPK form
    if (form) {
        form.querySelectorAll('input[name="invoice_ids[]"]').forEach(function(el) { el.remove(); });
        checked.forEach(function(cb) {
            var hidden = document.createElement('input');
            hidden.type = 'hidden'; hidden.name = 'invoice_ids[]'; hidden.value = cb.value;
            form.appendChild(hidden);
        });
    }
}

function escHtml(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

// Import z KSeF dla bieżącej paczki
function importKsefForBatch() {
    var btn = document.getElementById('ksef-import-batch-btn');
    var label = document.getElementById('ksef-import-batch-label');
    if (!btn || btn.disabled) return;
    btn.disabled = true;
    btn.style.opacity = '0.7';
    label.textContent = 'Importowanie z KSeF...';

    var fd = new FormData();
    fd.append('csrf_token', '<?= htmlspecialchars($csrf) ?>');
    fd.append('month', '<?= (int)$batch['period_month'] ?>');
    fd.append('year', '<?= (int)$batch['period_year'] ?>');

    fetch('/client/import-ksef', { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) {
                alert(data.error);
                btn.disabled = false;
                btn.style.opacity = '';
                label.textContent = '<?= $lang('download_from_ksef') ?>';
                return;
            }
            label.textContent = 'Gotowe! Odswiezanie...';
            setTimeout(function() { window.location.reload(); }, 1500);
        })
        .catch(function(err) {
            alert('Blad polaczenia: ' + err.message);
            btn.disabled = false;
            btn.style.opacity = '';
            label.textContent = '<?= $lang('download_from_ksef') ?>';
        });
}
</script>
