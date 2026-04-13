<?php

declare(strict_types=1);

namespace App\Api;

class ApiResponse
{
    public static function success(mixed $data, int $status = 200): never
    {
        self::send(['ok' => true, 'data' => $data], $status);
    }

    public static function created(mixed $data): never
    {
        self::send(['ok' => true, 'data' => $data], 201);
    }

    public static function noContent(): never
    {
        http_response_code(204);
        exit;
    }

    public static function paginated(array $items, int $page, int $perPage, int $total): never
    {
        self::send([
            'ok'   => true,
            'data' => $items,
            'meta' => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $total,
                'total_pages' => (int) ceil($total / max(1, $perPage)),
            ],
        ], 200);
    }

    public static function error(int $status, string $code, string $message = '', array $fields = []): never
    {
        $error = ['code' => $code];
        if ($message !== '') {
            $error['message'] = $message;
        }
        if (!empty($fields)) {
            $error['fields'] = $fields;
        }
        self::send(['ok' => false, 'error' => $error], $status);
    }

    public static function unauthorized(string $code = 'unauthorized'): never
    {
        self::error(401, $code, 'Authentication required');
    }

    public static function forbidden(string $code = 'forbidden'): never
    {
        self::error(403, $code, 'Access denied');
    }

    public static function notFound(string $code = 'not_found'): never
    {
        self::error(404, $code, 'Resource not found');
    }

    public static function validation(array $fields): never
    {
        self::error(422, 'validation_error', 'Validation failed', $fields);
    }

    public static function tooManyRequests(): never
    {
        header('Retry-After: 60');
        self::error(429, 'rate_limit_exceeded', 'Too many requests. Please try again later.');
    }

    private static function send(array $payload, int $status): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
