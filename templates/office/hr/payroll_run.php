<?php
$monthNames = ['','Styczeń','Luty','Marzec','Kwiecień','Maj','Czerwiec',
               'Lipiec','Sierpień','Wrzesień','Październik','Listopad','Grudzień'];
$statusClass = match ($run['status']) {
    'calculated' => 'badge-info',
    'approved'   => 'badge-success',
    'locked'     => 'badge-dark',
    default      => 'badge-secondary',
};
$isLocked = $run['status'] === 'locked';
?>

<div class="section-header">
    <div>
        <div class="breadcrumb-path" style="font-size:13px;color:var(--text-muted);margin-bottom:4px;">
            <a href="/office/hr/<?= $clientId ?>/payroll"><?= $lang('hr_payroll') ?></a> &rsaquo;
            <?= $monthNames[$run['period_month']] ?> <?= $run['period_year'] ?>
        </div>
        <h1>
            <?= $lang('hr_payroll_run') ?>: <?= $monthNames[$run['period_month']] ?> <?= $run['period_year'] ?>
            <span class="badge <?= $statusClass ?>" style="font-size:14px;vertical-align:middle;">
                <?= \App\Models\HrPayrollRun::getStatusLabel($run['status']) ?>
            </span>
        </h1>
    </div>
    <div style="display:flex;gap:8px;">
        <?php if (!$isLocked): ?>
        <form method="POST" action="/office/hr/<?= $clientId ?>/payroll/<?= $run['id'] ?>/calculate" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <button type="submit" class="btn btn-primary"><?= $lang('hr_payroll_calculate') ?></button>
        </form>
        <?php endif; ?>
        <?php if ($run['status'] === 'calculated'): ?>
        <form method="POST" action="/office/hr/<?= $clientId ?>/payroll/<?= $run['id'] ?>/approve" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <button type="submit" class="btn btn-success" onclick="return confirm('Zatwierdzić listę płac?')"><?= $lang('hr_payroll_approve') ?></button>
        </form>
        <?php endif; ?>
        <?php if ($run['status'] === 'approved'): ?>
        <form method="POST" action="/office/hr/<?= $clientId ?>/payroll/<?= $run['id'] ?>/lock" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <button type="submit" class="btn btn-danger" onclick="return confirm('Zablokować listę płac? Tej operacji nie można cofnąć.')"><?= $lang('hr_payroll_lock') ?></button>
        </form>
        <?php endif; ?>
        <?php if (in_array($run['status'], ['approved','locked'], true)): ?>
        <button class="btn btn-secondary" onclick="document.getElementById('correction-form-panel').style.display='block';">
            <?= $lang('hr_payroll_create_correction') ?>
        </button>
        <?php endif; ?>
        <a href="/office/hr/<?= $clientId ?>/payroll" class="btn btn-secondary"><?= $lang('back') ?></a>
    </div>
</div>

<?php include __DIR__ . '/../hr_nav.php'; ?>

<!-- Faza 17 — Correction run banner -->
<?php if (!empty($run['is_correction']) && $run['corrects_run_id']): ?>
<div class="alert" style="background:#fefce8;border-left:4px solid #f59e0b;margin-bottom:16px;padding:12px 16px;">
    <strong><?= $lang('hr_payroll_correction') ?></strong> —
    <?= $lang('hr_payroll_correction_of') ?>
    <a href="/office/hr/<?= $clientId ?>/payroll/<?= $run['corrects_run_id'] ?>">
        #<?= $run['corrects_run_id'] ?>
    </a>
</div>
<?php endif; ?>

<!-- Faza 17 — Unlock form (locked runs only) -->
<?php if ($isLocked): ?>
<div class="card" style="margin-bottom:16px;border-left:4px solid #dc2626;">
    <div class="card-body" style="padding:14px 16px;">
        <p style="margin:0 0 10px;font-size:13px;"><strong><?= $lang('hr_payroll_unlock') ?></strong> — <?= $lang('hr_payroll_unlock_desc') ?></p>
        <button class="btn btn-xs btn-danger" onclick="document.getElementById('unlock-form-panel').style.display='block';this.style.display='none';">
            <?= $lang('hr_payroll_unlock') ?>
        </button>
        <div id="unlock-form-panel" style="display:none;margin-top:10px;">
            <form method="POST" action="/office/hr/<?= $clientId ?>/payroll/<?= $run['id'] ?>/unlock"
                  onsubmit="return confirm('Odblokować listę płac?')">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <textarea name="unlock_reason" rows="2" class="form-control" placeholder="<?= $lang('hr_payroll_unlock_reason') ?>"
                          required style="margin-bottom:8px;"></textarea>
                <button type="submit" class="btn btn-danger btn-sm"><?= $lang('hr_payroll_unlock') ?></button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Faza 17 — Create correction form panel -->
