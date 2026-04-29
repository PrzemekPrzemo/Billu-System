<h1>Diagnostyka CEIDG API</h1>

<div class="section">
    <div class="form-card" style="padding:16px;">
        <form method="get" action="/admin/ceidg-diagnostic" style="display:flex; gap:12px; align-items:end; flex-wrap:wrap; margin-bottom:20px;">
            <div>
                <label for="nip">NIP do testu</label>
                <input type="text" name="nip" id="nip" class="form-input" style="width:200px;"
                       value="<?= htmlspecialchars($nip) ?>" placeholder="0000000000" maxlength="10" pattern="[0-9]{10}">
            </div>
            <button type="submit" class="btn btn-primary">Uruchom diagnostykę</button>
            <a href="/admin/api-settings" class="btn btn-secondary"><?= $lang('gus_settings') ?></a>
        </form>

        <h2 style="margin-top:0;">Wyniki diagnostyki CEIDG API</h2>

        <?php foreach ($steps as $i => $step): ?>
        <div style="border:1px solid <?= $step['ok'] ? '#2ecc71' : '#e74c3c' ?>; border-radius:8px; padding:12px; margin-bottom:12px; background:<?= $step['ok'] ? 'rgba(46,204,113,0.05)' : 'rgba(231,76,60,0.05)' ?>;">
            <h3 style="margin:0 0 8px 0;">
                <span style="font-size:1.2em;"><?= $step['ok'] ? '&#10004;' : '&#10008;' ?></span>
                Krok <?= $i + 1 ?>: <?= htmlspecialchars($step['name']) ?>
            </h3>
            <?php if (!empty($step['details'])): ?>
            <table class="table" style="margin:0;">
                <?php foreach ($step['details'] as $key => $val): ?>
                <tr>
                    <th style="width:200px; white-space:nowrap;"><?= htmlspecialchars($key) ?></th>
                    <td>
                        <?php if (is_array($val)): ?>
                            <ul style="margin:0; padding-left:20px;">
                            <?php foreach ($val as $item): ?>
                                <li><?= htmlspecialchars((string)$item) ?></li>
                            <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <?= htmlspecialchars((string)$val) ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if (!empty($logContent)): ?>
<div class="section">
    <h2>Log CEIDG API</h2>
    <p style="color:#888; font-size:0.9em;">Pełny log zapytań i odpowiedzi REST. Skopiuj i przekaż do analizy.</p>
    <div style="position:relative;">
        <button type="button" onclick="copyCeidgLog()" class="btn btn-secondary" style="position:absolute; top:8px; right:8px; padding:4px 12px; font-size:0.85em;">Kopiuj log</button>
        <textarea id="ceidg-log" readonly style="width:100%; height:500px; font-family:monospace; font-size:0.8em; background:#1a1a2e; color:#e0e0e0; border:1px solid #333; border-radius:8px; padding:12px; white-space:pre; overflow:auto; resize:vertical;"><?= htmlspecialchars($logContent) ?></textarea>
    </div>
</div>

<script>
function copyCeidgLog() {
    var ta = document.getElementById('ceidg-log');
    ta.select();
    ta.setSelectionRange(0, 999999);
    document.execCommand('copy');
    var btn = event.target;
    var orig = btn.textContent;
    btn.textContent = 'Skopiowano!';
    setTimeout(function() { btn.textContent = orig; }, 2000);
}
</script>
<?php endif; ?>

<div style="margin-top:16px;">
    <a href="/admin/api-settings" class="btn btn-secondary"><?= $lang('back') ?></a>
</div>
