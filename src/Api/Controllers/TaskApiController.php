<?php

declare(strict_types=1);

namespace App\Api\Controllers;

use App\Api\ApiResponse;
use App\Models\ClientTask;

class TaskApiController
{
    // GET /api/v1/tasks?status=
    public function index(array $params, ?int $clientId): void
    {
        $status = $_GET['status'] ?? null;
        $tasks  = ClientTask::findByClient($clientId, $status ?: null);

        ApiResponse::success(array_map([$this, 'formatTask'], $tasks));
    }

    // GET /api/v1/tasks/{id}
    public function show(array $params, ?int $clientId): void
    {
        $task = $this->findForClient((int) $params['id'], $clientId);
        ApiResponse::success($this->formatTask($task));
    }

    // PATCH /api/v1/tasks/{id}/status
    // Body: {status: "pending"|"in_progress"|"done"}
    public function updateStatus(array $params, ?int $clientId): void
    {
        $task   = $this->findForClient((int) $params['id'], $clientId);
        $body   = $this->getJsonBody();
        $status = $body['status'] ?? '';

        $allowed = ['pending', 'in_progress', 'done'];
        if (!in_array($status, $allowed, true)) {
            ApiResponse::validation(['status' => 'must be one of: ' . implode(', ', $allowed)]);
        }

        $completedByType = $status === 'done' ? 'client' : null;
        $completedById   = $status === 'done' ? $clientId : null;

        ClientTask::markStatus((int) $task['id'], $status, $completedByType, $completedById);

        $updated = ClientTask::findById((int) $task['id']);
        ApiResponse::success($this->formatTask($updated));
    }

    // ── Helpers ────────────────────────────────────

    private function findForClient(int $id, int $clientId): array
    {
        $task = ClientTask::findById($id);
        if (!$task || (int) $task['client_id'] !== $clientId) {
            ApiResponse::notFound('task_not_found');
        }
        return $task;
    }

    private function formatTask(array $t): array
    {
        return [
            'id'               => (int) $t['id'],
            'title'            => $t['title'],
            'description'      => $t['description'] ?? null,
            'status'           => $t['status'],
            'due_date'         => $t['due_date'] ?? null,
            'has_attachment'   => !empty($t['attachment_path']),
            'attachment_name'  => $t['attachment_name'] ?? null,
            'creator_type'     => $t['creator_type'] ?? null,
            'creator_name'     => ClientTask::getCreatorName($t['creator_type'] ?? '', (int) ($t['creator_id'] ?? 0)),
            'completed_at'     => $t['completed_at'] ?? null,
            'created_at'       => $t['created_at'],
        ];
    }

    private function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) return [];
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}
