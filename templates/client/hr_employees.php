<?php $csrf = \App\Core\Session::generateCsrfToken(); ?>
<div class="section-header" style="display:flex; align-items:center; justify-content:space-between;">
    <h1>Pracownicy</h1>
    <a href="/client/hr/employees/create" class="btn btn-primary">+ Dodaj pracownika</a>
</div>

<?php if (empty($employees)): ?>
<div class="empty-state">
    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:12px;opacity:0.4;"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
    <p>Brak pracowników.</p>
    <a href="/client/hr/employees/create" class="btn btn-primary">Dodaj pierwszego pracownika</a>
</div>
<?php else: ?>
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Lp</th>
                <th>Imię i Nazwisko</th>
                <th>PESEL</th>
                <th>Email logowania</th>
                <th>Konto</th>
                <th><?= $lang('status') ?></th>
                <th><?= $lang('actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($employees as $i => $emp): ?>
            <tr>
                <td class="text-muted"><?= $i + 1 ?></td>
                <td><strong><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></strong></td>
                <td class="text-muted"><?= htmlspecialchars($emp['pesel'] ?? '-') ?></td>
                <td><?= htmlspecialchars($emp['login_email'] ?? '-') ?></td>
                <td>
                    <?php if (!empty($emp['can_login']) && !empty($emp['password_hash'])): ?>
                        <span class="badge badge-success">Aktywne</span>
                    <?php elseif (!empty($emp['can_login']) && !empty($emp['activation_token'])): ?>
                        <span class="badge badge-warning">Zaproszenie wysłane</span>
                    <?php elseif (!empty($emp['can_login'])): ?>
                        <span class="badge badge-default">Włączone, brak hasła</span>
                    <?php else: ?>
                        <span class="badge badge-default">Wyłączone</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (((int)($emp['is_active'] ?? 0)) === 1): ?>
                        <span class="badge badge-success">Aktywny</span>
                    <?php else: ?>
                        <span class="badge badge-default">Nieaktywny</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="/client/hr/employees/<?= (int)$emp['id'] ?>/edit" class="btn btn-sm btn-secondary"><?= $lang('edit') ?></a>
                    <?php if (!empty($emp['can_login']) && !empty($emp['login_email']) && empty($emp['password_hash'])): ?>
                        <form method="POST" action="/client/hr/employees/<?= (int)$emp['id'] ?>/resend-invitation" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                            <button type="submit" class="btn btn-sm btn-secondary">Wyślij zaproszenie ponownie</button>
                        </form>
                    <?php endif; ?>
                    <?php if (((int)($emp['is_active'] ?? 0)) === 1): ?>
                        <form method="POST" action="/client/hr/employees/<?= (int)$emp['id'] ?>/delete" style="display:inline;"
                              onsubmit="return confirm('Dezaktywować pracownika? Historia płac zostanie zachowana.');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Dezaktywuj</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