<div id="correction-form-panel" style="display:none;margin-bottom:16px;">
<div class="card" style="border-left:4px solid #f59e0b;">
    <div class="card-header"><h3><?= $lang('hr_payroll_create_correction') ?></h3></div>
    <div class="card-body" style="padding:14px 16px;">
        <p style="font-size:13px;margin:0 0 10px;"><?= $lang('hr_payroll_correction_employees') ?></p>
        <form method="POST" action="/office/hr/<?= $clientId ?>/payroll/<?= $run['id'] ?>/correction">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <?php foreach ($items as $item): ?>
            <label style="display:flex;gap:10px;align-items:center;margin-bottom:6px;font-size:13px;cursor:pointer;">
                <input type="checkbox" name="employee_ids[]" value="<?= (int)$item['employee_id'] ?>" checked>
                <?= htmlspecialchars($item['employee_name'] ?? ('Pracownik #' . $item['employee_id'])) ?>
                — <?= number_format((float)$item['gross_salary'], 2, ',', ' ') ?> PLN brutto
            </label>
            <?php endforeach; ?>
            <div style="margin-top:12px;display:flex;gap:8px;">
                <button type="submit" class="btn btn-warning" onclick="return confirm('Utworzyć listę korygującą?')">
                    <?= $lang('hr_payroll_create_correction') ?>
                </button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('correction-form-panel').style.display='none';">
                    <?= $lang('cancel') ?>
                </button>
            </div>
        </form>
    </div>
</div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger" style="margin-bottom:16px;">
    <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Summary cards -->
<?php if ($run['status'] !== 'draft'): ?>
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px;">
    <?php
    $summaryCards = [
        ['Łączne brutto',        $run['total_gross'],         'var(--text)'],
        ['Łączne netto',         $run['total_net'],           'var(--success)'],
        ['ZUS pracodawcy',       $run['total_zus_employer'],  'var(--text-muted)'],
        ['Łączny koszt prac.',   $run['total_employer_cost'], 'var(--danger)'],
    ];
    foreach ($summaryCards as [$label, $val, $color]):
    ?>
    <div class="card">
        <div class="card-body" style="text-align:center;padding:12px;">
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;"><?= $label ?></div>
            <div style="font-size:20px;font-weight:700;color:<?= $color ?>;margin-top:4px;"><?= number_format($val, 2, ',', ' ') ?> zł</div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Employee items table -->
<div class="card">
    <div class="card-header">
        <h3>Pozycje listy płac (<?= count($items) ?> prac.)</h3>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <?php if (empty($items)): ?>
        <div style="padding:32px;text-align:center;color:var(--text-muted);">
            Brak pracowników. Naciśnij "Przelicz" aby obliczyć listę.
        </div>
        <?php else: ?>
        <table class="table" style="margin:0;font-size:13px;min-width:1100px;">
            <thead>
                <tr>
                    <th>Pracownik</th>
                    <th style="text-align:right;">Brutto</th>
                    <th style="text-align:right;">ZUS prac.</th>
                    <th style="text-align:right;">Podst. PIT</th>
                    <th style="text-align:right;">Zaliczka PIT</th>
                    <th style="text-align:right;">PPK prac.</th>
                    <th style="text-align:right;">Netto</th>
                    <th style="text-align:right;">Koszt pracodawcy</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sumGross = $sumZusEmp = $sumTaxBase = $sumPit = $sumPpkEmp = $sumNet = $sumCost = 0;
                foreach ($items as $item):
                    $sumGross   += $item['gross_salary'];
                    $sumZusEmp  += $item['zus_total_employee'];
                    $sumTaxBase += $item['tax_base'];
                    $sumPit     += $item['pit_advance'];
                    $sumPpkEmp  += $item['ppk_employee'];
                    $sumNet     += $item['net_salary'];
                    $sumCost    += $item['employer_total_cost'];
                ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($item['employee_name']) ?></strong>
                        <?php if ($item['position']): ?>
                        <div style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($item['position']) ?></div>
                        <?php endif; ?>
                        <div style="font-size:11px;">
                            <span class="badge badge-secondary"><?= \App\Models\HrContract::getContractTypeLabel($item['contract_type']) ?></span>
                        </div>
                    </td>
                    <td style="text-align:right;"><?= number_format($item['gross_salary'],      2, ',', ' ') ?></td>
                    <td style="text-align:right;"><?= number_format($item['zus_total_employee'], 2, ',', ' ') ?></td>
                    <td style="text-align:right;"><?= number_format($item['tax_base'],          2, ',', ' ') ?></td>
                    <td style="text-align:right;"><?= number_format($item['pit_advance'],       2, ',', ' ') ?></td>
                    <td style="text-align:right;"><?= number_format($item['ppk_employee'],      2, ',', ' ') ?></td>
                    <td style="text-align:right;font-weight:600;"><?= number_format($item['net_salary'],         2, ',', ' ') ?></td>
                    <td style="text-align:right;"><?= number_format($item['employer_total_cost'],2, ',', ' ') ?></td>
                    <td>
                        <?php if ($run['status'] !== 'draft'): ?>
                        <a href="/office/hr/<?= $clientId ?>/payroll/<?= $run['id'] ?>/payslip/<?= $item['employee_id'] ?>"
                           class="btn btn-sm btn-secondary">↓ PDF</a>
                        <?php endif; ?>
                        <?php if (!$isLocked): ?>
                        <button class="btn btn-sm btn-secondary"
                                onclick="openOverrideModal(<?= $item['employee_id'] ?>, <?= $item['contract_id'] ?>, '<?= htmlspecialchars(addslashes($item['employee_name'])) ?>', <?= $item['overtime_pay'] ?>, <?= $item['bonus'] ?>, <?= $item['other_additions'] ?>, <?= $item['sick_pay_reduction'] ?>)">
                            Korekta
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot style="background:var(--bg-subtle);font-weight:600;">
                <tr>
                    <td>Suma</td>
                    <td style="text-align:right;"><?= number_format($sumGross,   2, ',', ' ') ?></td>
                    <td style="text-align:right;"><?= number_format($sumZusEmp,  2, ',', ' ') ?></td>
                    <td style="text-align:right;"><?= number_format($sumTaxBase, 2, ',', ' ') ?></td>
                    <td style="text-align:right;"><?= number_format($sumPit,     2, ',', ' ') ?></td>
                    <td style="text-align:right;"><?= number_format($sumPpkEmp,  2, ',', ' ') ?></td>
                    <td style="text-align:right;"><?= number_format($sumNet,     2, ',', ' ') ?></td>
                    <td style="text-align:right;"><?= number_format($sumCost,    2, ',', ' ') ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Payslip Email Log -->
