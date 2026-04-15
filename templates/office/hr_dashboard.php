<div class="section-header">
    <h1><?= $lang('hr_module') ?></h1>
</div>

<?php $flash = \App\Core\Session::getFlash('success'); ?>
<?php if ($flash): ?>
    <div class="alert alert-success"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background:#dbeafe; color:#2563eb;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= (int)($totals['employees'] ?? 0) ?></div>
            <div class="stat-label">Pracownicy</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#dcfce7; color:#16a34a;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= (int)($totals['contracts'] ?? 0) ?></div>
            <div class="stat-label">Aktywne umowy</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fef3c7; color:#d97706;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= (int)($totals['payrolls_pending'] ?? 0) ?></div>
            <div class="stat-label">Listy plac do zatwierdzenia</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fce7f3; color:#db2777;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= (int)($totals['leaves_pending'] ?? 0) ?></div>
            <div class="stat-label">Urlopy oczekujace</div>
        </div>
    </div>
</div>

<?php if (empty($clients)): ?>
<div class="empty-state">
    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:12px;opacity:0.4;"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
    <p>Brak klientow z modulem kadrowo-placowym.</p>
</div>
<?php else: ?>

<div class="section" style="margin-top:20px;">
    <h2>Klienci - kadry i place</h2>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Klient</th>
                    <th>NIP</th>
                    <th>Pracownicy</th>
                    <th>Aktywne umowy</th>
                    <th>Ostatnia lista plac</th>
                    <th><?= $lang('actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clients as $c): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($c['company_name']) ?></strong></td>
                    <td class="text-muted"><?= htmlspecialchars($c['nip'] ?? '-') ?></td>
                    <td>
                        <span class="badge badge-info"><?= (int)($c['employee_count'] ?? 0) ?></span>
                    </td>
                    <td>
                        <span class="badge badge-success"><?= (int)($c['active_contracts'] ?? 0) ?></span>
                    </td>
                    <td>
                        <?php if (!empty($c['last_payroll_status'])): ?>
                            <?php
                                $pBadge = match($c['last_payroll_status']) {
                                    'approved' => 'badge-success',
                                    'calculated' => 'badge-info',
                                    'draft' => 'badge-default',
                                    default => 'badge-warning',
                                };
                                $pLabel = match($c['last_payroll_status']) {
                                    'approved' => 'Zatwierdzona',
                                    'calculated' => 'Obliczona',
                                    'draft' => 'Szkic',
                                    'exported' => 'Wyeksportowana',
                                    default => $c['last_payroll_status'],
                                };
                            ?>
                            <span class="badge <?= $pBadge ?>"><?= $pLabel ?></span>
                            <span class="text-muted" style="font-size:12px;"><?= htmlspecialchars($c['last_payroll_period'] ?? '') ?></span>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <?php if ($canModule('hr')): ?>
                                <a href="/office/hr/<?= $c['id'] ?>/employees" class="btn btn-xs">Pracownicy</a>
                            <?php endif; ?>
                            <?php if ($canModule('payroll-contracts')): ?>
                                <a href="/office/hr/<?= $c['id'] ?>/contracts" class="btn btn-xs">Umowy</a>
                            <?php endif; ?>
                            <?php if ($canModule('payroll-lists')): ?>
                                <a href="/office/hr/<?= $c['id'] ?>/payroll" class="btn btn-xs">Listy plac</a>
                            <?php endif; ?>
                            <?php if ($canModule('payroll-leave')): ?>
                                <a href="/office/hr/<?= $c['id'] ?>/leaves" class="btn btn-xs">Urlopy</a>
                            <?php endif; ?>
                            <?php if ($canModule('payroll-pit')): ?>
                                <a href="/office/hr/<?= $c['id'] ?>/declarations" class="btn btn-xs">Deklaracje</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($canModule('payroll-calc')): ?>
<div style="margin-top:20px;">
    <a href="/office/hr/calculator" class="btn btn-primary" style="display:inline-flex; align-items:center; gap:6px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="10" x2="10" y2="10"/><line x1="14" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="10" y2="14"/><line x1="14" y1="14" x2="16" y2="14"/><line x1="8" y1="18" x2="16" y2="18"/></svg>
        Kalkulator wynagrodzen
    </a>
</div>
<?php endif; ?>

<?php endif; ?>
