<div class="section-header" style="margin-bottom:24px;">
    <h1 style="margin:0;"><?= $lang('issued_invoices') ?></h1>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <a href="/client/sales/dashboard" class="btn"><?= $lang('sales_dashboard') ?></a>
        <a href="/client/sales/create" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <?= $lang('new_invoice') ?>
        </a>
    </div>
</div>

<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid" style="margin-bottom:20px;">
    <div class="stat-card">
        <div class="stat-label"><?= $lang('total') ?></div>
        <div class="stat-value"><?= $counts['total'] ?></div>
    </div>
    <div class="stat-card stat-warning">
        <div class="stat-label"><?= $lang('draft') ?></div>
        <div class="stat-value"><?= $counts['draft'] ?></div>
    </div>
    <div class="stat-card stat-success">
        <div class="stat-label"><?= $lang('issued') ?></div>
        <div class="stat-value"><?= $counts['issued'] ?></div>
    </div>
    <div class="stat-card" style="border-top-color: var(--info);">
        <div class="stat-label"><?= $lang('sent_ksef') ?></div>
        <div class="stat-value"><?= $counts['sent_ksef'] ?></div>
    </div>
</div>

<!-- Filters & Actions -->
<div class="form-card" style="padding:16px; margin-bottom:20px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <form method="GET" action="/client/sales" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <select name="status" class="form-input" style="width:auto; min-width:140px;">
                <option value=""><?= $lang('status') ?>: <?= $lang('total') ?></option>
                <option value="draft" <?= ($filterStatus ?? '') === 'draft' ? 'selected' : '' ?>><?= $lang('draft') ?></option>
                <option value="issued" <?= ($filterStatus ?? '') === 'issued' ? 'selected' : '' ?>><?= $lang('issued') ?></option>
                <option value="sent_ksef" <?= ($filterStatus ?? '') === 'sent_ksef' ? 'selected' : '' ?>><?= $lang('sent_ksef') ?></option>
                <option value="cancelled" <?= ($filterStatus ?? '') === 'cancelled' ? 'selected' : '' ?>><?= $lang('cancelled') ?></option>
            </select>
            <input type="text" name="search" class="form-input" style="width:220px;" placeholder="<?= $lang('search_contractors') ?>" value="<?= htmlspecialchars($filterSearch ?? '') ?>">
            <button type="submit" class="btn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <?= $lang('search') ?>
            </button>
        </form>
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <button type="button" id="bulkPdfBtn" class="btn" style="display:none;" onclick="showPdfExportMenu()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                PDF (<span id="pdfCount">0</span>)
            </button>
            <?php
            $listClient = \App\Models\Client::findById(\App\Core\Session::get('client_id'));
            if (!empty($listClient['can_send_invoices'])):
            ?>
            <button type="button" id="bulkEmailBtn" class="btn" style="display:none;" onclick="bulkSendEmail()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
                </svg>
                <?= $lang('send_email') ?> (<span id="emailCount">0</span>)
            </button>
            <?php endif; ?>
            <button type="button" id="bulkSendKsefBtn" class="btn btn-warning" style="display:none;" onclick="bulkSendKsef()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M22 2L11 13"/><path d="M22 2L15 22L11 13L2 9L22 2Z"/></svg>
                Wyślij zaznaczone (<span id="bulkCount">0</span>)
            </button>
            <button type="button" class="btn" onclick="bulkSendAllKsef()" title="Wyślij wszystkie niewysłane faktury do KSeF">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M22 2L11 13"/><path d="M22 2L15 22L11 13L2 9L22 2Z"/></svg>
                Wyślij do KSeF
            </button>
            <button type="button" class="btn" onclick="ksefRefreshStatus()" id="ksef-refresh-btn" title="Sprawdź statusy w KSeF, pobierz numery i UPO">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M1 4v6h6"/><path d="M3.51 15a9 9 0 105.64-12.36L3 9"/></svg>
                <span id="ksef-refresh-label">Odśwież status KSeF</span>
            </button>
        </div>
    </div>
