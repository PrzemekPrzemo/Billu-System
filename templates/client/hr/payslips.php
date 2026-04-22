<div class="section-header">
    <div>
        <h1><?= $lang('hr_payslips') ?></h1>
    </div>
</div>

<?php include __DIR__ . '/../hr_nav.php'; ?>

<?php if (empty($runsByYear)): ?>
<div class="card">
    <div class="card-body" style="text-align:center;padding:48px;color:var(--text-muted);">
        <p><?= $lang('hr_no_payslips') ?></p>
    </div>
</div>
<?php else: ?>
<?php foreach ($runsByYear as $year => $runs): ?>
<div class="card" style="margin-bottom:16px;">
    <div class="card-header">
        <h3><?= $year ?></h3>
    </div>
    <div class="card-body" style="padding:0;">
        <table class="table" style="margin:0;">
            <thead>
                <tr>
                    <th>Miesiąc</th>
                    <th>Pracownicy</th>
                    <th style="text-align:right;">Status</th>
                    <th style="text-align:right;">Akcje</th>
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
                <tr>
                    <td><strong><?= $monthNames[$run['period_month']] ?> <?= $run['period_year'] ?></strong></td>
                    <td><?= (int) $run['employee_count'] ?></td>
                    <td style="text-align:right;">
                        <span class="badge <?= $statusClass ?>"><?= \App\Models\HrPayrollRun::getStatusLabel($run['status']) ?></span>
                    </td>
                    <td style="text-align:right;">
                        <?php if (!empty($employeesByRun[$run['id']])): ?>
                            <?php foreach ($employeesByRun[$run['id']] as $emp): ?>
                            <a href="/client/hr/payslips/<?= $run['id'] ?>/<?= $emp['employee_id'] ?>/pdf"
                               class="btn btn-sm btn-secondary"
                               style="margin-bottom:2px;">
                                <?= htmlspecialchars($emp['employee_name']) ?> ↓ PDF
                            </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span style="font-size:12px;color:var(--text-muted);">Brak odcinków</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
