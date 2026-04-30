<?php
/**
 * @var array $today          ['submitted', 'accepted', 'rejected', 'errors', 'kas_letters']
 * @var array $week           rolling 7 days
 * @var array $expiryCounts   ['upl1_7d', 'upl1_14d', 'upl1_30d', 'cert_7d', 'cert_14d', 'cert_30d']
 * @var array $lagging        clients with stale polling
 * @var array $dailyMetrics   eus_metrics_daily rows last 30d
 * @var array $retentionRow   ['total', 'clients_affected']
 */
?>
<h1>e-Urząd Skarbowy — Dashboard</h1>
<p style="color:var(--gray-500); margin-bottom:16px;">
    Globalny widok wszystkich biur. Liczby aktualizowane co cron tick (~5 min).
    Brak danych osobowych — jedynie agregaty.
</p>

<!-- ── Today's counts ──────────────────────────────────── -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:16px; margin-bottom:24px;">
    <div class="stat-card" style="background:var(--gray-50); border:1px solid var(--gray-200); border-radius:8px; padding:14px;">
        <div style="font-size:11px; color:var(--gray-500); text-transform:uppercase; letter-spacing:0.05em;">Dziś — wysłane</div>
        <div style="font-size:32px; font-weight:700; color:#2563eb;"><?= (int) ($today['submitted'] ?? 0) ?></div>
    </div>
    <div class="stat-card" style="background:var(--gray-50); border:1px solid var(--gray-200); border-radius:8px; padding:14px;">
        <div style="font-size:11px; color:var(--gray-500); text-transform:uppercase; letter-spacing:0.05em;">Dziś — zaakceptowane</div>
        <div style="font-size:32px; font-weight:700; color:#16a34a;"><?= (int) ($today['accepted'] ?? 0) ?></div>
    </div>
    <div class="stat-card" style="background:var(--gray-50); border:1px solid var(--gray-200); border-radius:8px; padding:14px;">
        <div style="font-size:11px; color:var(--gray-500); text-transform:uppercase; letter-spacing:0.05em;">Dziś — odrzucone</div>
        <div style="font-size:32px; font-weight:700; color:#dc2626;"><?= (int) ($today['rejected'] ?? 0) ?></div>
    </div>
    <div class="stat-card" style="background:var(--gray-50); border:1px solid var(--gray-200); border-radius:8px; padding:14px;">
        <div style="font-size:11px; color:var(--gray-500); text-transform:uppercase; letter-spacing:0.05em;">Dziś — błędy</div>
        <div style="font-size:32px; font-weight:700; color:#f59e0b;"><?= (int) ($today['errors'] ?? 0) ?></div>
    </div>
    <div class="stat-card" style="background:var(--gray-50); border:1px solid var(--gray-200); border-radius:8px; padding:14px;">
        <div style="font-size:11px; color:var(--gray-500); text-transform:uppercase; letter-spacing:0.05em;">Dziś — pisma KAS</div>
        <div style="font-size:32px; font-weight:700; color:#7c3aed;"><?= (int) ($today['kas_letters'] ?? 0) ?></div>
    </div>
</div>

<!-- ── Last 7 days summary ─────────────────────────────── -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">Ostatnie 7 dni</div>
    <div class="card-body">
        <table class="table">
            <tr>
                <th>Wysłane</th><th>Zaakceptowane</th><th>Odrzucone</th><th>Pisma KAS</th>
            </tr>
            <tr>
                <td><?= (int) ($week['submitted'] ?? 0) ?></td>
                <td><?= (int) ($week['accepted'] ?? 0) ?></td>
                <td>
                    <?= (int) ($week['rejected'] ?? 0) ?>
                    <?php
                    $totalDecided = (int) ($week['accepted'] ?? 0) + (int) ($week['rejected'] ?? 0);
                    $rejPct = $totalDecided > 0
                        ? round(100 * (int) ($week['rejected'] ?? 0) / $totalDecided, 1)
                        : 0;
                    ?>
                    <small style="color:var(--gray-500);">(<?= $rejPct ?>% odrzuceń)</small>
                </td>
                <td><?= (int) ($week['kas_letters'] ?? 0) ?></td>
            </tr>
        </table>
    </div>
</div>

