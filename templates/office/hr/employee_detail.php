<div class="section-header">
    <div>
        <div class="breadcrumb-path" style="font-size:13px;color:var(--text-muted);margin-bottom:4px;">
            <a href="/office/hr/settings"><?= $lang('hr_module') ?></a> &rsaquo;
            <a href="/office/hr/<?= $clientId ?>/employees"><?= htmlspecialchars($client['company_name']) ?></a> &rsaquo;
            <?= htmlspecialchars($employee['full_name']) ?>
        </div>
        <h1><?= htmlspecialchars($employee['full_name']) ?></h1>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="/office/hr/<?= $clientId ?>/employees/<?= $employee['id'] ?>/edit" class="btn btn-secondary"><?= $lang('edit') ?></a>
        <?php if ($employee['is_active']): ?>
        <a href="/office/hr/<?= $clientId ?>/employees/<?= $employee['id'] ?>/archive-form" class="btn btn-danger">
            <?= $lang('hr_archive_employee') ?>
        </a>
        <?php endif; ?>
        <?php if (!empty($employee['swiadectwo_pdf_path'])): ?>
        <a href="/office/hr/<?= $clientId ?>/employees/<?= $employee['id'] ?>/swiadectwo.pdf"
           class="btn btn-secondary" title="<?= $lang('hr_swiadectwo_download') ?>">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            <?= $lang('hr_swiadectwo_pracy') ?>
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($flash_success): ?><div class="alert alert-success"><?= htmlspecialchars($flash_success) ?></div><?php endif; ?>
<?php if ($flash_error): ?><div class="alert alert-error"><?= $flash_error ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">

    <!-- Dane osobowe -->
    <div class="card">
        <div class="card-header"><h3><?= $lang('hr_personal_data') ?></h3></div>
        <div class="card-body">
            <table class="detail-table">
                <tr><th>PESEL</th><td><?= \App\Models\HrEmployee::maskPesel($employee['pesel']) ?></td></tr>
                <?php if ($employee['nip']): ?><tr><th>NIP</th><td><?= htmlspecialchars($employee['nip']) ?></td></tr><?php endif; ?>
                <?php if ($employee['birth_date']): ?><tr><th><?= $lang('hr_birth_date') ?></th><td><?= htmlspecialchars($employee['birth_date']) ?></td></tr><?php endif; ?>
                <?php if ($employee['email']): ?><tr><th><?= $lang('email') ?></th><td><?= htmlspecialchars($employee['email']) ?></td></tr><?php endif; ?>
                <?php if ($employee['phone']): ?><tr><th><?= $lang('hr_phone') ?></th><td><?= htmlspecialchars($employee['phone']) ?></td></tr><?php endif; ?>
                <?php if ($employee['address_city']): ?><tr><th>Adres</th><td><?= htmlspecialchars(implode(', ', array_filter([$employee['address_street'], $employee['address_zip'] . ' ' . $employee['address_city']]))) ?></td></tr><?php endif; ?>
                <?php if ($employee['bank_account_iban']): ?><tr><th><?= $lang('hr_bank_account') ?></th><td><code><?= htmlspecialchars($employee['bank_account_iban']) ?></code></td></tr><?php endif; ?>
            </table>
        </div>
    </div>

    <!-- Ustawienia podatkowe/ZUS -->
    <div class="card">
        <div class="card-header"><h3><?= $lang('hr_tax_settings') ?> / ZUS / PPK</h3></div>
        <div class="card-body">
            <table class="detail-table">
                <tr><th><?= $lang('hr_kup_amount') ?></th><td><?= htmlspecialchars($employee['kup_amount']) ?> PLN</td></tr>
                <tr>
                    <th><?= $lang('hr_pit2_submitted') ?></th>
                    <td><?= $employee['pit2_submitted'] ? '<span class="badge badge-success">Tak</span>' : '<span class="badge badge-default">Nie</span>' ?></td>
                </tr>
                <tr><th><?= $lang('hr_annual_leave_days') ?></th><td><?= (int)$employee['annual_leave_days'] ?> dni</td></tr>
                <tr><th><?= $lang('hr_zus_title_code') ?></th><td><?= htmlspecialchars($employee['zus_title_code'] ?? '—') ?></td></tr>
                <tr>
                    <th><?= $lang('hr_disability_level') ?></th>
                    <td><?= htmlspecialchars(match($employee['disability_level'] ?? 'none') {
                        'none' => 'Brak', 'mild' => 'Lekki', 'moderate' => 'Umiarkowany', 'severe' => 'Znaczny', default => '—'
                    }) ?></td>
                </tr>
                <tr>
                    <th><?= $lang('hr_ppk') ?></th>
                    <td>
                        <?php if ($employee['ppk_enrolled']): ?>
                            <span class="badge badge-success">Uczestnik</span>
                            <span class="text-muted" style="font-size:12px">prac. <?= (float)$employee['ppk_employee_rate'] ?>% / prac. <?= (float)$employee['ppk_employer_rate'] ?>%</span>
                        <?php else: ?>
                            <span class="badge badge-default">Nie uczestniczy</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if ($employee['tax_office_name']): ?>
                <tr><th>Urząd Skarbowy</th><td><?= htmlspecialchars($employee['tax_office_name']) ?></td></tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<!-- Umowy -->
