<?php
$company = \App\Core\Session::get('client_employee_company') ?? '';
$name = \App\Core\Session::get('client_employee_name') ?? '';
?>
<div class="section-header">
    <h1>Witaj, <?= htmlspecialchars($name) ?></h1>
    <?php if ($company): ?>
        <p class="text-muted">Konto pracownicze · <?= htmlspecialchars($company) ?></p>
    <?php endif; ?>
</div>

<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:16px; margin-bottom:24px;">
    <a href="/employee/payslips" class="form-card" style="padding:20px; text-decoration:none; color:inherit;">
        <h3 style="margin:0 0 8px 0;">📄 Paski wynagrodzeń</h3>
        <p class="text-muted" style="margin:0;">Lista pasków do wglądu i pobrania w PDF.</p>
    </a>
    <a href="/employee/leaves" class="form-card" style="padding:20px; text-decoration:none; color:inherit;">
        <h3 style="margin:0 0 8px 0;">🏖️ Urlopy</h3>
        <p class="text-muted" style="margin:0;">Historia urlopów + wniosek o urlop.</p>
    </a>
    <a href="/employee/profile" class="form-card" style="padding:20px; text-decoration:none; color:inherit;">
        <h3 style="margin:0 0 8px 0;">👤 Profil</h3>
        <p class="text-muted" style="margin:0;">Dane osobowe, hasło, 2FA.</p>
    </a>
</div>

<div class="form-card" style="padding:20px; margin-bottom:16px;">
    <h2 style="margin-top:0;">Ostatnie paski</h2>
    <?php if (empty($latestPayslips)): ?>
        <p class="text-muted">Brak pasków.</p>
    <?php else: ?>
        <ul style="list-style:none; padding:0;">
            <?php foreach ($latestPayslips as $p): ?>
                <li style="padding:8px 0; border-bottom:1px solid var(--gray-200);">
                    <?= htmlspecialchars(sprintf('%02d/%04d', (int)$p['month'], (int)$p['year'])) ?>
                    — netto <?= number_format((float)($p['net_salary'] ?? 0), 2, ',', ' ') ?> zł
                    <a href="/employee/payslips/<?= (int)$p['id'] ?>/pdf" class="btn btn-sm btn-secondary" style="float:right;">PDF</a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<div class="form-card" style="padding:20px;">
    <h2 style="margin-top:0;">Ostatnie urlopy</h2>
    <a href="/employee/leaves/request" class="btn btn-primary" style="float:right; margin-top:-44px;">Złóż wniosek</a>
    <?php if (empty($latestLeaves)): ?>
        <p class="text-muted">Brak wniosków urlopowych.</p>
    <?php else: ?>
        <ul style="list-style:none; padding:0;">
            <?php foreach ($latestLeaves as $l): ?>
                <li style="padding:8px 0; border-bottom:1px solid var(--gray-200);">
                    <?= htmlspecialchars($l['leave_type']) ?>
                    · <?= htmlspecialchars($l['start_date']) ?> → <?= htmlspecialchars($l['end_date']) ?>
                    · <?= (int) $l['business_days'] ?> dni
                    <span class="badge badge-<?= $l['status'] === 'approved' ? 'success' : ($l['status'] === 'rejected' ? 'danger' : 'warning') ?>"
                          style="float:right;"><?= htmlspecialchars($l['status']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
