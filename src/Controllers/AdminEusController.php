<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;

/**
 * Master-admin dashboard for the e-US integration.
 *
 * Read-only aggregate view across ALL offices — no per-client data
 * surface (operators use /office/eus/{cid} for per-client work).
 * Auth gate: Auth::requireAdmin() in constructor.
 *
 * Two data sources:
 *   1. Live counts from eus_documents (today + last 7d)
 *   2. Daily snapshots from eus_metrics_daily (last 30d)
 *
 * The dashboard is intentionally simple — no charts library, no
 * front-end framework. The numbers update each cron tick.
 */
class AdminEusController extends Controller
{
    public function __construct()
    {
        Auth::requireAdmin();
    }

    public function dashboard(): void
    {
        $db = Database::getInstance();

        // Live counts (today)
        $today = $db->fetchOne(
            "SELECT
               SUM(CASE WHEN status = 'submitted' AND DATE(submitted_at) = CURDATE() THEN 1 ELSE 0 END) AS submitted,
               SUM(CASE WHEN status = 'zaakceptowany' AND DATE(finalized_at) = CURDATE() THEN 1 ELSE 0 END) AS accepted,
               SUM(CASE WHEN status = 'odrzucony' AND DATE(finalized_at) = CURDATE() THEN 1 ELSE 0 END) AS rejected,
               SUM(CASE WHEN status = 'error' AND DATE(updated_at) = CURDATE() THEN 1 ELSE 0 END) AS errors,
               SUM(CASE WHEN direction = 'in' AND DATE(external_received_at) = CURDATE() THEN 1 ELSE 0 END) AS kas_letters
             FROM eus_documents"
        ) ?: [];

        // Last 7 days (rolling)
        $week = $db->fetchOne(
            "SELECT
               SUM(CASE WHEN status = 'submitted'    AND submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS submitted,
               SUM(CASE WHEN status = 'zaakceptowany' AND finalized_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS accepted,
               SUM(CASE WHEN status = 'odrzucony'    AND finalized_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS rejected,
               SUM(CASE WHEN direction = 'in' AND external_received_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS kas_letters
             FROM eus_documents"
        ) ?: [];

        // Cert + UPL-1 expiry heatmap — count rows expiring within
        // 30 / 14 / 7 days (across all offices). Highly visible to
        // master admin so a global rollout doesn't hit a wall on
        // forgotten pełnomocnictwa.
        $expiryCounts = $db->fetchOne(
            "SELECT
               SUM(CASE WHEN upl1_status = 'active' AND upl1_valid_to BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)  THEN 1 ELSE 0 END) AS upl1_7d,
               SUM(CASE WHEN upl1_status = 'active' AND upl1_valid_to BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY) THEN 1 ELSE 0 END) AS upl1_14d,
               SUM(CASE WHEN upl1_status = 'active' AND upl1_valid_to BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS upl1_30d,
               SUM(CASE WHEN cert_valid_to BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)  THEN 1 ELSE 0 END) AS cert_7d,
               SUM(CASE WHEN cert_valid_to BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 14 DAY) THEN 1 ELSE 0 END) AS cert_14d,
               SUM(CASE WHEN cert_valid_to BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS cert_30d
             FROM client_eus_configs"
        ) ?: [];

        // Polling lag — show clients whose last_poll_at is older than
        // 2x their poll_interval_minutes (likely cron stuck).
        $lagging = $db->fetchAll(
            "SELECT cfg.client_id, cfg.last_poll_at, cfg.poll_interval_minutes,
                    c.company_name, c.nip
               FROM client_eus_configs cfg
               JOIN clients c ON c.id = cfg.client_id
              WHERE cfg.poll_incoming_enabled = 1
                AND cfg.bramka_c_enabled = 1
                AND cfg.upl1_status = 'active'
                AND (cfg.last_poll_at IS NULL
                     OR cfg.last_poll_at < DATE_SUB(NOW(), INTERVAL (cfg.poll_interval_minutes * 2) MINUTE))
              ORDER BY cfg.last_poll_at IS NULL DESC, cfg.last_poll_at ASC
              LIMIT 20"
        );

        // Daily metrics (last 30 days) for the line/bar chart in PR-6.
        $dailyMetrics = $db->fetchAll(
            "SELECT * FROM eus_metrics_daily
              ORDER BY captured_date DESC
              LIMIT 30"
        );

        // Active KAS retention — total documents that block RODO delete.
        $retentionRow = $db->fetchOne(
            "SELECT
               COUNT(*) AS total,
               COUNT(DISTINCT client_id) AS clients_affected
             FROM eus_documents
             WHERE retain_until IS NOT NULL
               AND retain_until > CURDATE()
               AND purged_at IS NULL"
        ) ?: ['total' => 0, 'clients_affected' => 0];

        $this->render('admin/eus_dashboard', [
            'today'         => $today,
            'week'          => $week,
            'expiryCounts'  => $expiryCounts,
            'lagging'       => $lagging,
            'dailyMetrics'  => $dailyMetrics,
            'retentionRow'  => $retentionRow,
        ]);
    }
}
