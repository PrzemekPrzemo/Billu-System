<div class="section-header">
    <h1>Paski wynagrodzeń</h1>
</div>

<?php if (empty($entries)): ?>
    <div class="empty-state">
        <p>Brak pasków wynagrodzeń.</p>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Okres</th>
                    <th>Brutto</th>
                    <th>ZUS</th>
                    <th>Podatek</th>
                    <th>Netto</th>
                    <th><?= $lang('actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $e): ?>
                <tr>
                    <td><strong><?= htmlspecialchars(sprintf('%02d/%04d', (int)$e['month'], (int)$e['year'])) ?></strong></td>
                    <td><?= number_format((float)($e['gross_salary'] ?? 0), 2, ',', ' ') ?></td>
                    <td><?= number_format((float)($e['zus_total_employee'] ?? 0), 2, ',', ' ') ?></td>
                    <td><?= number_format((float)($e['tax_advance'] ?? 0), 2, ',', ' ') ?></td>
                    <td><strong><?= number_format((float)($e['net_salary'] ?? 0), 2, ',', ' ') ?></strong></td>
                    <td>
                        <a href="/employee/payslips/<?= (int)$e['id'] ?>/pdf" class="btn btn-sm btn-secondary" target="_blank">PDF</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
