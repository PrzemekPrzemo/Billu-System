<?php $flashError = \App\Core\Session::getFlash('error'); $flashSuccess = \App\Core\Session::getFlash('success'); ?>
<div class="auth-container">
    <div class="auth-card">
        <h1 class="auth-title">Panel pracowniczy</h1>
        <p class="auth-subtitle">Zaloguj się aby zobaczyć paski wynagrodzeń, urlopy i złożyć wniosek urlopowy.</p>

        <?php if ($flashError): ?>
            <div class="alert alert-error"><?= htmlspecialchars((string) $flashError) ?></div>
        <?php endif; ?>
        <?php if ($flashSuccess): ?>
            <div class="alert alert-success"><?= htmlspecialchars((string) $flashSuccess) ?></div>
        <?php endif; ?>

        <form method="POST" action="/employee/login" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Session::generateCsrfToken()) ?>">

            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-input" required autofocus
                       placeholder="np. jan.kowalski@firma.pl">
            </div>

            <div class="form-group">
                <label class="form-label">Hasło</label>
                <input type="password" name="password" class="form-input" required>
            </div>

            <button type="submit" class="btn btn-primary btn-full">Zaloguj się</button>
        </form>

        <p class="auth-links" style="margin-top:18px;">
            <a href="/login">Logowanie firmy / biura</a>
        </p>
    </div>
</div>
