<?php

namespace App\Core;

use App\Models\Module;

class ModuleAccess
{
    /**
     * Check if the current user has access to a module.
     * Admin always has access. Office/Employee check their office.
     * Client checks both office AND client-level module settings (cascade).
     */
    public static function isEnabled(string $slug): bool
    {
        if (Auth::isAdmin()) {
            return true;
        }

        $officeId = self::getCurrentOfficeId();
        if ($officeId === null) {
            return true; // No office context = no restriction
        }

        // For clients: check cascading office → client module access
        if (Auth::isClient()) {
            $clientId = self::getCurrentClientId();
            if ($clientId !== null) {
                return Module::isEnabledForClient($clientId, $officeId, $slug);
            }
        }

        return Module::isEnabledForOffice($officeId, $slug);
    }

    /**
     * Require module access or redirect to dashboard.
     */
    public static function requireModule(string $slug): void
    {
        if (!self::isEnabled($slug)) {
            Session::flash('error', 'module_not_available');
            if (Auth::isClient()) {
                header('Location: /client');
            } else {
                header('Location: /office');
            }
            exit;
        }
    }

    /**
     * Require HR parent module + specific payroll sub-module.
     */
    public static function requireHrModule(string $subModuleSlug): void
    {
        self::requireModule('hr');
        self::requireModule($subModuleSlug);
    }

    /**
     * Get list of enabled module slugs for the current session.
     * Returns ['*'] for admin (all access).
     */
    public static function getEnabledSlugs(): array
    {
        if (Auth::isAdmin()) {
            return ['*'];
        }

        $officeId = self::getCurrentOfficeId();
        if ($officeId === null) {
            return ['*'];
        }

        // For clients: return client-level filtered slugs
        if (Auth::isClient()) {
            $clientId = self::getCurrentClientId();
            if ($clientId !== null) {
                return Module::getEnabledSlugsForClient($clientId, $officeId);
            }
        }

        return Module::getEnabledSlugsForOffice($officeId);
    }

    /**
     * Get the office ID for the current session context.
     */
    private static function getCurrentOfficeId(): ?int
    {
        if (Auth::isOffice() || Auth::isEmployee()) {
            $id = Session::get('office_id');
            return $id ? (int)$id : null;
        }

        if (Auth::isClient()) {
            $clientId = Session::get('client_id');
            if ($clientId) {
                $client = \App\Models\Client::findById((int)$clientId);
                return $client && !empty($client['office_id']) ? (int)$client['office_id'] : null;
            }
        }

        return null;
    }

    /**
     * Get the client ID for the current session context.
     */
    private static function getCurrentClientId(): ?int
    {
        $clientId = Session::get('client_id');
        return $clientId ? (int)$clientId : null;
    }
}
