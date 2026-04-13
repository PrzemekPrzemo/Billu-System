<div class="section-header">
    <h1><?= $employee ? 'Edytuj pracownika' : 'Dodaj pracownika' ?> - <?= htmlspecialchars($client['company_name']) ?></h1>
    <a href="/office/hr/<?= $client['id'] ?>/employees" class="btn btn-secondary"><?= $lang('back') ?></a>
</div>

<?php $flash = \App\Core\Session::getFlash('error'); ?>
<?php if ($flash): ?>
    <div class="alert alert-error"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<form method="POST" action="<?= $employee ? '/office/hr/' . $client['id'] . '/employees/' . $employee['id'] . '/update' : '/office/hr/' . $client['id'] . '/employees/create' ?>" class="form-card">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

    <h3 style="margin-bottom:16px;">Dane osobowe</h3>

    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Imie *</label>
            <input type="text" name="first_name" class="form-input" required
                   value="<?= htmlspecialchars($employee['first_name'] ?? '') ?>"
                   placeholder="Jan">
        </div>
        <div class="form-group">
            <label class="form-label">Nazwisko *</label>
            <input type="text" name="last_name" class="form-input" required
                   value="<?= htmlspecialchars($employee['last_name'] ?? '') ?>"
                   placeholder="Kowalski">
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label class="form-label">PESEL *</label>
            <input type="text" name="pesel" class="form-input" required maxlength="11" pattern="\d{11}"
                   value="<?= htmlspecialchars($employee['pesel'] ?? '') ?>"
                   placeholder="12345678901">
        </div>
        <div class="form-group">
            <label class="form-label">Data urodzenia</label>
            <input type="date" name="date_of_birth" class="form-input"
                   value="<?= htmlspecialchars($employee['date_of_birth'] ?? '') ?>">
        </div>
    </div>

    <h3 style="margin-top:24px; margin-bottom:16px;">Dane kontaktowe</h3>

    <div class="form-row">
        <div class="form-group">
            <label class="form-label"><?= $lang('email') ?></label>
            <input type="email" name="email" class="form-input"
                   value="<?= htmlspecialchars($employee['email'] ?? '') ?>"
                   placeholder="jan.kowalski@email.pl">
        </div>
        <div class="form-group">
            <label class="form-label"><?= $lang('phone') ?></label>
            <input type="text" name="phone" class="form-input"
                   value="<?= htmlspecialchars($employee['phone'] ?? '') ?>"
                   placeholder="+48 123 456 789">
        </div>
    </div>

    <h3 style="margin-top:24px; margin-bottom:16px;">Adres zamieszkania</h3>

    <div class="form-row">
        <div class="form-group" style="flex:2;">
            <label class="form-label">Ulica i numer</label>
            <input type="text" name="address_street" class="form-input"
                   value="<?= htmlspecialchars($employee['address_street'] ?? '') ?>"
                   placeholder="ul. Kwiatowa 15/3">
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Kod pocztowy</label>
            <input type="text" name="address_postal_code" class="form-input" pattern="\d{2}-\d{3}"
                   value="<?= htmlspecialchars($employee['address_postal_code'] ?? '') ?>"
                   placeholder="00-001">
        </div>
        <div class="form-group">
            <label class="form-label">Miejscowosc</label>
            <input type="text" name="address_city" class="form-input"
                   value="<?= htmlspecialchars($employee['address_city'] ?? '') ?>"
                   placeholder="Warszawa">
        </div>
    </div>

    <h3 style="margin-top:24px; margin-bottom:16px;">Dane urzedowe i bankowe</h3>

    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Urzad skarbowy</label>
            <input type="text" name="tax_office" class="form-input"
                   value="<?= htmlspecialchars($employee['tax_office'] ?? '') ?>"
                   placeholder="Pierwszy Urzad Skarbowy w Warszawie">
        </div>
        <div class="form-group">
            <label class="form-label">Numer rachunku bankowego</label>
            <input type="text" name="bank_account" class="form-input" maxlength="32"
                   value="<?= htmlspecialchars($employee['bank_account'] ?? '') ?>"
                   placeholder="PL 00 0000 0000 0000 0000 0000 0000">
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Oddzial NFZ</label>
            <select name="nfz_branch" class="form-input">
                <option value="">-- Wybierz --</option>
                <?php
                    $nfzBranches = [
                        '01' => '01 - Dolnoslaski',
                        '02' => '02 - Kujawsko-Pomorski',
                        '03' => '03 - Lubelski',
                        '04' => '04 - Lubuski',
                        '05' => '05 - Lodzki',
                        '06' => '06 - Malopolski',
                        '07' => '07 - Mazowiecki',
                        '08' => '08 - Opolski',
                        '09' => '09 - Podkarpacki',
                        '10' => '10 - Podlaski',
                        '11' => '11 - Pomorski',
                        '12' => '12 - Slaski',
                        '13' => '13 - Swietokrzyski',
                        '14' => '14 - Warminsko-Mazurski',
                        '15' => '15 - Wielkopolski',
                        '16' => '16 - Zachodniopomorski',
                    ];
                    foreach ($nfzBranches as $code => $label):
                ?>
                <option value="<?= $code ?>" <?= ($employee['nfz_branch'] ?? '') === $code ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Data zatrudnienia</label>
            <input type="date" name="hired_at" class="form-input"
                   value="<?= htmlspecialchars($employee['hired_at'] ?? '') ?>">
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">Notatki</label>
        <textarea name="notes" class="form-input" rows="3" placeholder="Dodatkowe informacje..."><?= htmlspecialchars($employee['notes'] ?? '') ?></textarea>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $lang('save') ?></button>
        <a href="/office/hr/<?= $client['id'] ?>/employees" class="btn btn-secondary"><?= $lang('cancel') ?></a>
    </div>
</form>
