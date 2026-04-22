<div class="section-header">
    <div>
        <div class="breadcrumb-path" style="font-size:13px;color:var(--text-muted);margin-bottom:4px;">
            <a href="/office/hr/settings"><?= $lang('hr_module') ?></a> &rsaquo;
            <?= htmlspecialchars($client['company_name']) ?>
        </div>
        <h1>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
            <?= $lang('hr_employees') ?> — <?= htmlspecialchars($client['company_name']) ?>
        </h1>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="/office/hr/<?= $clientId ?>/attendance" class="btn btn-secondary">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <?= $lang('hr_attendance') ?>
        </a>
        <a href="/office/hr/<?= $clientId ?>/leaves" class="btn btn-secondary">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/></svg>
            <?= $lang('hr_leaves') ?>
        </a>
        <a href="/office/hr/<?= $clientId ?>/payroll" class="btn btn-secondary">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            <?= $lang('hr_payroll') ?>
        </a>
        <a href="/office/hr/<?= $clientId ?>/ppk" class="btn btn-secondary">PPK</a>
        <a href="/office/hr/<?= $clientId ?>/analytics" class="btn btn-secondary">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            <?= $lang('hr_analytics') ?>
        </a>
        <a href="/office/hr/<?= $clientId ?>/employees/create" class="btn btn-primary">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <?= $lang('hr_add_employee') ?>
        </a>
    </div>
</div>

<?php include __DIR__ . '/../hr_nav.php'; ?>

<?php if ($flash_success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($flash_success) ?></div>
<?php endif; ?>
<?php if ($flash_error): ?>
    <div class="alert alert-error"><?= $flash_error ?></div>
<?php endif; ?>

<?php if (empty($employees)): ?>
<div class="empty-state">
    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:12px;opacity:0.4;"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
    <p><?= $lang('hr_no_employees') ?></p>
    <a href="/office/hr/<?= $clientId ?>/employees/create" class="btn btn-primary"><?= $lang('hr_add_first_employee') ?></a>
</div>
<?php else: ?>
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th><?= $lang('name') ?></th>
                <th><?= $lang('pesel') ?></th>
                <th><?= $lang('hr_contract_type') ?></th>
                <th><?= $lang('hr_position') ?></th>
                <th><?= $lang('hr_base_salary') ?></th>
                <th><?= $lang('status') ?></th>
                <th><?= $lang('actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($employees as $emp): ?>
            <tr>
                <td>
                    <a href="/office/hr/<?= $clientId ?>/employees/<?= $emp['id'] ?>" class="fw-medium">
                        <?= htmlspecialchars($emp['full_name']) ?>
                    </a>
                    <?php if ($emp['email']): ?>
                        <div style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($emp['email']) ?></div>
                    <?php endif; ?>
                </td>
                <td class="text-muted"><?= \App\Models\HrEmployee::maskPesel($emp['pesel']) ?></td>
                <td>
                    <?php if ($emp['contract_type']): ?>
                        <span class="badge badge-info"><?= htmlspecialchars(\App\Models\HrContract::getContractTypeLabel($emp['contract_type'])) ?></span>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="text-muted"><?= htmlspecialchars($emp['position'] ?? '—') ?></td>
                <td>
                    <?php if ($emp['base_salary']): ?>
                        <strong><?= number_format((float)$emp['base_salary'], 2, ',', ' ') ?> PLN</strong>
                        <?php if ((float)($emp['work_time_fraction'] ?? 1) < 1): ?>
                            <span class="text-muted">(<?= (float)$emp['work_time_fraction'] * 100 ?>%)</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($emp['is_active']): ?>
                        <span class="badge badge-success"><?= $lang('active') ?></span>
                    <?php else: ?>
                        <span class="badge badge-default"><?= $lang('inactive') ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="action-buttons">
                        <a href="/office/hr/<?= $clientId ?>/employees/<?= $emp['id'] ?>" class="btn btn-xs"><?= $lang('details') ?></a>
                        <a href="/office/hr/<?= $clientId ?>/employees/<?= $emp['id'] ?>/edit" class="btn btn-xs"><?= $lang('edit') ?></a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>