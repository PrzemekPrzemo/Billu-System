<div class="section-header">
    <div>
        <h1><?= $lang('hr_zus_declarations') ?> — <?= htmlspecialchars($client['company_name']) ?></h1>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('new-zus-modal').style.display='flex'">
        + <?= $lang('hr_zus_generate') ?>
    </button>
</div>

<?php include __DIR__ . '/../hr_nav.php'; ?>

<!-- Year filter -->
<div style="margin-bottom:16px;display:flex;align-items:center;gap:12px;">
    <span style="font-size:13px;color:var(--text-muted);">Rok:</span>
    <?php foreach ($years as $y): ?>
    <a href="/office/hr/<?= $clientId ?>/zus?year=<?= $y ?>"
       class="btn btn-sm <?= $y == $selectedYear ? 'btn-primary' : 'btn-secondary' ?>">
        <?= $y ?>
    </a>
    <?php endforeach; ?>
    <?php if (empty($years)): ?>
        <span style="font-size:13px;color:var(--text-muted);"><?= date('Y') ?></span>
    <?php endif; ?>
</div>

<div class="card" style="margin-bottom:16px;background:var(--bg-warning, #fffbeb);border:1px solid var(--warning);">
    <div class="card-body" style="padding:12px 16px;font-size:13px;">
        <strong>Informacja:</strong> Wygenerowane pliki XML zawierają dane do deklaracji DRA+RCA.
        Przed wysłaniem zweryfikuj i zaimportuj plik w programie <strong>Płatnik Plus</strong> lub prześlij przez portal <strong>ZUS e-Płatnik</strong>.
    </div>
</div>

<?php if (empty($declarations)): ?>
<div class="card">
    <div class="card-body" style="text-align:center;padding:48px;color:var(--text-muted);">
        <p>Brak deklaracji ZUS za wybrany rok. Wygeneruj pierwszą deklarację.</p>
        <button class="btn btn-primary" onclick="document.getElementById('new-zus-modal').style.display='flex'">
            + <?= $lang('hr_zus_generate') ?>
        </button>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body" style="padding:0;">
        <table class="table" style="margin:0;">
            <thead>
                <tr>
                    <th>Miesiąc</th>
                    <th>Powiązana lista płac</th>
                    <th>Status</th>
                    <th>Wygenerowano</th>
                    <th style="text-align:right;">Akcje</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $monthNames = ['','Styczeń','Luty','Marzec','Kwiecień','Maj','Czerwiec',
                               'Lipiec','Sierpień','Wrzesień','Październik','Listopad','Grudzień'];
                foreach ($declarations as $decl):
                    $statusClass = match ($decl['status'] ?? 'pending') {
                        'generated' => 'badge-success',
                        'sent'      => 'badge-info',
                        default     => 'badge-secondary',
                    };
                ?>
                <tr>
                    <td><strong><?= $monthNames[$decl['period_month']] ?> <?= $decl['period_year'] ?></strong></td>
                    <td>
                        <?php if ($decl['payroll_run_id']): ?>
                        <a href="/office/hr/<?= $clientId ?>/payroll/<?= $decl['payroll_run_id'] ?>" style="font-size:13px;">
                            Lista płac #<?= $decl['payroll_run_id'] ?>
                            <?php if (!empty($decl['run_status'])): ?>
                            <span class="badge badge-secondary" style="font-size:11px;"><?= \App\Models\HrPayrollRun::getStatusLabel($decl['run_status']) ?></span>
                            <?php endif; ?>
                        </a>
                        <?php else: ?>
                        <span style="color:var(--text-muted);font-size:13px;">—</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?= $statusClass ?>"><?= \App\Models\HrZusDeclaration::getStatusLabel($decl['status'] ?? 'pending') ?></span></td>
                    <td style="font-size:13px;color:var(--text-muted);"><?= $decl['generated_at'] ? date('d.m.Y H:i', strtotime($decl['generated_at'])) : '—' ?></td>
                    <td style="text-align:right;">
                        <?php if (!empty($decl['xml_path'])): ?>
                        <a href="/office/hr/<?= $clientId ?>/zus/<?= $decl['id'] ?>/download" class="btn btn-sm btn-secondary">
                            ↓ XML DRA
                        </a>
                        <?php endif; ?>
                        <form method="POST" action="/office/hr/<?= $clientId ?>/zus/<?= $decl['id'] ?>/regenerate" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                            <button type="submit" class="btn btn-sm btn-secondary" onclick="return confirm('Wygenerować ponownie deklarację ZUS?')">
                                ↺ Regeneruj
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- New ZUS Declaration Modal -->
<div id="new-zus-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:var(--bg-card);border-radius:8px;padding:24px;width:420px;box-shadow:0 8px 32px rgba(0,0,0,.2);">
        <h3 style="margin:0 0 12px;"><?= $lang('hr_zus_generate') ?></h3>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;">
            Wybierz miesiąc i listę płac jako podstawę deklaracji ZUS DRA+RCA.
        </p>
        <form method="POST" action="/office/hr/<?= $clientId ?>/zus">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <div class="form-group">
                <label>Miesiąc</label>
                <select name="month" class="form-control" required>
                    <?php
                    $mn = ['','Styczeń','Luty','Marzec','Kwiecień','Maj','Czerwiec',
                           'Lipiec','Sierpień','Wrzesień','Październik','Listopad','Grudzień'];
                    for ($m = 1; $m <= 12; $m++):
                    ?>
                    <option value="<?= $m ?>" <?= $m == (int)date('n') ? 'selected' : '' ?>>
                        <?= $mn[$m] ?>
                    </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Rok</label>
                <input type="number" name="year" class="form-control" value="<?= date('Y') ?>" min="2020" max="2099" required>
            </div>
            <?php if (!empty($payrollRuns)): ?>
            <div class="form-group">
                <label>Powiązana lista płac (opcjonalnie)</label>
                <select name="payroll_run_id" class="form-control">
                    <option value="">— bez powiązania —</option>
                    <?php
                    $mn2 = ['','Sty','Lut','Mar','Kwi','Maj','Cze','Lip','Sie','Wrz','Paź','Lis','Gru'];
                    foreach ($payrollRuns as $pr):
                    ?>
                    <option value="<?= $pr['id'] ?>"><?= $mn2[$pr['period_month']] ?> <?= $pr['period_year'] ?> — <?= \App\Models\HrPayrollRun::getStatusLabel($pr['status']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;">
                <button type="button" onclick="document.getElementById('new-zus-modal').style.display='none'" class="btn btn-secondary"><?= $lang('cancel') ?></button>
                <button type="submit" class="btn btn-primary"><?= $lang('hr_zus_generate') ?></button>
            </div>
        </form>
    </div>
</div>