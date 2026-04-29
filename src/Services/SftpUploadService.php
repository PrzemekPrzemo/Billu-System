<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Crypto;
use App\Core\Database;
use App\Models\Client;
use App\Models\Office;
use phpseclib3\Net\SFTP;
use phpseclib3\Crypt\PublicKeyLoader;

/**
 * SFTP push: queue local files and let a cron worker (sftp-worker.php)
 * deliver them to each office's configured remote server.
 *
 * Hot path (enqueue) NEVER opens a network connection — it just inserts
 * a row. A request that triggers an upload (e.g. attaching a file to a
 * message) stays fast even if the office's SFTP server is unreachable.
 *
 * The worker is the only place that loads phpseclib and connects.
 */
final class SftpUploadService
{
    public const SOURCES = ['files', 'messages', 'invoices', 'exports', 'payslips'];
    private const MAX_ATTEMPTS = 5;
    private const QUEUE_BATCH  = 10;

    /**
     * Add a row to sftp_queue if (a) office has SFTP enabled and (b) the
     * given client has the matching push_<source> flag on. Otherwise no-op.
     * Safe to call from any controller / service after writing a local file.
     */
    public static function enqueue(int $officeId, int $clientId, string $sourceType, string $localPath, ?string $sourceRef = null): bool
    {
        if (!in_array($sourceType, self::SOURCES, true)) {
            return false;
        }
        if (!is_file($localPath)) {
            return false;
        }

        $office = Office::findById($officeId);
        if (!$office || empty($office['sftp_enabled'])) {
            return false;
        }
        $client = Client::findById($clientId);
        if (!$client || (int) ($client['office_id'] ?? 0) !== $officeId) {
            return false;
        }
        $flagColumn = 'sftp_push_' . $sourceType;
        if (empty($client[$flagColumn])) {
            return false;
        }

        Database::getInstance()->insert('sftp_queue', [
            'office_id'       => $officeId,
            'client_id'       => $clientId,
            'source_type'     => $sourceType,
            'source_ref'      => $sourceRef,
            'local_path'      => $localPath,
            'remote_filename' => basename($localPath),
            'status'          => 'pending',
        ]);
        return true;
    }

    /**
     * Drain the queue. Called from sftp-worker.php (cron, every minute).
     * Picks up to $batch pending rows with FOR UPDATE SKIP LOCKED so two
     * workers never claim the same row.
     *
     * @return array{processed:int, sent:int, failed:int}
     */
    public static function processQueue(int $batch = self::QUEUE_BATCH): array
    {
        $db = Database::getInstance();
        $processed = 0; $sent = 0; $failed = 0;

        // Group jobs by office so we open one SFTP connection per office per batch.
        $rows = $db->fetchAll(
            "SELECT id, office_id, client_id, source_type, local_path, remote_filename, attempts
             FROM sftp_queue
             WHERE status = 'pending' AND attempts < ?
             ORDER BY created_at
             LIMIT ?",
            [self::MAX_ATTEMPTS, $batch]
        );

        $byOffice = [];
        foreach ($rows as $r) {
            $byOffice[(int) $r['office_id']][] = $r;
        }

        foreach ($byOffice as $officeId => $jobs) {
            $sftp = self::openForOffice($officeId);
            foreach ($jobs as $job) {
                $processed++;
                if ($sftp === null) {
                    self::markFailed((int) $job['id'], (int) $job['attempts'], 'office SFTP config missing or invalid');
                    $failed++;
                    continue;
                }
                $remotePath = self::buildRemotePath($officeId, (int) $job['client_id'], $job['source_type'], $job['remote_filename']);
                if ($remotePath === null) {
                    self::markFailed((int) $job['id'], (int) $job['attempts'], 'cannot build remote path');
                    $failed++;
                    continue;
                }
                if (!is_file($job['local_path'])) {
                    self::markFailed((int) $job['id'], (int) $job['attempts'], 'local file missing');
                    $failed++;
                    continue;
                }

                $remoteDir = dirname($remotePath);
                if (!@$sftp->is_dir($remoteDir)) {
                    @$sftp->mkdir($remoteDir, 0750, true);
                }

                $ok = @$sftp->put($remotePath, $job['local_path'], SFTP::SOURCE_LOCAL_FILE);
                if ($ok) {
                    $db->update('sftp_queue',
                        ['status' => 'sent', 'sent_at' => date('Y-m-d H:i:s')],
                        'id = ?', [(int) $job['id']]
                    );
                    $sent++;
                } else {
                    $err = $sftp->getLastSFTPError() ?: 'put() failed';
                    self::markFailed((int) $job['id'], (int) $job['attempts'], (string) $err);
                    $failed++;
                }
            }
            if ($sftp !== null) { $sftp->disconnect(); }
        }

        return ['processed' => $processed, 'sent' => $sent, 'failed' => $failed];
    }

