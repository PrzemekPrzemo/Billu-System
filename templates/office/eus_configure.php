<?php
/**
 * @var array       $client
 * @var array|null  $config        — current EusConfig row, or null on first save
 * @var bool        $pz_available  — Profil Zaufany OAuth credentials present
 */

$cid = (int) $client['id'];
$config = $config ?? [];

$envs = [
    'mock' => 'Mock (do testów dewelopera, brak HTTP)',
    'test' => 'Test (test-eus.mf.gov.pl)',
    'prod' => 'Produkcja (eus.mf.gov.pl)',
];
$auths = [
    'cert_qual'      => 'Kwalifikowany podpis (PFX/P12)',
    'profil_zaufany' => 'Profil Zaufany (login.gov.pl)',
    'mdowod'         => 'mDowód (PR-3)',
];

$selectedEnv  = (string) ($config['environment']   ?? 'mock');
$selectedAuth = (string) ($config['auth_method']   ?? 'cert_qual');
$selectedScopeRaw = (string) ($config['upl1_scope'] ?? '');
$selectedScope    = $selectedScopeRaw !== '' ? explode(',', $selectedScopeRaw) : [];
?>
<h1>e-US: <?= htmlspecialchars((string) $client['company_name'], ENT_QUOTES) ?></h1>

<p style="margin-bottom:16px;">
    <a href="/office/eus" class="btn btn-sm">&larr; Powrót do listy</a>
    <a href="/office/clients/<?= $cid ?>/edit" class="btn btn-sm" style="margin-left:6px;">Karta klienta</a>
</p>

<?php $flashSuccess = \App\Core\Session::getFlash('success'); ?>
<?php if ($flashSuccess): ?>
    <div class="alert alert-success"><?= htmlspecialchars((string) $flashSuccess, ENT_QUOTES) ?></div>
<?php endif; ?>
<?php $flashError = \App\Core\Session::getFlash('error'); ?>
<?php if ($flashError): ?>
    <div class="alert alert-error"><?= htmlspecialchars((string) $flashError, ENT_QUOTES) ?></div>
<?php endif; ?>

