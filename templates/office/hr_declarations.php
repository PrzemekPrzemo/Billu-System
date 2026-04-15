<div class="section-header">
    <h1>Deklaracje - <?= htmlspecialchars($client['company_name']) ?></h1>
    <a href="/office/hr" class="btn btn-secondary"><?= $lang('back') ?></a>
</div>

<?php $flash = \App\Core\Session::getFlash('success'); ?>
<?php if ($flash): ?>
    <div class="alert alert-success"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<?php
    $statusBadge = fn($s) => match($s) {
        'generated' => 'badge-success',
        'pending' => 'badge-warning',
        'error' => 'badge-error',
        'sent' => 'badge-info',
        default => 'badge-default',
    };
    $statusLabel = fn($s) => match($s) {
        'generated' => 'Wygenerowana',
        'pending' => 'Oczekujaca',
        'error' => 'Blad',
        'sent' => 'Wyslana',
        default => $s,
    };
    $typeLabel = fn($t) => match($t) {
        'PIT-11' => 'PIT-11',
        'PIT-4R' => 'PIT-4R',
        'ZUS-DRA' => 'ZUS DRA',
        'ZUS-RCA' => 'ZUS RCA',
        default => $t,
    };
?>

<!-- Generate declarations -->
<div class="form-card" style="padding:16px; margin-bottom:20px;">
    <h3 style="margin-bottom:12px;">Generuj deklaracje</h3>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <!-- PIT-11 -->
        <form method="POST" action="/office/hr/<?= $client['id'] ?>/declarations/generate" class="inline-form" style="display:flex; gap:8px; align-items:flex-end;">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="type" value="PIT-11">
            <div class="form-group" style="margin:0;">
                <label class="form-label" style="font-size:12px;">Rok</label>
                <select name="year" class="form-input" style="width:auto;">
                    <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 2; $y--): ?>
                    <option value="<?= $y ?>"><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label" style="font-size:12px;">Pracownik</label>
                <select name="employee_id" class="form-input" style="width:auto;">
                    <option value="">-- Wszyscy --</option>
                    <?php foreach ($employees as $emp): ?>
                    <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">PIT-11</button>
        </form>

        <!-- PIT-4R -->
        <form method="POST" action="/office/hr/<?= $client['id'] ?>/declarations/generate" class="inline-form" style="display:flex; gap:8px; align-items:flex-end;">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="type" value="PIT-4R">
            <div class="form-group" style="margin:0;">
                <label class="form-label" style="font-size:12px;">Rok</label>
                <select name="year" class="form-input" style="width:auto;">
                    <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 2; $y--): ?>
                    <option value="<?= $y ?>"><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-sm">PIT-4R</button>
        </form>

        <!-- ZUS DRA -->
        <form method="POST" action="/office/hr/<?= $client['id'] ?>/declarations/generate" class="inline-form" style="display:flex; gap:8px; align-items:flex-end;">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="type" value="ZUS-DRA">
            <div class="form-group" style="margin:0;">
                <label class="form-label" style="font-size:12px;">Rok</label>
                <select name="year" class="form-input" style="width:auto;">
                    <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 2; $y--): ?>
                    <option value="<?= $y ?>"><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label" style="font-size:12px;">Miesiac</label>
                <select name="month" class="form-input" style="width:auto;">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $m == (int)date('n') ? 'selected' : '' ?>><?= sprintf('%02d', $m) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-sm">ZUS DRA</button>
        </form>

        <!-- ZUS RCA -->
        <form method="POST" action="/office/hr/<?= $client['id'] ?>/declarations/generate" class="inline-form" style="display:flex; gap:8px; align-items:flex-end;">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="type" value="ZUS-RCA">
            <div class="form-group" style="margin:0;">
                <label class="form-label" style="font-size:12px;">Rok</label>
                <select name="year" class="form-input" style="width:auto;">
                    <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 2; $y--): ?>
                    <option value="<?= $y ?>"><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label" style="font-size:12px;">Miesiac</label>
                <select name="month" class="form-input" style="width:auto;">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $m == (int)date('n') ? 'selected' : '' ?>><?= sprintf('%02d', $m) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-sm">ZUS RCA</button>
        </form>
    </div>
</div>

<?php if (empty($declarations)): ?>
<div class="empty-state">
    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:12px;opacity:0.4;"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
    <p>Brak wygenerowanych deklaracji.</p>
</div>
<?php else: ?>
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Typ</th>
                <th>Rok</th>
                <th>Miesiac</th>
                <th>Pracownik</th>
                <th><?= $lang('status') ?></th>
                <th>Data wygenerowania</th>
                <th><?= $lang('actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($declarations as $decl): ?>
            <tr>
                <td><strong><?= $typeLabel($decl['type'] ?? '') ?></strong></td>
                <td><?= (int)($decl['year'] ?? 0) ?></td>
                <td><?= $decl['month'] ? sprintf('%02d', (int)$decl['month']) : '-' ?></td>
                <td><?= htmlspecialchars($decl['employee_name'] ?? 'Wszyscy') ?></td>
                <td>
                    <span class="badge <?= $statusBadge($decl['status'] ?? 'pending') ?>"><?= $statusLabel($decl['status'] ?? 'pending') ?></span>
                </td>
                <td class="text-muted"><?= htmlspecialchars($decl['generated_at'] ?? '-') ?></td>
                <td>
                    <div class="action-buttons">
                        <?php if (!empty($decl['pdf_path'])): ?>
                        <a href="/office/hr/<?= $client['id'] ?>/declarations/<?= $decl['id'] ?>/pdf" class="btn btn-xs">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            PDF
                        </a>
                        <?php endif; ?>
                        <?php if (!empty($decl['xml_path'])): ?>
                        <a href="/office/hr/<?= $client['id'] ?>/declarations/<?= $decl['id'] ?>/xml" class="btn btn-xs">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            XML
                        </a>
                        <?php endif; ?>
                        <form method="POST" action="/office/hr/<?= $client['id'] ?>/declarations/<?= $decl['id'] ?>/delete" class="inline-form">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <button type="submit" class="btn btn-xs btn-danger" onclick="return confirm('Usunac deklaracje?')"><?= $lang('delete') ?></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
