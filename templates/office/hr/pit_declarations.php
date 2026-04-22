<?php
$statusClass = function(string $status): string {
    return match($status) {
        'generated' => 'badge-success',
        'issued'    => 'badge-info',
        default     => 'badge-secondary',
    };
};

// Separate PIT-11 and PIT-4R
$pit11 = array_filter($declarations, fn($d) => $d['declaration_type'] === 'PIT-11');
$pit4r = array_filter($declarations, fn($d) => $d['declaration_type'] === 'PIT-4R');
?>

<div class="section-header">
    <div>
        <div class="breadcrumb-path" style="font-size:13px;color:var(--text-muted);margin-bottom:4px;">
            <a href="/office/hr/<?= $clientId ?>/employees"><?= $lang('hr_employees') ?></a> &rsaquo;
            <?= $lang('hr_pit_declarations') ?>
        </div>
        <h1><?= $lang('hr_pit_declarations') ?></h1>
    </div>
</div>

<?php include __DIR__ . '/../hr_nav.php'; ?>

<div class="alert" style="background:var(--warning-bg,#fff8e1);border:1px solid #ffe082;color:#795548;margin-bottom:16px;padding:12px 16px;border-radius:6px;">
    <strong><?= $lang('hr_pit_deadline_warning') ?>:</strong>
    PIT-11 — wydać pracownikom do 28 lutego roku następnego &bull;
    PIT-4R — złożyć do Urzędu Skarbowego do 31 stycznia roku następnego.
</div>

<!-- Year filter -->
<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
    <?php foreach ($years as $y): ?>
    <a href="/office/hr/<?= $clientId ?>/pit?year=<?= $y ?>"
       class="btn <?= $y == $selectedYear ? 'btn-primary' : 'btn-secondary' ?>"><?= $y ?></a>
    <?php endforeach; ?>
</div>

<!-- PIT-11 section -->
<div class="card" style="margin-bottom:24px;">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
        <h3><?= $lang('hr_pit11') ?> — <?= $selectedYear ?></h3>
        <form method="POST" action="/office/hr/<?= $clientId ?>/pit" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="type" value="pit11-all">
            <input type="hidden" name="year" value="<?= $selectedYear ?>">
            <button type="submit" class="btn btn-primary btn-sm"
                    onclick="return confirm('Wygenerować PIT-11 dla wszystkich pracowników za rok <?= $selectedYear ?>?')">
                <?= $lang('hr_pit_generate_all') ?>
            </button>
        </form>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <?php if (empty($pit11)): ?>
        <div style="padding:24px;text-align:center;color:var(--text-muted);">
            Brak deklaracji PIT-11 za rok <?= $selectedYear ?>. Wygeneruj dla wszystkich pracowników lub indywidualnie.
        </div>
        <?php else: ?>
        <table class="table" style="margin:0;font-size:13px;">
            <thead>
                <tr>
                    <th><?= $lang('employee') ?></th>
                    <th style="text-align:right;">Brutto</th>
                    <th style="text-align:right;">ZUS prac.</th>
                    <th style="text-align:right;">Zaliczki PIT</th>
                    <th><?= $lang('status') ?></th>
                    <th>Wygenerowano</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pit11 as $d): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($d['employee_name'] ?? '—') ?></strong></td>
                    <td style="text-align:right;"><?= number_format($d['total_gross'], 2, ',', ' ') ?> zł</td>
                    <td style="text-align:right;"><?= number_format($d['total_zus_employee'], 2, ',', ' ') ?> zł</td>
                    <td style="text-align:right;"><?= number_format($d['total_pit_advances'], 2, ',', ' ') ?> zł</td>
                    <td><span class="badge <?= $statusClass($d['status']) ?>"><?= \App\Models\HrPitDeclaration::getStatusLabel($d['status']) ?></span></td>
                    <td style="font-size:12px;color:var(--text-muted);"><?= $d['generated_at'] ? substr($d['generated_at'], 0, 16) : '—' ?></td>
                    <td style="display:flex;gap:4px;flex-wrap:wrap;">
                        <?php if ($d['export_path']): ?>
                        <a href="/office/hr/<?= $clientId ?>/pit/<?= $d['id'] ?>/download" class="btn btn-sm btn-secondary">↓ PDF</a>
                        <?php endif; ?>
                        <form method="POST" action="/office/hr/<?= $clientId ?>/pit" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="type" value="pit11">
                            <input type="hidden" name="employee_id" value="<?= $d['employee_id'] ?>">
                            <input type="hidden" name="year" value="<?= $selectedYear ?>">
                            <button type="submit" class="btn btn-sm btn-primary">↻ Generuj</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Per-employee generate form (for employees not yet in table) -->
<?php if (!empty($employees)): ?>
<div class="card" style="margin-bottom:24px;">
    <div class="card-header"><h3>Generuj PIT-11 dla pracownika</h3></div>
    <div class="card-body">
        <form method="POST" action="/office/hr/<?= $clientId ?>/pit" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="type" value="pit11">
            <input type="hidden" name="year" value="<?= $selectedYear ?>">
            <div class="form-group" style="margin:0;">
                <label>Pracownik</label>
                <select name="employee_id" class="form-control" required>
                    <option value="">— wybierz —</option>
                    <?php foreach ($employees as $emp): ?>
                    <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><?= $lang('hr_pit_generate_all') ?></button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- PIT-4R section -->
<div class="card">
    <div class="card-header"><h3><?= $lang('hr_pit4r') ?> — <?= $selectedYear ?></h3></div>
    <div class="card-body">
        <?php if (!empty($pit4r)):
            $p4 = array_values($pit4r)[0]; ?>
        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:12px;">
            <span class="badge <?= $statusClass($p4['status']) ?>"><?= \App\Models\HrPitDeclaration::getStatusLabel($p4['status']) ?></span>
            <span style="font-size:13px;color:var(--text-muted);">
                Łączne zaliczki PIT: <strong><?= number_format($p4['total_pit_advances'], 2, ',', ' ') ?> zł</strong>
            </span>
            <?php if ($p4['generated_at']): ?>
            <span style="font-size:12px;color:var(--text-muted);">Wygenerowano: <?= substr($p4['generated_at'], 0, 16) ?></span>
            <?php endif; ?>
            <?php if ($p4['export_path']): ?>
            <a href="/office/hr/<?= $clientId ?>/pit/<?= $p4['id'] ?>/download" class="btn btn-secondary btn-sm">↓ Pobierz PDF</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="/office/hr/<?= $clientId ?>/pit">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="type" value="pit4r">
            <input type="hidden" name="year" value="<?= $selectedYear ?>">
            <button type="submit" class="btn btn-primary"
                    onclick="return confirm('Wygenerować PIT-4R za rok <?= $selectedYear ?>?')">
                <?php echo empty($pit4r) ? 'Generuj PIT-4R' : '↻ Regeneruj PIT-4R'; ?>
            </button>
        </form>
    </div>
</div>