</div>

<?php if (empty($invoices)): ?>
    <div class="form-card" style="padding:48px; text-align:center;">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--gray-300)" stroke-width="1.5" style="margin-bottom:16px;"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
        <p style="color:var(--gray-500); margin-bottom:16px;"><?= $lang('no_sales_data') ?></p>
        <a href="/client/sales/create" class="btn btn-primary"><?= $lang('new_invoice') ?></a>
    </div>
<?php else: ?>
    <div class="form-card" style="padding:0; overflow:hidden;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:36px; text-align:center;"><input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)"></th>
                        <th><?= $lang('invoice_number') ?></th>
                        <th><?= $lang('issue_date') ?></th>
                        <th><?= $lang('buyer') ?></th>
                        <th style="text-align:right;"><?= $lang('net_amount') ?></th>
                        <th style="text-align:right;" class="hide-mobile"><?= $lang('vat_amount') ?></th>
                        <th style="text-align:right;"><?= $lang('gross_amount') ?></th>
                        <th style="text-align:center;"><?= $lang('status') ?></th>
                        <th style="text-align:center;"><?= $lang('actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $totalNet = 0; $totalVat = 0; $totalGross = 0;
                    foreach ($invoices as $inv):
                        $totalNet += (float)$inv['net_amount'];
                        $totalVat += (float)$inv['vat_amount'];
                        $totalGross += (float)$inv['gross_amount'];
                        $ksefSt = $inv['ksef_status'] ?? 'none';
                        $canSendKsef = $inv['status'] !== 'draft' && $inv['status'] !== 'cancelled'
                            && (empty($ksefSt) || $ksefSt === 'none' || $ksefSt === 'error')
                            && ($inv['invoice_type'] ?? 'FV') !== 'FP'; // Proforma cannot be sent to KSeF
                    ?>
                    <tr>
                        <td style="text-align:center;">
                            <input type="checkbox" class="invoice-checkbox" value="<?= $inv['id'] ?>" data-can-ksef="<?= $canSendKsef ? '1' : '0' ?>" onchange="updateBulkCounts()">
                        </td>
                        <td>
                            <a href="/client/sales/<?= $inv['id'] ?>" style="font-weight:600; color:var(--primary); text-decoration:none;">
                                <?= htmlspecialchars($inv['invoice_number']) ?>
                            </a>
                            <?php if (($inv['invoice_type'] ?? 'FV') === 'FP'): ?>
                                <span class="badge" style="font-size:10px; padding:1px 6px; background:#f0f9ff; color:#0369a1; border:1px solid #bae6fd; vertical-align:middle; margin-left:4px;">PRO</span>
                            <?php elseif (($inv['invoice_type'] ?? 'FV') === 'FV_ZAL'): ?>
                                <span class="badge" style="font-size:10px; padding:1px 6px; background:#fefce8; color:#a16207; border:1px solid #fde68a; vertical-align:middle; margin-left:4px;">ZAL</span>
                            <?php elseif (($inv['invoice_type'] ?? 'FV') === 'FV_KON'): ?>
                                <span class="badge" style="font-size:10px; padding:1px 6px; background:#f0fdf4; color:#166534; border:1px solid #86efac; vertical-align:middle; margin-left:4px;">KON</span>
                            <?php endif; ?>
                            <?php if (!empty($inv['email_sent_at'])): ?>
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2" style="vertical-align:middle; margin-left:4px;" title="<?= $lang('invoice_email_sent') ?> <?= htmlspecialchars($inv['email_sent_at']) ?>">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
                                </svg>
                            <?php endif; ?>
                        </td>
                        <td style="white-space:nowrap; color:var(--gray-500); font-size:13px;"><?= htmlspecialchars($inv['issue_date']) ?></td>
                        <td><span class="text-truncate" title="<?= htmlspecialchars($inv['buyer_name']) ?>"><?= htmlspecialchars($inv['buyer_name']) ?></span></td>
                        <td style="text-align:right; font-variant-numeric:tabular-nums;"><?= number_format((float)$inv['net_amount'], 2, ',', ' ') ?></td>
                        <td style="text-align:right; font-variant-numeric:tabular-nums;" class="hide-mobile"><?= number_format((float)$inv['vat_amount'], 2, ',', ' ') ?></td>
                        <td style="text-align:right; font-weight:600; font-variant-numeric:tabular-nums;"><?= number_format((float)$inv['gross_amount'], 2, ',', ' ') ?></td>
                        <td style="text-align:center;">
                            <?php
                            // Jeden badge z logiką KSeF:
                            // error/rejected → czerwony, sent (czeka) → niebieski, accepted → zielony
                            if ($ksefSt === 'error' || $ksefSt === 'rejected'): ?>
                                <span class="badge badge-error" title="<?= htmlspecialchars($inv['ksef_error'] ?? '') ?>">KSeF odrzucona</span>
                            <?php elseif ($ksefSt === 'accepted'): ?>
                                <span class="badge badge-success">KSeF ✓</span>
                            <?php elseif ($ksefSt === 'sent' || $ksefSt === 'pending'): ?>
                                <span class="badge badge-info">Wysłana do KSeF</span>
                            <?php elseif ($inv['status'] === 'cancelled'): ?>
                                <span class="badge badge-error"><?= $lang('cancelled') ?></span>
                            <?php elseif ($inv['status'] === 'draft'): ?>
                                <span class="badge badge-warning"><?= $lang('draft') ?></span>
                            <?php elseif ($inv['status'] === 'issued' || $inv['status'] === 'sent_ksef'): ?>
                                <span class="badge badge-warning-orange"><?= $lang('issued') ?></span>
                            <?php else: ?>
                                <span class="badge"><?= $lang($inv['status']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center; white-space:nowrap;">
                            <div style="display:inline-flex; gap:4px; align-items:center;">
                                <a href="/client/sales/<?= $inv['id'] ?>" class="btn btn-xs" title="<?= $lang('view') ?>">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </a>
                                <?php
                                $invKsefSent = !empty($ksefSt) && !in_array($ksefSt, ['none', 'error']);
                                $invCanEdit = $inv['status'] === 'draft'
                                    || ($inv['status'] === 'issued' && !$invKsefSent);
                                ?>
                                <?php if ($invCanEdit): ?>
                                    <a href="/client/sales/<?= $inv['id'] ?>/edit" class="btn btn-xs" title="<?= $lang('edit') ?>">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </a>
                                <?php endif; ?>
                                <?php
                                $canDelete = empty($ksefSt) || $ksefSt === 'none' || $ksefSt === 'error';
                                ?>
                                <?php if ($canDelete): ?>
                                    <form method="POST" action="/client/sales/<?= $inv['id'] ?>/delete" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Session::get('csrf_token') ?? '') ?>">
                                        <button type="submit" class="btn btn-xs btn-danger" onclick="return confirm('Usunąć fakturę?')" title="<?= $lang('delete') ?>">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-footer">
                        <td></td>
                        <td colspan="3" style="font-weight:700;"><?= $lang('total') ?></td>
                        <td style="text-align:right; font-weight:700; font-variant-numeric:tabular-nums;"><?= number_format($totalNet, 2, ',', ' ') ?></td>
                        <td style="text-align:right; font-weight:700; font-variant-numeric:tabular-nums;" class="hide-mobile"><?= number_format($totalVat, 2, ',', ' ') ?></td>
                        <td style="text-align:right; font-weight:700; font-variant-numeric:tabular-nums;"><?= number_format($totalGross, 2, ',', ' ') ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- PDF Export Forms (hidden) -->
<form method="POST" action="/client/sales/bulk-pdf" id="salesPdfVerticalForm" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Session::get('csrf_token') ?? '') ?>">
    <input type="hidden" name="layout" value="vertical">
    <input type="hidden" name="invoice_ids" id="salesPdfVerticalIds" value="">
