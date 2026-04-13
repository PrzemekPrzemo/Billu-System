<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Auth\ApiAuthController;
use App\Api\Controllers\BatchApiController;
use App\Api\Controllers\PurchaseInvoiceApiController;
use App\Api\Controllers\SalesInvoiceApiController;
use App\Api\Controllers\MessageApiController;
use App\Api\Controllers\TaskApiController;
use App\Api\Controllers\ReportApiController;
use App\Api\Controllers\ProfileApiController;
use App\Api\Controllers\CostCenterApiController;
use App\Api\Controllers\NotificationApiController;
use App\Api\Middleware\JwtMiddleware;
use App\Models\Setting;

class ApiKernel
{
    private ApiRouter $router;

    public function __construct()
    {
        $this->router = new ApiRouter();
        $this->registerRoutes();
    }

    public function handle(string $method, string $uri): void
    {
        // Handle CORS preflight
        $this->setCorsHeaders();
        if ($method === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        header('Content-Type: application/json; charset=utf-8');

        // Global mobile API kill-switch (setting stored in `settings` table)
        if (!Setting::get('mobile_api_enabled', '1')) {
            ApiResponse::error(503, 'api_disabled', 'API mobilne jest chwilowo wyłączone');
        }

        $match = $this->router->match($method, $uri);

        if ($match === null) {
            ApiResponse::notFound('endpoint_not_found');
        }

        ['controller' => $controllerClass, 'action' => $action, 'params' => $params, 'requiresAuth' => $requiresAuth] = $match;

        try {
            $clientId = null;

            if ($requiresAuth === 'pre_auth') {
                $clientId = JwtMiddleware::requirePreAuth();
            } elseif ($requiresAuth === true) {
                $clientId = JwtMiddleware::requireAuth();
            }

            $controller = new $controllerClass();
            $controller->$action($params, $clientId);

        } catch (\Throwable $e) {
            error_log('[API] Unhandled exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            ApiResponse::error(500, 'internal_error', 'An internal error occurred');
        }
    }

    private function registerRoutes(): void
    {
        $r = $this->router;

        // ── Auth (no auth required) ────────────────────────────
        $r->post('/auth/login',         ApiAuthController::class, 'login',        false);
        $r->post('/auth/2fa/verify',    ApiAuthController::class, 'verify2fa',    'pre_auth');
        $r->post('/auth/token/refresh', ApiAuthController::class, 'refreshToken', false);
        $r->post('/auth/logout',        ApiAuthController::class, 'logout',       true);
        $r->get('/auth/me',             ApiAuthController::class, 'me',           true);

        // ── Batches ────────────────────────────────────────────
        $r->get('/batches',       BatchApiController::class, 'index');
        $r->get('/batches/{id}',  BatchApiController::class, 'show');

        // ── Purchase Invoices ──────────────────────────────────
        $r->get('/invoices',                        PurchaseInvoiceApiController::class, 'index');
        $r->get('/invoices/{id}',                   PurchaseInvoiceApiController::class, 'show');
        $r->post('/invoices/{id}/verify',           PurchaseInvoiceApiController::class, 'verify');
        $r->post('/invoices/{id}/comment',          PurchaseInvoiceApiController::class, 'addComment');
        $r->get('/invoices/{id}/comments',          PurchaseInvoiceApiController::class, 'comments');
        $r->patch('/invoices/{id}/cost-center',     PurchaseInvoiceApiController::class, 'setCostCenter');
        $r->patch('/invoices/{id}/paid',            PurchaseInvoiceApiController::class, 'togglePaid');
        $r->get('/invoices/{id}/pdf',               PurchaseInvoiceApiController::class, 'pdf');
        $r->post('/invoices/bulk-verify',           PurchaseInvoiceApiController::class, 'bulkVerify');

        // ── Sales Invoices ─────────────────────────────────────
        $r->get('/sales',                       SalesInvoiceApiController::class, 'index');
        $r->post('/sales',                      SalesInvoiceApiController::class, 'create');
        $r->get('/sales/{id}',                  SalesInvoiceApiController::class, 'show');
        $r->put('/sales/{id}',                  SalesInvoiceApiController::class, 'update');
        $r->delete('/sales/{id}',               SalesInvoiceApiController::class, 'destroy');
        $r->post('/sales/{id}/issue',           SalesInvoiceApiController::class, 'issue');
        $r->post('/sales/{id}/send-ksef',       SalesInvoiceApiController::class, 'sendKsef');
        $r->post('/sales/{id}/duplicate',       SalesInvoiceApiController::class, 'duplicate');
        $r->get('/sales/{id}/pdf',              SalesInvoiceApiController::class, 'pdf');

        // ── Messages ───────────────────────────────────────────
        $r->get('/messages',              MessageApiController::class, 'index');
        $r->post('/messages',             MessageApiController::class, 'create');
        $r->get('/messages/{id}',         MessageApiController::class, 'show');
        $r->post('/messages/{id}/reply',  MessageApiController::class, 'reply');
        $r->get('/messages/{id}/attachment',  MessageApiController::class, 'downloadAttachment');
        $r->post('/messages/{id}/attachment', MessageApiController::class, 'uploadAttachment');

        // ── Tasks ──────────────────────────────────────────────
        $r->get('/tasks',              TaskApiController::class, 'index');
        $r->get('/tasks/{id}',         TaskApiController::class, 'show');
        $r->patch('/tasks/{id}/status', TaskApiController::class, 'updateStatus');

        // ── Reports ────────────────────────────────────────────
        $r->get('/reports/dashboard-summary',  ReportApiController::class, 'dashboardSummary');
        $r->get('/reports/monthly-stats',      ReportApiController::class, 'monthlyStats');
        $r->get('/reports/supplier-analysis',  ReportApiController::class, 'supplierAnalysis');

        // ── Profile & Account ──────────────────────────────────
        $r->get('/profile',                        ProfileApiController::class, 'show');
        $r->get('/profile/cost-centers',           CostCenterApiController::class, 'index');
        $r->get('/profile/notifications',          NotificationApiController::class, 'index');
        $r->post('/profile/notifications/read',    NotificationApiController::class, 'markRead');
        $r->post('/profile/fcm-token',             ProfileApiController::class, 'saveFcmToken');
    }

    private function setCorsHeaders(): void
    {
        $config         = require __DIR__ . '/../../config/app.php';
        $allowedOrigins = $config['api_allowed_origins'] ?? ['*'];

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (in_array('*', $allowedOrigins, true)) {
            header('Access-Control-Allow-Origin: *');
        } elseif (in_array($origin, $allowedOrigins, true)) {
            header("Access-Control-Allow-Origin: {$origin}");
            header('Vary: Origin');
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');
        header('Access-Control-Max-Age: 86400');
    }
}
