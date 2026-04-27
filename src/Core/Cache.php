<?php

declare(strict_types=1);

namespace App\Core;

use Predis\Client as RedisClient;
use Throwable;

/**
 * Aplikacyjna warstwa cache z fallbackiem na driver "null" gdy Redis niedostępny.
 * Sterowniki:
 *  - redis: Predis (czysty PHP, bez ext-redis)
 *  - null:  no-op (każdy get zwraca null, set ignoruje) - bezpieczny tryb gdy Redis padł
 */
final class Cache
{
    private static ?self $instance = null;

    /** @var array<string,mixed> */
    private array $config;

    private string $driver = 'null';
    private ?RedisClient $redis = null;
    private string $prefix = 'billu:';

    /** @var array<string,int> */
    private array $ttls = [];

    /** @param array<string,mixed> $config */
    private function __construct(array $config)
    {
        $this->config = $config;
        $this->prefix = (string)($config['prefix'] ?? 'billu:');
        $this->ttls   = $config['ttl'] ?? [];

        $driver = (string)($config['driver'] ?? 'null');
        if ($driver === 'redis' && class_exists(RedisClient::class)) {
            try {
                $this->redis = new RedisClient([
                    'scheme'   => 'tcp',
                    'host'     => $config['redis']['host'] ?? '127.0.0.1',
                    'port'     => (int)($config['redis']['port'] ?? 6379),
                    'password' => $config['redis']['password'] ?? null,
                    'database' => (int)($config['redis']['database'] ?? 0),
                    'timeout'  => (float)($config['redis']['timeout'] ?? 1.5),
                ]);
                $this->redis->ping();
                $this->driver = 'redis';
            } catch (Throwable) {
                $this->redis  = null;
                $this->driver = 'null';
            }
        }
    }

    /** @param array<string,mixed> $config */
    public static function init(array $config): void
    {
        self::$instance = new self($config);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self(['driver' => 'null']);
        }
        return self::$instance;
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function ttl(string $bucket, int $fallback = 3600): int
    {
        return $this->ttls[$bucket] ?? $fallback;
    }

    public function get(string $key): mixed
    {
        if ($this->driver !== 'redis' || $this->redis === null) {
            return null;
        }
        try {
            $raw = $this->redis->get($this->prefix . $key);
            if ($raw === null) {
                return null;
            }
            $decoded = json_decode($raw, true);
            return is_array($decoded) && array_key_exists('v', $decoded) ? $decoded['v'] : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        if ($this->driver !== 'redis' || $this->redis === null) {
            return false;
        }
        try {
            $payload = json_encode(['v' => $value], JSON_UNESCAPED_UNICODE);
            if ($payload === false) {
                return false;
            }
            $this->redis->setex($this->prefix . $key, max(1, $ttl), $payload);
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Zwraca wartość z cache lub uruchamia $producer i zapisuje wynik.
     * Wynik jest cachowany TYLKO jeśli nie jest null - chroni to przed
     * trwałym zapamiętaniem pustej odpowiedzi przy chwilowych awariach API.
     *
     * Cache stampede protection: gdy klucz wygaśnie i wiele requestów
     * uderzy jednocześnie, tylko pierwszy odbudowuje cache (zdobywa lock
     * `key:lock` na 5s przez SET NX EX). Pozostałe czekają max 1s na
     * świeżą wartość; jeśli się nie pojawi, fallback do bezpośredniego
     * uruchomienia producera (degradacja, nie awaria).
     */
    public function remember(string $key, int $ttl, callable $producer): mixed
    {
        $hit = $this->get($key);
        if ($hit !== null) {
            return $hit;
        }

        if ($this->driver === 'redis' && $this->redis !== null) {
            $lockKey = $this->prefix . $key . ':lock';
            try {
                $acquired = $this->redis->set($lockKey, '1', 'EX', 5, 'NX');
                if (!$acquired) {
                    // Inny proces odbudowuje cache - krótki polling.
                    for ($i = 0; $i < 10; $i++) {
                        usleep(100000); // 100 ms
                        $hit = $this->get($key);
                        if ($hit !== null) {
                            return $hit;
                        }
                    }
                    // Timeout - degradujemy do bezpośredniego wywołania.
                    return $producer();
                }
            } catch (Throwable) {
                // Lock failed - degradacja do zwykłego flow bez stampede protection.
            }

            try {
                $value = $producer();
                if ($value !== null) {
                    $this->set($key, $value, $ttl);
                }
                return $value;
            } finally {
                try {
                    $this->redis->del([$lockKey]);
                } catch (Throwable) {
                    // ignore
                }
            }
        }

        // Driver null lub błąd - zwykły flow.
        $value = $producer();
        if ($value !== null) {
            $this->set($key, $value, $ttl);
        }
        return $value;
    }

    public function forget(string $key): void
    {
        if ($this->driver !== 'redis' || $this->redis === null) {
            return;
        }
        try {
            $this->redis->del([$this->prefix . $key]);
        } catch (Throwable) {
            // ignore
        }
    }

    /**
     * Usuwa wszystkie klucze pasujące do prefiksu tagu (np. "client:42:*").
     * Używa SCAN zamiast KEYS - bezpieczne dla produkcji.
     */
    public function flushTag(string $tag): void
    {
        if ($this->driver !== 'redis' || $this->redis === null) {
            return;
        }
        try {
            $pattern = $this->prefix . $tag . ':*';
            $cursor  = 0;
            do {
                $result = $this->redis->scan($cursor, ['MATCH' => $pattern, 'COUNT' => 200]);
                if (!is_array($result) || count($result) < 2) {
                    break;
                }
                [$cursor, $keys] = $result;
                if (!empty($keys)) {
                    $this->redis->del($keys);
                }
            } while ((int)$cursor !== 0);
        } catch (Throwable) {
            // ignore
        }
    }
}
