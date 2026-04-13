<?php

declare(strict_types=1);

namespace App\Api\Controllers;

use App\Api\ApiResponse;
use App\Models\Message;
use App\Core\Database;

class MessageApiController
{
    private const UPLOAD_MAX_MB = 10;

    // GET /api/v1/messages?page=&per_page=
    public function index(array $params, ?int $clientId): void
    {
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 25)));
        $offset  = ($page - 1) * $perPage;

        $threads = Message::findByClient($clientId, $perPage, $offset);
        $total   = Database::getInstance()->fetchOne(
            "SELECT COUNT(*) as cnt FROM messages WHERE client_id = ? AND parent_id IS NULL",
            [$clientId]
        )['cnt'] ?? 0;

        ApiResponse::paginated(
            array_map([$this, 'formatThreadSummary'], $threads),
            $page,
            $perPage,
            (int) $total
        );
    }

    // GET /api/v1/messages/{id}
    public function show(array $params, ?int $clientId): void
    {
        $message = $this->findForClient((int) $params['id'], $clientId);

        // Mark thread as read
        Message::markReadByClient((int) $message['id']);

        $thread = Message::findThread((int) $message['id']);

        ApiResponse::success([
            'root'    => $this->formatMessage($message),
            'replies' => array_map([$this, 'formatMessage'], $thread),
        ]);
    }

    // POST /api/v1/messages
    // Body: {subject: string, body: string, invoice_id?: int}
    public function create(array $params, ?int $clientId): void
    {
        $body    = $this->getJsonBody();
        $subject = trim($body['subject'] ?? '');
        $text    = trim($body['body'] ?? '');

        if (empty($subject) || empty($text)) {
            ApiResponse::validation([
                'subject' => empty($subject) ? 'required' : null,
                'body'    => empty($text)    ? 'required' : null,
            ]);
        }

        $messageId = Message::create([
            'client_id'    => $clientId,
            'office_id'    => $this->getClientOfficeId($clientId),
            'sender_type'  => 'client',
            'sender_id'    => $clientId,
            'subject'      => $subject,
            'body'         => $text,
            'parent_id'    => null,
            'invoice_id'   => isset($body['invoice_id']) ? (int) $body['invoice_id'] : null,
            'is_read_by_client' => 1,
            'is_read_by_office' => 0,
        ]);

        $message = Message::findById($messageId);
        ApiResponse::created($this->formatMessage($message));
    }

    // POST /api/v1/messages/{id}/reply
    // Body: {body: string}
    public function reply(array $params, ?int $clientId): void
    {
        $root = $this->findForClient((int) $params['id'], $clientId);
        $body = $this->getJsonBody();
        $text = trim($body['body'] ?? '');

        if (empty($text)) {
            ApiResponse::validation(['body' => 'required']);
        }

        $messageId = Message::create([
            'client_id'    => $clientId,
            'office_id'    => (int) $root['office_id'],
            'sender_type'  => 'client',
            'sender_id'    => $clientId,
            'subject'      => 'Re: ' . $root['subject'],
            'body'         => $text,
            'parent_id'    => (int) $root['id'],
            'invoice_id'   => $root['invoice_id'] ?? null,
            'is_read_by_client' => 1,
            'is_read_by_office' => 0,
        ]);

        $message = Message::findById($messageId);
        ApiResponse::created($this->formatMessage($message));
    }

    // GET /api/v1/messages/{id}/attachment
    public function downloadAttachment(array $params, ?int $clientId): void
    {
        $message = $this->findForClient((int) $params['id'], $clientId);

        $path = Message::getAttachmentFullPath($message);
        if (!$path || !file_exists($path)) {
            ApiResponse::notFound('attachment_not_found');
        }

        while (ob_get_level()) ob_end_clean();

        $mime = mime_content_type($path) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    // POST /api/v1/messages/{id}/attachment
    public function uploadAttachment(array $params, ?int $clientId): void
    {
        $message = $this->findForClient((int) $params['id'], $clientId);

        if (empty($_FILES['attachment'])) {
            ApiResponse::validation(['attachment' => 'required']);
        }

        $file = $_FILES['attachment'];

        if ($file['size'] > self::UPLOAD_MAX_MB * 1024 * 1024) {
            ApiResponse::validation(['attachment' => 'File too large (max ' . self::UPLOAD_MAX_MB . 'MB)']);
        }

        $config  = require __DIR__ . '/../../../../config/app.php';
        $storage = rtrim($config['storage'], '/');
        $dir     = $storage . '/attachments/messages';

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'msg_' . $message['id'] . '_' . time() . '.' . $ext;
        $dest     = $dir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            ApiResponse::error(500, 'upload_failed', 'Could not save attachment');
        }

        Message::updateAttachment((int) $message['id'], $dest, $file['name']);

        ApiResponse::success([
            'filename'     => $file['name'],
            'uploaded'     => true,
        ]);
    }

    // ── Helpers ────────────────────────────────────

    private function findForClient(int $id, int $clientId): array
    {
        $message = Message::findById($id);
        if (!$message || (int) $message['client_id'] !== $clientId) {
            ApiResponse::notFound('message_not_found');
        }
        return $message;
    }

    private function formatThreadSummary(array $m): array
    {
        return [
            'id'             => (int) $m['id'],
            'subject'        => $m['subject'],
            'body_preview'   => mb_strimwidth(strip_tags($m['body'] ?? ''), 0, 100, '...'),
            'sender_type'    => $m['sender_type'],
            'sender_name'    => Message::getSenderName($m['sender_type'], (int) $m['sender_id']),
            'is_read'        => (bool) ($m['is_read_by_client'] ?? false),
            'has_attachment' => !empty($m['attachment_path']),
            'invoice_id'     => $m['invoice_id'] ? (int) $m['invoice_id'] : null,
            'created_at'     => $m['created_at'],
        ];
    }

    private function formatMessage(array $m): array
    {
        return [
            'id'             => (int) $m['id'],
            'subject'        => $m['subject'],
            'body'           => $m['body'],
            'sender_type'    => $m['sender_type'],
            'sender_name'    => Message::getSenderName($m['sender_type'], (int) $m['sender_id']),
            'has_attachment' => !empty($m['attachment_path']),
            'attachment_name'=> $m['attachment_name'] ?? null,
            'invoice_id'     => $m['invoice_id'] ? (int) $m['invoice_id'] : null,
            'created_at'     => $m['created_at'],
        ];
    }

    private function getClientOfficeId(int $clientId): ?int
    {
        $row = Database::getInstance()->fetchOne("SELECT office_id FROM clients WHERE id = ?", [$clientId]);
        return $row ? (int) $row['office_id'] : null;
    }

    private function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) return [];
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}