</form>
<form method="POST" action="/client/sales/bulk-pdf" id="salesPdfHorizontalForm" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Session::get('csrf_token') ?? '') ?>">
    <input type="hidden" name="layout" value="horizontal">
    <input type="hidden" name="invoice_ids" id="salesPdfHorizontalIds" value="">
</form>

<!-- PDF Export Menu (floating) -->
<div id="pdfExportMenu" class="floating-menu" style="display:none; position:fixed; bottom:80px; right:20px; border-radius:8px; padding:12px; box-shadow:0 4px 12px rgba(0,0,0,0.15); z-index:1000; min-width:240px; max-width:calc(100vw - 40px);">
    <h4 style="margin:0 0 8px; font-size:14px;">Eksport PDF</h4>
    <button type="button" class="btn btn-sm" style="width:100%; margin-bottom:6px;" onclick="submitSalesPdf('vertical')">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Pełna wizualizacja (pionowo)
    </button>
    <button type="button" class="btn btn-sm" style="width:100%;" onclick="submitSalesPdf('horizontal')">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
        Zestawienie tabelaryczne (poziomo)
    </button>
    <button type="button" class="btn btn-sm btn-secondary" style="width:100%; margin-top:6px;" onclick="closePdfMenu()">Anuluj</button>
</div>