<?php if (!empty($emailLog)): ?>
<div class="card" style="margin-top:16px;">
    <div class="card-header">
        <h3>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            <?= $lang('hr_payslip_email_log') ?>
        </h3>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="table" style="margin:0;font-size:13px;">
            <thead>
                <tr>
                    <th><?= $lang('hr_employee') ?></th>
                    <th><?= $lang('hr_payslip_log_recipient') ?></th>
                    <th><?= $lang('status') ?></th>
                    <th><?= $lang('hr_payslip_log_sent_at') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($emailLog as $log): ?>
                <tr>
                    <td><?= htmlspecialchars($log['employee_name']) ?></td>
                    <td class="text-muted"><?= htmlspecialchars($log['recipient_email']) ?></td>
                    <td>
                        <?php if ($log['status'] === 'sent'): ?>
                            <span class="badge badge-success"><?= $lang('hr_payslip_log_sent') ?></span>
                        <?php else: ?>
                            <span class="badge badge-danger" title="<?= htmlspecialchars($log['error_message'] ?? '') ?>"><?= $lang('hr_payslip_log_failed') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted"><?= htmlspecialchars($log['sent_at']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Override Modal -->
<?php if (!$isLocked): ?>
<div id="override-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:var(--bg-card);border-radius:8px;padding:24px;width:420px;box-shadow:0 8px 32px rgba(0,0,0,.2);">
        <h3 id="override-modal-title" style="margin:0 0 16px;">Korekta składników</h3>
        <form method="POST" action="/office/hr/<?= $clientId ?>/payroll/<?= $run['id'] ?>/overrides" id="override-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="employee_id" id="override-emp-id">
            <input type="hidden" name="contract_id" id="override-contract-id">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label>Nadgodziny (PLN)</label>
                    <input type="number" name="overtime_pay" id="override-overtime" class="form-control" step="0.01" min="0" value="0">
                </div>
                <div class="form-group">
                    <label>Premia (PLN)</label>
                    <input type="number" name="bonus" id="override-bonus" class="form-control" step="0.01" min="0" value="0">
                </div>
                <div class="form-group">
                    <label>Inne dodatki (PLN)</label>
                    <input type="number" name="other_additions" id="override-other" class="form-control" step="0.01" min="0" value="0">
                </div>
                <div class="form-group">
                    <label>Potrącenie L4 (PLN)</label>
                    <input type="number" name="sick_pay_reduction" id="override-sick" class="form-control" step="0.01" min="0" value="0">
                </div>
            </div>
            <p style="font-size:12px;color:var(--text-muted);margin:4px 0 16px;">Po zapisaniu naciśnij "Przelicz" aby odświeżyć obliczenia.</p>
            <div style="display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('override-modal').style.display='none'" class="btn btn-secondary"><?= $lang('cancel') ?></button>
                <button type="submit" class="btn btn-primary"><?= $lang('save') ?></button>
            </div>
        </form>
    </div>
</div>

<script>
function openOverrideModal(empId, contractId, name, overtime, bonus, other, sick) {
    document.getElementById('override-modal-title').textContent = 'Korekta: ' + name;
    document.getElementById('override-emp-id').value    = empId;
    document.getElementById('override-contract-id').value = contractId;
    document.getElementById('override-overtime').value  = overtime;
    document.getElementById('override-bonus').value     = bonus;
    document.getElementById('override-other').value     = other;
    document.getElementById('override-sick').value      = sick;
    document.getElementById('override-modal').style.display = 'flex';
}
</script>
<?php endif; ?>