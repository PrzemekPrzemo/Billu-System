<?php

declare(strict_types=1);

/**
 * OPcache preload script.
 *
 * Wymaga w php.ini:
 *   opcache.preload=/var/www/billu/config/opcache-preload.php
 *   opcache.preload_user=www-data
 *
 * Ładuje gorące klasy w fazie startu PHP-FPM, dzięki czemu są w pamięci
 * pomiędzy requestami i nie wymagają autoloadu / kompilacji.
 *
 * Bezpieczne - jeśli klasa nie istnieje, opcache_compile_file() zostanie pominięty.
 */

$root = dirname(__DIR__);

require_once $root . '/vendor/autoload.php';

$files = [
    // Core
    $root . '/src/Core/Database.php',
    $root . '/src/Core/Session.php',
    $root . '/src/Core/Auth.php',
    $root . '/src/Core/Router.php',
    $root . '/src/Core/Cache.php',
    $root . '/src/Core/Language.php',

    // API kernel
    $root . '/src/Api/ApiKernel.php',
    $root . '/src/Api/ApiRouter.php',
    $root . '/src/Api/ApiResponse.php',

    // Hot models (highest QPS)
    $root . '/src/Models/Invoice.php',
    $root . '/src/Models/IssuedInvoice.php',
    $root . '/src/Models/InvoiceBatch.php',
    $root . '/src/Models/Client.php',
    $root . '/src/Models/Office.php',
    $root . '/src/Models/User.php',
    $root . '/src/Models/Setting.php',
    $root . '/src/Models/AuditLog.php',

    // Hot services
    $root . '/src/Services/ImportService.php',
    $root . '/src/Services/DuplicateDetectionService.php',
    $root . '/src/Services/GusApiService.php',
    $root . '/src/Services/CeidgApiService.php',
    $root . '/src/Services/ViesService.php',
    $root . '/src/Services/NbpExchangeRateService.php',
    $root . '/src/Services/WhiteListService.php',
    $root . '/src/Services/KsefApiService.php',
];

foreach ($files as $file) {
    if (is_file($file)) {
        opcache_compile_file($file);
    }
}