<div class="card" style="margin-bottom:16px;">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
        <h3><?= $lang('hr_contracts') ?></h3>
        <a href="/office/hr/<?= $clientId ?>/employees/<?= $employee['id'] ?>/contracts/create" class="btn btn-xs btn-primary">
            + <?= $lang('hr_add_contract') ?>
        </a>
    </div>
    <div class="card-body" style="padding:0;">
        <?php if (empty($contracts)): ?>
            <div style="padding:20px;text-align:center;color:var(--text-muted)"><?= $lang('hr_no_contracts') ?></div>
        <?php else: ?>
        <table class="table" style="margin:0;">
            <thead>
                <tr>
                    <th><?= $lang('hr_contract_type') ?></th>
                    <th><?= $lang('hr_position') ?></th>
                    <th><?= $lang('hr_base_salary') ?></th>
                    <th>Od</th><th>Do</th>
                    <th><?= $lang('status') ?></th>
                    <th><?= $lang('actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contracts as $c): ?>
                <tr>
                    <td><span class="badge badge-info"><?= htmlspecialchars(\App\Models\HrContract::getContractTypeLabel($c['contract_type'])) ?></span></td>
                    <td><?= htmlspecialchars($c['position'] ?? '—') ?></td>
                    <td><strong><?= number_format((float)$c['base_salary'], 2, ',', ' ') ?> PLN</strong></td>
                    <td><?= htmlspecialchars($c['start_date']) ?></td>
                    <td><?= $c['end_date'] ? htmlspecialchars($c['end_date']) : '<span class="text-muted">bezterminowo</span>' ?></td>
                    <td>
                        <?= $c['is_current']
                            ? '<span class="badge badge-success">Aktualna</span>'
                            : '<span class="badge badge-default">Zakończona</span>' ?>
                    </td>
                    <td>
                        <?php if ($c['is_current'] && !$c['end_date']): ?>
                        <button class="btn btn-xs btn-danger"
                                onclick="document.getElementById('terminate-form-<?= $c['id'] ?>').style.display='block'">
                            Rozwiąż
                        </button>
                        <div id="terminate-form-<?= $c['id'] ?>" style="display:none;margin-top:8px;">
                            <form method="POST" action="/office/hr/<?= $clientId ?>/employees/<?= $employee['id'] ?>/contracts/<?= $c['id'] ?>/terminate">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <input type="date" name="end_date" class="form-control" value="<?= date('Y-m-d') ?>" style="margin-bottom:6px;">
                                <button type="submit" class="btn btn-xs btn-danger">Potwierdź</button>
                            </form>
                        </div>
                        <?php endif; ?>
                        <a href="/office/hr/<?= $clientId ?>/employees/<?= $employee['id'] ?>/contracts/<?= $c['id'] ?>/pdf"
                           class="btn btn-xs btn-secondary" title="<?= $lang('hr_umowa_download') ?>">PDF</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Onboarding / Offboarding progress widget (Faza 14) -->
<?php if ($onboardingProgress['total'] > 0 || $offboardingProgress['total'] > 0): ?>
<div class="card" style="margin-bottom:16px;padding:14px 16px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
        <h3 style="margin:0;font-size:14px;"><?= $lang('hr_onboarding') ?> / <?= $lang('hr_offboarding') ?></h3>
        <a href="/office/hr/<?= $clientId ?>/employees/<?= $employee['id'] ?>/onboarding" style="font-size:13px;">
            <?= $lang('hr_onboarding_progress') ?> &#8594;
        </a>
    </div>
    <?php foreach (['onboarding'=>$onboardingProgress,'offboarding'=>$offboardingProgress] as $ph=>$prog):
        if ($prog['total'] === 0) continue;
        $pct      = $prog['pct'];
        $barColor = $pct>=100 ? '#16a34a' : ($pct>0 ? '#3b82f6' : '#9ca3af');
        $phLabel  = $ph==='onboarding' ? $lang('hr_onboarding') : $lang('hr_offboarding');
    ?>
    <div style="margin-bottom:8px;">
        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:3px;">
            <span><?= htmlspecialchars($phLabel) ?></span>
            <span style="color:<?= $barColor ?>;font-weight:600;"><?= $prog['done'] ?>/<?= $prog['total'] ?></span>
        </div>
        <div style="background:#e5e7eb;border-radius:4px;height:6px;">
            <div style="width:<?= $pct ?>%;background:<?= $barColor ?>;height:100%;border-radius:4px;"></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Saldo urlopowe -->
<?php if (!empty($leaveBalance)): ?>
<div class="card" style="margin-bottom:16px;">
    <div class="card-header"><h3><?= $lang('hr_leave_balance') ?> <?= date('Y') ?></h3></div>
    <div class="card-body" style="padding:0;">
        <table class="table" style="margin:0;">
            <thead>
                <tr>
                    <th>Typ urlopu</th>
                    <th>Przysługuje</th>
                    <th>Przeniesione</th>
                    <th>Wykorzystane</th>
                    <th>Planowane</th>
                    <th>Pozostało</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leaveBalance as $lb): ?>
                <tr>
                    <td><?= htmlspecialchars($lb['name_pl']) ?></td>
                    <td><?= (float)$lb['limit_days'] ?></td>
                    <td><?= (float)$lb['carried_over'] ?></td>
                    <td><?= (float)$lb['used_days'] ?></td>
                    <td><?= (float)$lb['planned_days'] ?></td>
                    <td><strong class="<?= (float)$lb['remaining_days'] < 0 ? 'text-danger' : '' ?>"><?= (float)$lb['remaining_days'] ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Ostatnie wnioski urlopowe -->
<?php if (!empty($leaveRequests)): ?>
<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
        <h3><?= $lang('hr_leave_requests') ?></h3>
        <a href="/office/hr/<?= $clientId ?>/leaves" class="btn btn-xs"><?= $lang('view') ?> wszystkie</a>
    </div>
    <div class="card-body" style="padding:0;">
        <table class="table" style="margin:0;">
            <thead><tr><th>Typ</th><th>Od</th><th>Do</th><th>Dni</th><th><?= $lang('status') ?></th></tr></thead>
            <tbody>
                <?php foreach (array_slice($leaveRequests, 0, 5) as $lr): ?>
                <tr>
                    <td><?= htmlspecialchars($lr['leave_type_name']) ?></td>
                    <td><?= htmlspecialchars($lr['date_from']) ?></td>
                    <td><?= htmlspecialchars($lr['date_to']) ?></td>
                    <td><?= (float)$lr['days_count'] ?></td>
                    <td>
                        <?php $statusClass = match($lr['status']) { 'approved' => 'badge-success', 'rejected' => 'badge-danger', 'cancelled' => 'badge-default', default => 'badge-warning' }; ?>
                        <span class="badge <?= $statusClass ?>"><?= $lang('hr_leave_' . $lr['status']) ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>