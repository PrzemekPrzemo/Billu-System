<div class="section-header">
    <div>
        <div class="breadcrumb-path" style="font-size:13px;color:var(--text-muted);margin-bottom:4px;">
            <a href="/client/hr/employees"><?= $lang('hr_employees') ?></a> &rsaquo;
            <?= htmlspecialchars($employee['full_name']) ?>
        </div>
        <h1><?= htmlspecialchars($employee['full_name']) ?></h1>
    </div>
    <a href="/client/hr/employees" class="btn btn-secondary"><?= $lang('back') ?></a>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
    <div class="card">
        <div class="card-header"><h3><?= $lang('hr_personal_data') ?></h3></div>
        <div class="card-body">
            <table class="detail-table">
                <?php if ($employee['email']): ?><tr><th><?= $lang('email') ?></th><td><?= htmlspecialchars($employee['email']) ?></td></tr><?php endif; ?>
                <?php if ($employee['phone']): ?><tr><th>Telefon</th><td><?= htmlspecialchars($employee['phone']) ?></td></tr><?php endif; ?>
                <?php if ($employee['address_city']): ?>
                <tr><th>Adres</th><td><?= htmlspecialchars(implode(', ', array_filter([$employee['address_street'], $employee['address_zip'] . ' ' . $employee['address_city']]))) ?></td></tr>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>Aktualna umowa</h3></div>
        <div class="card-body">
            <?php if ($currentContract): ?>
            <table class="detail-table">
                <tr><th>Typ</th><td><?= htmlspecialchars(\App\Models\HrContract::getContractTypeLabel($currentContract['contract_type'])) ?></td></tr>
                <tr><th>Stanowisko</th><td><?= htmlspecialchars($currentContract['position'] ?? '—') ?></td></tr>
                <tr><th>Od</th><td><?= htmlspecialchars($currentContract['start_date']) ?></td></tr>
                <tr><th>Wymiar</th><td><?= (float)$currentContract['work_time_fraction'] * 100 ?>% etatu</td></tr>
            </table>
            <?php else: ?>
                <p class="text-muted">Brak aktywnej umowy.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($leaveBalance)): ?>
<div class="card">
    <div class="card-header"><h3><?= $lang('hr_leave_balance') ?> <?= date('Y') ?></h3></div>
    <div class="card-body" style="padding:0;">
        <table class="table" style="margin:0;">
            <thead>
                <tr>
                    <th>Typ urlopu</th>
                    <th>Przysługuje</th>
                    <th>Wykorzystane</th>
                    <th>Pozostało</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leaveBalance as $lb): ?>
                <tr>
                    <td><?= htmlspecialchars($lb['name_pl']) ?></td>
                    <td><?= (float)($lb['limit_days']) + (float)($lb['carried_over']) ?></td>
                    <td><?= (float)$lb['used_days'] ?></td>
                    <td><strong class="<?= (float)$lb['remaining_days'] < 0 ? 'text-danger' : '' ?>"><?= (float)$lb['remaining_days'] ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
