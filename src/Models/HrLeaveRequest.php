<?php

namespace App\Models;

use App\Core\HrDatabase;

class HrLeaveRequest
{
    public static function findById(int $id): ?array
    {
        return HrDatabase::getInstance()->fetchOne(
            "SELECT lr.*,
                    CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                    lt.name_pl AS leave_type_name, lt.code AS leave_type_code
             FROM hr_leave_requests lr
             JOIN hr_employees e ON lr.employee_id = e.id
             JOIN hr_leave_types lt ON lr.leave_type_id = lt.id
             WHERE lr.id = ?",
            [$id]
        );
    }

    public static function findByEmployee(int $employeeId, ?string $status = null): array
    {
        $sql = "SELECT lr.*, lt.name_pl AS leave_type_name, lt.code AS leave_type_code
                FROM hr_leave_requests lr
                JOIN hr_leave_types lt ON lr.leave_type_id = lt.id
                WHERE lr.employee_id = ?";
        $params = [$employeeId];
        if ($status !== null) {
            $sql .= " AND lr.status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY lr.date_from DESC";
        return HrDatabase::getInstance()->fetchAll($sql, $params);
    }

    public static function findByClient(int $clientId, ?string $status = null): array
    {
        $sql = "SELECT lr.*,
                       CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                       lt.name_pl AS leave_type_name, lt.code AS leave_type_code
                FROM hr_leave_requests lr
                JOIN hr_employees e ON lr.employee_id = e.id
                JOIN hr_leave_types lt ON lr.leave_type_id = lt.id
                WHERE lr.client_id = ?";
        $params = [$clientId];
        if ($status !== null) {
            $sql .= " AND lr.status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY lr.created_at DESC";
        return HrDatabase::getInstance()->fetchAll($sql, $params);
    }

    public static function countPendingByClient(int $clientId): int
    {
        $row = HrDatabase::getInstance()->fetchOne(
            "SELECT COUNT(*) AS cnt FROM hr_leave_requests WHERE client_id = ? AND status = 'pending'",
            [$clientId]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    public static function create(array $data): int
    {
        return HrDatabase::getInstance()->insert('hr_leave_requests', $data);
    }

    public static function approve(int $id, string $reviewerType, int $reviewerId): void
    {
        HrDatabase::getInstance()->update('hr_leave_requests', [
            'status'           => 'approved',
            'reviewed_by_type' => $reviewerType,
            'reviewed_by_id'   => $reviewerId,
            'reviewed_at'      => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
    }

    public static function reject(int $id, string $reviewerType, int $reviewerId, string $reason = ''): void
    {
        HrDatabase::getInstance()->update('hr_leave_requests', [
            'status'           => 'rejected',
            'reviewed_by_type' => $reviewerType,
            'reviewed_by_id'   => $reviewerId,
            'reviewed_at'      => date('Y-m-d H:i:s'),
            'rejection_reason' => $reason,
        ], 'id = ?', [$id]);
    }

    public static function cancel(int $id): void
    {
        HrDatabase::getInstance()->update('hr_leave_requests', [
            'status' => 'cancelled',
        ], 'id = ?', [$id]);
    }
}
