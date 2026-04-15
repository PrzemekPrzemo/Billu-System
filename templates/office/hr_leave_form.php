<div class="section-header">
    <h1>Nowy wniosek urlopowy - <?= htmlspecialchars($client['company_name']) ?></h1>
    <a href="/office/hr/<?= $client['id'] ?>/leaves" class="btn btn-secondary"><?= $lang('back') ?></a>
</div>

<?php $flash = \App\Core\Session::getFlash('error'); ?>
<?php if ($flash): ?>
    <div class="alert alert-error"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<form method="POST" action="/office/hr/<?= $client['id'] ?>/leaves/create" class="form-card">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Pracownik *</label>
            <select name="employee_id" class="form-input" required>
                <option value="">-- Wybierz pracownika --</option>
                <?php foreach ($employees as $emp): ?>
                <option value="<?= $emp['id'] ?>" <?= ($leave['employee_id'] ?? '') == $emp['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Typ urlopu *</label>
            <select name="leave_type" class="form-input" required>
                <option value="">-- Wybierz typ --</option>
                <option value="wypoczynkowy" <?= ($leave['leave_type'] ?? '') === 'wypoczynkowy' ? 'selected' : '' ?>>Urlop wypoczynkowy</option>
                <option value="chorobowy" <?= ($leave['leave_type'] ?? '') === 'chorobowy' ? 'selected' : '' ?>>Zwolnienie chorobowe</option>
                <option value="okolicznosciowy" <?= ($leave['leave_type'] ?? '') === 'okolicznosciowy' ? 'selected' : '' ?>>Urlop okolicznosciowy</option>
                <option value="bezplatny" <?= ($leave['leave_type'] ?? '') === 'bezplatny' ? 'selected' : '' ?>>Urlop bezplatny</option>
                <option value="macierzynski" <?= ($leave['leave_type'] ?? '') === 'macierzynski' ? 'selected' : '' ?>>Urlop macierzynski</option>
                <option value="rodzicielski" <?= ($leave['leave_type'] ?? '') === 'rodzicielski' ? 'selected' : '' ?>>Urlop rodzicielski</option>
                <option value="ojcowski" <?= ($leave['leave_type'] ?? '') === 'ojcowski' ? 'selected' : '' ?>>Urlop ojcowski</option>
                <option value="opieka" <?= ($leave['leave_type'] ?? '') === 'opieka' ? 'selected' : '' ?>>Opieka nad dzieckiem (art. 188)</option>
                <option value="na_zadanie" <?= ($leave['leave_type'] ?? '') === 'na_zadanie' ? 'selected' : '' ?>>Urlop na zadanie</option>
            </select>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Data od *</label>
            <input type="date" name="start_date" class="form-input" required id="leaveStartDate"
                   value="<?= htmlspecialchars($leave['start_date'] ?? '') ?>"
                   onchange="calcBusinessDays()">
        </div>
        <div class="form-group">
            <label class="form-label">Data do *</label>
            <input type="date" name="end_date" class="form-input" required id="leaveEndDate"
                   value="<?= htmlspecialchars($leave['end_date'] ?? '') ?>"
                   onchange="calcBusinessDays()">
        </div>
        <div class="form-group">
            <label class="form-label">Dni robocze</label>
            <input type="text" id="businessDaysHint" class="form-input" readonly
                   value="" placeholder="Wybierz daty" style="background:var(--gray-50);">
            <small class="form-hint">Obliczane automatycznie (przyblizone)</small>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">Notatki</label>
        <textarea name="notes" class="form-input" rows="3" placeholder="Dodatkowe informacje..."><?= htmlspecialchars($leave['notes'] ?? '') ?></textarea>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $lang('save') ?></button>
        <a href="/office/hr/<?= $client['id'] ?>/leaves" class="btn btn-secondary"><?= $lang('cancel') ?></a>
    </div>
</form>

<script>
function calcBusinessDays() {
    var start = document.getElementById('leaveStartDate').value;
    var end = document.getElementById('leaveEndDate').value;
    var hint = document.getElementById('businessDaysHint');
    if (!start || !end) { hint.value = ''; return; }
    var s = new Date(start);
    var e = new Date(end);
    if (e < s) { hint.value = 'Nieprawidlowy zakres'; return; }
    var count = 0;
    var cur = new Date(s);
    while (cur <= e) {
        var day = cur.getDay();
        if (day !== 0 && day !== 6) count++;
        cur.setDate(cur.getDate() + 1);
    }
    hint.value = count + ' dni';
}
document.addEventListener('DOMContentLoaded', calcBusinessDays);
</script>
