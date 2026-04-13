<?php
    $fmt = fn($v) => number_format((float)$v, 2, ',', ' ');
    $statusBadge = fn($s) => match($s) {
        'draft' => 'badge-default',
        'calculated' => 'badge-info',
        'approved' => 'badge-success',
        'exported' => 'badge-warning',
        default => 'badge-default',
    };
    $statusLabel = fn($s) => match($s) {
        'draft' => 'Szkic',
        'calculated' => 'Obliczona',
        'approved' => 'Zatwierdzona',
        'exported' => 'Wyeksportowana',
        default => $s,
    };
?>

<div class="section-header">
    <h1>Lista plac <?= sprintf('%02d/%04d', $list['month'], $list['year']) ?></h1>
    <div style="display:flex; gap:8px;">
        <a href="/client/hr/payroll" class="btn btn-secondary"><?= $lang('back') ?></a>
        <a href="/client/hr/payroll/<?= $list['id'] ?>/pdf" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Pobierz PDF
        </a>
    </div>
</div>

<div style="display:flex; align-items:center; gap:12px; margin-bottom:20px;">
    <span class="badge <?= $statusBadge($list['status'] ?? 'draft') ?>" style="font-size:14px; padding:6px 14px;">
        <?= $statusLabel($list['status'] ?? 'draft') ?>
    </span>
    <?php if (!empty($list['title'])): ?>
        <span class="text-muted"><?= htmlspecialchars($list['title']) ?></span>
    <?php endif; ?>
</div>

<!-- Summary cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background:#dbeafe; color:#2563eb;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= $fmt($list['total_gross'] ?? 0) ?></div>
            <div class="stat-label">Brutto (PLN)</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#dcfce7; color:#16a34a;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= $fmt($list['total_net'] ?? 0) ?></div>
            <div class="stat-label">Netto (PLN)</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fef3c7; color:#d97706;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= $fmt($list['total_employer_cost'] ?? 0) ?></div>
            <div class="stat-label">Koszt pracodawcy (PLN)</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fce7f3; color:#db2777;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= count($entries) ?></div>
            <div class="stat-label">Pracownikow</div>
        </div>
    </div>
</div>

<!-- Entries table -->
<?php if (empty($entries)): ?>
<div class="empty-state">
    <p>Brak pozycji na liscie plac.</p>
</div>
<?php else: ?>
<div class="table-responsive" style="margin-top:20px;">
    <table class="table">
        <thead>
            <tr>
                <th>Pracownik</th>
                <th class="text-right">Brutto</th>
                <th class="text-right">ZUS pr.</th>
                <th class="text-right">Zdrowotna</th>
                <th class="text-right">KUP</th>
                <th class="text-right">Podst. PIT</th>
                <th class="text-right">Zal. PIT</th>
                <th class="text-right">PPK</th>
                <th class="text-right">Netto</th>
                <th class="text-right">Koszt prac.</th>
                <th><?= $lang('actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($entries as $e): ?>
            <tr>
                <td><strong><?= htmlspecialchars($e['employee_name'] ?? '-') ?></strong></td>
                <td class="text-right" style="font-variant-numeric:tabular-nums;"><?= $fmt($e['gross'] ?? 0) ?></td>
                <td class="text-right" style="font-variant-numeric:tabular-nums;"><?= $fmt($e['zus_employee'] ?? 0) ?></td>
                <td class="text-right" style="font-variant-numeric:tabular-nums;"><?= $fmt($e['health_insurance'] ?? 0) ?></td>
                <td class="text-right" style="font-variant-numeric:tabular-nums;"><?= $fmt($e['tax_deductible_costs'] ?? 0) ?></td>
                <td class="text-right" style="font-variant-numeric:tabular-nums;"><?= $fmt($e['pit_base'] ?? 0) ?></td>
                <td class="text-right" style="font-variant-numeric:tabular-nums;"><?= $fmt($e['pit_advance'] ?? 0) ?></td>
                <td class="text-right" style="font-variant-numeric:tabular-nums;"><?= $fmt($e['ppk_employee'] ?? 0) ?></td>
                <td class="text-right" style="font-variant-numeric:tabular-nums; font-weight:600;"><?= $fmt($e['net'] ?? 0) ?></td>
                <td class="text-right" style="font-variant-numeric:tabular-nums;"><?= $fmt($e['employer_cost'] ?? 0) ?></td>
                <td>
                    <a href="/client/hr/payroll/<?= $list['id'] ?>/payslip/<?= $e['id'] ?>/pdf" class="btn btn-xs" title="Pobierz pasek placowy">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Pasek
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight:700; background:var(--gray-50);">
                <td>RAZEM</td>
                <td class="text-right" style="font-variant-numeric:tabular-nums;"><?= $fmt($list['total_gross'] ?? 0) ?></td>
                <td class="text-right" style="font-variant-numeric:tabular-nums;"><?= $fmt(array_sum(array_column($entries, 'zus_employee'))) ?></td>
                <td class="text-right" style="font-variant-numeric:tabular-nums;"><?= $fmt(array_sum(array_column($entries, 'health_insurance'))) ?></td>
                <td class="text-right" style="font-variant-numeric:tabular-nums;"><?= $fmt(array_sum(array_column($entries, 'tax_deductible_costs'))) ?></td>
                <td class="text-right" style="font-variant-numeric:tabular-nums;"><?= $fmt(array_sum(array_column($entries, 'pit_base'))) ?></td>
                <td class="text-right" style="font-variant-numeric:tabular-nums;"><?= $fmt(array_sum(array_column($entries, 'pit_advance'))) ?></td>
                <td class="text-right" style="font-variant-numeric:tabular-nums;"><?= $fmt(array_sum(array_column($entries, 'ppk_employee'))) ?></td>
                <td class="text-right" style="font-variant-numeric:tabular-nums;"><?= $fmt($list['total_net'] ?? 0) ?></td>
                <td class="text-right" style="font-variant-numeric:tabular-nums;"><?= $fmt($list['total_employer_cost'] ?? 0) ?></td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>
<?php endif; ?>
