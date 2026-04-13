<div class="section-header">
    <h1>Ustawienia</h1>
</div>

<?php if (!empty($flash_success)): ?>
    <div class="alert alert-success"><?= $lang($flash_success) ?></div>
<?php endif; ?>
<?php if (!empty($flash_error)): ?>
    <div class="alert alert-error"><?= $lang($flash_error) ?></div>
<?php endif; ?>

<form method="POST" action="/office/settings" enctype="multipart/form-data" class="form-card">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

    <h3 style="margin-bottom:12px;">Wgraj Logo biura</h3>
    <p class="text-muted" style="font-size:13px; margin-bottom:16px;">
        Logo powinno mieć format PNG lub JPG, wymiary zalecane: 200x80px do 600x200px, maksymalny rozmiar pliku: 2MB. Przezroczyste tło (PNG) zalecane.
    </p>

    <?php if (!empty($office['logo_path'])): ?>
        <div style="margin-bottom:16px; padding:16px; background:var(--gray-50); border-radius:8px; display:flex; align-items:center; gap:16px;">
            <img src="<?= htmlspecialchars($office['logo_path']) ?>" alt="Logo" style="max-height:60px; max-width:200px; border-radius:4px;">
            <div>
                <div style="font-size:13px; color:var(--gray-600);">Aktualne logo</div>
                <label class="checkbox-label" style="margin-top:4px;">
                    <input type="checkbox" name="remove_logo" value="1">
                    <span style="color:var(--error); font-size:13px;">Usuń logo</span>
                </label>
            </div>
        </div>
    <?php endif; ?>

    <div class="form-group">
        <label class="form-label">Wgraj nowe logo</label>
        <input type="file" name="logo" class="form-input" accept=".png,.jpg,.jpeg,.webp">
        <small class="form-hint">Akceptowane formaty: PNG, JPG, JPEG, WEBP. Maksymalny rozmiar: 2MB.</small>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $lang('save') ?></button>
    </div>
</form>
