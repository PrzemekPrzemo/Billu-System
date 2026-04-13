<h1><?= $lang($isEdit ? 'edit_contractor' : 'new_contractor') ?></h1>

<?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="section">
    <div class="form-card" style="padding:20px; max-width:700px;">
        <form method="POST" action="<?= $isEdit ? '/client/contractors/' . $contractor['id'] . '/update' : '/client/contractors/create' ?>">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

            <div style="display:grid; grid-template-columns: 1fr auto; gap:12px; align-items:end;">
                <div class="form-group">
                    <label class="form-label"><?= $lang('nip') ?></label>
                    <input type="text" name="nip" id="contractor-nip" class="form-input" value="<?= htmlspecialchars($contractor['nip'] ?? '') ?>" maxlength="20">
                </div>
                <div class="form-group">
                    <button type="button" id="gus-lookup-btn" class="btn btn-primary" style="margin-bottom:4px;"><?= $lang('gus_lookup') ?></button>
                </div>
            </div>
            <small class="form-hint" style="margin-top:-8px; display:block; margin-bottom:12px;"><?= $lang('gus_lookup_hint') ?></small>

            <div id="gus-message" style="display:none; margin-bottom:12px;"></div>

            <div class="form-group">
                <label class="form-label"><?= $lang('company_name') ?> * <small style="font-weight:normal; color:var(--gray-500);">(pelna nazwa z GUS, uzywana na fakturach)</small></label>
                <input type="text" name="company_name" id="contractor-company" class="form-input" value="<?= htmlspecialchars($contractor['company_name'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Skrocona nazwa <small style="font-weight:normal; color:var(--gray-500);">(wyswietlana w systemie, opcjonalna)</small></label>
                <input type="text" name="short_name" id="contractor-short-name" class="form-input" value="<?= htmlspecialchars($contractor['short_name'] ?? '') ?>" maxlength="100" placeholder="np. Firma ABC">
            </div>

            <div style="display:grid; grid-template-columns: 2fr 1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label class="form-label"><?= $lang('address_street') ?></label>
                    <input type="text" name="address_street" id="contractor-street" class="form-input" value="<?= htmlspecialchars($contractor['address_street'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('address_postal') ?></label>
                    <input type="text" name="address_postal" id="contractor-postal" class="form-input" value="<?= htmlspecialchars($contractor['address_postal'] ?? '') ?>" placeholder="00-000">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('address_city') ?></label>
                    <input type="text" name="address_city" id="contractor-city" class="form-input" value="<?= htmlspecialchars($contractor['address_city'] ?? '') ?>">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label class="form-label"><?= $lang('email') ?></label>
                    <input type="email" name="email" class="form-input" value="<?= htmlspecialchars($contractor['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('phone') ?></label>
                    <input type="text" name="phone" class="form-input" value="<?= htmlspecialchars($contractor['phone'] ?? '') ?>">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label class="form-label"><?= $lang('contact_person') ?></label>
                    <input type="text" name="contact_person" class="form-input" value="<?= htmlspecialchars($contractor['contact_person'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('default_payment_days') ?></label>
                    <input type="number" name="default_payment_days" class="form-input" value="<?= htmlspecialchars($contractor['default_payment_days'] ?? '') ?>" min="0" max="365">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label"><?= $lang('notes') ?></label>
                <textarea name="notes" class="form-input" rows="3"><?= htmlspecialchars($contractor['notes'] ?? '') ?></textarea>
            </div>

            <div style="display:flex; gap:12px;">
                <button type="submit" class="btn btn-primary"><?= $lang('save') ?></button>
                <a href="/client/contractors" class="btn"><?= $lang('cancel') ?></a>
            </div>
        </form>

        <?php if ($isEdit): ?>
        <div style="margin-top:24px; border-top:1px solid var(--gray-200); padding-top:16px;">
            <h3><?= $lang('contractor_logo') ?></h3>
            <?php if (!empty($contractor['logo_path'])): ?>
                <div style="margin-bottom:12px;">
                    <img src="/<?= htmlspecialchars($contractor['logo_path']) ?>" alt="Logo" style="max-height:80px; max-width:200px; border:1px solid var(--gray-200); border-radius:4px; padding:4px;">
                </div>
                <form method="POST" action="/client/contractors/<?= $contractor['id'] ?>/logo/delete" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <button type="submit" class="btn btn-sm btn-danger"><?= $lang('delete_logo') ?></button>
                </form>
            <?php endif; ?>
            <form method="POST" action="/client/contractors/<?= $contractor['id'] ?>/logo" enctype="multipart/form-data" style="margin-top:8px;">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <div class="form-group">
                    <input type="file" name="logo" class="form-input" accept="image/png,image/jpeg,image/gif" style="max-width:300px;">
                </div>
                <button type="submit" class="btn btn-sm"><?= $lang('upload_logo') ?></button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('gus-lookup-btn')?.addEventListener('click', function() {
    const nip = document.getElementById('contractor-nip').value.replace(/[\s-]/g, '');
    if (!nip || nip.length < 10) return;

    const msg = document.getElementById('gus-message');
    msg.style.display = 'block';
    msg.className = 'alert';
    msg.textContent = '...';

    fetch('/client/contractors/gus-lookup?nip=' + encodeURIComponent(nip))
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                msg.className = 'alert alert-error';
                msg.textContent = data.error;
                return;
            }
            var sourceLabel = data.source === 'ceidg' ? ' (dane z CEIDG)' : (data.source === 'gus' ? ' (dane z GUS)' : '');
            msg.className = 'alert alert-success';
            msg.textContent = '<?= $lang('success') ?? "OK" ?>' + sourceLabel;
            if (data.company_name) document.getElementById('contractor-company').value = data.company_name;
            if (data.street) document.getElementById('contractor-street').value = data.street;
            if (data.postal) document.getElementById('contractor-postal').value = data.postal;
            if (data.city) document.getElementById('contractor-city').value = data.city;
        })
        .catch(() => {
            msg.className = 'alert alert-error';
            msg.textContent = '<?= $lang('gus_error') ?>';
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
