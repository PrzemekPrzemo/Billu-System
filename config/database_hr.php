<?php

/**
 * HR Database Configuration
 *
 * All HR/Payroll tables are stored in a dedicated MySQL database for data isolation.
 *
 * To set up:
 * 1. CREATE DATABASE billu_hr CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
 * 2. CREATE USER 'hr_user'@'localhost' IDENTIFIED BY 'strong_password';
 *    GRANT ALL PRIVILEGES ON billu_hr.* TO 'hr_user'@'localhost';
 * 3. Run the HR migration SQL.
 */

return [
    'host'     => 'localhost',
    'port'     => 3306,
    'database' => 'billu_hr',
    'username' => 'root',
    'password' => '',
    'charset'  => 'utf8mb4',

    'ssl' => [
        'enabled' => false,
        'ca'      => null,
        'cert'    => null,
        'key'     => null,
        'verify'  => false,
    ],
];
