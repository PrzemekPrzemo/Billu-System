<?php

namespace App\Models;

use App\Core\Database;

class TaxCustomEvent
{
    public static function findByOfficeAndMonth(int $officeId, int $year, int $month): array
    {
        $db = Database::getInstance();
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));

        return $db->fetchAll(
            "SELECT e.*,
                    c.company_name AS client_name,
                    oe.name AS employee_name
             FROM tax_custom_events e
             LEFT JOIN clients c ON c.id = e.client_id
             LEFT JOIN office_employees oe ON oe.id = e.employee_id
             WHERE e.office_id = ? AND e.event_date BETWEEN ? AND ?
             ORDER BY e.event_date, e.title",
            [$officeId, $startDate, $endDate]
        );
    }

    public static function findById(int $id): ?array
    {
        $db = Database::getInstance();
        return $db->fetchOne("SELECT * FROM tax_custom_events WHERE id = ?", [$id]) ?: null;
    }

    public static function create(int $officeId, ?int $clientId, string $date, string $title, ?string $description = null, string $color = '#6366f1', ?int $employeeId = null): int
    {
        $db = Database::getInstance();
        $db->query(
            "INSERT INTO tax_custom_events (office_id, client_id, employee_id, event_date, title, description, color) VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$officeId, $clientId, $employeeId, $date, $title, $description, $color]
        );
        return (int) $db->lastInsertId();
    }

    public static function delete(int $id, int $officeId): bool
    {
        $db = Database::getInstance();
        return $db->query("DELETE FROM tax_custom_events WHERE id = ? AND office_id = ?", [$id, $officeId]) !== false;
    }
}
