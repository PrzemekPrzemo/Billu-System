<?php
/**
 * @var array $document  — incoming KAS letter (eus_documents row, direction=in)
 * @var array $client
 */

$cid    = (int) $client['id'];
$docId  = (int) $document['id'];
$ref    = (string) ($document['reference_no'] ?? '');
$status = (string) ($document['status'] ?? 'received');

// Pre-rendered letter body for reference.
$letterPath = __DIR__ . '/../../' . ltrim((string) ($document['payload_path'] ?? ''), '/');
$letterBody = is_file($letterPath) ? (string) @file_get_contents($letterPath) : '';
?>
<h1>Odpowiedź do KAS</h1>

<p style="margin-bottom:16px;">
    <a href="/office/eus" class="btn btn-sm">&larr; Powrót do listy</a>
    <a href="/office/eus/<?= $cid ?>/configure" class="btn btn-sm" style="margin-left:6px;">Konfiguracja klienta</a>
</p>

<?php $flashError = \App\Core\Session::getFlash('error'); ?>
<?php if ($flashError): ?>
    <div class="alert alert-error"><?= htmlspecialchars((string) $flashError, ENT_QUOTES) ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom:20px; max-width:920px;">
    <div class="card-header">Pismo KAS — <?= htmlspecialchars($ref, ENT_QUOTES) ?></div>
    <div class="card-body">
        <p><strong>Klient:</strong> <?= htmlspecialchars((string) $client['company_name'], ENT_QUOTES) ?> (NIP <?= htmlspecialchars((string) $client['nip'], ENT_QUOTES) ?>)</p>
        <p><strong>Status:</strong> <code><?= htmlspecialchars($status, ENT_QUOTES) ?></code></p>
        <p><strong>Otrzymane:</strong> <?= htmlspecialchars((string) ($document['external_received_at'] ?? '—'), ENT_QUOTES) ?></p>
        <hr style="margin:12px 0;">
        <details>
            <summary style="cursor:pointer; font-weight:600;">Treść pisma</summary>
            <pre style="white-space:pre-wrap; font-size:13px; background:var(--gray-50); padding:10px; border-radius:6px; margin-top:8px;"><?= htmlspecialchars($letterBody, ENT_QUOTES) ?></pre>
        </details>
    </div>
</div>

<form method="POST" action="/office/eus/letter/<?= $docId ?>/reply" class="form-card" style="max-width:920px;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">

    <div class="section">
        <h2>Treść odpowiedzi</h2>
        <p style="color:var(--gray-500); font-size:13px; margin-bottom:12px;">
            Plain text v1 — rich text + załączniki PDF dorzucone w follow-up commit (PR-4).
            Maks. 50 000 znaków. Odpowiedź zostanie podpisana XAdES-BES (real env) lub zapisana
            jako envelope XML (mock env) i wysłana do Bramki C.
        </p>
        <div class="form-group">
            <label class="form-label">Treść</label>
            <textarea name="body" rows="14" maxlength="50000" class="form-input" required
                      style="width:100%; font-family:ui-monospace, monospace; font-size:14px;"
                      placeholder="Szanowni Państwo,&#10;&#10;w odpowiedzi na pismo numer ..."></textarea>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Wyślij odpowiedź do e-US</button>
        <a href="/office/eus" class="btn btn-secondary" style="margin-left:8px;">Anuluj</a>
    </div>
</form>
