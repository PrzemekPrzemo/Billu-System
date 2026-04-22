<?php $n2 = fn($v) => number_format((float)$v, 2, ',', ' '); ?>

<div class="section-header">
    <div>
        <div class="breadcrumb-path" style="font-size:13px;color:var(--text-muted);margin-bottom:4px;">
            <a href="/office/hr/settings"><?= $lang('hr_module') ?></a> &rsaquo;
            <a href="/office/hr/<?= $clientId ?>/employees"><?= htmlspecialchars($client['company_name']) ?></a> &rsaquo;
            Masowa aktualizacja wynagrodzeń
        </div>
        <h1>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            Masowa aktualizacja wynagrodzeń
        </h1>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="/office/hr/<?= $clientId ?>/mass-export" class="btn btn-secondary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Eksport pracowników (Excel)
        </a>
    </div>
</div>

<?php include __DIR__ . '/../hr_nav.php'; ?>

<?php if ($flash_success): ?><div class="alert alert-success"><?= htmlspecialchars($flash_success) ?></div><?php endif; ?>
<?php if ($flash_error): ?><div class="alert alert-error"><?= htmlspecialchars($flash_error) ?></div><?php endif; ?>

<div class="alert" style="background:#fffbeb;border-left:4px solid #f59e0b;margin-bottom:16px;padding:12px 16px;font-size:13px;">
    <strong>Uwaga:</strong> Ta operacja zmieni wynagrodzenie brutto w aktywnych umowach wybranych pracowników.
    Zmiana jest natychmiastowa i wpłynie na następną listę płac.
</div>

<form method="POST" action="/office/hr/<?= $clientId ?>/mass-salary/apply">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

    <!-- Mode selection -->
    <div class="card" style="margin-bottom:16px;">
        <div class="card-header"><strong>Parametry podwyżki</strong></div>
        <div class="card-body" style="display:flex;gap:24px;align-items:center;flex-wrap:wrap;">
            <div class="form-group" style="margin:0;">
                <label class="form-label">Tryb</label>
                <select name="mode" class="form-control" id="salary-mode" onchange="updatePreview()">
                    <option value="percentage">Procentowa (%)</option>
                    <option value="fixed">Kwotowa (PLN)</option>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label" id="amount-label">Procent podwyżki</label>
                <input type="number" name="amount" id="salary-amount" class="form-control" step="0.01" min="0"
                       placeholder="np. 5.00" style="width:140px;" oninput="updatePreview()">
            </div>
            <div style="margin-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="selectAll(true)">Zaznacz wszystkich</button>
                <button type="button" class="btn btn-secondary" onclick="selectAll(false)">Odznacz</button>
            </div>
        </div>
    </div>

    <!-- Employee table -->
    <?php if (empty($employees)): ?>
    <div class="empty-state"><p>Brak aktywnych pracowników.</p></div>
    <?php else: ?>
    <div class="card">
        <div class="card-body" style="padding:0;">
            <table class="table" style="margin:0;font-size:13px;">
                <thead>
                    <tr>
                        <th style="width:30px;"><input type="checkbox" id="check-all" onchange="selectAll(this.checked)"></th>
                        <th>Pracownik</th>
                        <th>Stanowisko</th>
                        <th style="text-align:right;">Obecne brutto</th>
                        <th style="text-align:right;">Nowe brutto</th>
                        <th style="text-align:right;">Różnica</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $emp):
                        $ct = $contractMap[$emp['id']] ?? null;
                        if (!$ct) continue;
                        $salary = (float) $ct['base_salary'];
                    ?>
                    <tr>
                        <td><input type="checkbox" name="employee_ids[]" value="<?= $emp['id'] ?>" class="emp-check" data-salary="<?= $salary ?>"></td>
                        <td><strong><?= htmlspecialchars($emp['full_name']) ?></strong></td>
                        <td class="text-muted"><?= htmlspecialchars($ct['position'] ?? '—') ?></td>
                        <td style="text-align:right;"><?= $n2($salary) ?> PLN</td>
                        <td style="text-align:right;" class="new-salary">—</td>
                        <td style="text-align:right;" class="diff-salary">—</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div style="margin-top:16px;text-align:right;">
        <button type="submit" class="btn btn-primary" onclick="return confirm('Czy na pewno chcesz zaktualizować wynagrodzenia wybranych pracowników?');">
            Zastosuj podwyżkę
        </button>
    </div>
    <?php endif; ?>
</form>

<script>
function selectAll(checked) {
    document.querySelectorAll('.emp-check').forEach(el => el.checked = checked);
    document.getElementById('check-all').checked = checked;
}
function updatePreview() {
    const mode = document.getElementById('salary-mode').value;
    const amount = parseFloat(document.getElementById('salary-amount').value) || 0;
    document.getElementById('amount-label').textContent = mode === 'percentage' ? 'Procent podwyżki' : 'Kwota podwyżki (PLN)';

    document.querySelectorAll('.emp-check').forEach(el => {
        const salary = parseFloat(el.dataset.salary) || 0;
        const row = el.closest('tr');
        let newSalary = mode === 'percentage' ? salary * (1 + amount / 100) : salary + amount;
        newSalary = Math.round(newSalary * 100) / 100;
        const diff = newSalary - salary;
        row.querySelector('.new-salary').textContent = newSalary.toLocaleString('pl-PL', {minimumFractionDigits:2}) + ' PLN';
        row.querySelector('.diff-salary').textContent = (diff >= 0 ? '+' : '') + diff.toLocaleString('pl-PL', {minimumFractionDigits:2}) + ' PLN';
        row.querySelector('.diff-salary').style.color = diff > 0 ? 'var(--success)' : (diff < 0 ? 'var(--danger)' : '');
    });
}
</script>