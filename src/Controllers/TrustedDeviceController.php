<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\TrustedDevice;

/**
 * Self-service management of trusted devices (the "remember this device"
 * 2FA bypass). Works for every authenticated user type — currentUserType
 * + currentUserId from Auth provide the tenant scope automatically, and
 * TrustedDevice::revoke / findByUser only operate on rows matching that
 * pair, so users never see or touch other users' devices.
 */
class TrustedDeviceController extends Controller
{
    public function index(): void
    {
        $this->requireLoggedIn();
        $userType = Auth::currentUserType();
        $userId   = (int) Auth::currentUserId();

        $devices = TrustedDevice::findByUser($userType, $userId);
        $currentToken = $_COOKIE[TrustedDevice::COOKIE_NAME] ?? '';
        $currentHash = $currentToken !== '' ? hash('sha256', $currentToken) : null;

        $this->render('account/trusted_devices', [
            'devices'     => $devices,
            'currentHash' => $currentHash,
            'backUrl'     => self::backUrlForType($userType),
        ]);
    }

    public function revoke(int $id): void
    {
        $this->requireLoggedIn();
        if (!$this->validateCsrf()) { $this->redirect('/trusted-devices'); return; }

        $userType = Auth::currentUserType();
        $userId   = (int) Auth::currentUserId();

        if (TrustedDevice::revoke($id, $userType, $userId)) {
            AuditLog::log($userType, $userId, 'trusted_device_revoked',
                "Trusted device #{$id} revoked", 'trusted_device', $id);
            Session::flash('success', 'trusted_device_revoked');
        }
        $this->redirect('/trusted-devices');
    }

    public function revokeAll(): void
    {
        $this->requireLoggedIn();
        if (!$this->validateCsrf()) { $this->redirect('/trusted-devices'); return; }

        $userType = Auth::currentUserType();
        $userId   = (int) Auth::currentUserId();

        $n = TrustedDevice::revokeAllForUser($userType, $userId);
        Auth::clearTrustedDeviceCookie();

        AuditLog::log($userType, $userId, 'trusted_devices_revoked_all',
            "All trusted devices revoked ({$n})", 'trusted_device', null);
        Session::flash('success', 'trusted_devices_revoked_all');
        $this->redirect('/trusted-devices');
    }

    private function requireLoggedIn(): void
    {
        if (!Auth::isLoggedIn()) {
            $this->redirect('/login');
            exit;
        }
    }

    /** Where the "back" button on the page should point per user type. */
    private static function backUrlForType(string $type): string
    {
        return match ($type) {
            'admin'           => '/admin/security',
            'office'          => '/office/security',
            'employee'        => '/office/security',
            'client'          => '/client/security',
            'client_employee' => '/employee/profile',
            default           => '/',
        };
    }
}
