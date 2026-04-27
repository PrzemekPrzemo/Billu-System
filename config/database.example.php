<?php

return [
    'host'     => 'localhost',
    'port'     => 3306,
    'database' => 'billu',
    'username' => 'root',
    'password' => '',
    'charset'  => 'utf8mb4',

    // Optional TLS — set ssl_ca to a CA bundle path to encrypt the MySQL connection.
    // ssl_verify enforces server certificate validation; set to false only for self-signed certs in dev.
    'ssl_ca'     => null,
    'ssl_verify' => true,
];
