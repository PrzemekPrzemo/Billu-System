<?php $flashError = \App\Core\Session::getFlash('error'); ?>
<div class="auth-container">
    <div class="auth-card">
        <h1 class="auth-title">Aktywuj konto pracownicze</h1>
        <p class="auth-subtitle">
            Witaj <strong><?= htmlspecialchars(trim(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''))) ?></strong>!
            Ustaw hasło aby aktywować konto w panelu pracowniczym
            <?= !empty($employee['company_name']) ? 'firmy <strong>' . htmlspecialchars($employee['company_name']) . '</strong>' : '' ?>.
        </p>

        <?php if ($flashError): ?>
            <div class="alert alert-error"><?= htmlspecialchars((string) $flashError) ?></div>
        <?php endif; ?>

        <form method="POST" action="/employee/activate" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Session::generateCsrfToken()) ?>">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

            <div class="form-group">
                <label class="form-label">Email logowania</label>
                <input type="email" class="form-input" disabled
                       value="<?= htmlspecialchars($employee['login_email'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Nowe hasło</label>
                <input type="password" name="password" class="form-input" required minlength="12">
                <small class="form-hint">Min. 12 znaków, zawiera małą i wielką literę, cyfrę oraz znak specjalny.</small>
            </div>

            <div class="form-group">
                <label class="form-label">Potwierdź hasło</label>
                <input type="password" name="confirm_password" class="form-input" required minlength="12">
            </div>

            <button type="submit" class="btn btn-primary btn-full">Aktywuj konto</button>
        </form>
    </div>
</div>
