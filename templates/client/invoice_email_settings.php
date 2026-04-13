<div class="section-header" style="margin-bottom:24px;">
    <h1><?= $lang('invoice_email_settings') ?></h1>
</div>

<?php if (!empty($flash_success)): ?>
    <div class="alert alert-success"><?= $lang($flash_success) ?></div>
<?php endif; ?>
<?php if (!empty($flash_error)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($flash_error) ?></div>
<?php endif; ?>

<!-- Branding Section -->
<form method="POST" action="/client/sales/email-settings" enctype="multipart/form-data" class="form-card" style="margin-bottom:20px;">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
    <input type="hidden" name="_section" value="branding">

    <h3 style="margin-bottom:6px;"><?= $lang('client_email_branding') ?></h3>
    <p style="font-size:13px; color:var(--gray-500); margin-bottom:16px;">
        <?= $lang('email_branding_description') ?>
    </p>

    <div class="form-row">
        <div class="form-group">
            <label class="form-label"><?= $lang('header_color') ?></label>
            <div style="display:flex; gap:8px; align-items:center;">
                <input type="color" name="header_color" id="header-color"
                       value="<?= htmlspecialchars($emailTemplate['header_color'] ?? '#008F8F') ?>"
                       style="width:50px; height:38px; border:1px solid var(--gray-300); border-radius:6px; cursor:pointer;">
                <input type="text" id="header-color-text" class="form-input" style="width:100px;"
                       value="<?= htmlspecialchars($emailTemplate['header_color'] ?? '#008F8F') ?>" readonly>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label"><?= $lang('logo_in_emails') ?></label>
            <label class="checkbox-label" style="margin-top:8px;">
                <input type="checkbox" name="logo_in_emails" value="1"
                       <?= ($emailTemplate['logo_in_emails'] ?? 0) ? 'checked' : '' ?>>
                <?= $lang('show_logo_in_emails') ?>
            </label>
            <?php if (!empty($emailTemplate['logo_path'])): ?>
                <div style="margin-top:8px; display:flex; align-items:center; gap:12px;">
                    <img src="<?= htmlspecialchars($emailTemplate['logo_path']) ?>" alt="Logo" style="max-height:40px; max-width:180px; border-radius:4px; border:1px solid var(--gray-200);">
                    <label class="checkbox-label" style="color:var(--error); font-size:12px;">
                        <input type="checkbox" name="remove_logo" value="1">
                        <?= $lang('remove_logo') ?>
                    </label>
                </div>
            <?php endif; ?>
            <div style="margin-top:8px;">
                <label class="form-label" style="font-size:13px;"><?= $lang('upload_logo') ?></label>
                <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="form-input" style="font-size:13px;">
                <small class="form-hint"><?= $lang('logo_requirements') ?></small>
            </div>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label"><?= $lang('footer_text') ?></label>
        <textarea name="footer_text" class="wysiwyg-editor" rows="3"><?= htmlspecialchars($emailTemplate['footer_text'] ?? '') ?></textarea>
        <small class="form-hint"><?= $lang('footer_text_hint') ?></small>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $lang('save') ?></button>
    </div>
</form>

<!-- Placeholders -->
<div class="form-card" style="margin-bottom:20px;">
    <h3 style="margin-bottom:8px;"><?= $lang('available_placeholders') ?></h3>
    <p style="font-size:13px; color:var(--gray-500); margin-bottom:8px;">
        <?= $lang('invoice_placeholders_hint') ?>
    </p>
    <div style="display:flex; flex-wrap:wrap; gap:6px;">
        <?php
        $placeholders = [
            'invoice_number' => $lang('ph_invoice_number'),
            'gross_amount'   => $lang('ph_gross_amount'),
            'net_amount'     => $lang('ph_net_amount'),
            'currency'       => $lang('ph_currency'),
            'due_date'       => $lang('ph_due_date'),
            'issue_date'     => $lang('ph_issue_date'),
            'sale_date'      => $lang('ph_sale_date'),
            'contractor_name' => $lang('ph_contractor_name'),
            'seller_name'    => $lang('ph_seller_name'),
        ];
        foreach ($placeholders as $ph => $desc): ?>
            <span class="badge badge-info" style="cursor:pointer; font-size:12px; padding:4px 10px;" title="<?= htmlspecialchars($desc) ?>"
                  onclick="navigator.clipboard.writeText('{{<?= $ph ?>}}'); this.style.opacity='0.5'; setTimeout(()=>this.style.opacity='1',300);">{{<?= $ph ?>}}</span>
        <?php endforeach; ?>
    </div>
</div>

<!-- Template Section with PL/EN tabs -->
<form method="POST" action="/client/sales/email-settings" class="form-card">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
    <input type="hidden" name="_section" value="template">

    <!-- Language Tabs -->
    <div style="display:flex; gap:0; margin-bottom:16px; border-bottom:2px solid var(--gray-200);">
        <button type="button" class="email-lang-tab active" onclick="switchLangTab('pl')"
                id="tab-pl" style="padding:8px 20px; border:none; background:none; cursor:pointer; font-weight:600; font-size:14px; border-bottom:2px solid var(--primary); margin-bottom:-2px; color:var(--primary);">
            &#127477;&#127473; Polski
        </button>
        <button type="button" class="email-lang-tab" onclick="switchLangTab('en')"
                id="tab-en" style="padding:8px 20px; border:none; background:none; cursor:pointer; font-weight:600; font-size:14px; border-bottom:2px solid transparent; margin-bottom:-2px; color:var(--gray-500);">
            &#127468;&#127463; English
        </button>
    </div>

    <!-- PL Template -->
    <div id="lang-pl">
        <div class="form-group">
            <label class="form-label"><?= $lang('subject') ?> (PL)</label>
            <input type="text" name="subject_template_pl" class="form-input"
                   value="<?= htmlspecialchars($emailTemplate['subject_template_pl'] ?? 'Faktura {{invoice_number}}') ?>">
        </div>
        <div class="form-group">
            <label class="form-label"><?= $lang('body') ?> (PL)</label>
            <textarea name="body_template_pl" class="wysiwyg-editor" rows="10"><?= htmlspecialchars($emailTemplate['body_template_pl'] ?? $defaultBodyPl) ?></textarea>
        </div>
    </div>

    <!-- EN Template -->
    <div id="lang-en" style="display:none;">
        <div class="form-group">
            <label class="form-label">Subject (EN)</label>
            <input type="text" name="subject_template_en" class="form-input"
                   value="<?= htmlspecialchars($emailTemplate['subject_template_en'] ?? 'Invoice {{invoice_number}}') ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Body (EN)</label>
            <textarea name="body_template_en" class="wysiwyg-editor" rows="10"><?= htmlspecialchars($emailTemplate['body_template_en'] ?? $defaultBodyEn) ?></textarea>
        </div>
    </div>

    <div class="form-actions" style="margin-top:16px;">
        <button type="submit" class="btn btn-primary"><?= $lang('save') ?></button>
        <a href="/client/sales" class="btn btn-secondary"><?= $lang('cancel') ?></a>
    </div>
</form>

<!-- Preview -->
<div class="form-card" style="margin-top:20px;">
    <h3 style="margin-bottom:8px;"><?= $lang('preview') ?> (PL)</h3>
    <div style="border:1px solid var(--gray-200); border-radius:8px; overflow:hidden; max-width:500px; box-shadow:0 2px 8px rgba(0,0,0,0.08);">
        <div id="preview-header" style="background:<?= htmlspecialchars($emailTemplate['header_color'] ?? '#008F8F') ?>; color:white; padding:16px;">
            <?php if (!empty($emailTemplate['logo_path']) && ($emailTemplate['logo_in_emails'] ?? 0)): ?>
                <img src="<?= htmlspecialchars($emailTemplate['logo_path']) ?>" alt="Logo" style="max-height:30px; max-width:150px; margin-bottom:6px; display:block;">
            <?php endif; ?>
            <strong><?= $lang('sample_email_subject') ?></strong>
        </div>
        <div style="padding:16px; font-size:13px;">
            <?php
            $sampleVars = [
                'invoice_number' => 'FV/2026/04/001',
                'gross_amount' => '1 230,00',
                'net_amount' => '1 000,00',
                'currency' => 'PLN',
                'due_date' => '2026-04-18',
                'issue_date' => '2026-04-04',
                'sale_date' => '2026-04-04',
                'contractor_name' => 'Przykładowa Firma Sp. z o.o.',
                'seller_name' => 'Twoja Firma',
            ];
            $bodyTpl = $emailTemplate['body_template_pl'] ?? $defaultBodyPl;
            echo \App\Models\ClientInvoiceEmailTemplate::renderBody($bodyTpl, $sampleVars);
            ?>
        </div>
        <div style="padding:8px; text-align:center; color:#9ca3af; font-size:11px; border-top:1px solid var(--gray-100);">
            <?= htmlspecialchars($emailTemplate['footer_text'] ?? 'BiLLU Financial Solutions - System weryfikacji faktur') ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../_partials/wysiwyg.php'; ?>
<script>
document.getElementById('header-color').addEventListener('input', function() {
    document.getElementById('header-color-text').value = this.value;
    document.getElementById('preview-header').style.background = this.value;
});

function switchLangTab(lang) {
    document.getElementById('lang-pl').style.display = lang === 'pl' ? '' : 'none';
    document.getElementById('lang-en').style.display = lang === 'en' ? '' : 'none';
    document.getElementById('tab-pl').style.borderBottomColor = lang === 'pl' ? 'var(--primary)' : 'transparent';
    document.getElementById('tab-pl').style.color = lang === 'pl' ? 'var(--primary)' : 'var(--gray-500)';
    document.getElementById('tab-en').style.borderBottomColor = lang === 'en' ? 'var(--primary)' : 'transparent';
    document.getElementById('tab-en').style.color = lang === 'en' ? 'var(--primary)' : 'var(--gray-500)';
}
</script>
