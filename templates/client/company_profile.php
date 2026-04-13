<h1><?= $lang('company_profile') ?></h1>

<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Company Data -->
<div class="section">
    <h2><?= $lang('company_data') ?></h2>
    <div class="form-card" style="padding:20px;">
        <div style="display:flex; justify-content:flex-end; margin-bottom:12px;">
            <button type="button" id="gus-fetch-btn" class="btn btn-secondary" onclick="fetchFromGus()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle; margin-right:4px;"><path d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.66 0 3-4.03 3-9s-1.34-9-3-9m0 18c-1.66 0-3-4.03-3-9s1.34-9 3-9m-9 9a9 9 0 019-9"/></svg>
                <?= $lang('fetch_from_gus') ?>
            </button>
        </div>
        <div id="gus-status" style="display:none; margin-bottom:12px;"></div>

        <form method="POST" action="/client/company">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                <div class="form-group">
                    <label class="form-label"><?= $lang('company_name') ?></label>
                    <input type="text" class="form-input" value="<?= htmlspecialchars($client['company_name'] ?? '') ?>" disabled>
                    <small class="form-hint">NIP: <?= htmlspecialchars($client['nip'] ?? '') ?></small>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('trade_name') ?></label>
                    <input type="text" name="trade_name" class="form-input" value="<?= htmlspecialchars($profile['trade_name'] ?? '') ?>">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 2fr 1fr 1fr; gap:16px;">
                <div class="form-group">
                    <label class="form-label"><?= $lang('address_street') ?></label>
                    <input type="text" name="address_street" id="field-street" class="form-input" value="<?= htmlspecialchars($profile['address_street'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('address_postal') ?></label>
                    <input type="text" name="address_postal" id="field-postal" class="form-input" value="<?= htmlspecialchars($profile['address_postal'] ?? '') ?>" placeholder="00-000">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('address_city') ?></label>
                    <input type="text" name="address_city" id="field-city" class="form-input" value="<?= htmlspecialchars($profile['address_city'] ?? '') ?>">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:16px;">
                <div class="form-group">
                    <label class="form-label"><?= $lang('regon') ?></label>
                    <input type="text" name="regon" id="field-regon" class="form-input" value="<?= htmlspecialchars($profile['regon'] ?? '') ?>" maxlength="14">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('krs') ?></label>
                    <input type="text" name="krs" class="form-input" value="<?= htmlspecialchars($profile['krs'] ?? '') ?>" maxlength="20">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('bdo') ?></label>
                    <input type="text" name="bdo" class="form-input" value="<?= htmlspecialchars($profile['bdo'] ?? '') ?>" maxlength="20">
                </div>
            </div>

            <hr style="margin:20px 0; border-color:var(--gray-200);">

            <h3 style="margin-bottom:16px;"><?= $lang('invoice_settings') ?></h3>

            <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:16px;">
                <div class="form-group">
                    <label class="form-label"><?= $lang('numbering_pattern') ?></label>
                    <input type="text" name="invoice_number_pattern" id="numbering-pattern" class="form-input" value="<?= htmlspecialchars($profile['invoice_number_pattern'] ?? 'FV/{NR}/{MM}/{RRRR}') ?>">
                    <small class="form-hint">
                        Dostępne tokeny: <code>{NR}</code> numer kolejny, <code>{MM}</code> miesiąc,
                        <code>{RRRR}</code> rok 4-cyfrowy, <code>{RR}</code> rok 2-cyfrowy,
                        <code>{DZIAŁ}</code> dział/oddział
                    </small>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('next_number') ?></label>
                    <input type="number" name="next_invoice_number" id="numbering-next" class="form-input" value="<?= (int)($profile['next_invoice_number'] ?? 1) ?>" min="1">
                </div>
                <div class="form-group">
                    <label class="form-label">Reset numeracji</label>
                    <select name="numbering_reset_mode" id="numbering-reset-mode" class="form-input">
                        <?php $resetMode = $profile['numbering_reset_mode'] ?? 'monthly'; ?>
                        <option value="monthly" <?= $resetMode === 'monthly' ? 'selected' : '' ?>>Miesięcznie</option>
                        <option value="yearly" <?= $resetMode === 'yearly' ? 'selected' : '' ?>>Rocznie</option>
                        <option value="continuous" <?= $resetMode === 'continuous' ? 'selected' : '' ?>>Ciągła</option>
                    </select>
                    <small class="form-hint">Kiedy numer kolejny wraca do 1</small>
                </div>
            </div>
            <div class="form-group" style="margin-top:4px; margin-bottom:16px;">
                <label class="form-label" style="font-size:0.85em; color:var(--gray-500);">Podgląd numeru:</label>
                <div id="numbering-preview" style="font-size:1.05em; font-weight:600; color:var(--primary); padding:6px 10px; background:var(--gray-50); border-radius:6px; display:inline-block;"></div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                <div class="form-group">
                    <label class="form-label"><?= $lang('default_payment') ?></label>
                    <select name="default_payment_method" class="form-input">
                        <?php
                        $methods = ['przelew' => 'payment_transfer', 'gotowka' => 'payment_cash', 'karta' => 'payment_card', 'kompensata' => 'payment_compensation', 'barter' => 'payment_barter'];
                        foreach ($methods as $val => $key): ?>
                            <option value="<?= $val ?>" <?= ($profile['default_payment_method'] ?? 'przelew') === $val ? 'selected' : '' ?>><?= $lang($key) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('default_payment_days') ?></label>
                    <input type="number" name="default_payment_days" class="form-input" value="<?= (int)($profile['default_payment_days'] ?? 14) ?>" min="0" max="365">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label"><?= $lang('default_notes') ?></label>
                <textarea name="invoice_notes" class="form-input" rows="3"><?= htmlspecialchars($profile['invoice_notes'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary"><?= $lang('save') ?></button>
        </form>
    </div>
</div>

<!-- Bank Accounts -->
<div class="section">
    <h2><?= $lang('bank_accounts') ?></h2>
    <div class="form-card" style="padding:20px;">
        <p style="color:var(--gray-500); font-size:0.9em; margin-bottom:16px;">
            Możesz dodać wiele kont bankowych w dowolnej walucie (w tym kilka kont PLN). Konto oznaczone jako
            <strong style="color:var(--primary);">Domyślny</strong> będzie automatycznie wybierane na nowych fakturach.
        </p>
        <?php if (empty($bankAccounts)): ?>
            <p style="color:var(--gray-500); margin-bottom:16px;"><?= $lang('no_bank_accounts') ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table" style="margin-bottom:20px;">
                    <thead>
                        <tr>
                            <th><?= $lang('account_name') ?></th>
                            <th><?= $lang('bank_name') ?></th>
                            <th><?= $lang('account_number') ?></th>
                            <th><?= $lang('currency') ?></th>
                            <th>Domyślny</th>
                            <th><?= $lang('actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bankAccounts as $ba): ?>
                        <tr>
                            <td><?= htmlspecialchars($ba['account_name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($ba['bank_name'] ?? '') ?></td>
                            <td><code><?= htmlspecialchars($ba['account_number'] ?? '') ?></code></td>
                            <td>
                                <span class="badge" style="background:var(--gray-100); color:var(--gray-700); font-weight:600; padding:2px 8px; border-radius:4px;">
                                    <?= htmlspecialchars($ba['currency'] ?? 'PLN') ?>
                                </span>
                            </td>
                            <td style="white-space:nowrap;">
                                <?php
                                    $isDefault = !empty($ba['is_default_receiving']) || !empty($ba['is_default_outgoing']);
                                ?>
                                <?php if (!empty($ba['is_default_receiving'])): ?>
                                    <span class="badge badge-success" style="margin-right:4px;" title="Domyślne konto na fakturach sprzedaży">Domyślny (sprzedaż)</span>
                                <?php else: ?>
                                    <form method="POST" action="/client/company/bank-account/<?= $ba['id'] ?>/set-default" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="type" value="receiving">
                                        <button type="submit" class="btn btn-sm btn-secondary" title="Ustaw jako domyślne konto na fakturach sprzedaży"><?= $lang('set_as_default_receiving') ?></button>
                                    </form>
                                <?php endif; ?>
                                <?php if (!empty($ba['is_default_outgoing'])): ?>
                                    <span class="badge badge-success" title="Domyślne konto do eksportu przelewów">Domyślny (przelewy)</span>
                                <?php else: ?>
                                    <form method="POST" action="/client/company/bank-account/<?= $ba['id'] ?>/set-default" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="type" value="outgoing">
                                        <button type="submit" class="btn btn-sm btn-secondary" title="Ustaw jako domyślne konto do eksportu przelewów"><?= $lang('set_as_default_outgoing') ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td style="white-space:nowrap;">
                                <button type="button" class="btn btn-sm btn-secondary whitelist-check-btn"
                                        data-account-id="<?= $ba['id'] ?>"
                                        title="<?= $lang('check_whitelist') ?>"
                                        style="margin-right:4px;">
                                    &#x1f6e1; <?= $lang('check_whitelist') ?>
                                </button>
                                <span class="whitelist-result" id="wl-result-<?= $ba['id'] ?>" style="margin-right:6px;"></span>
                                <form method="POST" action="/client/company/bank-account/<?= $ba['id'] ?>/delete" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('<?= $lang('delete') ?>?')"><?= $lang('delete') ?></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <h3 style="margin-bottom:12px;"><?= $lang('add_bank_account') ?></h3>
        <form method="POST" action="/client/company/bank-account">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label class="form-label"><?= $lang('account_name') ?></label>
                    <input type="text" name="account_name" class="form-input" placeholder="np. PLN główny">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('bank_name') ?></label>
                    <input type="text" name="bank_name" id="new-bank-name" class="form-input" required>
                </div>
            </div>
            <div style="display:grid; grid-template-columns: 2fr 1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label class="form-label"><?= $lang('account_number') ?></label>
                    <input type="text" name="account_number" id="new-account-number" class="form-input" required maxlength="34" placeholder="PL00 0000 0000 0000 0000 0000 0000">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('swift_code') ?></label>
                    <input type="text" name="swift" class="form-input" maxlength="11">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('currency') ?></label>
                    <select name="currency" class="form-input">
                        <option value="PLN">PLN</option>
                        <option value="EUR">EUR</option>
                        <option value="USD">USD</option>
                        <option value="GBP">GBP</option>
                    </select>
                </div>
            </div>
            <div class="form-group" style="margin-bottom:8px;">
                <label style="display:flex; align-items:center; gap:8px;">
                    <input type="checkbox" name="is_default_receiving" value="1">
                    <?= $lang('default_receiving') ?> <small style="color:var(--gray-500);">(na fakturach sprzedaży)</small>
                </label>
            </div>
            <div class="form-group">
                <label style="display:flex; align-items:center; gap:8px;">
                    <input type="checkbox" name="is_default_outgoing" value="1">
                    <?= $lang('default_outgoing') ?> <small style="color:var(--gray-500);">(eksport przelewów bankowych)</small>
                </label>
            </div>
            <button type="submit" class="btn btn-primary"><?= $lang('add_bank_account') ?></button>
        </form>
    </div>
</div>

<!-- Services Catalog (moved to separate page) -->
<div class="section">
    <h2><?= $lang('services_catalog') ?></h2>
    <div class="form-card" style="padding:20px;">
        <p style="margin-bottom:12px;">
            Katalog usług i towarów został przeniesiony do osobnej strony w sekcji Dokumenty.
        </p>
        <a href="/client/services" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle; margin-right:4px;"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
            Przejdź do katalogu usług/towarów
        </a>
    </div>
</div>

<script>
function fetchFromGus() {
    const btn = document.getElementById('gus-fetch-btn');
    const status = document.getElementById('gus-status');

    btn.disabled = true;
    btn.textContent = '<?= $lang('loading') ?>...';
    status.style.display = 'none';

    fetch('/client/company/gus-lookup')
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                status.className = 'alert alert-error';
                status.textContent = data.error;
                status.style.display = 'block';
                return;
            }

            // Fill fields with GUS data
            const fields = {
                'field-street': data.street || '',
                'field-postal': data.postal || '',
                'field-city': data.city || '',
                'field-regon': data.regon || ''
            };

            let filled = 0;
            for (const [id, val] of Object.entries(fields)) {
                const el = document.getElementById(id);
                if (el && val) {
                    el.value = val;
                    el.style.borderColor = 'var(--primary)';
                    setTimeout(() => el.style.borderColor = '', 2000);
                    filled++;
                }
            }

            status.className = 'alert alert-success';
            status.textContent = '<?= $lang('gus_data_loaded') ?>' + (data.source === 'ceidg' ? ' (dane z CEIDG)' : '');
            status.style.display = 'block';
        })
        .catch(() => {
            status.className = 'alert alert-error';
            status.textContent = '<?= $lang('gus_error') ?>';
            status.style.display = 'block';
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle; margin-right:4px;"><path d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.66 0 3-4.03 3-9s-1.34-9-3-9m0 18c-1.66 0-3-4.03-3-9s1.34-9 3-9m-9 9a9 9 0 019-9"/></svg> <?= $lang('fetch_from_gus') ?>';
        });
}

<?php
// Auto-fetch on first visit if profile address fields are empty
$profileEmpty = empty($profile['address_street']) && empty($profile['address_city']) && empty($profile['regon']);
if ($profileEmpty): ?>
document.addEventListener('DOMContentLoaded', () => fetchFromGus());
<?php endif; ?>

// Live numbering preview
(function() {
    const patternInput = document.getElementById('numbering-pattern');
    const nextInput = document.getElementById('numbering-next');
    const preview = document.getElementById('numbering-preview');

    function updatePreview() {
        if (!patternInput || !preview) return;
        const now = new Date();
        const mm = String(now.getMonth() + 1).padStart(2, '0');
        const rrrr = String(now.getFullYear());
        const rr = rrrr.slice(-2);
        const nr = String(parseInt(nextInput.value) || 1).padStart(3, '0');
        let result = patternInput.value
            .replace(/\{NR\}/g, nr)
            .replace(/\{MM\}/g, mm)
            .replace(/\{RRRR\}/g, rrrr)
            .replace(/\{RR\}/g, rr)
            .replace(/\{DZIAŁ\}/g, 'A');
        preview.textContent = result;
    }

    if (patternInput) {
        patternInput.addEventListener('input', updatePreview);
        nextInput.addEventListener('input', updatePreview);
        updatePreview();
    }
})();
</script>

<script>
// F5: Auto-detect bank name from IBAN
document.addEventListener('DOMContentLoaded', function() {
    const acctInput = document.getElementById('new-account-number');
    const bankInput = document.getElementById('new-bank-name');
    if (acctInput && bankInput) {
        acctInput.addEventListener('blur', function() {
            const val = acctInput.value.trim();
            if (val.length < 10) return;
            fetch('/client/company/bank-account/identify-bank', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'csrf_token=<?= urlencode($csrf) ?>&account_number=' + encodeURIComponent(val)
            })
            .then(r => r.json())
            .then(data => {
                if (data.bank_name && !bankInput.value.trim()) {
                    bankInput.value = data.bank_name;
                    bankInput.style.borderColor = 'var(--green-500, #22c55e)';
                    setTimeout(() => bankInput.style.borderColor = '', 2000);
                }
            })
            .catch(() => {});
        });
    }

    // F4: Whitelist check buttons
    document.querySelectorAll('.whitelist-check-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const accountId = btn.dataset.accountId;
            const resultEl = document.getElementById('wl-result-' + accountId);
            btn.disabled = true;
            btn.textContent = '<?= $lang('whitelist_checking') ?>...';
            resultEl.textContent = '';

            fetch('/client/company/bank-account/whitelist-check', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'csrf_token=<?= urlencode($csrf) ?>&bank_account_id=' + accountId
            })
            .then(r => r.json())
            .then(data => {
                if (data.verified) {
                    resultEl.innerHTML = '<span style="color:var(--green-600, #16a34a); font-weight:600;">&#10003; <?= $lang('whitelist_verified') ?></span>';
                } else if (data.status === 'error') {
                    resultEl.innerHTML = '<span style="color:var(--gray-500);">&#9888; ' + (data.message || '<?= $lang('whitelist_check_error') ?>') + '</span>';
                } else {
                    resultEl.innerHTML = '<span style="color:var(--red-600, #dc2626); font-weight:600;">&#10007; <?= $lang('whitelist_not_found') ?></span>';
                }
            })
            .catch(() => {
                resultEl.innerHTML = '<span style="color:var(--gray-500);">&#9888; <?= $lang('whitelist_check_error') ?></span>';
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '&#x1f6e1; <?= $lang('check_whitelist') ?>';
            });
        });
    });
});
</script>

<style>
@media (max-width: 768px) {
    div[style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }
}
</style>
