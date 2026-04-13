<div class="section-header">
    <h1><?= $lang('bank_export_title') ?></h1>
    <a href="/client/invoices/<?= $batch['id'] ?>" class="btn btn-secondary">&larr; <?= $lang('back_to_invoices') ?></a>
</div>

<p style="color:var(--gray-500); margin-bottom:16px;">
    <?= $lang('batch') ?>: <strong><?= sprintf('%02d/%04d', $batch['period_month'], $batch['period_year']) ?></strong>
    — <?= $lang('bank_export_description') ?>
</p>

<?php if (!empty($results)): ?>
<!-- ══════ RESULTS ══════ -->
<div class="section" style="margin-bottom:24px;">

    <?php foreach ($results['packages'] as $currency => $pkg): ?>
    <?php if (!empty($pkg['verified'])): ?>
    <div class="alert alert-success" style="margin-bottom:16px;">
        <strong><?= count($pkg['verified']) ?> <?= $lang('transfers') ?> (<?= htmlspecialchars($currency) ?>)</strong>
        — <?= $pkg['format'] === 'elixir' ? 'Elixir-O' : 'SEPA XML' ?>
        <?php if (!empty($pkg['file_name'])): ?>
        <div style="margin-top:8px;">
            <a href="/client/bank-export/download/<?= urlencode($pkg['file_name']) ?>" class="btn btn-success btn-sm">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px; vertical-align:-2px;"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <?= $lang('download_file') ?> <?= htmlspecialchars($pkg['file_name']) ?>
            </a>
        </div>
        <?php endif; ?>
    </div>

    <div class="form-card" style="padding:0; margin-bottom:16px; overflow:hidden;">
        <h3 style="padding:12px 16px; margin:0; background:var(--green-50, #f0fdf4); border-bottom:1px solid var(--green-200, #bbf7d0);">
            <?= $lang('transfers_in_file') ?> <?= htmlspecialchars($currency) ?> (<?= count($pkg['verified']) ?>)
        </h3>
        <div class="table-responsive">
        <table class="table" style="margin:0;">
            <thead>
                <tr>
                    <th>#</th>
                    <th><?= $lang('invoice_number') ?></th>
                    <th><?= $lang('seller') ?></th>
                    <th>NIP</th>
                    <th><?= $lang('ksef_number') ?></th>
                    <th class="text-right"><?= $lang('gross_amount') ?></th>
                    <th class="text-right hide-mobile">VAT</th>
                    <th><?= $lang('bank_account') ?></th>
                    <th><?= $lang('type') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pkg['verified'] as $i => $v): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><strong><?= htmlspecialchars($v['invoice_number']) ?></strong></td>
                    <td><?= htmlspecialchars($v['seller_name']) ?></td>
                    <td><?= htmlspecialchars($v['seller_nip']) ?></td>
                    <td><small style="color:var(--info);"><?= htmlspecialchars($v['ksef_ref'] ?? '') ?></small></td>
                    <td class="text-right"><strong><?= number_format($v['amount'], 2, ',', ' ') ?> <?= htmlspecialchars($v['currency'] ?? $currency) ?></strong></td>
                    <td class="text-right hide-mobile"><?= number_format($v['vat_amount'] ?? 0, 2, ',', ' ') ?> <?= htmlspecialchars($v['currency'] ?? $currency) ?></td>
                    <td><code style="font-size:11px;"><?= htmlspecialchars($v['bank_account'] ?? '') ?></code></td>
                    <td>
                        <?php if (!empty($v['split_payment'])): ?>
                            <span class="badge badge-warning" style="font-size:10px;" title="MPP">MPP</span>
                        <?php elseif ($currency === 'EUR'): ?>
                            <span class="badge badge-info" style="font-size:10px;">SEPA</span>
                        <?php else: ?>
                            <span class="badge badge-success" style="font-size:10px;"><?= $lang('standard') ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:700;">
                    <td colspan="5" class="text-right"><?= $lang('total') ?>:</td>
                    <td class="text-right"><?= number_format(array_sum(array_column($pkg['verified'], 'amount')), 2, ',', ' ') ?> <?= htmlspecialchars($currency) ?></td>
                    <td class="text-right hide-mobile"><?= number_format(array_sum(array_column($pkg['verified'], 'vat_amount')), 2, ',', ' ') ?> <?= htmlspecialchars($currency) ?></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
        </div>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>

    <!-- Manual transfers (other currencies) -->
    <?php if (!empty($results['manual'])): ?>
    <?php foreach ($results['manual'] as $currency => $manualPkg): ?>
    <div class="form-card" style="padding:0; margin-bottom:16px; overflow:hidden;">
        <h3 style="padding:12px 16px; margin:0; background:var(--blue-50, #eff6ff); border-bottom:1px solid var(--blue-200, #bfdbfe);">
            <?= $lang('manual_transfers') ?> <?= htmlspecialchars($currency) ?> (<?= count($manualPkg['invoices']) ?>)
        </h3>
        <div style="padding:8px 16px; font-size:13px; color:var(--gray-600); background:var(--blue-50, #eff6ff); border-bottom:1px solid var(--blue-100, #dbeafe);">
            <?= $lang('manual_transfers_hint') ?>
        </div>
        <div class="table-responsive">
        <table class="table" style="margin:0;">
            <thead>
                <tr>
                    <th>#</th>
                    <th><?= $lang('invoice_number') ?></th>
                    <th><?= $lang('seller') ?></th>
                    <th>NIP</th>
                    <th class="text-right"><?= $lang('amount') ?></th>
                    <th><?= $lang('bank_account') ?></th>
                    <th>SWIFT</th>
                    <th><?= $lang('ksef_number') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($manualPkg['invoices'] as $i => $m): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><strong><?= htmlspecialchars($m['invoice_number']) ?></strong></td>
                    <td><?= htmlspecialchars($m['seller_name']) ?></td>
                    <td><?= htmlspecialchars($m['seller_nip']) ?></td>
                    <td class="text-right"><strong><?= number_format($m['amount'], 2, ',', ' ') ?> <?= htmlspecialchars($m['currency']) ?></strong></td>
                    <td><code style="font-size:11px; cursor:pointer;" data-copy="<?= htmlspecialchars($m['bank_account'], ENT_QUOTES) ?>" onclick="navigator.clipboard.writeText(this.dataset.copy); this.style.opacity='0.5'; setTimeout(()=>this.style.opacity='1',300);" title="<?= $lang('click_to_copy') ?>"><?= htmlspecialchars($m['bank_account']) ?></code></td>
                    <td><?= htmlspecialchars($m['swift']) ?></td>
                    <td><small style="color:var(--info);"><?= htmlspecialchars($m['ksef_ref']) ?></small></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:700;">
                    <td colspan="4" class="text-right"><?= $lang('total') ?>:</td>
                    <td class="text-right"><?= number_format($manualPkg['total'], 2, ',', ' ') ?> <?= htmlspecialchars($currency) ?></td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
        </table>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($results['failed'])): ?>
    <div class="alert alert-error" style="margin-bottom:16px;">
        <strong><?= count($results['failed']) ?> <?= $lang('invoices_rejected') ?></strong>
    </div>

    <div class="form-card" style="padding:0; margin-bottom:16px; overflow:hidden;">
        <h3 style="padding:12px 16px; margin:0; background:var(--red-50, #fef2f2); border-bottom:1px solid var(--red-200, #fecaca); color:var(--red-700, #b91c1c);">
            <?= $lang('rejected_invoices') ?> (<?= count($results['failed']) ?>)
        </h3>
        <div class="table-responsive">
        <table class="table" style="margin:0;">
            <thead>
                <tr>
                    <th>#</th>
                    <th><?= $lang('invoice_number') ?></th>
                    <th><?= $lang('seller') ?></th>
                    <th>NIP</th>
                    <th class="text-right"><?= $lang('amount') ?></th>
                    <th><?= $lang('rejection_reason') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results['failed'] as $i => $f): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><strong><?= htmlspecialchars($f['invoice_number'] ?? '-') ?></strong></td>
                    <td><?= htmlspecialchars($f['seller_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($f['seller_nip'] ?? '-') ?></td>
                    <td class="text-right"><?= isset($f['amount']) ? number_format($f['amount'], 2, ',', ' ') . ' ' . htmlspecialchars($f['currency'] ?? 'PLN') : '-' ?></td>
                    <td><span style="color:var(--red-600);"><?= htmlspecialchars($f['reason'] ?? 'Nieznany błąd') ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if (empty($results['verified']) && empty($results['failed']) && empty($results['manual'])): ?>
    <div class="alert alert-warning"><?= $lang('no_invoices_to_process') ?></div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (empty($invoices)): ?>
<div class="alert alert-info"><?= $lang('no_unpaid_invoices') ?></div>
<?php else: ?>

<?php if (empty($bankAccounts)): ?>
<div class="alert alert-warning" style="margin-bottom:16px;">
    <?= $lang('no_bank_accounts_warning') ?>
</div>
<?php endif; ?>

<!-- Currency account warnings -->
<?php
$invoiceCurrencies = array_unique(array_column($invoices, 'currency'));
$missingAccounts = [];
foreach ($invoiceCurrencies as $cur) {
    if ($cur !== 'PLN' && empty($accountsByCurrency[$cur])) {
        $missingAccounts[] = $cur;
    }
}
?>
<?php if (!empty($missingAccounts)): ?>
<div class="alert alert-warning" style="margin-bottom:16px;">
    <?= $lang('missing_currency_accounts') ?>: <strong><?= htmlspecialchars(implode(', ', $missingAccounts)) ?></strong>.
    <a href="/client/company"><?= $lang('add_bank_account') ?></a>
</div>
<?php endif; ?>

<!-- ══════ INVOICE SELECTION FORM ══════ -->
<form method="POST" action="/client/bank-export/generate" id="export-form">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
    <input type="hidden" name="batch_id" value="<?= $batch['id'] ?>">

    <div class="form-card" style="padding:0; margin-bottom:16px; overflow:hidden;">
        <div style="padding:12px 16px; background:var(--gray-50); border-bottom:1px solid var(--gray-200); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
            <h3 style="margin:0;"><?= $lang('unpaid_invoices') ?> (<?= count($invoices) ?>)</h3>
            <label style="font-size:13px; cursor:pointer;">
                <input type="checkbox" id="selectAll" onchange="toggleAll(this)"> <?= $lang('select_all') ?>
            </label>
        </div>

        <div class="table-responsive">
        <table class="table" style="margin:0;">
            <thead>
                <tr>
                    <th style="width:36px; text-align:center;"></th>
                    <th>#</th>
                    <th><?= $lang('invoice_number') ?></th>
                    <th><?= $lang('seller') ?></th>
                    <th class="hide-mobile">NIP</th>
                    <th class="text-right"><?= $lang('gross_amount') ?></th>
                    <th><?= $lang('currency_short') ?></th>
                    <th class="hide-mobile"><?= $lang('due_date') ?></th>
                    <th><?= $lang('ksef_number') ?></th>
                    <th class="hide-mobile"><?= $lang('bank_account') ?></th>
                    <th><?= $lang('status') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $i => $inv): ?>
                <?php
                    $warnings = [];
                    if (!$inv['has_ksef_ref']) $warnings[] = $lang('no_ksef_ref');
                    if (!$inv['has_bank_account']) $warnings[] = $lang('no_bank_account');
                    $canExport = empty($warnings);
                ?>
                <tr style="<?= !$canExport ? 'opacity:0.6;' : '' ?>">
                    <td style="text-align:center;">
                        <input type="checkbox" name="invoice_ids[]" value="<?= $inv['id'] ?>"
                               class="inv-check" <?= $canExport ? '' : 'disabled' ?>
                               onchange="updateCount()">
                    </td>
                    <td><?= $i + 1 ?></td>
                    <td><strong><?= htmlspecialchars($inv['invoice_number']) ?></strong></td>
                    <td><span class="text-truncate" title="<?= htmlspecialchars($inv['seller_name']) ?>"><?= htmlspecialchars($inv['seller_name']) ?></span></td>
                    <td class="hide-mobile"><?= htmlspecialchars($inv['seller_nip']) ?></td>
                    <td class="text-right"><strong><?= number_format((float)$inv['gross_amount'], 2, ',', ' ') ?></strong></td>
                    <td>
                        <?php $cur = $inv['currency'] ?? 'PLN'; ?>
                        <?php if ($cur === 'PLN'): ?>
                            <span class="badge badge-success" style="font-size:10px;">PLN</span>
                        <?php elseif ($cur === 'EUR'): ?>
                            <span class="badge badge-info" style="font-size:10px;">EUR</span>
                        <?php else: ?>
                            <span class="badge badge-warning" style="font-size:10px;"><?= htmlspecialchars($cur) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="hide-mobile"><?= $inv['payment_due_date'] ?? '-' ?></td>
                    <td>
                        <?php if ($inv['has_ksef_ref']): ?>
                            <small style="color:var(--info);" title="<?= htmlspecialchars($inv['ksef_reference_number']) ?>"><?= htmlspecialchars(mb_substr($inv['ksef_reference_number'], -20)) ?></small>
                        <?php else: ?>
                            <span class="badge badge-warning" style="font-size:10px;"><?= $lang('missing') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="hide-mobile">
                        <?php if ($inv['has_bank_account']): ?>
                            <code style="font-size:10px;"><?= htmlspecialchars(mb_substr($inv['bank_account_preview'] ?? '', -8)) ?></code>
                        <?php else: ?>
                            <span class="badge badge-warning" style="font-size:10px;"><?= $lang('missing') ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($canExport): ?>
                            <span class="badge badge-success" style="font-size:10px;">OK</span>
                        <?php else: ?>
                            <span class="badge badge-error" style="font-size:10px;" title="<?= htmlspecialchars(implode(', ', $warnings)) ?>"><?= htmlspecialchars($warnings[0]) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:700;">
                    <td></td>
                    <td colspan="4" class="text-right"><?= $lang('total') ?>:</td>
                    <td class="text-right"><?= number_format(array_sum(array_column($invoices, 'gross_amount')), 2, ',', ' ') ?></td>
                    <td colspan="5"></td>
                </tr>
            </tfoot>
        </table>
        </div>
    </div>

    <?php if (!empty($bankAccounts)): ?>
    <div class="form-card" style="padding:16px; margin-bottom:16px;">
        <h3 style="margin:0 0 12px;"><?= $lang('export_parameters') ?></h3>
        <div class="responsive-grid-2" style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label"><?= $lang('ordering_bank_account') ?> (PLN)</label>
                <select name="bank_account_id" class="form-input" required>
                    <option value="">-- <?= $lang('select_account') ?> --</option>
                    <?php foreach ($bankAccounts as $ba): ?>
                    <option value="<?= $ba['id'] ?>" <?= !empty($ba['is_default_outgoing']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ba['account_name'] ?: $ba['bank_name']) ?>
                        — <?= htmlspecialchars($ba['account_number']) ?>
                        (<?= $ba['currency'] ?? 'PLN' ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <small class="form-hint"><?= $lang('auto_match_hint') ?></small>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label"><?= $lang('execution_date') ?></label>
                <input type="date" name="execution_date" class="form-input" value="<?= date('Y-m-d') ?>" required>
            </div>
        </div>
        <div style="margin-top:16px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary" id="exportBtn" disabled>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px; vertical-align:-2px;"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <?= $lang('verify_and_generate') ?> (<span id="selectedCount">0</span> <?= $lang('invoices_short') ?>)
            </button>
            <small style="color:var(--gray-500);"><?= $lang('bank_export_whitelist_hint') ?></small>
        </div>
    </div>
    <?php endif; ?>
</form>
<?php endif; ?>

<style>
@media (max-width: 768px) {
    div[style*="grid-template-columns: 1fr 1fr"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<script>
function toggleAll(el) {
    document.querySelectorAll('.inv-check:not(:disabled)').forEach(function(cb) {
        cb.checked = el.checked;
    });
    updateCount();
}

function updateCount() {
    var checked = document.querySelectorAll('.inv-check:checked').length;
    document.getElementById('selectedCount').textContent = checked;
    document.getElementById('exportBtn').disabled = checked === 0;
}
</script>
