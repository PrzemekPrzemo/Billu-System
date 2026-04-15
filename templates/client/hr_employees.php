<div class="section-header">
    <h1>Pracownicy</h1>
</div>

<?php if (empty($employees)): ?>
<div class="empty-state">
    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:12px;opacity:0.4;"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
    <p>Brak pracownikow.</p>
</div>
<?php else: ?>
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Lp</th>
                <th>Imie i Nazwisko</th>
                <th>PESEL</th>
                <th>Stanowisko</th>
                <th>Data zatrudnienia</th>
                <th><?= $lang('status') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($employees as $i => $emp): ?>
            <tr>
                <td class="text-muted"><?= $i + 1 ?></td>
                <td><strong><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></strong></td>
                <td class="text-muted"><?= htmlspecialchars($emp['pesel'] ?? '-') ?></td>
                <td><?= htmlspecialchars($emp['position'] ?? '-') ?></td>
                <td><?= htmlspecialchars($emp['hired_at'] ?? '-') ?></td>
                <td>
                    <?php if (($emp['status'] ?? 'active') === 'active'): ?>
                        <span class="badge badge-success">Aktywny</span>
                    <?php elseif (($emp['status'] ?? '') === 'on_leave'): ?>
                        <span class="badge badge-warning">Na urlopie</span>
                    <?php else: ?>
                        <span class="badge badge-default">Nieaktywny</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
