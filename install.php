<?php

/**
 * Installation script.
 * Run once: php install.php
 * Then delete this file!
 */

declare(strict_types=1);

echo "=== BiLLU Financial Solutions - Instalacja ===\n\n";

// 1. Check PHP version
echo "PHP version: " . PHP_VERSION . "\n";
if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    die("ERROR: Wymagane PHP >= 8.1\n");
}

// 2. Check extensions
$required = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'gd', 'zip'];
foreach ($required as $ext) {
    $status = extension_loaded($ext) ? 'OK' : 'BRAK';
    echo "  ext-{$ext}: {$status}\n";
    if ($status === 'BRAK') {
        echo "  WARNING: Zainstaluj rozszerzenie {$ext}\n";
    }
}

// 3. Check composer
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "\n[!] Brak vendor/. Uruchom: composer install\n";
}

// 4. Copy database config
if (!file_exists(__DIR__ . '/config/database.php')) {
    copy(__DIR__ . '/config/database.example.php', __DIR__ . '/config/database.php');
    echo "\n[!] Utworzono config/database.php - EDYTUJ dane dostępowe!\n";
} else {
    echo "\nconfig/database.php istnieje.\n";
}

// 5. Check storage directories
foreach (['storage/imports', 'storage/exports', 'storage/logs'] as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (!is_writable($path)) {
        echo "[!] Katalog {$dir} nie jest zapisywalny. Uruchom: chmod 775 {$path}\n";
    } else {
        echo "  {$dir}: OK (writable)\n";
    }
}

// 6. Generate admin password hash
$defaultPassword = 'Admin123!@#$';
$hash = password_hash($defaultPassword, PASSWORD_BCRYPT, ['cost' => 12]);
echo "\n=== Domyślne hasło admina ===\n";
echo "Login: admin\n";
echo "Hasło: {$defaultPassword}\n";
echo "Hash:  {$hash}\n";
echo "\nZaktualizuj hash w sql/schema.sql lub bezpośrednio w bazie:\n";
echo "UPDATE users SET password_hash = '{$hash}' WHERE username = 'admin';\n";

echo "\n=== Kroki instalacji ===\n";
echo "1. Edytuj config/database.php (dane MySQL)\n";
echo "2. Edytuj config/mail.php (ustawienia SMTP)\n";
echo "3. Edytuj config/app.php (URL, secret_key)\n";
echo "4. Uruchom: composer install\n";
echo "5. Zaimportuj bazę: mysql -u user -p < sql/schema.sql\n";
echo "6. Zaktualizuj hash admina (powyżej)\n";
echo "7. Skonfiguruj Apache/nginx - document root: public/\n";
echo "8. Dodaj cron: 0 8 * * * php /path/to/cron.php\n";
echo "9. USUŃ ten plik: rm install.php\n";
echo "\nGotowe!\n";
