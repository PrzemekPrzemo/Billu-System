<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;

/**
 * Handles user interactions with advertisement banners:
 * dismiss (hide for entire session) and minimize/restore (collapse to title bar).
 *
 * All endpoints require any authenticated user and CSRF validation.
 * State is stored in the PHP session so it persists across page navigations
 * within the current login session and is automatically cleared on logout.
 */
class AdvertisementController extends Controller
{
    private function requireLoggedIn(): void
    {
        if (!Auth::isLoggedIn()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'unauthenticated']);
            exit;
        }
    }

    public function dismiss(string $id): void
    {
        $this->requireLoggedIn();
        header('Content-Type: application/json');

        if (!$this->validateCsrf()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'csrf']);
            return;
        }

        $id = (int) $id;
        $ad = \App\Models\Advertisement::findById($id);
        if (!$ad || !$ad['is_active']) {
            http_response_code(404);
            echo json_encode(['ok' => false]);
            return;
        }

        if (!isset($_SESSION['dismissed_ads'])) {
            $_SESSION['dismissed_ads'] = [];
        }
        $_SESSION['dismissed_ads'][$id] = 1;
        unset($_SESSION['minimized_ads'][$id]);

        echo json_encode(['ok' => true]);
    }

    public function minimize(string $id): void
    {
        $this->requireLoggedIn();
        header('Content-Type: application/json');

        if (!$this->validateCsrf()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'csrf']);
            return;
        }

        $id = (int) $id;
        $ad = \App\Models\Advertisement::findById($id);
        if (!$ad || !$ad['is_active']) {
            http_response_code(404);
            echo json_encode(['ok' => false]);
            return;
        }

        if (!isset($_SESSION['minimized_ads'])) {
            $_SESSION['minimized_ads'] = [];
        }
        $_SESSION['minimized_ads'][$id] = 1;

        echo json_encode(['ok' => true]);
    }

    public function restore(string $id): void
    {
        $this->requireLoggedIn();
        header('Content-Type: application/json');

        if (!$this->validateCsrf()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'csrf']);
            return;
        }

        $id = (int) $id;
        unset($_SESSION['minimized_ads'][$id]);

        echo json_encode(['ok' => true]);
    }
}
