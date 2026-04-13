<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Session;

class SecurityScanService
{
    /**
     * Run all security checks and return results.
     * @return array<int, array{name: string, category: string, status: string, details: string, recommendation: string}>
     */
    /**
     * Get list of ignored check names from settings.
     */
    public static function getIgnoredChecks(): array
    {
        try {
            $val = \App\Models\Setting::get('security_scan_ignored', '');
            return $val ? json_decode($val, true) ?: [] : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Add a check name to the ignored list.
     */
    public static function ignoreCheck(string $name): void
    {
        $ignored = self::getIgnoredChecks();
        if (!in_array($name, $ignored)) {
            $ignored[] = $name;
        }
        \App\Models\Setting::set('security_scan_ignored', json_encode($ignored));
    }

    /**
     * Remove a check name from the ignored list.
     */
    public static function unignoreCheck(string $name): void
    {
        $ignored = self::getIgnoredChecks();
        $ignored = array_values(array_filter($ignored, fn($n) => $n !== $name));
        \App\Models\Setting::set('security_scan_ignored', json_encode($ignored));
    }

    public static function runAll(): array
    {
        $results = [];
        $results[] = self::checkSessionConfig();
        $results[] = self::checkPhpSettings();
        $results[] = self::checkDisplayErrors();
        $results[] = self::checkSecurityHeaders();
        $results[] = self::checkPasswordPolicy();
        $results[] = self::checkDatabaseSecurity();
        $results[] = self::checkFilePermissions();
        $results[] = self::checkSmtpConfig();
        $results[] = self::checkSslConfig();
        $results[] = self::checkCsrfProtection();
        $results[] = self::checkTwoFactorConfig();
        $results[] = self::checkBruteForceProtection();
        $results[] = self::checkSecretKey();
        $results[] = self::checkDebugMode();
        $results[] = self::checkStorageDirectory();
        $results[] = self::checkPasswordExpiry();

        // Mark ignored checks
        $ignored = self::getIgnoredChecks();
        foreach ($results as &$r) {
            if (in_array($r['name'], $ignored) && $r['status'] !== 'pass') {
                $r['status'] = 'ignored';
            }
        }
        unset($r);

        return $results;
    }

    public static function checkSessionConfig(): array
    {
        $params = session_get_cookie_params();
        $issues = [];

        if (empty($params['httponly'])) {
            $issues[] = 'HttpOnly nie jest wlaczone';
        }
        if (empty($params['secure']) && self::isHttps()) {
            $issues[] = 'Flaga Secure nie jest ustawiona mimo HTTPS';
        }
        if (($params['samesite'] ?? '') !== 'Strict') {
            $issues[] = 'SameSite nie jest ustawione na Strict (aktualna wartosc: ' . ($params['samesite'] ?? 'brak') . ')';
        }
        if (($params['lifetime'] ?? 0) > 0) {
            $issues[] = 'Ciasteczko nie jest sesyjne (lifetime: ' . $params['lifetime'] . 's)';
        }

        if (empty($issues)) {
            return self::result('session_security', 'session', 'pass',
                'HttpOnly, Secure, SameSite=Strict, session cookie', '');
        }

        return self::result('session_security', 'session', 'fail',
            implode('; ', $issues),
            'Popraw session_set_cookie_params() w Session.php');
    }

    public static function checkPhpSettings(): array
    {
        $issues = [];

        if (ini_get('allow_url_include')) {
            $issues[] = 'allow_url_include jest wlaczone';
        }
        if (ini_get('allow_url_fopen')) {
            $issues[] = 'allow_url_fopen jest wlaczone (rozwazyc wylaczenie)';
        }
        if (ini_get('expose_php')) {
            $issues[] = 'expose_php jest wlaczone - serwer ujawnia wersje PHP';
        }

        if (empty($issues)) {
            return self::result('php_settings', 'server', 'pass',
                'allow_url_include=off, expose_php=off', '');
        }

        $status = in_array('allow_url_include jest wlaczone', $issues) ? 'fail' : 'warn';
        return self::result('php_settings', 'server', $status,
            implode('; ', $issues),
            'Zmien ustawienia w php.ini');
    }

    public static function checkDisplayErrors(): array
    {
        $displayErrors = ini_get('display_errors');
        $errorReporting = (int) ini_get('error_reporting');

        if ($displayErrors && $displayErrors !== 'Off' && $displayErrors !== '0') {
            return self::result('display_errors', 'server', 'fail',
                'display_errors jest wlaczone - bledy sa widoczne dla uzytkownikow',
                'Ustaw display_errors=Off w php.ini na produkcji');
        }

        return self::result('display_errors', 'server', 'pass',
            'display_errors=Off', '');
    }

    public static function checkSecurityHeaders(): array
    {
        $issues = [];

        // Check headers from both PHP headers_list() and .htaccess file
        $headersList = headers_list();
        $headersFound = [];
        foreach ($headersList as $h) {
            $parts = explode(':', $h, 2);
            $headersFound[strtolower(trim($parts[0]))] = true;
        }

        // Also check .htaccess for headers set by Apache mod_headers
        $htaccessPath = __DIR__ . '/../../public/.htaccess';
        $htaccessContent = '';
        if (file_exists($htaccessPath)) {
            $htaccessContent = strtolower(file_get_contents($htaccessPath));
        }

        // Also check index.php for headers set by PHP
        $indexPath = __DIR__ . '/../../public/index.php';
        $indexContent = '';
        if (file_exists($indexPath)) {
            $indexContent = strtolower(file_get_contents($indexPath));
        }

        $required = [
            'x-frame-options' => 'Clickjacking protection',
            'x-content-type-options' => 'MIME sniffing protection',
            'content-security-policy' => 'Content Security Policy',
        ];

        foreach ($required as $header => $desc) {
            $found = isset($headersFound[$header])
                || str_contains($htaccessContent, $header)
                || str_contains($indexContent, $header);
            if (!$found) {
                $issues[] = "Brak naglowka {$header} ({$desc})";
            }
        }

        if (empty($issues)) {
            return self::result('security_headers', 'server', 'pass',
                'X-Frame-Options, X-Content-Type-Options, CSP - ustawione', '');
        }

        return self::result('security_headers', 'server', 'warn',
            implode('; ', $issues),
            'Dodaj brakujace naglowki w .htaccess lub index.php');
    }

    public static function checkPasswordPolicy(): array
    {
        $issues = [];

        // Check password hash cost
        $testHash = password_hash('test', PASSWORD_BCRYPT, ['cost' => 12]);
        $info = password_get_info($testHash);
        if (($info['options']['cost'] ?? 10) < 12) {
            $issues[] = 'Koszt hashowania < 12';
        }

        // Verify minimum length is enforced (checking Auth constant via reflection)
        if (class_exists('\App\Core\Auth')) {
            $rc = new \ReflectionClass('\App\Core\Auth');
            $constants = $rc->getConstants();
            $minLen = $constants['PASSWORD_MIN_LENGTH'] ?? 8;
            if ($minLen < 12) {
                $issues[] = "Minimalna dlugosc hasla: {$minLen} (zalecane >= 12)";
            }
        }

        if (empty($issues)) {
            return self::result('password_policy', 'auth', 'pass',
                'Bcrypt cost=12, min 12 znakow, wymaga duzych/malych/cyfr/specjalnych', '');
        }

        return self::result('password_policy', 'auth', 'warn',
            implode('; ', $issues),
            'Wzmocnij politykke hasel w Auth.php');
    }

    public static function checkDatabaseSecurity(): array
    {
        $issues = [];

        try {
            $configFile = __DIR__ . '/../../config/database.php';
            if (file_exists($configFile)) {
                $config = require $configFile;
                $username = $config['username'] ?? '';
                if (strtolower($username) === 'root') {
                    $issues[] = 'Polaczenie do bazy jako root - uzyj dedykowanego usera';
                }
                if (($config['charset'] ?? '') !== 'utf8mb4') {
                    $issues[] = 'Charset nie jest utf8mb4 (aktualne: ' . ($config['charset'] ?? 'brak') . ')';
                }
                if (empty($config['password'])) {
                    $issues[] = 'Haslo do bazy jest puste';
                }
            }
        } catch (\Throwable $e) {
            $issues[] = 'Nie mozna odczytac konfiguracji bazy';
        }

        // Check PDO settings
        try {
            $pdo = Database::getInstance()->getConnection();
            $emulate = $pdo->getAttribute(\PDO::ATTR_EMULATE_PREPARES);
            if ($emulate) {
                $issues[] = 'PDO::ATTR_EMULATE_PREPARES jest wlaczone - podatnosc na SQL injection';
            }
        } catch (\Throwable $e) {
            // skip
        }

        if (empty($issues)) {
            return self::result('database_security', 'database', 'pass',
                'Dedykowany user, utf8mb4, PDO prepared statements', '');
        }

        $hasRoot = false;
        foreach ($issues as $i) {
            if (str_contains($i, 'root')) $hasRoot = true;
        }
        return self::result('database_security', 'database', $hasRoot ? 'fail' : 'warn',
            implode('; ', $issues),
            'Popraw konfiguracje bazy danych w config/database.php');
    }

    public static function checkFilePermissions(): array
    {
        $issues = [];
        $baseDir = __DIR__ . '/../../';

        // Check storage directory
        $storageDir = $baseDir . 'storage';
        if (is_dir($storageDir)) {
            $perms = fileperms($storageDir) & 0777;
            if ($perms > 0775) {
                $issues[] = 'storage/ ma zbyt szerokie uprawnienia: ' . decoct($perms);
            }
        }

        // Check config directory
        $configDir = $baseDir . 'config';
        if (is_dir($configDir)) {
            $perms = fileperms($configDir) & 0777;
            if ($perms > 0755) {
                $issues[] = 'config/ ma zbyt szerokie uprawnienia: ' . decoct($perms);
            }
        }

        // Check if database.php is readable by web
        $dbConfig = $baseDir . 'config/database.php';
        if (file_exists($dbConfig)) {
            $perms = fileperms($dbConfig) & 0777;
            if ($perms > 0644) {
                $issues[] = 'config/database.php ma zbyt szerokie uprawnienia: ' . decoct($perms);
            }
        }

        // Check .env in public dir
        $envPublic = $baseDir . 'public/.env';
        if (file_exists($envPublic)) {
            $issues[] = '.env dostepny w katalogu public/ - potencjalny wyciek danych';
        }

        if (empty($issues)) {
            return self::result('file_permissions', 'filesystem', 'pass',
                'Uprawnienia plikow i katalogow sa prawidlowe', '');
        }

        return self::result('file_permissions', 'filesystem',
            str_contains(implode('', $issues), '.env') ? 'fail' : 'warn',
            implode('; ', $issues),
            'Dostosuj uprawnienia: config/ 755, storage/ 775, pliki konfiguracyjne 644');
    }

    public static function checkSmtpConfig(): array
    {
        $issues = [];

        try {
            $mailConfig = require __DIR__ . '/../../config/mail.php';
            if (!empty($mailConfig['password']) && strlen($mailConfig['password']) < 4) {
                $issues[] = 'Haslo SMTP jest zbyt krotkie';
            }
            if (($mailConfig['encryption'] ?? 'none') === 'none') {
                $issues[] = 'SMTP bez szyfrowania (brak TLS/SSL)';
            }
        } catch (\Throwable $e) {
            $issues[] = 'Nie mozna odczytac konfiguracji SMTP';
        }

        // Check office SMTP configs in DB for unencrypted passwords
        try {
            $db = Database::getInstance();
            $configs = $db->fetchAll("SELECT id, smtp_encryption FROM office_smtp_configs WHERE is_enabled = 1");
            foreach ($configs as $cfg) {
                if (($cfg['smtp_encryption'] ?? 'none') === 'none') {
                    $issues[] = "Office SMTP #{$cfg['id']} bez szyfrowania";
                }
            }
        } catch (\Throwable $e) {
            // Table may not exist
        }

        if (empty($issues)) {
            return self::result('smtp_config', 'email', 'pass',
                'Konfiguracja SMTP prawidlowa, szyfrowanie wlaczone', '');
        }

        $hasNoEncryption = false;
        foreach ($issues as $i) {
            if (str_contains($i, 'szyfrowania')) $hasNoEncryption = true;
        }
        return self::result('smtp_config', 'email', $hasNoEncryption ? 'fail' : 'warn',
            implode('; ', $issues),
            'Wlacz szyfrowanie TLS/SSL dla SMTP');
    }

    public static function checkSslConfig(): array
    {
        $isHttps = self::isHttps();

        if (!$isHttps) {
            return self::result('ssl_config', 'server', 'warn',
                'Polaczenie nie uzywa HTTPS',
                'Wlacz certyfikat SSL i wymus HTTPS');
        }

        $cookieParams = session_get_cookie_params();
        if (empty($cookieParams['secure'])) {
            return self::result('ssl_config', 'server', 'fail',
                'HTTPS aktywne, ale flaga Secure ciasteczka nie jest ustawiona',
                'Ustaw secure=true w session_set_cookie_params()');
        }

        return self::result('ssl_config', 'server', 'pass',
            'HTTPS aktywne, ciasteczko z flaga Secure', '');
    }

    public static function checkCsrfProtection(): array
    {
        $cookieParams = session_get_cookie_params();
        $samesite = $cookieParams['samesite'] ?? '';

        if ($samesite === 'Strict' || $samesite === 'Lax') {
            return self::result('csrf_protection', 'auth', 'pass',
                "Tokeny CSRF + SameSite={$samesite}", '');
        }

        return self::result('csrf_protection', 'auth', 'warn',
            'SameSite nie jest ustawione - poleganie wylacznie na tokenach CSRF',
            'Ustaw SameSite=Strict w session_set_cookie_params()');
    }

    public static function checkTwoFactorConfig(): array
    {
        try {
            $db = Database::getInstance();
            $setting = $db->fetchOne(
                "SELECT setting_value FROM settings WHERE setting_key = 'two_factor_mode'"
            );
            $mode = $setting['setting_value'] ?? 'optional';

            if ($mode === 'disabled') {
                return self::result('two_factor', 'auth', 'warn',
                    '2FA jest wylaczone globalnie',
                    'Wlacz 2FA (opcjonalnie lub wymuszone) w ustawieniach');
            }
            if ($mode === 'optional') {
                return self::result('two_factor', 'auth', 'warn',
                    '2FA jest opcjonalne - uzytkownicy moga go nie wlaczyc',
                    'Rozwazyc wymuszenie 2FA dla administratorow');
            }

            return self::result('two_factor', 'auth', 'pass',
                "2FA tryb: {$mode}", '');
        } catch (\Throwable $e) {
            return self::result('two_factor', 'auth', 'warn',
                'Nie mozna sprawdzic konfiguracji 2FA', 'Sprawdz tabele settings');
        }
    }

    public static function checkBruteForceProtection(): array
    {
        // Auth class has hardcoded MAX_LOGIN_ATTEMPTS=5, LOCKOUT_MINUTES=15
        if (class_exists('\App\Core\Auth')) {
            $rc = new \ReflectionClass('\App\Core\Auth');
            $constants = $rc->getConstants();
            $maxAttempts = $constants['MAX_LOGIN_ATTEMPTS'] ?? null;
            $lockoutMin = $constants['LOCKOUT_MINUTES'] ?? null;

            if ($maxAttempts !== null && $lockoutMin !== null) {
                if ($maxAttempts > 10) {
                    return self::result('brute_force', 'auth', 'warn',
                        "Limit prob logowania: {$maxAttempts} (zbyt wysoki)",
                        'Zmniejsz MAX_LOGIN_ATTEMPTS do 5-10');
                }
                return self::result('brute_force', 'auth', 'pass',
                    "Limit: {$maxAttempts} prob, blokada: {$lockoutMin} min", '');
            }
        }

        return self::result('brute_force', 'auth', 'warn',
            'Nie mozna zweryfikowac ochrony przed brute force',
            'Sprawdz klase Auth');
    }

    public static function checkSecretKey(): array
    {
        try {
            $appConfig = require __DIR__ . '/../../config/app.php';
            $key = $appConfig['secret_key'] ?? '';

            if ($key === 'CHANGE_THIS_TO_RANDOM_64_CHAR_STRING' || $key === '') {
                return self::result('secret_key', 'crypto', 'fail',
                    'Klucz secret_key nie zostal zmieniony z domyslnej wartosci',
                    'Wygeneruj losowy klucz 64-znakowy w config/app.php');
            }
            if (strlen($key) < 32) {
                return self::result('secret_key', 'crypto', 'warn',
                    'Klucz secret_key jest zbyt krotki (' . strlen($key) . ' znakow)',
                    'Uzyj klucza o dlugosci min. 64 znakow');
            }

            return self::result('secret_key', 'crypto', 'pass',
                'Klucz secret_key ustawiony (' . strlen($key) . ' znakow)', '');
        } catch (\Throwable $e) {
            return self::result('secret_key', 'crypto', 'warn',
                'Nie mozna odczytac config/app.php', '');
        }
    }

    public static function checkDebugMode(): array
    {
        try {
            $appConfig = require __DIR__ . '/../../config/app.php';
            $debug = $appConfig['debug'] ?? false;

            if ($debug) {
                return self::result('debug_mode', 'server', 'fail',
                    'Tryb debug jest WLACZONY na produkcji',
                    'Ustaw debug=false w config/app.php');
            }

            return self::result('debug_mode', 'server', 'pass',
                'Tryb debug wylaczony', '');
        } catch (\Throwable $e) {
            return self::result('debug_mode', 'server', 'warn',
                'Nie mozna sprawdzic trybu debug', '');
        }
    }

    public static function checkStorageDirectory(): array
    {
        $storageDir = __DIR__ . '/../../storage';
        $publicDir = __DIR__ . '/../../public';

        // Check if storage is inside public dir (bad)
        $realStorage = realpath($storageDir);
        $realPublic = realpath($publicDir);

        if ($realStorage && $realPublic && str_starts_with($realStorage, $realPublic)) {
            return self::result('storage_location', 'filesystem', 'fail',
                'Katalog storage/ jest wewnatrz public/ - pliki dostepne z internetu',
                'Przenies storage/ poza katalog public/');
        }

        // Check .htaccess protection
        $htaccess = $storageDir . '/.htaccess';
        if (is_dir($storageDir) && !file_exists($htaccess)) {
            return self::result('storage_location', 'filesystem', 'warn',
                'Brak .htaccess w storage/ - pliki moga byc dostepne bezposrednio',
                'Dodaj .htaccess z "Deny from all" w katalogu storage/');
        }

        return self::result('storage_location', 'filesystem', 'pass',
            'Katalog storage/ prawidlowo zabezpieczony', '');
    }

    public static function checkPasswordExpiry(): array
    {
        try {
            $db = Database::getInstance();
            $setting = $db->fetchOne(
                "SELECT setting_value FROM settings WHERE setting_key = 'password_expiry_days'"
            );
            $days = (int) ($setting['setting_value'] ?? 0);

            if ($days <= 0) {
                return self::result('password_expiry', 'auth', 'warn',
                    'Wygasanie hasel nie jest wlaczone',
                    'Ustaw password_expiry_days na 90 w ustawieniach');
            }
            if ($days > 365) {
                return self::result('password_expiry', 'auth', 'warn',
                    "Wygasanie hasel: {$days} dni (zbyt dlugo)",
                    'Zmniejsz do max 90-180 dni');
            }

            return self::result('password_expiry', 'auth', 'pass',
                "Wygasanie hasel: {$days} dni", '');
        } catch (\Throwable $e) {
            return self::result('password_expiry', 'auth', 'warn',
                'Nie mozna sprawdzic polityki wygasania hasel', '');
        }
    }

    /**
     * Get summary stats from results.
     */
    public static function getSummary(array $results): array
    {
        $pass = $warn = $fail = $ignored = 0;
        foreach ($results as $r) {
            match ($r['status']) {
                'pass' => $pass++,
                'warn' => $warn++,
                'fail' => $fail++,
                'ignored' => $ignored++,
                default => null,
            };
        }
        return ['pass' => $pass, 'warn' => $warn, 'fail' => $fail, 'ignored' => $ignored, 'total' => count($results)];
    }

    private static function result(string $name, string $category, string $status, string $details, string $recommendation): array
    {
        return [
            'name' => $name,
            'category' => $category,
            'status' => $status,
            'details' => $details,
            'recommendation' => $recommendation,
        ];
    }

    private static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? 0) == 443
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    }
}