    /**
     * Synchronous test: try to connect and list base_path. Used by the
     * 'Test connection' button on /office/sftp.
     *
     * @param array $cfg keys: host, port, user, password|private_key|key_passphrase, base_path
     * @return array{ok:bool, message:string, fingerprint:?string}
     */
    public static function testConnection(array $cfg): array
    {
        try {
            $sftp = self::dialFromConfig($cfg);
            if ($sftp === null) {
                return ['ok' => false, 'message' => 'authentication failed', 'fingerprint' => null];
            }
            $fp = $sftp->getServerPublicHostKey();
            $fingerprint = $fp ? 'sha256:' . base64_encode(hash('sha256', $fp, true)) : null;

            $base = $cfg['base_path'] ?? '/';
            $listing = $sftp->nlist($base);
            $sftp->disconnect();

            if ($listing === false) {
                return ['ok' => false, 'message' => 'cannot list ' . $base, 'fingerprint' => $fingerprint];
            }
            return ['ok' => true, 'message' => 'connected', 'fingerprint' => $fingerprint];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'fingerprint' => null];
        }
    }

    // ── Internals ────────────────────────────────────

    /** Decrypt office credentials and connect; null on any failure. */
    private static function openForOffice(int $officeId): ?SFTP
    {
        $office = Office::findById($officeId);
        if (!$office || empty($office['sftp_enabled']) || empty($office['sftp_host'])) {
            return null;
        }
        $cfg = [
            'host'           => (string) $office['sftp_host'],
            'port'           => (int) ($office['sftp_port'] ?: 22),
            'user'           => (string) $office['sftp_user'],
            'base_path'      => (string) ($office['sftp_base_path'] ?: '/'),
            'fingerprint'    => $office['sftp_host_fingerprint'] ?? null,
        ];
        if (!empty($office['sftp_password_enc'])) {
            $cfg['password'] = Crypto::decrypt((string) $office['sftp_password_enc'], 'sftp.password');
        }
        if (!empty($office['sftp_private_key_enc'])) {
            $cfg['private_key'] = Crypto::decrypt((string) $office['sftp_private_key_enc'], 'sftp.private_key');
        }
        if (!empty($office['sftp_key_passphrase_enc'])) {
            $cfg['key_passphrase'] = Crypto::decrypt((string) $office['sftp_key_passphrase_enc'], 'sftp.key_passphrase');
        }
        return self::dialFromConfig($cfg);
    }

    private static function dialFromConfig(array $cfg): ?SFTP
    {
        $host = (string) ($cfg['host'] ?? '');
        $port = (int)    ($cfg['port'] ?? 22);
        $user = (string) ($cfg['user'] ?? '');
        if ($host === '' || $user === '') return null;

        $sftp = new SFTP($host, $port, 8); // 8s timeout
        // Host fingerprint pin (TOFU): if office has one set, refuse to connect to a different host.
        if (!empty($cfg['fingerprint'])) {
            $hostKey = $sftp->getServerPublicHostKey();
            if ($hostKey === false) return null;
            $observed = 'sha256:' . base64_encode(hash('sha256', $hostKey, true));
            if (!hash_equals((string) $cfg['fingerprint'], $observed)) {
                return null; // host key mismatch — possible MITM
            }
        }

        if (!empty($cfg['private_key'])) {
            try {
                $key = PublicKeyLoader::load($cfg['private_key'], $cfg['key_passphrase'] ?? false);
                if (!$sftp->login($user, $key)) return null;
                return $sftp;
            } catch (\Throwable) {
                // fall through to password
            }
        }
        if (!empty($cfg['password'])) {
            return $sftp->login($user, $cfg['password']) ? $sftp : null;
        }
        return null;
    }

    private static function buildRemotePath(int $officeId, int $clientId, string $sourceType, string $filename): ?string
    {
        $office = Office::findById($officeId);
        $client = Client::findById($clientId);
        if (!$office || !$client) return null;

        $base = rtrim((string) ($office['sftp_base_path'] ?: '/'), '/');
        $subdir = $client['sftp_subdir'] ?? null;
        if ($subdir === null || $subdir === '') {
            $subdir = preg_replace('/[^0-9]/', '', (string) ($client['nip'] ?? '')) ?: ('client_' . $clientId);
        }
        // Sanitize: no path traversal, no hidden absolute paths.
        $subdir = trim((string) $subdir, '/');
        if (str_contains($subdir, '..')) return null;
        $filename = basename($filename);

        return $base . '/' . $subdir . '/' . $sourceType . '/' . $filename;
    }

    private static function markFailed(int $jobId, int $attempts, string $error): void
    {
        $next = $attempts + 1;
        $finalStatus = $next >= self::MAX_ATTEMPTS ? 'failed' : 'pending';
        Database::getInstance()->update('sftp_queue', [
            'status'     => $finalStatus,
            'attempts'   => $next,
            'last_error' => mb_substr($error, 0, 1000),
        ], 'id = ?', [$jobId]);
    }
}
