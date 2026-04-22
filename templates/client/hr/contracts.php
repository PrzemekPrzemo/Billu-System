<?php $n2 = fn($v) => number_format((float)$v, 2, ',', ' '); ?>

<div class="section-header">
    <div>
        <h1>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            <?= $lang('hr_contracts') ?>
        </h1>
    </div>
</div>

<?php include __DIR__ . '/../hr_nav.php'; ?>

<?php if (empty($contracts)): ?>
<div class="empty-state">
    <p>Brak umów do wyświetlenia.</p>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body" style="padding:0;">
        <table class="table" style="margin:0;">
            <thead>
                <tr>
                    <th><?= $lang('hr_leave_employee') ?></th>
                    <th><?= $lang('hr_contract_type') ?></th>
                    <th><?= $lang('hr_position') ?></th>
                    <th><?= $lang('hr_leave_date_from') ?></th>
                    <th><?= $lang('hr_leave_date_to') ?></th>
                    <th><?= $lang('hr_gross_salary') ?></th>
                    <th><?= $lang('status') ?></th>
                    <th>PDF</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contracts as $c):
                    $isExpired = $c['end_date'] && strtotime($c['end_date']) < time();
                    $isExpiring = $c['end_date'] && !$isExpired && strtotime($c['end_date']) < strtotime('+30 days');
                ?>
                <tr style="<?= $isExpired ? 'opacity:0.6;' : '' ?>">
                    <td>
                        <a href="/client/hr/employees/<?= $c['employee_id'] ?>">
                            <strong><?= htmlspecialchars($c['employee_name']) ?></strong>
                        </a>
                    </td>
                    <td><span class="badge badge-info"><?= htmlspecialchars(\App\Models\HrContract::getContractTypeLabel($c['contract_type'])) ?></span></td>
                    <td><?= htmlspecialchars($c['position'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($c['start_date']) ?></td>
                    <td>
                        <?php if ($c['end_date']): ?>
                            <span style="color:<?= $isExpiring ? 'var(--warning)' : ($isExpired ? 'var(--danger)' : 'var(--text)') ?>;font-weight:<?= $isExpiring || $isExpired ? '600' : '400' ?>;">
                                <?= htmlspecialchars($c['end_date']) ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted">nieokreślony</span>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;"><?= $n2($c['base_salary']) ?> PLN</td>
                    <td>
                        <?php if ($c['is_current']): ?>
                            <span class="badge badge-success">Aktywna</span>
                        <?php else: ?>
                            <span class="badge badge-default">Zakończona</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="/client/hr/contracts/<?= $c['id'] ?>/pdf" class="btn btn-xs btn-secondary" target="_blank" title="Pobierz PDF">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
