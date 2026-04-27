<?php
$isEdit = !empty($employee);
$emp = $employee ?? [];
$action = $isEdit
    ? '/client/hr/employees/' . (int)$emp['id'] . '/update'
    : '/client/hr/employees/create';
$canLogin = !empty($emp['can_login']);
$hasPassword = !empty($emp['password_hash']);
?>
<div class="section-header">
    <h1><?= $isEdit ? 'Edytuj pracownika' : 'Dodaj pracownika' ?></h1>
    <a href="/client/hr/employees" class="btn btn-secondary"><?= $lang('back') ?></a>
</div>

<form method="POST" action="<?= htmlspecialchars($action) ?>" class="form-card" style="padding:24px;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Session::generateCsrfToken()) ?>">

    <h3 style="margin-top:0;">Dane osobowe</h3>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Imię *</label>
            <input type="text" name="first_name" class="form-input" required
                   value="<?= htmlspecialchars($emp['first_name'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Nazwisko *</label>
            <input type="text" name="last_name" class="form-input" required
                   value="<?= htmlspecialchars($emp['last_name'] ?? '') ?>">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label">PESEL</label>
            <input type="text" name="pesel" class="form-input" maxlength="11" pattern="[0-9]{11}"
                   value="<?= htmlspecialchars($emp['pesel'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Data urodzenia</label>
            <input type="date" name="date_of_birth" class="form-input"
                   value="<?= htmlspecialchars($emp['date_of_birth'] ?? '') ?>">
        </div>
    </div>

    <h3>Kontakt</h3>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Email kontaktowy (HR)</label>
            <input type="email" name="email" class="form-input"
                   value="<?= htmlspecialchars($emp['email'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Telefon</label>
            <input type="text" name="phone" class="form-input"
                   value="<?= htmlspecialchars($emp['phone'] ?? '') ?>">
        </div>
    </div>

    <h3>Adres</h3>
    <div class="form-group">
        <label class="form-label">Ulica i numer</label>
        <input type="text" name="address_street" class="form-input"
               value="<?= htmlspecialchars($emp['address_street'] ?? '') ?>">
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Kod pocztowy</label>
            <input type="text" name="address_postal_code" class="form-input" maxlength="10"
                   value="<?= htmlspecialchars($emp['address_postal_code'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Miasto</label>
            <input type="text" name="address_city" class="form-input"
                   value="<?= htmlspecialchars($emp['address_city'] ?? '') ?>">
        </div>
    </div>

    <h3>Kadry / Płace</h3>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Urząd Skarbowy</label>
            <input type="text" name="tax_office" class="form-input"
                   value="<?= htmlspecialchars($emp['tax_office'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Oddział NFZ (01-16)</label>
            <input type="text" name="nfz_branch" class="form-input" maxlength="4"
                   value="<?= htmlspecialchars($emp['nfz_branch'] ?? '') ?>">
        </div>
    </div>
    <div class="form-group">
        <label class="form-label">Numer rachunku bankowego (IBAN)</label>
        <input type="text" name="bank_account" class="form-input"
               value="<?= htmlspecialchars($emp['bank_account'] ?? '') ?>">
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Data zatrudnienia</label>
            <input type="date" name="hired_at" class="form-input"
                   value="<?= htmlspecialchars($emp['hired_at'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Data zakończenia (opcjonalnie)</label>
            <input type="date" name="terminated_at" class="form-input"
                   value="<?= htmlspecialchars($emp['terminated_at'] ?? '') ?>">
        </div>
    </div>

    <h3>Konto pracownicze</h3>
    <p class="form-hint" style="margin-top:0;">
        Włącz aby pracownik mógł logować się do własnego panelu (wnioski urlopowe, paski wynagrodzeń).
        Po zapisaniu zostanie wysłany email z linkiem aktywacyjnym.
    </p>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Email logowania</label>
            <input type="email" name="login_email" class="form-input"
                   placeholder="np. jan.kowalski@firma.pl"
                   value="<?= htmlspecialchars($emp['login_email'] ?? '') ?>">
            <?php if ($isEdit && $hasPassword): ?>
                <small class="form-hint">Pracownik ma już aktywne hasło — zmiana e-maila nie wymusi ponownego ustawienia.</small>
            <?php endif; ?>
        </div>
        <div class="form-group" style="display:flex; align-items:center;">
            <label class="checkbox-label">
                <input type="checkbox" name="can_login" value="1" <?= $canLogin ? 'checked' : '' ?>>
                Pracownik może się logować
            </label>
        </div>
    </div>

    <h3>Status i notatki</h3>
    <label class="checkbox-label">
        <input type="checkbox" name="is_active" value="1" <?= ($emp['is_active'] ?? 1) ? 'checked' : '' ?>>
        Aktywny
    </label>
    <div class="form-group">
        <label class="form-label">Notatki wewnętrzne</label>
        <textarea name="notes" class="form-input" rows="3"><?= htmlspecialchars($emp['notes'] ?? '') ?></textarea>
    </div>

    <div style="margin-top:24px; display:flex; gap:12px;">
        <button type="submit" class="btn btn-primary">
            <?= $isEdit ? 'Zapisz zmiany' : 'Dodaj pracownika' ?>
        </button>
        <a href="/client/hr/employees" class="btn btn-secondary"><?= $lang('cancel') ?></a>

        <?php if ($isEdit && !empty($emp['login_email']) && $canLogin && !$hasPassword): ?>
            <button type="submit"
                    formaction="/client/hr/employees/<?= (int)$emp['id'] ?>/resend-invitation"
                    class="btn btn-secondary"
                    style="margin-left:auto;">
                Wyślij ponownie zaproszenie
            </button>
        <?php endif; ?>
    </div>
</form>
