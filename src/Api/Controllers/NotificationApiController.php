<?php

declare(strict_types=1);

namespace App\Api\Controllers;

use App\Api\ApiResponse;
use App\Models\Notification;

class NotificationApiController
{
    // GET /api/v1/profile/notifications?page=&per_page=
    public function index(array $params, ?int $clientId): void
    {
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 25)));
        $limit   = $perPage;

        $notifications = Notification::getAll('client', $clientId, $limit);
        $unread        = Notification::countUnread('client', $clientId);

        ApiResponse::success([
            'unread_count'  => (int) $unread,
            'notifications' => array_map(fn($n) => [
                'id'         => (int) $n['id'],
                'title'      => $n['title'],
                'message'    => $n['message'] ?? null,
                'type'       => $n['type'] ?? 'info',
                'link'       => $n['link'] ?? null,
                'is_read'    => (bool) ($n['is_read'] ?? false),
                'created_at' => $n['created_at'],
            ], $notifications),
        ]);
    }

    // POST /api/v1/profile/notifications/read
    // Body: {ids: [int]} or {} for mark all
    public function markRead(array $params, ?int $clientId): void
    {
        $body = $this->getJsonBody();
        $ids  = $body['ids'] ?? [];

        if (!empty($ids)) {
            foreach (array_map('intval', $ids) as $id) {
                Notification::markAsRead($id, 'client', $clientId);
            }
        } else {
            Notification::markAllAsRead('client', $clientId);
        }

        ApiResponse::success(['marked_read' => true]);
    }

    private function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) return [];
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}
