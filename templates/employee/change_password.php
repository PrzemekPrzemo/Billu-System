<?php $flashError = \App\Core\Session::getFlash('error'); $flashSuccess = \App\Core\Session::getFlash('success'); ?>
<div class="section-header">
    <h1>Zmiana hasła</h1>
    <a href="/employee/profile" class="btn btn-secondary">Wróć do profilu</a>
</div>

<?php if ($flashError): ?>
    <div class="alert alert-error"><?= htmlspecialchars((string) $flashError) ?></div>
<?php endif; ?>
<?php if ($flashSuccess): ?>
    <div class="alert alert-success"><?= htmlspecialchars((string) $flashSuccess) ?></div>
<?php endif; ?>

<form method="POST" action="/employee/change-password" class="form-card" style="padding:24px; max-width:520px;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Session::generateCsrfToken()) ?>">

    <div class="form-group">
        <label class="form-label">Aktualne hasło</label>
        <input type="password" name="current_password" class="form-input" required>
    </div>

    <div class="form-group">
        <label class="form-label">Nowe hasło</label>
        <input type="password" name="new_password" class="form-input" required minlength="12">
        <small class="form-hint">Min. 12 znaków, mała + wielka litera + cyfra + znak specjalny.</small>
    </div>

    <div class="form-group">
        <label class="form-label">Powtórz nowe hasło</label>
        <input type="password" name="confirm_password" class="form-input" required minlength="12">
    </div>

    <button type="submit" class="btn btn-primary">Zmień hasło</button>
</form>
