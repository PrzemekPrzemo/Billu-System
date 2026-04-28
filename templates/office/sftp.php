<?php
$csrf = \App\Core\Session::generateCsrfToken();
$flash = \App\Core\Session::getFlash('success');
$hasPassword = !empty($office['sftp_password_enc']);
$hasKey      = !empty($office['sftp_private_key_enc']);
?>
<div class="section-header">
    <h1>Push plików na SFTP biura</h1>
</div>

<?php if ($flash): ?>
    <div class="alert alert-success"><?= htmlspecialchars($lang($flash) ?: $flash) ?></div>
<?php endif; ?>

<p class="text-muted" style="margin-bottom:18px;max-width:780px;">
    Skonfiguruj zdalny serwer SFTP, na który BiLLU będzie automatycznie kopiować pliki klientów (faktury, paski, załączniki).
    Dane dostępowe zapisujemy zaszyfrowane (AES-256-GCM). Hasło wpisujesz raz —
    pole pozostaje puste podczas kolejnej edycji, a hasło dalej działa, dopóki go nie zmienisz lub nie wyczyścisz.
</p>

<form method="POST" action="/office/sftp" id="sftp-form" class="form-card" style="padding:20px;max-width:780px;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

    <label class="checkbox-label" style="margin-bottom:14px;">
        <input type="checkbox" name="sftp_enabled" value="1" <?= !empty($office['sftp_enabled']) ? 'checked' : '' ?>>
        Włącz wysyłkę SFTP dla tego biura
    </label>

    <div class="form-row">
        <div class="form-group" style="flex:2;">
            <label class="form-label">Host *</label>
            <input type="text" name="sftp_host" class="form-input" required
                   placeholder="sftp.biuro.pl"
                   value="<?= htmlspecialchars($office['sftp_host'] ?? '') ?>">
        </div>
        <div class="form-group" style="flex:1;">
            <label class="form-label">Port</label>
            <input type="number" name="sftp_port" class="form-input" min="1" max="65535"
                   value="<?= htmlspecialchars((string) ($office['sftp_port'] ?? 22)) ?>">
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">Użytkownik *</label>
        <input type="text" name="sftp_user" class="form-input" required
               value="<?= htmlspecialchars($office['sftp_user'] ?? '') ?>">
    </div>

    <h3 style="margin-top:20px;font-size:14px;">Uwierzytelnianie</h3>
    <p class="text-muted" style="font-size:12px;margin-bottom:8px;">
        Preferowany sposób: hasło. Klucz prywatny jest opcjonalną alternatywą — gdy podasz oba, system spróbuje najpierw klucza, potem hasła.
    </p>

    <div class="form-group">
        <label class="form-label">Hasło <?= $hasPassword ? '(zapisane — wpisz, żeby nadpisać)' : '' ?></label>
        <input type="password" name="sftp_password" class="form-input" autocomplete="new-password"
               placeholder="<?= $hasPassword ? '••••••••' : '' ?>">
        <?php if ($hasPassword): ?>
            <label class="checkbox-label" style="font-size:12px;margin-top:4px;">
                <input type="checkbox" name="sftp_clear_password" value="1"> Usuń zapisane hasło
            </label>
        <?php endif; ?>
    </div>

    <details style="margin-top:8px;">
        <summary style="cursor:pointer;font-weight:600;font-size:13px;">Klucz prywatny SSH (opcjonalnie)</summary>
        <div class="form-group" style="margin-top:8px;">
            <label class="form-label">Klucz prywatny (PEM) <?= $hasKey ? '(zapisany — wklej, żeby nadpisać)' : '' ?></label>
            <textarea name="sftp_private_key" class="form-input" rows="6" autocomplete="off"
                      placeholder="-----BEGIN OPENSSH PRIVATE KEY-----&#10;…&#10;-----END OPENSSH PRIVATE KEY-----"></textarea>
            <?php if ($hasKey): ?>
                <label class="checkbox-label" style="font-size:12px;margin-top:4px;">
                    <input type="checkbox" name="sftp_clear_private_key" value="1"> Usuń zapisany klucz
                </label>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label class="form-label">Hasło do klucza prywatnego (jeśli zaszyfrowany)</label>
            <input type="password" name="sftp_key_passphrase" class="form-input" autocomplete="new-password">
        </div>
    </details>

    <h3 style="margin-top:20px;font-size:14px;">Lokalizacja</h3>
    <div class="form-group">
        <label class="form-label">Ścieżka bazowa na serwerze *</label>
        <input type="text" name="sftp_base_path" class="form-input" required
               placeholder="/billu/klienci"
               value="<?= htmlspecialchars($office['sftp_base_path'] ?? '/') ?>">
        <small class="form-hint">Pliki klientów lądują w <code>{base_path}/{NIP_klienta_lub_subdir}/{kategoria}/{plik}</code>.</small>
    </div>

    <div class="form-group">
        <label class="form-label">Fingerprint hosta (TOFU)</label>
        <input type="text" name="sftp_host_fingerprint" class="form-input"
               placeholder="sha256:abc..."
               value="<?= htmlspecialchars($office['sftp_host_fingerprint'] ?? '') ?>">
        <small class="form-hint">
            Po kliknięciu „Test połączenia" odczytamy fingerprint hosta — przekopiuj go do tego pola, żeby pin'nąć.
            Bez wpisanego fingerprintu BiLLU akceptuje każdy host (TOFU pierwszego użycia).
        </small>
    </div>

    <div style="display:flex;gap:8px;margin-top:18px;align-items:center;">
        <button type="submit" class="btn btn-primary"><?= $lang('save') ?></button>
        <button type="button" id="sftp-test-btn" class="btn btn-secondary">Test połączenia</button>
        <span id="sftp-test-result" style="font-size:13px;"></span>
    </div>
