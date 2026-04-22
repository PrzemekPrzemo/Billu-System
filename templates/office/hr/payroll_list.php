<div class="section-header">
    <div>
        <h1><?= $lang('hr_payroll') ?> — <?= htmlspecialchars($client['company_name']) ?></h1>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('new-run-modal').style.display='flex'">
        + <?= $lang('hr_payroll_new') ?>
    </button>
</div>

<?php include __DIR__ . '/../hr_nav.php'; ?>

<!-- Year filter -->
<div style="margin-bottom:16px;display:flex;align-items:center;gap:12px;">
    <span style="font-size:13px;color:var(--text-muted);">Rok:</span>
    <?php foreach ($years as $y): ?>
    <a href="/office/hr/<?= $clientId ?>/payroll?year=<?= $y ?>"
       class="btn btn-sm <?= $y == $selectedYear ? 'btn-primary' : 'btn-secondary' ?>">
        <?= $y ?>
    </a>
    <?php endforeach; ?>
    <?php if (empty($years)): ?>
        <span style="font-size:13px;color:var(--text-muted);"><?= date('Y') ?></span>
    <?php endif; ?>
</div>

<?php if (empty($runs)): ?>
<div class="card">
    <div class="card-body" style="text-align:center;padding:48px;color:var(--text-muted);">
        <p>Brak list płac za wybrany rok. Utwórz pierwszą listę płac.</p>
        <button class="btn btn-primary" onclick="document.getElementById('new-run-modal').style.display='flex'">
            + <?= $lang('hr_payroll_new') ?>
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
                    <th>Status</th>
                    <th style="text-align:right;">Prac.</th>
                    <th style="text-align:right;">Brutto łącznie</th>
                    <th style="text-align:right;">Netto łącznie</th>
                    <th style="text-align:right;">Koszt pracodawcy</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $monthNames = ['','Styczeń','Luty','Marzec','Kwiecień','Maj','Czerwiec',
                               'Lipiec','Sierpień','Wrzesień','Październik','Listopad','Grudzień'];
                foreach ($runs as $run):
                    $statusClass = match ($run['status']) {
                        'calculated' => 'badge-info',
                        'approved'   => 'badge-success',
                        'locked'     => 'badge-dark',
                        default      => 'badge-secondary',
                    };
                ?>
                <tr<?= !empty($run['is_correction']) ? ' style="background:#fffbeb;"' : '' ?>>
                    <td>
                        <strong><?= $monthNames[$run['period_month']] ?> <?= $run['period_year'] ?></strong>
                        <?php if (!empty($run['is_correction'])): ?>
                        <span class="badge badge-warning" style="margin-left:6px;font-size:10px;"
                              title="<?= $lang('hr_payroll_correction_of') ?> #<?= $run['corrects_run_id'] ?>">
                            Korekta
                        </span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?= $statusClass ?>"><?= \App\Models\HrPayrollRun::getStatusLabel($run['status']) ?></span></td>
                    <td style="text-align:right;"><?= (int) $run['employee_count'] ?></td>
                    <td style="text-align:right;"><?= number_format($run['total_gross'],      2, ',', ' ') ?> zł</td>
                    <td style="text-align:right;"><?= number_format($run['total_net'],        2, ',', ' ') ?> zł</td>
                    <td style="text-align:right;"><?= number_format($run['total_employer_cost'], 2, ',', ' ') ?> zł</td>
                    <td style="text-align:right;">
                        <a href="/office/hr/<?= $clientId ?>/payroll/<?= $run['id'] ?>" class="btn btn-sm btn-secondary">Szczegóły</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- New Run Modal -->
<div id="new-run-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:var(--bg-card);border-radius:8px;padding:24px;width:360px;box-shadow:0 8px 32px rgba(0,0,0,.2);">
        <h3 style="margin:0 0 16px;"><?= $lang('hr_payroll_new') ?></h3>
        <form method="POST" action="/office/hr/<?= $clientId ?>/payroll">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <div class="form-group">
                <label>Miesiąc</label>
                <select name="month" class="form-control" required>
                    <?php
                    $monthNames2 = ['','Styczeń','Luty','Marzec','Kwiecień','Maj','Czerwiec',
                                    'Lipiec','Sierpień','Wrzesień','Październik','Listopad','Grudzień'];
                    for ($m = 1; $m <= 12; $m++):
                    ?>
                    <option value="<?= $m ?>" <?= $m == (int)date('n') ? 'selected' : '' ?>>
                        <?= $monthNames2[$m] ?>
                    </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Rok</label>
                <input type="number" name="year" class="form-control" value="<?= date('Y') ?>" min="2020" max="2099" required>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;">
                <button type="button" onclick="document.getElementById('new-run-modal').style.display='none'" class="btn btn-secondary"><?= $lang('cancel') ?></button>
                <button type="submit" class="btn btn-primary">Utwórz</button>
            </div>
        </form>
    </div>
</div>