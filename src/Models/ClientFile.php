<?php

namespace App\Models;

use App\Core\Database;

class ClientFile
{
    public static function create(
        int $clientId,
        string $uploadedByType,
        int $uploadedById,
        string $originalFilename,
        string $storedPath,
        int $fileSize,
        ?string $mimeType,
        string $category = 'general',
        ?string $description = null
    ): int {
        $db = Database::getInstance();
        $db->query(
            "INSERT INTO client_files (client_id, uploaded_by_type, uploaded_by_id, original_filename, stored_path, file_size, mime_type, category, description)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $clientId,
                $uploadedByType,
                $uploadedById,
                $originalFilename,
                $storedPath,
                $fileSize,
                $mimeType,
                $category,
                $description,
            ]
        );
        return (int) $db->lastInsertId();
    }

    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM client_files WHERE id = ?",
            [$id]
        );
    }

    /** Ownership-checked accessor for client-scoped routes. */
    public static function findByIdForClient(int $id, int $clientId): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM client_files WHERE id = ? AND client_id = ?",
            [$id, $clientId]
        );
    }

    /** Ownership-checked accessor for office-scoped routes. */
    public static function findByIdForOffice(int $id, int $officeId): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT cf.* FROM client_files cf JOIN clients c ON cf.client_id = c.id WHERE cf.id = ? AND c.office_id = ?",
            [$id, $officeId]
        );
    }

    /**
     * Find all files for a client with optional category filter, ordered by created_at DESC.
     */
    public static function findByClient(int $clientId, ?string $category = null, int $limit = 100, int $offset = 0): array
    {
        $sql = "SELECT * FROM client_files WHERE client_id = ?";
        $params = [$clientId];
        if ($category !== null) {
            $sql .= " AND category = ?";
            $params[] = $category;
        }
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        return Database::getInstance()->fetchAll($sql, $params);
    }

    /**
     * Count files by client.
     */
    public static function countByClient(int $clientId): int
    {
        $row = Database::getInstance()->fetchOne(
            "SELECT COUNT(*) AS cnt FROM client_files WHERE client_id = ?",
            [$clientId]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Delete a file record (caller handles actual file deletion).
     */
    public static function delete(int $id): void
    {
        Database::getInstance()->query("DELETE FROM client_files WHERE id = ?", [$id]);
    }

    /**
     * Get the full filesystem path for a file, validating it's safe.
     * If client has file_storage_path set, use that as base; otherwise use default storage/client_files/{nip}/.
     */
    public static function getFullPath(array $fileRecord, ?string $clientStoragePath, string $clientNip): ?string
    {
        if (empty($fileRecord['stored_path'])) {
            return null;
        }

        if (!empty($clientStoragePath)) {
            // Custom path configured by office
            $basePath = rtrim($clientStoragePath, '/');
            $fullPath = $basePath . '/' . $fileRecord['stored_path'];
        } else {
            // Default path
            $sanitizedNip = preg_replace('/[^0-9]/', '', $clientNip);
            if ($sanitizedNip === '') {
                $sanitizedNip = 'client_' . $fileRecord['client_id'];
            }
            $fullPath = realpath(__DIR__ . '/../../storage/client_files') . '/' . $sanitizedNip . '/' . $fileRecord['stored_path'];
        }

        // Validate the path exists
        if (file_exists($fullPath)) {
            return $fullPath;
        }
        return null;
    }

    /**
     * Get storage stats for a client (total files, total size).
     */
    public static function getStorageStats(int $clientId): array
    {
        $row = Database::getInstance()->fetchOne(
            "SELECT COUNT(*) AS total_files, COALESCE(SUM(file_size), 0) AS total_size FROM client_files WHERE client_id = ?",
            [$clientId]
        );
        return [
            'total_files' => (int) ($row['total_files'] ?? 0),
            'total_size' => (int) ($row['total_size'] ?? 0),
        ];
    }
}
