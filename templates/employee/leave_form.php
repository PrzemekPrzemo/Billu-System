<?php $flashError = \App\Core\Session::getFlash('error'); ?>
<div class="section-header">
    <h1>Wniosek urlopowy</h1>
    <a href="/employee/leaves" class="btn btn-secondary"><?= $lang('back') ?></a>
</div>

<?php if ($flashError): ?>
    <div class="alert alert-error"><?= htmlspecialchars((string) $flashError) ?></div>
<?php endif; ?>

<?php if (empty($contract)): ?>
    <div class="alert alert-warning">
        Nie masz aktywnej umowy w systemie. Skontaktuj się z firmą żeby uzupełnić dane umowy
        zanim złożysz wniosek urlopowy.
    </div>
<?php else: ?>
<form method="POST" action="/employee/leaves/request" class="form-card" style="padding:24px; max-width:640px;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Session::generateCsrfToken()) ?>">

    <div class="form-group">
        <label class="form-label">Typ urlopu *</label>
        <select name="leave_type" class="form-input" required>
            <option value="wypoczynkowy">Wypoczynkowy</option>
            <option value="na_zadanie">Na żądanie (4 dni / rok)</option>
            <option value="okolicznosciowy">Okolicznościowy</option>
            <option value="bezplatny">Bezpłatny</option>
            <option value="opieka_art188">Opieka nad dzieckiem (art. 188)</option>
            <option value="chorobowy">Chorobowy</option>
            <option value="macierzynski">Macierzyński</option>
            <option value="ojcowski">Ojcowski</option>
            <option value="wychowawczy">Wychowawczy</option>
        </select>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Data od *</label>
            <input type="date" name="start_date" class="form-input" required>
        </div>
        <div class="form-group">
            <label class="form-label">Data do *</label>
            <input type="date" name="end_date" class="form-input" required>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">Notatka (opcjonalnie)</label>
        <textarea name="notes" class="form-input" rows="3"
                  placeholder="Powód, ważne uwagi…"></textarea>
    </div>

    <p class="form-hint">
        Wniosek zostanie przekazany do akceptacji pracodawcy.
        Status zobaczysz w zakładce „Moje urlopy".
    </p>

    <button type="submit" class="btn btn-primary">Złóż wniosek</button>
</form>
<?php endif; ?>