<!-- ── Expiry heatmap ──────────────────────────────────── -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">Wygasające pełnomocnictwa + certyfikaty (across offices)</div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr><th>Typ</th><th style="text-align:center;">≤7 dni</th><th style="text-align:center;">≤14 dni</th><th style="text-align:center;">≤30 dni</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>UPL-1</strong></td>
                    <td style="text-align:center; color:<?= ((int) ($expiryCounts['upl1_7d']  ?? 0)) > 0 ? '#dc2626' : 'var(--gray-400)' ?>; font-weight:700;"><?= (int) ($expiryCounts['upl1_7d']  ?? 0) ?></td>
                    <td style="text-align:center; color:<?= ((int) ($expiryCounts['upl1_14d'] ?? 0)) > 0 ? '#f59e0b' : 'var(--gray-400)' ?>;"><?= (int) ($expiryCounts['upl1_14d'] ?? 0) ?></td>
                    <td style="text-align:center;"><?= (int) ($expiryCounts['upl1_30d'] ?? 0) ?></td>
                </tr>
                <tr>
                    <td><strong>Certyfikat</strong></td>
                    <td style="text-align:center; color:<?= ((int) ($expiryCounts['cert_7d']  ?? 0)) > 0 ? '#dc2626' : 'var(--gray-400)' ?>; font-weight:700;"><?= (int) ($expiryCounts['cert_7d']  ?? 0) ?></td>
                    <td style="text-align:center; color:<?= ((int) ($expiryCounts['cert_14d'] ?? 0)) > 0 ? '#f59e0b' : 'var(--gray-400)' ?>;"><?= (int) ($expiryCounts['cert_14d'] ?? 0) ?></td>
                    <td style="text-align:center;"><?= (int) ($expiryCounts['cert_30d'] ?? 0) ?></td>
                </tr>
            </tbody>
        </table>
        <small style="color:var(--gray-500);">
            Cron co tick tworzy wysokopriorytetowe zadania dla biur — sprawdź <code>client_tasks</code> z prefixem "e-US:".
        </small>
    </div>
</div>

<!-- ── Polling lag ─────────────────────────────────────── -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">Zaległy polling Bramki C (top 20)</div>
    <div class="card-body" style="padding:0;">
        <?php if (empty($lagging)): ?>
            <p style="padding:14px; margin:0; color:var(--gray-500);">
                Wszyscy klienci w terminie — brak zaległych pollingów.
            </p>
        <?php else: ?>
        <table class="table" style="margin:0;">
            <thead>
                <tr><th>Klient</th><th>NIP</th><th>Ostatni poll</th><th>Interwał</th></tr>
            </thead>
            <tbody>
            <?php foreach ($lagging as $row): ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($row['company_name'] ?? ''), ENT_QUOTES) ?></td>
                    <td><code><?= htmlspecialchars((string) ($row['nip'] ?? ''), ENT_QUOTES) ?></code></td>
                    <td>
                        <?php if (empty($row['last_poll_at'])): ?>
                            <span style="color:#dc2626;">nigdy</span>
                        <?php else: ?>
                            <?= htmlspecialchars((string) $row['last_poll_at'], ENT_QUOTES) ?>
                        <?php endif; ?>
                    </td>
                    <td><?= (int) $row['poll_interval_minutes'] ?> min</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- ── KAS retention ───────────────────────────────────── -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">Aktywna retencja KAS (10 lat)</div>
    <div class="card-body">
        <p>
            <strong><?= (int) ($retentionRow['total'] ?? 0) ?></strong> dokumentów objętych retencją,
            dotyczących <strong><?= (int) ($retentionRow['clients_affected'] ?? 0) ?></strong> klientów.
        </p>
        <p style="color:var(--gray-500); font-size:13px;">
            <code>RodoDeleteService</code> odmówi usunięcia tych klientów dopóki retencja jest aktywna.
            Do skasowania wymagany ręczny eksport offline + DELETE z eus_documents.
        </p>
    </div>
</div>

<!-- ── Last 30 daily snapshots ─────────────────────────── -->
<div class="card">
    <div class="card-header">Snapshoty dzienne (ostatnie 30 dni)</div>
    <div class="card-body" style="padding:0;">
        <?php if (empty($dailyMetrics)): ?>
            <p style="padding:14px; margin:0; color:var(--gray-500);">
                Brak danych. Cron zacznie zbierać po pierwszym uruchomieniu.
            </p>
        <?php else: ?>
        <table class="table" style="margin:0;">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Wysłane</th>
                    <th>Zaakcep.</th>
                    <th>Odrzuc.</th>
                    <th>Błędy</th>
                    <th>Pisma KAS</th>
                    <th>Poll err.</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($dailyMetrics as $m): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $m['captured_date'], ENT_QUOTES) ?></td>
                    <td><?= (int) $m['submitted_count'] ?></td>
                    <td style="color:#16a34a;"><?= (int) $m['accepted_count'] ?></td>
                    <td style="color:<?= ((int) $m['rejected_count']) > 0 ? '#dc2626' : 'inherit' ?>;"><?= (int) $m['rejected_count'] ?></td>
                    <td><?= (int) $m['error_count'] ?></td>
                    <td><?= (int) $m['kas_letters_received_count'] ?></td>
                    <td style="color:<?= ((int) $m['polling_errors_count']) > 0 ? '#f59e0b' : 'inherit' ?>;"><?= (int) $m['polling_errors_count'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
