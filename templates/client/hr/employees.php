<div class="section-header">
    <h1>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
        <?= $lang('hr_employees') ?>
    </h1>
    <a href="/client/hr/leaves" class="btn btn-secondary">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <?= $lang('hr_leaves') ?>
    </a>
</div>

<?php include __DIR__ . '/../hr_nav.php'; ?>

<?php if (empty($employees)): ?>
<div class="empty-state">
    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:12px;opacity:0.4;"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
    <p><?= $lang('hr_no_employees') ?></p>
    <p class="text-muted">Skontaktuj się z biurem księgowym, aby dodać pracowników.</p>
</div>
<?php else: ?>
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th><?= $lang('name') ?></th>
                <th><?= $lang('hr_contract_type') ?></th>
                <th><?= $lang('hr_position') ?></th>
                <th><?= $lang('status') ?></th>
                <th><?= $lang('actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($employees as $emp): ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($emp['full_name']) ?></strong>
                    <?php if ($emp['email']): ?>
                        <div style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($emp['email']) ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($emp['contract_type']): ?>
                        <span class="badge badge-info"><?= htmlspecialchars(\App\Models\HrContract::getContractTypeLabel($emp['contract_type'])) ?></span>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="text-muted"><?= htmlspecialchars($emp['position'] ?? '—') ?></td>
                <td>
                    <?php if ($emp['is_active']): ?>
                        <span class="badge badge-success"><?= $lang('active') ?></span>
                    <?php else: ?>
                        <span class="badge badge-default"><?= $lang('inactive') ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="/client/hr/employees/<?= $emp['id'] ?>" class="btn btn-xs"><?= $lang('details') ?></a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
