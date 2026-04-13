<h1><?= $lang('ksef_diagnostics') ?></h1>

<div class="section">
    <div class="form-card" style="padding:16px;">
        <form method="get" action="/client/ksef/diagnostic" style="display:flex; gap:12px; align-items:end; flex-wrap:wrap; margin-bottom:20px;">
            <div>
                <label for="month"><?= $lang('month') ?></label>
                <select name="month" id="month" class="form-input" style="width:auto;">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= ($month == $m) ? 'selected' : '' ?>><?= sprintf('%02d', $m) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label for="year"><?= $lang('year') ?></label>
                <select name="year" id="year" class="form-input" style="width:auto;">
                    <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 3; $y--): ?>
                    <option value="<?= $y ?>" <?= ($year == $y) ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><?= $lang('ksef_run_diagnostics') ?></button>
        </form>

        <h2 style="margin-top:0;">Wyniki diagnostyki</h2>

        <?php foreach ($steps as $i => $step): ?>
        <div style="border:1px solid <?= $step['ok'] ? '#2ecc71' : '#e74c3c' ?>; border-radius:8px; padding:12px; margin-bottom:12px; background:<?= $step['ok'] ? 'rgba(46,204,113,0.05)' : 'rgba(231,76,60,0.05)' ?>;">
            <h3 style="margin:0 0 8px 0;">
                <span style="font-size:1.2em;"><?= $step['ok'] ? '&#10004;' : '&#10008;' ?></span>
                Krok <?= $i + 1 ?>: <?= htmlspecialchars($step['name']) ?>
            </h3>
            <?php if (!empty($step['details'])): ?>
            <div class="table-responsive">
            <table class="table" style="margin:0;">
                <?php foreach ($step['details'] as $key => $val): ?>
                <tr>
                    <th style="min-width:140px; white-space:nowrap;"><?= htmlspecialchars($key) ?></th>
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
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($logContent): ?>
<div class="section">
    <h2>Log sesji KSeF</h2>
    <p style="color:#888; font-size:0.9em;">Skopiuj poniższy log i przekaż do analizy w celu zdiagnozowania problemu.</p>
    <div style="position:relative;">
        <button type="button" onclick="copyLog()" class="btn btn-secondary" style="position:absolute; top:8px; right:8px; padding:4px 12px; font-size:0.85em;">Kopiuj log</button>
        <textarea id="ksef-log" readonly style="width:100%; height:500px; font-family:monospace; font-size:0.8em; background:#1a1a2e; color:#e0e0e0; border:1px solid #333; border-radius:8px; padding:12px; white-space:pre; overflow:auto; resize:vertical;"><?= htmlspecialchars($logContent) ?></textarea>
    </div>
</div>

<script>
function copyLog() {
    var ta = document.getElementById('ksef-log');
    ta.select();
    ta.setSelectionRange(0, 999999);
    document.execCommand('copy');
    var btn = event.target;
    var orig = btn.textContent;
    btn.textContent = 'Skopiowano!';
    setTimeout(function() { btn.textContent = orig; }, 2000);
}
</script>
<?php else: ?>
<div class="section">
    <div class="alert alert-warning">Brak logu sesji — diagnostyka mogła nie wygenerować żadnych zapytań API.</div>
</div>
<?php endif; ?>

<div style="margin-top:16px;">
    <a href="/client/ksef" class="btn btn-secondary"><?= $lang('back') ?></a>
</div>