</form>

<h2 style="margin-top:32px;">Ostatnie 50 transferów</h2>
<?php if (empty($recent)): ?>
    <p class="text-muted">Brak transferów (kolejka jest pusta).</p>
<?php else: ?>
<div class="form-card" style="padding:0;">
    <table class="table" style="margin:0;">
        <thead>
            <tr>
                <th>#</th><th>Kategoria</th><th>Plik</th>
                <th>Status</th><th>Próby</th><th>Utworzone</th><th>Wysłane</th><th>Błąd</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($recent as $r): ?>
            <tr>
                <td class="text-muted"><?= (int) $r['id'] ?></td>
                <td><?= htmlspecialchars($r['source_type']) ?></td>
                <td><?= htmlspecialchars($r['remote_filename']) ?></td>
                <td>
                    <?php
                    $cls = match ($r['status']) {
                        'sent' => 'badge-success',
                        'failed' => 'badge-error',
                        'sending' => 'badge-warning',
                        default => 'badge-default',
                    };
                    ?>
                    <span class="badge <?= $cls ?>"><?= htmlspecialchars($r['status']) ?></span>
                </td>
                <td class="text-muted"><?= (int) $r['attempts'] ?></td>
                <td class="text-muted"><?= htmlspecialchars($r['created_at']) ?></td>
                <td class="text-muted"><?= htmlspecialchars($r['sent_at'] ?? '-') ?></td>
                <td class="text-muted" style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                    title="<?= htmlspecialchars($r['last_error'] ?? '') ?>">
                    <?= htmlspecialchars($r['last_error'] ?? '') ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<script>
document.getElementById('sftp-test-btn').addEventListener('click', function() {
    var btn = this;
    var out = document.getElementById('sftp-test-result');
    var form = document.getElementById('sftp-form');
    var fd = new FormData(form);
    btn.disabled = true;
    out.textContent = 'Testuję…';
    fetch('/office/sftp/test', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(j) {
            if (j.ok) {
                out.innerHTML = '<span style="color:var(--success);">✓ Połączenie OK</span>'
                    + (j.fingerprint ? ' · fingerprint: <code>' + j.fingerprint + '</code>' : '');
            } else {
                out.innerHTML = '<span style="color:var(--danger,#dc2626);">✗ ' + (j.message || 'Błąd') + '</span>';
            }
        })
        .catch(function(e) { out.innerHTML = '<span style="color:var(--danger,#dc2626);">✗ ' + e.message + '</span>'; })
        .finally(function() { btn.disabled = false; });
});
</script>
