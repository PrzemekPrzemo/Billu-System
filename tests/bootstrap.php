<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('TESTING')) {
    define('TESTING', true);
}

if (!isset($_SERVER['REMOTE_ADDR'])) {
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
}
if (!isset($_SERVER['HTTP_USER_AGENT'])) {
    $_SERVER['HTTP_USER_AGENT'] = 'phpunit';
}

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