<form method="POST" action="/office/eus/<?= $cid ?>/configure" class="form-card" style="max-width:720px;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">

    <div class="section">
        <h2>Środowisko + uwierzytelnianie</h2>

        <div class="form-group">
            <label class="form-label">Środowisko</label>
            <select name="environment" class="form-input">
                <?php foreach ($envs as $k => $label): ?>
                    <option value="<?= htmlspecialchars($k, ENT_QUOTES) ?>" <?= $selectedEnv === $k ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label, ENT_QUOTES) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="form-hint">
                Zacznij od <strong>mock</strong>. Po 14 dniach poprawnych testów przełącz na <strong>test</strong>,
                a po kolejnych 14 dniach na <strong>prod</strong>.
            </small>
        </div>

        <div class="form-group">
            <label class="form-label">Metoda uwierzytelniania</label>
            <select name="auth_method" class="form-input">
                <?php foreach ($auths as $k => $label): ?>
                    <option value="<?= htmlspecialchars($k, ENT_QUOTES) ?>" <?= $selectedAuth === $k ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label, ENT_QUOTES) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="form-hint">
                <?php if (!$pz_available): ?>
                    Profil Zaufany niedostępny — brak konfiguracji OAuth (skontaktuj się z master adminem).
                <?php else: ?>
                    Profil Zaufany jest dostępny — przy zapisie z tą metodą zostaniesz przekierowany do login.gov.pl.
                <?php endif; ?>
            </small>
        </div>
    </div>

    <div class="section">
        <h2>UPL-1 (pełnomocnictwo klienta)</h2>
        <p style="color:var(--gray-500); font-size:13px; margin-bottom:12px;">
            Klient musi wystawić UPL-1 (kwalifikowany podpis lub Profil Zaufany) i przekazać biuru, zanim biuro
            może działać w jego imieniu w e-US. Bez aktywnego UPL-1 system odrzuca każdą wysyłkę.
        </p>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Status UPL-1</label>
                <select name="upl1_status" class="form-input">
                    <?php foreach (['none','pending','active','revoked','expired'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($config['upl1_status'] ?? 'none') === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Ważne od</label>
                <input type="date" name="upl1_valid_from" class="form-input"
                       value="<?= htmlspecialchars((string) ($config['upl1_valid_from'] ?? ''), ENT_QUOTES) ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Ważne do</label>
                <input type="date" name="upl1_valid_to" class="form-input"
                       value="<?= htmlspecialchars((string) ($config['upl1_valid_to'] ?? ''), ENT_QUOTES) ?>">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Zakres pełnomocnictwa</label>
            <label style="display:inline-block; margin-right:16px;">
                <input type="checkbox" name="upl1_scope[]" value="declarations"
                       <?= in_array('declarations', $selectedScope, true) ? 'checked' : '' ?>>
                Deklaracje (Bramka B)
            </label>
            <label style="display:inline-block; margin-right:16px;">
                <input type="checkbox" name="upl1_scope[]" value="correspondence"
                       <?= in_array('correspondence', $selectedScope, true) ? 'checked' : '' ?>>
                Korespondencja (Bramka C)
            </label>
            <label style="display:inline-block;">
                <input type="checkbox" name="upl1_scope[]" value="full"
                       <?= in_array('full', $selectedScope, true) ? 'checked' : '' ?>>
                Pełny zakres
            </label>
        </div>
    </div>

    <div class="section">
        <h2>Bramki + automatyzacja</h2>

        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="bramka_b_enabled" value="1"
                       <?= !empty($config['bramka_b_enabled']) ? 'checked' : '' ?>>
                <strong>Bramka B</strong> — wysyłka deklaracji (JPK_V7M)
            </label>
        </div>

        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="bramka_c_enabled" value="1"
                       <?= !empty($config['bramka_c_enabled']) ? 'checked' : '' ?>>
                <strong>Bramka C</strong> — odbiór korespondencji KAS
            </label>
        </div>

        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="auto_submit_eus" value="1"
                       <?= !empty($config['auto_submit_eus']) ? 'checked' : '' ?>>
                Automatyczna wysyłka JPK_V7M do e-US po wygenerowaniu (chained ze scheduled_exports)
            </label>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Polling Bramki C — co ile minut</label>
                <input type="number" name="poll_interval_minutes" class="form-input"
                       min="15" max="60" step="5"
                       value="<?= (int) ($config['poll_interval_minutes'] ?? 15) ?>" style="width:120px;">
                <small class="form-hint">Domyślnie 15 minut. Maks. 60.</small>
            </div>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="poll_incoming_enabled" value="1"
                           <?= !empty($config['poll_incoming_enabled']) || $config === [] ? 'checked' : '' ?>>
                    Pobieraj nowe pisma KAS automatycznie
                </label>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Zapisz konfigurację</button>
    </div>
</form>

<?php if (!empty($config)): ?>
<form method="POST" action="/office/eus/<?= $cid ?>/test-connection" style="margin-top:20px; max-width:720px;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
    <button type="submit" class="btn btn-secondary">Testuj połączenie z e-US</button>
    <small style="color:var(--gray-500); margin-left:12px;">
        Wykona zapytanie /health do bramek włączonych w konfiguracji.
    </small>
</form>

<?php if (!empty($config['bramka_b_enabled'])): ?>
<div class="card" style="margin-top:20px; max-width:720px;">
    <div class="card-header">Wyślij JPK_V7M do e-US</div>
    <div class="card-body">
        <p style="color:var(--gray-500); font-size:13px; margin-bottom:12px;">
            Wymaga: aktywne UPL-1, wygenerowany lokalnie plik JPK (storage/jpk/).
            Status wysyłki pojawi się jako wiadomość w panelu klienta.
        </p>
        <form method="POST" action="/office/eus/<?= $cid ?>/submit-jpk-v7m" style="display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
            <div class="form-group" style="margin:0;">
                <label class="form-label">Okres (YYYY-MM)</label>
                <input type="text" name="period" class="form-input"
                       pattern="\d{4}-\d{2}" placeholder="2026-04"
                       value="<?= htmlspecialchars(date('Y-m', strtotime('-1 month')), ENT_QUOTES) ?>"
                       required style="width:160px;">
            </div>
            <button type="submit" class="btn btn-primary">Wyślij do e-US</button>
        </form>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php if (!empty($config['cert_subject']) || !empty($config['auth_provider_subject'])): ?>
<div class="card" style="margin-top:20px; max-width:720px;">
    <div class="card-header">Aktualne dane uwierzytelnienia</div>
    <div class="card-body">
        <?php if (!empty($config['cert_subject'])): ?>
            <p><strong>Certyfikat:</strong> <?= htmlspecialchars((string) $config['cert_subject'], ENT_QUOTES) ?></p>
            <p><small>Ważny od: <?= htmlspecialchars((string) ($config['cert_valid_from'] ?? '—'), ENT_QUOTES) ?> do <?= htmlspecialchars((string) ($config['cert_valid_to'] ?? '—'), ENT_QUOTES) ?></small></p>
        <?php endif; ?>
        <?php if (!empty($config['auth_provider_subject'])): ?>
            <p><strong>Profil Zaufany:</strong> <?= htmlspecialchars((string) $config['auth_provider_subject'], ENT_QUOTES) ?></p>
            <p><small>Ważny do: <?= htmlspecialchars((string) ($config['auth_provider_valid_to'] ?? '—'), ENT_QUOTES) ?></small></p>
        <?php endif; ?>
        <p style="color:var(--gray-500); font-size:13px;">
            Upload certyfikatu PFX i przepływ Profil Zaufany — dorzucone w follow-up commit PR-2.
        </p>
    </div>
</div>
<?php endif; ?>
