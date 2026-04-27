<?php $emp = $employee; ?>
<div class="section-header">
    <h1>Mój profil</h1>
    <a href="/employee/change-password" class="btn btn-secondary">Zmień hasło</a>
</div>

<div class="form-card" style="padding:24px;">
    <h2 style="margin-top:0;">Dane osobowe</h2>
    <dl style="display:grid; grid-template-columns:200px 1fr; gap:8px 16px;">
        <dt>Imię i nazwisko</dt><dd><?= htmlspecialchars(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '')) ?></dd>
        <dt>PESEL</dt><dd><?= htmlspecialchars($emp['pesel'] ?? '-') ?></dd>
        <dt>Email logowania</dt><dd><?= htmlspecialchars($emp['login_email'] ?? '-') ?></dd>
        <dt>Email kontaktowy</dt><dd><?= htmlspecialchars($emp['email'] ?? '-') ?></dd>
        <dt>Telefon</dt><dd><?= htmlspecialchars($emp['phone'] ?? '-') ?></dd>
    </dl>

    <h2>Adres</h2>
    <dl style="display:grid; grid-template-columns:200px 1fr; gap:8px 16px;">
        <dt>Ulica</dt><dd><?= htmlspecialchars($emp['address_street'] ?? '-') ?></dd>
        <dt>Kod pocztowy / Miasto</dt><dd><?= htmlspecialchars(($emp['address_postal_code'] ?? '') . ' ' . ($emp['address_city'] ?? '')) ?></dd>
    </dl>

    <h2>Zatrudnienie</h2>
    <dl style="display:grid; grid-template-columns:200px 1fr; gap:8px 16px;">
        <dt>Data zatrudnienia</dt><dd><?= htmlspecialchars($emp['hired_at'] ?? '-') ?></dd>
        <dt>Rachunek bankowy</dt><dd><?= htmlspecialchars($emp['bank_account'] ?? '-') ?></dd>
        <dt>Urząd Skarbowy</dt><dd><?= htmlspecialchars($emp['tax_office'] ?? '-') ?></dd>
        <dt>Oddział NFZ</dt><dd><?= htmlspecialchars($emp['nfz_branch'] ?? '-') ?></dd>
    </dl>

    <p class="text-muted" style="margin-top:24px;">
        Aby zmienić dane osobowe, skontaktuj się z firmą lub działem kadr.
        Dane można zmodyfikować tylko po stronie pracodawcy.
    </p>
</div>