<style>
.badge-info { background: var(--blue-100, #dbeafe); color: var(--blue-700, #1d4ed8); }
.badge-warning { background: var(--yellow-100, #fef3c7); color: var(--yellow-700, #a16207); }
.badge-error { background: var(--red-100, #fee2e2); color: var(--red-700, #b91c1c); }
.bulk-progress { position:fixed; bottom:20px; right:20px; background:#ffffff; border:1px solid var(--gray-300, #d1d5db); border-radius:8px; padding:16px; box-shadow:0 4px 12px rgba(0,0,0,0.15); z-index:1000; min-width:280px; max-width:calc(100vw - 40px); max-height:60vh; overflow-y:auto; }
[data-theme="dark"] .bulk-progress { background:#162E3A; border-color:#2D4551; color:#D9D9D9; }
.bulk-progress h4 { margin:0 0 8px; }
.bulk-item { display:flex; justify-content:space-between; align-items:center; padding:4px 0; font-size:13px; border-bottom:1px solid var(--gray-100, #f3f4f6); }
.bulk-item:last-child { border-bottom:none; }
</style>

<script>
function toggleSelectAll(el) {
    document.querySelectorAll('.invoice-checkbox').forEach(function(cb) { cb.checked = el.checked; });
    updateBulkCounts();
}

function updateBulkCounts() {
    var allChecked = document.querySelectorAll('.invoice-checkbox:checked');
    var ksefChecked = Array.from(allChecked).filter(function(cb) { return cb.dataset.canKsef === '1'; });

    var ksefBtn = document.getElementById('bulkSendKsefBtn');
    var ksefCnt = document.getElementById('bulkCount');
    ksefCnt.textContent = ksefChecked.length;
    ksefBtn.style.display = ksefChecked.length > 0 ? 'inline-block' : 'none';

    var pdfBtn = document.getElementById('bulkPdfBtn');
    var pdfCnt = document.getElementById('pdfCount');
    if (pdfCnt) pdfCnt.textContent = allChecked.length;
    if (pdfBtn) pdfBtn.style.display = allChecked.length > 0 ? 'inline-flex' : 'none';

    var emailBtn = document.getElementById('bulkEmailBtn');
    var emailCnt = document.getElementById('emailCount');
    if (emailBtn && emailCnt) {
        emailCnt.textContent = allChecked.length;
        emailBtn.style.display = allChecked.length > 0 ? 'inline-flex' : 'none';
    }
}

function getSelectedIds() {
    return Array.from(document.querySelectorAll('.invoice-checkbox:checked'))
        .filter(function(cb) { return cb.dataset.canKsef === '1'; })
        .map(function(cb) { return parseInt(cb.value); });
}

function bulkSendKsef() {
    var ids = getSelectedIds();
    if (ids.length === 0) return;
    if (!confirm('Wyslac ' + ids.length + ' faktur do KSeF?')) return;
    startBulkSend(ids);
}

function bulkSendEmail() {
    var ids = Array.from(document.querySelectorAll('.invoice-checkbox:checked'))
        .map(function(cb) { return parseInt(cb.value); });
    if (ids.length === 0) return;
    if (!confirm('Wysłać ' + ids.length + ' faktur emailem do kontrahentów?')) return;

    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '/client/sales/bulk-send-email';
    var csrf = document.createElement('input');
    csrf.type = 'hidden'; csrf.name = 'csrf_token'; csrf.value = '<?= $csrf ?>';
    form.appendChild(csrf);
    var idsInput = document.createElement('input');
    idsInput.type = 'hidden'; idsInput.name = 'invoice_ids'; idsInput.value = JSON.stringify(ids);
    form.appendChild(idsInput);
    document.body.appendChild(form);
    form.submit();
}

function bulkSendAllKsef() {
    if (!confirm('Wyslac WSZYSTKIE niewysłane faktury do KSeF?')) return;
    startBulkSend([]);
}

function ksefRefreshStatus() {
    var btn = document.getElementById('ksef-refresh-btn');
    var label = document.getElementById('ksef-refresh-label');
    if (!btn || btn.disabled) return;

    btn.disabled = true;
    btn.style.opacity = '0.7';
    label.textContent = 'Sprawdzanie statusów KSeF...';

    var formData = new FormData();
    formData.append('csrf_token', '<?= htmlspecialchars(\App\Core\Session::get('csrf_token') ?? '') ?>');

    fetch('/client/sales/ksef-backfill', { method: 'POST', body: formData })
        .then(function(r) {
            if (!r.ok) {
                return r.text().then(function(txt) {
                    throw new Error('HTTP ' + r.status + ': ' + txt.substring(0, 200));
                });
            }
            return r.json();
        })
        .then(function(data) {
            btn.disabled = false;
            btn.style.opacity = '';
            label.textContent = 'Odśwież status KSeF';
            if (data.error) {
                alert('Błąd: ' + data.error);
                return;
            }
            var msg = data.message || 'Gotowe.';
            if (data.failed > 0) {
                msg += '\nNie udało się przetworzyć: ' + data.failed;
            }
            if (data.errors && data.errors.length > 0) {
                msg += '\n\nBłędy:\n' + data.errors.join('\n');
            }
            alert(msg);
            if ((data.recovered || 0) > 0 || (data.status_updated || 0) > 0) {
                window.location.reload();
            }
        })
        .catch(function(err) {
            btn.disabled = false;
            btn.style.opacity = '';
            label.textContent = 'Odśwież status KSeF';
            alert('Błąd: ' + err.message);
        });
}

function startBulkSend(ids) {
    var formData = new FormData();
    formData.append('csrf_token', '<?= htmlspecialchars(\App\Core\Session::get('csrf_token') ?? '') ?>');
    if (ids.length > 0) {
        formData.append('invoice_ids', JSON.stringify(ids));
    }

    document.getElementById('bulkSendKsefBtn').disabled = true;

    fetch('/client/sales/bulk-send-ksef', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                document.getElementById('bulkSendKsefBtn').disabled = false;
                return;
            }
            if (!data.batch_job_id) {
                alert('Brak faktur do wyslania');
                document.getElementById('bulkSendKsefBtn').disabled = false;
                return;
            }
            showBatchProgress(data.batch_job_id, data.invoice_count, data.invoice_numbers || {});
        })
        .catch(err => {
            alert('Blad: ' + err.message);
            document.getElementById('bulkSendKsefBtn').disabled = false;
        });
}

function showBatchProgress(batchJobId, invoiceCount, invoiceNumbers) {
    var existing = document.getElementById('bulkProgressPanel');
    if (existing) existing.remove();

    var panel = document.createElement('div');
    panel.id = 'bulkProgressPanel';
    panel.className = 'bulk-progress';
    panel.innerHTML = '<h4>Wysylanie do KSeF (' + invoiceCount + ' faktur)</h4>' +
        '<div id="batchStatusMsg" class="badge badge-warning" style="display:inline-block; margin-bottom:8px; font-size:13px; padding:4px 12px;">Uruchamianie...</div>' +
        '<div id="batchResultsList"></div>';
    document.body.appendChild(panel);

    pollBatchStatus(batchJobId, invoiceNumbers, 0);
}

function pollBatchStatus(batchJobId, invoiceNumbers, attempt) {
    if (attempt > 180) {
        var msg = document.getElementById('batchStatusMsg');
        if (msg) { msg.textContent = 'Timeout - odswiez strone'; msg.className = 'badge badge-error'; }
        return;
    }

    fetch('/client/ksef-send-status?job_id=' + batchJobId)
        .then(r => r.json())
        .then(data => {
            var statusMsg = document.getElementById('batchStatusMsg');
            var resultsList = document.getElementById('batchResultsList');

            if (statusMsg) {
                statusMsg.textContent = data.message || 'Przetwarzanie...';
                if (data.status === 'completed') {
                    statusMsg.className = 'badge badge-success';
                } else if (data.status === 'error') {
                    statusMsg.className = 'badge badge-error';
                }
            }

            // Update results list
            if (data.results && resultsList) {
                var html = '';
                for (var invId in data.results) {
                    var r = data.results[invId];
                    var name = r.invoice_number || invoiceNumbers[invId] || '#' + invId;
                    var badgeClass = r.status === 'completed' ? 'badge-success' : (r.status === 'error' ? 'badge-error' : 'badge-warning');
                    var badgeText = r.status === 'completed' ? 'wyslano' : (r.status === 'error' ? 'blad' : 'wysylanie...');
                    html += '<div class="bulk-item"><span>' + escapeHtml(name) + '</span>' +
                        '<span class="badge ' + badgeClass + '" title="' + escapeHtml(r.message || '') + '">' + badgeText + '</span></div>';
                }
                resultsList.innerHTML = html;
            }

            if (data.status === 'completed' || data.status === 'error') {
                var panel = document.getElementById('bulkProgressPanel');
                if (panel) {
                    panel.innerHTML += '<button class="btn btn-sm btn-primary" onclick="location.reload()" style="margin-top:8px; width:100%;">Odswiez strone</button>';
                }
                document.getElementById('bulkSendKsefBtn').disabled = false;
            } else {
                setTimeout(function() { pollBatchStatus(batchJobId, invoiceNumbers, attempt + 1); }, 2000);
            }
        })
        .catch(function() {
            setTimeout(function() { pollBatchStatus(batchJobId, invoiceNumbers, attempt + 1); }, 3000);
        });
}

function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// PDF export functions
function getPdfSelectedIds() {
    return Array.from(document.querySelectorAll('.invoice-checkbox:checked')).map(function(cb) { return parseInt(cb.value); });
}

function showPdfExportMenu() {
    document.getElementById('pdfExportMenu').style.display = 'block';
}

function closePdfMenu() {
    document.getElementById('pdfExportMenu').style.display = 'none';
}

function submitSalesPdf(layout) {
    var ids = getPdfSelectedIds();
    if (ids.length === 0) return;
    var idsJson = JSON.stringify(ids);
    if (layout === 'vertical') {
        document.getElementById('salesPdfVerticalIds').value = idsJson;
        document.getElementById('salesPdfVerticalForm').submit();
    } else {
        document.getElementById('salesPdfHorizontalIds').value = idsJson;
        document.getElementById('salesPdfHorizontalForm').submit();
    }
    closePdfMenu();
}
</script>
