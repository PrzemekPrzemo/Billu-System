<!DOCTYPE html>
<html lang="<?= \App\Core\Language::getLocale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
    <meta name="theme-color" content="<?= htmlspecialchars($branding['primary_color'] ?? '#008F8F') ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars($branding['system_name'] ?? 'BiLLU') ?>">
    <meta name="mobile-web-app-capable" content="yes">
    <title><?= htmlspecialchars($branding['system_name'] ?? 'BiLLU') ?></title>
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/assets/img/icon-192.png">
    <script>if(localStorage.getItem('theme')==='dark')document.documentElement.setAttribute('data-theme','dark');</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Roboto+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= \App\Core\Asset::url('css/style.css') ?>">
    <style>
        :root {
            --primary: <?= htmlspecialchars($branding['primary_color'] ?? '#008F8F') ?>;
            --primary-dark: <?= htmlspecialchars($branding['secondary_color'] ?? '#0B2430') ?>;
            --accent: <?= htmlspecialchars($branding['accent_color'] ?? '#882D61') ?>;
        }
    </style>
</head>
<body>
    <?php if (\App\Core\Auth::isLoggedIn()): ?>
    <?php if (\App\Core\Auth::isEmployeeImpersonating()): ?>
    <div class="impersonation-bar" style="background:var(--info); color:#fff;">
        <span><?= $lang('impersonating_as_client') ?>: <strong><?= htmlspecialchars(\App\Core\Session::get('client_name', '')) ?></strong>
        (<?= $lang('office_employee') ?>: <?= htmlspecialchars(\App\Core\Session::get('impersonator_name', '')) ?>)</span>
        <a href="/stop-impersonation" class="btn btn-sm" style="background:#fff; color:var(--info);">Powrot do panelu biura</a>
    </div>
    <?php elseif ($isImpersonating ?? false): ?>
    <div class="impersonation-bar">
        <span>Zalogowano jako: <strong>
            <?php if (\App\Core\Auth::isClient()): ?>
                <?= htmlspecialchars(\App\Core\Session::get('client_name', '')) ?>
            <?php elseif (\App\Core\Auth::isOffice()): ?>
                <?= htmlspecialchars(\App\Core\Session::get('office_name', '')) ?>
            <?php endif; ?>
        </strong> (admin: <?= htmlspecialchars($impersonatorName) ?>)</span>
        <a href="/stop-impersonation" class="btn btn-sm btn-warning">Powrot do admina</a>
    </div>
    <?php endif; ?>

    <?php $isAdmin = \App\Core\Auth::isAdmin(); ?>
    <?php $isOffice = \App\Core\Auth::isOffice(); ?>
    <?php $isEmployee = \App\Core\Auth::isEmployee(); ?>
    <?php $isClient = \App\Core\Auth::isClient(); ?>
    <?php $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH); ?>

    <nav class="navbar">
        <div class="container nav-container">
            <div style="display:flex;align-items:center;gap:8px;">
                <button class="sidebar-toggle" onclick="document.body.classList.toggle('sidebar-open')" aria-label="Menu">&#9776;</button>
            </div>
            <div class="nav-topbar-right">
                <?php
                    $notifUrl = $isAdmin ? '/admin/notifications' : (($isOffice || $isEmployee) ? '/office/notifications' : '/client/notifications');
                ?>
                <a href="<?= $notifUrl ?>" class="nav-link nav-bell" title="<?= $lang('notifications') ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
                    <?php if (($notificationCount ?? 0) > 0): ?>
                        <span class="notif-badge"><?= $notificationCount ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?= $isAdmin ? '/admin' : (($isOffice || $isEmployee) ? '/office' : '/client') ?>" class="nav-user">
                    <?php if ($isAdmin): ?>
                        <?= htmlspecialchars(\App\Core\Session::get('username', '')) ?>
                    <?php elseif ($isEmployee): ?>
                        <?= htmlspecialchars(\App\Core\Session::get('employee_name', '')) ?>
                    <?php elseif ($isOffice): ?>
                        <?= htmlspecialchars(\App\Core\Session::get('office_name', '')) ?>
                    <?php else: ?>
                        <?= htmlspecialchars(\App\Core\Session::get('client_name', '')) ?>
                    <?php endif; ?>
                </a>
                <?php if (!$isAdmin): ?>
                <span class="nav-lang">
                    <?php $langBase = ($isOffice || $isEmployee) ? '/office' : '/client'; ?>
                    <a href="<?= $langBase ?>/language?lang=pl" class="nav-link <?= \App\Core\Language::getLocale() === 'pl' ? 'active' : '' ?>">PL</a>
                    <a href="<?= $langBase ?>/language?lang=en" class="nav-link <?= \App\Core\Language::getLocale() === 'en' ? 'active' : '' ?>">EN</a>
                </span>
                <?php endif; ?>
                <button class="theme-toggle" onclick="toggleTheme()" title="Dark/Light mode" aria-label="Toggle dark mode">
                    <svg class="theme-icon-moon" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M21.752 15.002A9.72 9.72 0 0112.035 3.75a9.72 9.72 0 00-8.285 11.252A9.72 9.72 0 0012 24a9.72 9.72 0 009.752-8.998z"/></svg>
                    <svg class="theme-icon-sun" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                </button>
                <a href="/logout" class="nav-link nav-logout"><?= $lang('logout') ?></a>
            </div>
        </div>
    </nav>

    <!-- ===== SIDEBAR ===== -->
    <aside class="app-sidebar" id="app-sidebar">
        <div class="sidebar-logo">
            <a href="<?= $isAdmin ? '/admin' : (($isOffice || $isEmployee) ? '/office' : '/client') ?>">
                <img src="<?= htmlspecialchars($branding['logo_path'] ?? '/assets/img/logo.svg') ?>" alt="BiLLU" class="sidebar-logo-light">
                <img src="<?= htmlspecialchars($branding['logo_path_dark'] ?? $branding['logo_path'] ?? '/assets/img/logo.svg') ?>" alt="BiLLU" class="sidebar-logo-dark">
            </a>
            <div class="sidebar-logo-text">Panel</div>
        </div>
        <nav class="sidebar-nav">
            <?php if ($isAdmin): ?>
                <!-- ADMIN SIDEBAR -->
                <a href="/admin" class="sidebar-link <?= $currentPath === '/admin' ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                    <?= $lang('dashboard') ?>
                </a>
                <a href="/admin/analytics" class="sidebar-link <?= $currentPath === '/admin/analytics' ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                    <?= $lang('analytics') ?>
                </a>

                <div class="sidebar-group-label"><?= $lang('nav_management') ?></div>
                <a href="/admin/offices" class="sidebar-link <?= str_starts_with($currentPath, '/admin/offices') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    <?= $lang('offices') ?>
                </a>
                <a href="/admin/clients" class="sidebar-link <?= str_starts_with($currentPath, '/admin/clients') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                    <?= $lang('clients') ?>
                </a>

                <div class="sidebar-group-label"><?= $lang('nav_invoices') ?></div>
                <a href="/admin/import" class="sidebar-link <?= str_starts_with($currentPath, '/admin/import') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <?= $lang('import') ?>
                </a>
                <a href="/admin/batches" class="sidebar-link <?= str_starts_with($currentPath, '/admin/batches') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg>
                    <?= $lang('batches') ?>
                </a>
                <a href="/admin/erp-export" class="sidebar-link <?= str_starts_with($currentPath, '/admin/erp-export') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <?= $lang('erp_export') ?>
                </a>
                <a href="/admin/scheduled-exports" class="sidebar-link <?= str_starts_with($currentPath, '/admin/scheduled-export') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <?= $lang('scheduled_exports') ?>
                </a>

                <div class="sidebar-group-label"><?= $lang('nav_reports') ?></div>
                <a href="/admin/reports/aggregate" class="sidebar-link <?= str_starts_with($currentPath, '/admin/reports/aggregate') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                    <?= $lang('aggregate_report') ?>
                </a>
                <a href="/admin/reports/comparison" class="sidebar-link <?= str_starts_with($currentPath, '/admin/reports/comparison') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    <?= $lang('period_comparison') ?>
                </a>
                <a href="/admin/reports/suppliers" class="sidebar-link <?= str_starts_with($currentPath, '/admin/reports/suppliers') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/></svg>
                    <?= $lang('supplier_analysis') ?>
                </a>

                <div class="sidebar-group-label">KSeF</div>
                <a href="/admin/ksef-operations" class="sidebar-link <?= str_starts_with($currentPath, '/admin/ksef-operations') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                    <?= $lang('ksef_operations') ?>
                </a>
                <a href="/admin/ksef-logs" class="sidebar-link <?= str_starts_with($currentPath, '/admin/ksef-logs') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    <?= $lang('ksef_logs') ?>
                </a>

                <div class="sidebar-group-label"><?= $lang('nav_system') ?></div>
                <a href="/admin/settings" class="sidebar-link <?= $currentPath === '/admin/settings' ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                    <?= $lang('settings') ?>
                </a>
                <a href="/admin/email-templates" class="sidebar-link <?= str_starts_with($currentPath, '/admin/email-templates') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    <?= $lang('email_templates') ?>
                </a>
                <a href="/admin/audit-log" class="sidebar-link <?= str_starts_with($currentPath, '/admin/audit-log') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                    <?= $lang('audit_log') ?>
                </a>
                <a href="/admin/webhooks" class="sidebar-link <?= str_starts_with($currentPath, '/admin/webhooks') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>
                    <?= $lang('webhooks') ?>
                </a>
                <a href="/admin/security" class="sidebar-link <?= $currentPath === '/admin/security' ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                    <?= $lang('security') ?>
                </a>
                <a href="/admin/security-scan" class="sidebar-link <?= $currentPath === '/admin/security-scan' ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
                    <?= $lang('security_scan') ?>
                </a>
                <a href="/admin/duplicates" class="sidebar-link <?= str_starts_with($currentPath, '/admin/duplicates') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="8" y="2" width="13" height="16" rx="2"/><rect x="3" y="6" width="13" height="16" rx="2"/></svg>
                    <?= $lang('duplicates_report') ?>
                </a>
                <a href="/admin/module-bundles" class="sidebar-link <?= str_starts_with($currentPath, '/admin/module-bundles') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 002 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0022 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                    Pakiety modułów
                </a>
                <a href="/admin/demo" class="sidebar-link <?= str_starts_with($currentPath, '/admin/demo') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/></svg>
                    Demo
                </a>

            <?php elseif ($isOffice || $isEmployee): ?>
                <!-- OFFICE/EMPLOYEE SIDEBAR -->
                <a href="/office" class="sidebar-link <?= $currentPath === '/office' ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                    <?= $lang('dashboard') ?>
                </a>

                <div class="sidebar-group-label"><?= $lang('nav_management') ?></div>
                <a href="/office/clients" class="sidebar-link <?= str_starts_with($currentPath, '/office/clients') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                    <?= $isEmployee ? 'Przypisani Klienci' : $lang('clients') ?>
                </a>
                <?php if (!$isEmployee && $canModule('hr')): ?>
                <a href="/office/employees" class="sidebar-link <?= str_starts_with($currentPath, '/office/employees') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                    <?= $lang('employees') ?>
                </a>
                <?php endif; ?>

                <div class="sidebar-group-label"><?= $lang('nav_invoices') ?></div>
                <?php if (!$isEmployee): ?>
                <a href="/office/import" class="sidebar-link <?= str_starts_with($currentPath, '/office/import') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <?= $lang('import') ?>
                </a>
                <?php endif; ?>
                <a href="/office/batches" class="sidebar-link <?= str_starts_with($currentPath, '/office/batches') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg>
                    <?= $lang('batches') ?>
                </a>

                <div class="sidebar-group-label"><?= $lang('nav_reports') ?></div>
                <?php if ($canModule('analytics')): ?>
                <a href="/office/analytics" class="sidebar-link <?= str_starts_with($currentPath, '/office/analytics') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                    <?= $lang('office_analytics') ?>
                </a>
                <?php endif; ?>
                <?php if ($canModule('reports')): ?>
                <a href="/office/reports" class="sidebar-link <?= str_starts_with($currentPath, '/office/reports') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                    <?= $lang('reports') ?>
                </a>
                <?php endif; ?>
                <?php if (!$isEmployee && $canModule('erp-export')): ?>
                <a href="/office/erp-export" class="sidebar-link <?= str_starts_with($currentPath, '/office/erp-export') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <?= $lang('erp_export') ?>
                </a>
                <?php endif; ?>

                <div class="sidebar-group-label"><?= $lang('communication') ?></div>
                <?php
                    $offMsgCount = $isEmployee
                        ? \App\Models\Message::countAllUnreadForEmployee(\App\Core\Session::get('employee_id'))
                        : \App\Models\Message::countAllUnreadForOffice(\App\Core\Session::get('office_id'));
                    $offTaskCount = $isEmployee
                        ? \App\Models\ClientTask::countAllOpenByEmployee(\App\Core\Session::get('employee_id'))
                        : \App\Models\ClientTask::countAllOpenByOffice(\App\Core\Session::get('office_id'));
                ?>
                <?php if ($canModule('messages')): ?>
                <a href="/office/messages" class="sidebar-link <?= str_starts_with($currentPath, '/office/messages') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                    <?= $lang('messages') ?>
                    <?php if ($offMsgCount > 0): ?><span class="sidebar-badge"><?= $offMsgCount ?></span><?php endif; ?>
                </a>
                <?php endif; ?>
                <?php if ($canModule('tasks')): ?>
                <a href="/office/tasks" class="sidebar-link <?= ($currentPath === '/office/tasks' || (str_starts_with($currentPath, '/office/tasks') && !str_starts_with($currentPath, '/office/tasks/billing'))) ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                    <?= $lang('tasks') ?>
                    <?php if ($offTaskCount > 0): ?><span class="sidebar-badge"><?= $offTaskCount ?></span><?php endif; ?>
                </a>
                <a href="/office/tasks/billing" class="sidebar-link <?= str_starts_with($currentPath, '/office/tasks/billing') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                    Rozliczenia zadań
                </a>
                <?php endif; ?>
                <?php if ($canModule('tax-payments')): ?>
                <a href="/office/tax-payments" class="sidebar-link <?= str_starts_with($currentPath, '/office/tax-payments') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                    <?= $lang('tax_payments') ?>
                </a>
                <?php endif; ?>
                <!-- Kalendarz i kalkulatory tymczasowo ukryte -->
                <!--
                <a href="/office/tax-calendar" class="sidebar-link <?= str_starts_with($currentPath, '/office/tax-calendar') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <?= $lang('tax_calendar') ?>
                </a>
                <a href="/office/tax-calculator" class="sidebar-link <?= str_starts_with($currentPath, '/office/tax-calculator') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="10" x2="10" y2="10"/><line x1="12" y1="10" x2="14" y2="10"/><line x1="8" y1="14" x2="10" y2="14"/><line x1="12" y1="14" x2="14" y2="14"/><line x1="8" y1="18" x2="10" y2="18"/><line x1="12" y1="18" x2="16" y2="18"/></svg>
                    <?= $lang('calculators') ?>
                </a>
                -->
                <?php if ($canModule('duplicates')): ?>
                <a href="/office/duplicates" class="sidebar-link <?= str_starts_with($currentPath, '/office/duplicates') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="8" y="2" width="13" height="16" rx="2"/><rect x="3" y="6" width="13" height="16" rx="2"/></svg>
                    <?= $lang('duplicates_report') ?>
                </a>
                <?php endif; ?>

                <?php if ($canModule('hr')): ?>
                <div class="sidebar-group-label">Kadry i Płace</div>
                <a href="/office/hr" class="sidebar-link <?= $currentPath === '/office/hr' ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                    Panel HR
                </a>
                <?php if ($canModule('payroll-calc')): ?>
                <a href="/office/hr/calculator" class="sidebar-link <?= $currentPath === '/office/hr/calculator' ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="10" x2="10" y2="10"/><line x1="12" y1="10" x2="14" y2="10"/><line x1="8" y1="14" x2="10" y2="14"/><line x1="12" y1="14" x2="14" y2="14"/><line x1="8" y1="18" x2="10" y2="18"/><line x1="12" y1="18" x2="16" y2="18"/></svg>
                    Kalkulator płac
                </a>
                <?php endif; ?>
                <?php endif; ?>

                <div class="sidebar-group-label"><?= $lang('nav_system') ?></div>
                <?php if (!$isEmployee): ?>
                <a href="/office/settings" class="sidebar-link <?= $currentPath === '/office/settings' ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                    <?= $lang('settings') ?>
                </a>
                <a href="/office/email-settings" class="sidebar-link <?= $currentPath === '/office/email-settings' ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    <?= $lang('office_email_settings') ?>
                </a>
                <?php endif; ?>
                <a href="/office/security" class="sidebar-link <?= $currentPath === '/office/security' ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                    <?= $lang('security') ?>
                </a>

            <?php else: ?>
                <!-- CLIENT SIDEBAR -->
                <a href="/client" class="sidebar-link <?= $currentPath === '/client' ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                    <?= $lang('dashboard') ?>
                </a>

                <?php if ($canModule('sales')): ?>
                <div class="sidebar-group-label"><?= $lang('nav_sales') ?></div>
                <a href="/client/sales" class="sidebar-link <?= $currentPath === '/client/sales' || (str_starts_with($currentPath, '/client/sales/') && $currentPath !== '/client/sales/email-settings') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    <?= $lang('issued_invoices') ?>
                </a>
                <a href="/client/services" class="sidebar-link <?= $currentPath === '/client/services' ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                    Katalog usług/towarów
                </a>
                <?php
                $sidebarCanSend = false;
                $sidebarClientIdForEmail = \App\Core\Session::get('client_id');
                if ($sidebarClientIdForEmail) {
                    $sidebarClientForEmail = \App\Models\Client::findById($sidebarClientIdForEmail);
                    $sidebarCanSend = !empty($sidebarClientForEmail['can_send_invoices']);
                }
                if ($sidebarCanSend): ?>
                <a href="/client/sales/email-settings" class="sidebar-link <?= $currentPath === '/client/sales/email-settings' ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    <?= $lang('invoice_email_settings') ?>
                </a>
                <?php endif; ?>
                <?php endif; ?>

                <div class="sidebar-group-label">Koszty</div>
                <?php
                    $sidebarBatchLink = '/client';
                    $sidebarClientId = \App\Core\Session::get('client_id');
                    if ($sidebarClientId) {
                        $sidebarActiveBatches = \App\Models\InvoiceBatch::findActiveByClient((int)$sidebarClientId);
                        if (!empty($sidebarActiveBatches)) {
                            $sidebarBatchLink = '/client/invoices/' . $sidebarActiveBatches[0]['id'];
                        } else {
                            $sidebarAllBatches = \App\Models\InvoiceBatch::findByClient((int)$sidebarClientId);
                            if (!empty($sidebarAllBatches)) {
                                $sidebarBatchLink = '/client/invoices/' . $sidebarAllBatches[0]['id'];
                            }
                        }
                    }
                ?>
                <a href="<?= $sidebarBatchLink ?>" class="sidebar-link <?= str_starts_with($currentPath, '/client/invoices') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    <?= $lang('invoices_to_verify') ?>
                </a>

                <div class="sidebar-group-label"><?= $lang('nav_management') ?></div>
                <?php if ($canModule('contractors')): ?>
                <a href="/client/contractors" class="sidebar-link <?= str_starts_with($currentPath, '/client/contractors') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                    <?= $lang('contractors') ?>
                </a>
                <?php endif; ?>
                <?php if ($canModule('company-profile')): ?>
                <a href="/client/company" class="sidebar-link <?= str_starts_with($currentPath, '/client/company') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    <?= $lang('company_profile') ?>
                </a>
                <?php endif; ?>
                <?php if ($canModule('reports')): ?>
                <a href="/client/reports" class="sidebar-link <?= str_starts_with($currentPath, '/client/reports') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                    <?= $lang('reports') ?>
                </a>
                <?php endif; ?>


                <div class="sidebar-group-label"><?= $lang('communication') ?></div>
                <?php
                    $cliMsgCount = \App\Models\Message::countUnreadByClient(\App\Core\Session::get('client_id'));
                    $cliTaskCount = \App\Models\ClientTask::countOpenByClient(\App\Core\Session::get('client_id'));
                ?>
                <?php if ($canModule('messages')): ?>
                <a href="/client/messages" class="sidebar-link <?= str_starts_with($currentPath, '/client/messages') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                    <?= $lang('messages') ?>
                    <?php if ($cliMsgCount > 0): ?><span class="sidebar-badge"><?= $cliMsgCount ?></span><?php endif; ?>
                </a>
                <?php endif; ?>
                <?php if ($canModule('files')): ?>
                <a href="/client/files" class="sidebar-link <?= str_starts_with($currentPath, '/client/files') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9z"/>
                        <polyline points="13 2 13 9 20 9"/>
                    </svg>
                    Pliki
                </a>
                <?php endif; ?>
                <?php if ($canModule('tasks')): ?>
                <a href="/client/tasks" class="sidebar-link <?= str_starts_with($currentPath, '/client/tasks') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                    <?= $lang('tasks') ?>
                    <?php if ($cliTaskCount > 0): ?><span class="sidebar-badge"><?= $cliTaskCount ?></span><?php endif; ?>
                </a>
                <?php endif; ?>
                <?php if ($canModule('tax-payments')): ?>
                <a href="/client/tax-payments" class="sidebar-link <?= str_starts_with($currentPath, '/client/tax-payments') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                    <?= $lang('tax_payments') ?>
                </a>
                <?php endif; ?>
                <!-- Kalendarz i kalkulatory tymczasowo ukryte -->
                <!--
                <a href="/client/tax-calendar" class="sidebar-link <?= str_starts_with($currentPath, '/client/tax-calendar') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <?= $lang('tax_calendar') ?>
                </a>
                <a href="/client/calculators" class="sidebar-link <?= str_starts_with($currentPath, '/client/calculators') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="10" x2="10" y2="10"/><line x1="12" y1="10" x2="14" y2="10"/><line x1="8" y1="14" x2="10" y2="14"/><line x1="12" y1="14" x2="14" y2="14"/><line x1="8" y1="18" x2="10" y2="18"/><line x1="12" y1="18" x2="16" y2="18"/></svg>
                    <?= $lang('calculators') ?>
                </a>
                -->

                <?php if ($canModule('hr')): ?>
                <div class="sidebar-group-label">Kadry i Płace</div>
                <a href="/client/hr/employees" class="sidebar-link <?= $currentPath === '/client/hr/employees' ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                    Pracownicy
                </a>
                <?php if ($canModule('payroll-lists')): ?>
                <a href="/client/hr/payroll" class="sidebar-link <?= str_starts_with($currentPath, '/client/hr/payroll') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    Listy płac
                </a>
                <?php endif; ?>
                <?php if ($canModule('payroll-leave')): ?>
                <a href="/client/hr/leaves" class="sidebar-link <?= str_starts_with($currentPath, '/client/hr/leaves') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    Urlopy
                </a>
                <?php endif; ?>
                <?php if ($canModule('payroll-pit') || $canModule('payroll-zus')): ?>
                <a href="/client/hr/declarations" class="sidebar-link <?= str_starts_with($currentPath, '/client/hr/declarations') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    Deklaracje
                </a>
                <?php endif; ?>
                <?php endif; ?>

                <div class="sidebar-group-label"><?= $lang('nav_system') ?></div>
                <?php if ($canModule('ksef')): ?>
                <a href="/client/ksef" class="sidebar-link <?= $currentPath === '/client/ksef' ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                    Konfiguracja KSeF
                </a>
                <a href="/client/ksef/certificates" class="sidebar-link <?= str_starts_with($currentPath, '/client/ksef/certificates') ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                    <?= $lang('certificates') ?>
                </a>
                <?php endif; ?>
                <a href="/client/security" class="sidebar-link <?= $currentPath === '/client/security' ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                    <?= $lang('security') ?>
                </a>
            <?php endif; ?>
        </nav>
    </aside>
    <div class="sidebar-overlay" onclick="document.body.classList.remove('sidebar-open')"></div>
    <?php endif; ?>

    <main class="<?= \App\Core\Auth::isLoggedIn() ? 'app-main' : 'container' ?> main-content">
        <?php
        $isDemoAccount = false;
        if (\App\Core\Auth::isClient()) {
            $demoCheck = \App\Core\Database::getInstance()->fetchOne("SELECT is_demo FROM clients WHERE id = ?", [\App\Core\Session::get('client_id')]);
            $isDemoAccount = !empty($demoCheck['is_demo']);
        } elseif (\App\Core\Auth::isOffice()) {
            $demoCheck = \App\Core\Database::getInstance()->fetchOne("SELECT is_demo FROM offices WHERE id = ?", [\App\Core\Session::get('office_id')]);
            $isDemoAccount = !empty($demoCheck['is_demo']);
        }
        ?>
        <?php if ($isDemoAccount): ?>
            <div style="background:#fef3c7; color:#92400e; text-align:center; padding:8px 16px; border-radius:8px; margin-bottom:16px; font-weight:600; font-size:13px;">
                KONTO DEMO &mdash; dane testowe, moga zostac zresetowane przez administratora
            </div>
        <?php endif; ?>
        <?php if (!empty($flash_success)): ?>
            <div class="alert alert-success"><?= $lang($flash_success) ?></div>
        <?php endif; ?>
        <?php if (!empty($flash_info_extra)): ?>
            <div class="alert alert-warning"><?= htmlspecialchars($flash_info_extra) ?></div>
        <?php endif; ?>
        <?php if (!empty($flash_error)): ?>
            <div class="alert alert-error"><?= $lang($flash_error) ?></div>
        <?php endif; ?>
        <?= $content ?>
    </main>

    <footer class="footer <?= \App\Core\Auth::isLoggedIn() ? 'app-footer' : '' ?>">
        <div class="container">
            &copy; <?= date('Y') ?> <?= htmlspecialchars($branding['system_name'] ?? 'BiLLU') ?>
            <div class="footer-links">
                <a href="/privacy-policy"><?= $lang('footer_privacy_policy') ?></a>
                <?php if (\App\Core\Auth::isClient()): ?>
                <a href="/client/rodo-export"><?= $lang('footer_rodo') ?></a>
                <?php endif; ?>
            </div>
        </div>
    </footer>
    <!-- Cookie Consent Banner -->
    <div id="cookie-consent" class="cookie-consent" style="display:none;">
        <div class="cookie-consent-inner">
            <p class="cookie-consent-text">
                Ta strona używa plików cookie niezbędnych do działania aplikacji.
                Korzystając z serwisu, akceptujesz naszą <a href="/privacy-policy">Politykę Prywatności</a>.
            </p>
            <div class="cookie-consent-actions">
                <button id="cookie-accept" class="btn btn-primary btn-sm">Akceptuję</button>
                <a href="/privacy-policy" class="btn btn-sm">Więcej informacji</a>
            </div>
        </div>
    </div>

    <script defer src="<?= \App\Core\Asset::url('js/app.js') ?>"></script>
</body>
</html>
