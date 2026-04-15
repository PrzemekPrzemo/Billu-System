<?php
/**
 * Advertisement routes. Included from public/index.php where $router is in scope.
 *
 * Admin CRUD + toggle: 7 routes
 * User AJAX (dismiss/minimize/restore): 3 routes
 */

use App\Controllers\AdminAdsController;
use App\Controllers\AdvertisementController;

// Admin CRUD
$router->get('/admin/advertisements',                     [AdminAdsController::class, 'index']);
$router->get('/admin/advertisements/create',              [AdminAdsController::class, 'create']);
$router->post('/admin/advertisements/create',             [AdminAdsController::class, 'store']);
$router->get('/admin/advertisements/{id}/edit',           [AdminAdsController::class, 'edit']);
$router->post('/admin/advertisements/{id}/update',        [AdminAdsController::class, 'update']);
$router->post('/admin/advertisements/{id}/delete',        [AdminAdsController::class, 'delete']);
$router->post('/admin/advertisements/{id}/toggle',        [AdminAdsController::class, 'toggle']);

// User AJAX (any authenticated user)
$router->post('/ads/{id}/dismiss',                        [AdvertisementController::class, 'dismiss']);
$router->post('/ads/{id}/minimize',                       [AdvertisementController::class, 'minimize']);
$router->post('/ads/{id}/restore',                        [AdvertisementController::class, 'restore']);
