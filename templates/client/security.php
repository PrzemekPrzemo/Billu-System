<h1><?= $lang('security') ?></h1>

<a href="/trusted-devices" class="form-card" style="display:flex;align-items:center;gap:12px;padding:14px 18px;text-decoration:none;color:inherit;margin-bottom:16px;">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
    <div style="flex:1;">
        <div style="font-weight:600;"><?= $lang('trusted_devices') ?></div>
        <div class="text-muted" style="font-size:13px;"><?= $lang('trusted_devices_intro') ?></div>
    </div>
    <span aria-hidden="true">&rarr;</span>
</a>

<div class="section">
    <h2><?= $lang('change_password') ?></h2>
    <div class="form-card" style="padding:16px; max-width:500px;">

        <?php if ($passwordDaysLeft <= 14): ?>
        <div class="alert <?= $passwordDaysLeft === 0 ? 'alert-error' : 'alert-warning' ?>" style="margin-bottom:16px;">
            <?php if ($passwordDaysLeft === 0): ?>
                <?= $lang('password_expires_today') ?>
            <?php else: ?>
                <?= sprintf($lang('password_expires_in'), $passwordDaysLeft) ?>
            <?php endif; ?>
            <?= $lang('password_change_recommended') ?>
        </div>
        <?php endif; ?>

        <div style="margin-bottom:16px;font-size:13px;color:var(--gray-500);">
            <div><strong><?= $lang('password_last_changed') ?>:</strong> <?= htmlspecialchars($passwordChangedAt) ?></div>
            <div><strong><?= $lang('password_expiry_date') ?>:</strong> <?= htmlspecialchars($passwordExpiryDate) ?>
                (<?= $passwordDaysLeft ?> <?= $lang('days_left') ?>)</div>
        </div>

        <form method="POST" action="/change-password">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

            <div class="form-group">
                <label class="form-label"><?= $lang('current_password') ?></label>
                <input type="password" name="current_password" class="form-input" required autocomplete="current-password">
            </div>

            <div class="form-group">
                <label class="form-label"><?= $lang('new_password') ?></label>
                <input type="password" name="new_password" class="form-input" required minlength="12" autocomplete="new-password">
                <small class="form-hint"><?= $lang('password_requirements') ?></small>
            </div>

            <div class="form-group">
                <label class="form-label"><?= $lang('confirm_password') ?></label>
                <input type="password" name="confirm_password" class="form-input" required autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-primary"><?= $lang('change_password_button') ?></button>
        </form>
    </div>
</div>

<div class="section">
    <h2><?= $lang('2fa_settings') ?></h2>
    <div class="form-card" style="padding:16px; max-width:500px;">
        <?php include __DIR__ . '/../partials/two_factor_profile.php'; ?>
    </div>
</div>

<div class="section" style="margin-top:40px;">
    <h2 style="color:var(--danger);">Usuń konto i dane</h2>
    <div class="form-card rodo-delete-warning" style="padding:20px; max-width:600px; border:2px solid var(--danger); border-radius:var(--radius);">
        <div class="alert alert-error" style="margin-bottom:16px; background:rgba(220,38,38,0.08); border:1px solid rgba(220,38,38,0.25);">
            <strong>Uwaga! Ta operacja jest nieodwracalna.</strong>
            <p style="margin-top:8px; margin-bottom:0;">Usunięcie konta spowoduje trwałe usunięcie:</p>
            <ul style="margin:8px 0 0 16px; padding:0;">
                <li>Wszystkich faktur zakupowych i sprzedażowych</li>
                <li>Wiadomości i załączników</li>
                <li>Plików udostępnionych</li>
                <li>Zadań i notatek</li>
                <li>Danych firmy, kontrahentów i konfiguracji KSeF</li>
                <li>Całej historii działań na koncie</li>
            </ul>
            <p style="margin-top:8px; margin-bottom:0;">Twoje biuro rachunkowe zostanie powiadomione o usunięciu konta.</p>
        </div>

        <form method="POST" action="/client/account/delete" id="rodo-delete-form">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

            <div class="form-group">
                <label class="form-label">Aby potwierdzić, wpisz <strong>USUN</strong></label>
                <input type="text" name="confirm_text" id="rodo-confirm-text" class="form-input" placeholder="Wpisz USUN" autocomplete="off" required style="max-width:250px;">
            </div>

            <div class="form-group">
                <label class="form-label">Potwierdź hasłem</label>
                <input type="password" name="password" class="form-input" required autocomplete="current-password" style="max-width:250px;">
            </div>

            <button type="submit" id="rodo-delete-btn" class="btn" style="background:var(--danger); color:#fff; opacity:0.5; cursor:not-allowed;" disabled>
                Usuń konto i wszystkie dane
            </button>
        </form>
    </div>
</div>

<script>
(function() {
    var confirmInput = document.getElementById('rodo-confirm-text');
    var deleteBtn = document.getElementById('rodo-delete-btn');
    var deleteForm = document.getElementById('rodo-delete-form');

    if (confirmInput && deleteBtn) {
        confirmInput.addEventListener('input', function() {
            var enabled = this.value.trim() === 'USUN';
            deleteBtn.disabled = !enabled;
            deleteBtn.style.opacity = enabled ? '1' : '0.5';
            deleteBtn.style.cursor = enabled ? 'pointer' : 'not-allowed';
        });
    }

    if (deleteForm) {
        deleteForm.addEventListener('submit', function(e) {
            if (!confirm('Czy na pewno chcesz trwale usunąć swoje konto i wszystkie dane? Tej operacji nie można cofnąć.')) {
                e.preventDefault();
            }
        });
    }
})();
</script>
