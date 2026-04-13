#!/bin/bash

#=====================================================
# BiLLU v2.2 - Pełna reinstalacja
# Uruchom jako root:
#   chmod +x reinstall.sh && ./reinstall.sh
#=====================================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo ""
echo -e "${BLUE}╔═══════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   BiLLU v2.2 - Pełna reinstalacja              ║${NC}"
echo -e "${BLUE}╚═══════════════════════════════════════════════════╝${NC}"
echo ""

# ── Sprawdzenie root ──────────────────────────────────
if [[ "$EUID" -ne 0 ]]; then
    echo -e "${RED}Ten skrypt wymaga uprawnień root!${NC}"
    echo "Uruchom: sudo ./reinstall.sh"
    exit 1
fi

# ── Konfiguracja domeny ───────────────────────────────
DEFAULT_DOMAIN="flamboyant-jones.46-242-128-26.plesk.page"
read -p "Domena [$DEFAULT_DOMAIN]: " DOMAIN
DOMAIN=${DOMAIN:-$DEFAULT_DOMAIN}

# Wykryj katalog httpdocs
if [[ -d "/var/www/vhosts/${DOMAIN}/httpdocs" ]]; then
    HTTPDOCS="/var/www/vhosts/${DOMAIN}/httpdocs"
elif [[ -d "/var/www/vhosts/${DOMAIN}" ]]; then
    HTTPDOCS="/var/www/vhosts/${DOMAIN}"
else
    echo -e "${YELLOW}Nie znaleziono katalogu dla domeny ${DOMAIN}${NC}"
    read -p "Podaj pełną ścieżkę do httpdocs: " HTTPDOCS
fi

echo -e "Katalog: ${GREEN}${HTTPDOCS}${NC}"

if [[ ! -d "$HTTPDOCS" ]]; then
    echo -e "${RED}Katalog $HTTPDOCS nie istnieje!${NC}"
    exit 1
fi

# ── Wykryj PHP ────────────────────────────────────────
PHP_BIN=""
for V in 8.3 8.2 8.1; do
    if [[ -x "/opt/plesk/php/${V}/bin/php" ]]; then
        PHP_BIN="/opt/plesk/php/${V}/bin/php"
        break
    fi
done

if [[ -z "$PHP_BIN" ]] && command -v php &>/dev/null; then
    PHP_BIN=$(which php)
fi

if [[ -z "$PHP_BIN" ]]; then
    echo -e "${RED}PHP nie znalezione!${NC}"
    echo "Dostępne:"
    ls /opt/plesk/php/*/bin/php 2>/dev/null || echo "  brak"
    read -p "Podaj ścieżkę do PHP: " PHP_BIN
fi

PHP_VER=$($PHP_BIN -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;" 2>/dev/null)
echo -e "PHP: ${GREEN}${PHP_VER}${NC} (${PHP_BIN})"

# Sprawdź rozszerzenia
echo "Sprawdzanie rozszerzeń PHP..."
MISSING=""
for EXT in pdo pdo_mysql mbstring gd zip dom xml; do
    if $PHP_BIN -m 2>/dev/null | grep -qi "^${EXT}$"; then
        echo -e "  ${EXT}: ${GREEN}OK${NC}"
    else
        echo -e "  ${EXT}: ${RED}BRAK${NC}"
        MISSING="$MISSING $EXT"
    fi
done

if [[ -n "$MISSING" ]]; then
    echo -e "${RED}Brakujące rozszerzenia:${MISSING}${NC}"
    echo "Włącz je w Plesk -> domena -> PHP Settings"
    read -p "Kontynuować? (t/n): " CONT
    [[ "$CONT" != "t" && "$CONT" != "T" ]] && exit 1
fi

# ── Backup starej konfiguracji bazy ───────────────────
echo ""
echo -e "${BLUE}[1/8] Backup konfiguracji...${NC}"

OLD_DB_HOST="" OLD_DB_PORT="" OLD_DB_NAME="" OLD_DB_USER="" OLD_DB_PASS=""
if [[ -f "${HTTPDOCS}/config/database.php" ]]; then
    OLD_DB_HOST=$($PHP_BIN -r "\$c = require '${HTTPDOCS}/config/database.php'; echo \$c['host'] ?? '';" 2>/dev/null)
    OLD_DB_PORT=$($PHP_BIN -r "\$c = require '${HTTPDOCS}/config/database.php'; echo \$c['port'] ?? '3306';" 2>/dev/null)
    OLD_DB_NAME=$($PHP_BIN -r "\$c = require '${HTTPDOCS}/config/database.php'; echo \$c['database'] ?? '';" 2>/dev/null)
    OLD_DB_USER=$($PHP_BIN -r "\$c = require '${HTTPDOCS}/config/database.php'; echo \$c['username'] ?? '';" 2>/dev/null)
    OLD_DB_PASS=$($PHP_BIN -r "\$c = require '${HTTPDOCS}/config/database.php'; echo \$c['password'] ?? '';" 2>/dev/null)
    echo -e "  Znaleziono starą konfigurację: DB=${GREEN}${OLD_DB_NAME}${NC} User=${GREEN}${OLD_DB_USER}${NC}"
fi

# ── Dane bazy danych ──────────────────────────────────
echo ""
echo -e "${BLUE}[2/8] Konfiguracja bazy danych...${NC}"
echo ""

read -p "  Host bazy [${OLD_DB_HOST:-localhost}]: " DB_HOST
DB_HOST=${DB_HOST:-${OLD_DB_HOST:-localhost}}

read -p "  Port [${OLD_DB_PORT:-3306}]: " DB_PORT
DB_PORT=${DB_PORT:-${OLD_DB_PORT:-3306}}

read -p "  Nazwa bazy [${OLD_DB_NAME:-faktury_ksef}]: " DB_NAME
DB_NAME=${DB_NAME:-${OLD_DB_NAME:-faktury_ksef}}

read -p "  Użytkownik [${OLD_DB_USER}]: " DB_USER
DB_USER=${DB_USER:-$OLD_DB_USER}
while [[ -z "$DB_USER" ]]; do
    read -p "  Użytkownik (wymagany): " DB_USER
done

if [[ -n "$OLD_DB_PASS" ]]; then
    read -sp "  Hasło bazy [Enter = stare hasło]: " DB_PASS
    echo ""
    DB_PASS=${DB_PASS:-$OLD_DB_PASS}
else
    read -sp "  Hasło bazy: " DB_PASS
    echo ""
    while [[ -z "$DB_PASS" ]]; do
        read -sp "  Hasło (wymagane): " DB_PASS
        echo ""
    done
fi

# Test połączenia
echo "  Test połączenia..."
DB_TEST=$($PHP_BIN -r "
try {
    new PDO('mysql:host=${DB_HOST};port=${DB_PORT}', '${DB_USER}', '${DB_PASS}');
    echo 'OK';
} catch (Exception \$e) {
    echo 'ERR: ' . \$e->getMessage();
}" 2>&1)

if [[ "$DB_TEST" == "OK" ]]; then
    echo -e "  ${GREEN}Połączenie OK${NC}"
else
    echo -e "  ${RED}${DB_TEST}${NC}"
    read -p "  Kontynuować? (t/n): " CONT
    [[ "$CONT" != "t" && "$CONT" != "T" ]] && exit 1
fi

# ── Hasło admina ──────────────────────────────────────
echo ""
echo -e "${BLUE}[3/8] Hasło administratora...${NC}"
echo -e "${YELLOW}Min. 12 znaków, wielkie+małe litery, cyfry, znaki specjalne${NC}"
echo ""

VALID=0
while [[ "$VALID" == "0" ]]; do
    read -sp "  Hasło admina: " ADMIN_PASS
    echo ""
    [[ ${#ADMIN_PASS} -lt 12 ]]               && echo -e "  ${RED}Min. 12 znaków!${NC}" && continue
    ! [[ "$ADMIN_PASS" =~ [a-z] ]]            && echo -e "  ${RED}Brak małej litery!${NC}" && continue
    ! [[ "$ADMIN_PASS" =~ [A-Z] ]]            && echo -e "  ${RED}Brak wielkiej litery!${NC}" && continue
    ! [[ "$ADMIN_PASS" =~ [0-9] ]]            && echo -e "  ${RED}Brak cyfry!${NC}" && continue
    ! [[ "$ADMIN_PASS" =~ [^a-zA-Z0-9] ]]     && echo -e "  ${RED}Brak znaku specjalnego!${NC}" && continue
    read -sp "  Powtórz hasło: " ADMIN_PASS2
    echo ""
    [[ "$ADMIN_PASS" != "$ADMIN_PASS2" ]]      && echo -e "  ${RED}Hasła różne!${NC}" && continue
    VALID=1
done

read -p "  Email admina: " ADMIN_EMAIL
while [[ -z "$ADMIN_EMAIL" ]]; do
    read -p "  Email (wymagany): " ADMIN_EMAIL
done

# ── SMTP ──────────────────────────────────────────────
echo ""
echo -e "${BLUE}[4/8] Konfiguracja SMTP...${NC}"
read -p "  Skonfigurować SMTP teraz? (t/n): " SETUP_MAIL

SMTP_HOST="" SMTP_PORT="587" SMTP_ENC="tls" SMTP_USER="" SMTP_PASS="" SMTP_FROM="" SMTP_NAME="BiLLU"
if [[ "$SETUP_MAIL" == "t" || "$SETUP_MAIL" == "T" ]]; then
    read -p "  SMTP host: " SMTP_HOST
    read -p "  SMTP port [587]: " SMTP_PORT
    SMTP_PORT=${SMTP_PORT:-587}
    read -p "  Szyfrowanie (tls/ssl) [tls]: " SMTP_ENC
    SMTP_ENC=${SMTP_ENC:-tls}
    read -p "  SMTP login: " SMTP_USER
    read -sp "  SMTP hasło: " SMTP_PASS
    echo ""
    read -p "  Email nadawcy [${SMTP_USER}]: " SMTP_FROM
    SMTP_FROM=${SMTP_FROM:-$SMTP_USER}
    read -p "  Nazwa nadawcy [BiLLU]: " SMTP_NAME
    SMTP_NAME=${SMTP_NAME:-BiLLU}
fi

# ── URL ───────────────────────────────────────────────
DEFAULT_URL="https://${DOMAIN}"
read -p "  URL systemu [${DEFAULT_URL}]: " APP_URL
APP_URL=${APP_URL:-$DEFAULT_URL}
APP_URL=${APP_URL%/}

# ── Potwierdzenie ─────────────────────────────────────
echo ""
echo -e "${YELLOW}══════════ PODSUMOWANIE ══════════${NC}"
echo -e "  Domena:     ${GREEN}${DOMAIN}${NC}"
echo -e "  Katalog:    ${GREEN}${HTTPDOCS}${NC}"
echo -e "  Baza:       ${GREEN}${DB_NAME}${NC} @ ${DB_HOST}"
echo -e "  User DB:    ${GREEN}${DB_USER}${NC}"
echo -e "  Admin:      ${GREEN}admin${NC} / ${ADMIN_EMAIL}"
echo -e "  URL:        ${GREEN}${APP_URL}${NC}"
echo -e "  SMTP:       ${GREEN}${SMTP_HOST:-NIE SKONFIGUROWANY}${NC}"
echo ""
echo -e "${RED}UWAGA: Stare pliki i baza danych zostaną USUNIĘTE!${NC}"
read -p "Kontynuować instalację? (t/n): " FINAL
if [[ "$FINAL" != "t" && "$FINAL" != "T" ]]; then
    echo "Przerwano."
    exit 0
fi

echo ""
echo -e "${BLUE}═══════════════════════════════════════════${NC}"
echo -e "${BLUE}  ROZPOCZYNAM INSTALACJĘ...                ${NC}"
echo -e "${BLUE}═══════════════════════════════════════════${NC}"
echo ""

# ── Usuwanie starej bazy ──────────────────────────────
echo -e "${BLUE}[5/8] Reset bazy danych...${NC}"

$PHP_BIN -r "
try {
    \$pdo = new PDO('mysql:host=${DB_HOST};port=${DB_PORT}', '${DB_USER}', '${DB_PASS}');
    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    \$pdo->exec('DROP DATABASE IF EXISTS \`${DB_NAME}\`');
    \$pdo->exec('CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci');
    echo 'OK';
} catch (Exception \$e) {
    echo 'ERR: ' . \$e->getMessage();
    exit(1);
}" 2>&1

echo -e "  ${GREEN}Baza ${DB_NAME} utworzona na czysto.${NC}"

# ── Usuwanie starych plików ───────────────────────────
echo -e "${BLUE}[6/8] Pobieranie nowej wersji...${NC}"

cd "$HTTPDOCS"

# Zachowaj skrypt
cp "$0" /tmp/_reinstall_backup.sh 2>/dev/null || true

# Wyczyść katalog
find . -mindepth 1 -maxdepth 1 -exec rm -rf {} + 2>/dev/null || true

# Pobierz z GitHub
if command -v git &>/dev/null; then
    git config --global --add safe.directory "$HTTPDOCS" 2>/dev/null || true
    git config --global --add safe.directory "${HTTPDOCS}/tmp_repo" 2>/dev/null || true
    git clone --depth 1 --branch claude/invoice-verification-system-E8wzz \
        https://github.com/PrzemekPrzemo/Faktury.git tmp_repo 2>&1
    shopt -s dotglob
    mv tmp_repo/* . 2>/dev/null || true
    shopt -u dotglob
    rm -rf tmp_repo
else
    echo "  Git niedostępny, pobieram ZIP..."
    curl -sL -o repo.zip "https://github.com/PrzemekPrzemo/Faktury/archive/refs/heads/claude/invoice-verification-system-E8wzz.zip"
    unzip -qo repo.zip
    DIR=$(ls -d Faktury-* 2>/dev/null | head -1)
    if [[ -n "$DIR" ]]; then
        shopt -s dotglob
        mv "$DIR"/* . 2>/dev/null || true
        shopt -u dotglob
        rm -rf "$DIR"
    fi
    rm -f repo.zip
fi

echo -e "  ${GREEN}Pliki pobrane.${NC}"

# ── Composer ──────────────────────────────────────────
echo -e "${BLUE}[7/8] Composer install...${NC}"

# Zawsze używaj composer.phar z właściwą wersją PHP (Plesk PHP 8.3, nie systemowe 7.4)
curl -sS https://getcomposer.org/installer | $PHP_BIN -- --quiet 2>/dev/null
COMPOSER_ALLOW_SUPERUSER=1 $PHP_BIN composer.phar install --no-dev --optimize-autoloader --no-interaction 2>&1
echo -e "  ${GREEN}Zależności zainstalowane.${NC}"

# ── Konfiguracja i import ─────────────────────────────
echo -e "${BLUE}[8/8] Konfiguracja systemu...${NC}"

# config/database.php
cat > config/database.php << DBEOF
<?php

return [
    'host'     => '${DB_HOST}',
    'port'     => ${DB_PORT},
    'database' => '${DB_NAME}',
    'username' => '${DB_USER}',
    'password' => '${DB_PASS}',
    'charset'  => 'utf8mb4',
];
DBEOF

# config/app.php
SECRET_KEY=$($PHP_BIN -r "echo bin2hex(random_bytes(32));")
cat > config/app.php << APPEOF
<?php

return [
    'name'       => 'BiLLU',
    'url'        => '${APP_URL}',
    'debug'      => false,
    'timezone'   => 'Europe/Warsaw',
    'locale'     => 'pl',
    'storage'    => __DIR__ . '/../storage',
    'secret_key' => '${SECRET_KEY}',
];
APPEOF

# config/mail.php
cat > config/mail.php << MAILEOF
<?php

return [
    'host'       => '${SMTP_HOST}',
    'port'       => ${SMTP_PORT},
    'encryption' => '${SMTP_ENC}',
    'username'   => '${SMTP_USER}',
    'password'   => '${SMTP_PASS}',
    'from_email' => '${SMTP_FROM}',
    'from_name'  => '${SMTP_NAME}',
];
MAILEOF

# Import bazy danych
echo "  Import schema.sql..."
$PHP_BIN -r "
try {
    \$pdo = new PDO('mysql:host=${DB_HOST};port=${DB_PORT};dbname=${DB_NAME};charset=utf8mb4', '${DB_USER}', '${DB_PASS}');
    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    \$sql = file_get_contents('sql/schema.sql');
    // Usuń blok nagłówkowy (SET, CREATE DATABASE, USE) - wszystko przed pierwszym CREATE TABLE
    \$pos = strpos(\$sql, 'CREATE TABLE');
    if (\$pos !== false) {
        \$sql = substr(\$sql, \$pos);
    }
    // Wykonaj cały SQL jednym exec() - MySQL PDO obsługuje multi-statement
    \$pdo->exec(\$sql);
    echo 'IMPORT_OK';
} catch (Exception \$e) {
    echo 'IMPORT_ERR: ' . \$e->getMessage();
    exit(1);
}" 2>&1

echo -e "  ${GREEN}Baza zaimportowana.${NC}"

# Hasło admina
ADMIN_HASH=$($PHP_BIN -r "echo password_hash('${ADMIN_PASS}', PASSWORD_BCRYPT, ['cost' => 12]);")
$PHP_BIN -r "
\$pdo = new PDO('mysql:host=${DB_HOST};port=${DB_PORT};dbname=${DB_NAME};charset=utf8mb4', '${DB_USER}', '${DB_PASS}');
\$stmt = \$pdo->prepare('UPDATE users SET password_hash = ?, email = ? WHERE username = ?');
\$stmt->execute(['${ADMIN_HASH}', '${ADMIN_EMAIL}', 'admin']);" 2>&1

echo -e "  ${GREEN}Konto admina skonfigurowane.${NC}"

# Storage + uprawnienia
mkdir -p storage/imports storage/exports storage/logs storage/jpk

# Wykryj użytkownika Plesk dla tej domeny
VHOST_USER=$(stat -c '%U' "$HTTPDOCS" 2>/dev/null || echo "")
if [[ -z "$VHOST_USER" || "$VHOST_USER" == "root" ]]; then
    # Próbuj odczytać z Plesk
    VHOST_USER=$(ls -ld "$HTTPDOCS/.." 2>/dev/null | awk '{print $3}')
fi

if [[ -n "$VHOST_USER" && "$VHOST_USER" != "root" ]]; then
    chown -R ${VHOST_USER}:psacln storage/ 2>/dev/null || true
    chown -R ${VHOST_USER}:psacln config/ 2>/dev/null || true
    echo -e "  Owner: ${GREEN}${VHOST_USER}${NC}"
fi
chmod -R 775 storage/

# Sprzątanie
rm -f install.php install_faktury.sh composer.phar 2>/dev/null

# ── Konfiguracja Plesk przez CLI ──────────────────────
echo ""
echo -e "${BLUE}Konfiguracja Plesk...${NC}"

# Document root -> public/
if command -v plesk &>/dev/null; then
    echo "  Ustawiam Document root na /public/..."
    plesk bin site --update "${DOMAIN}" -document_root "${HTTPDOCS}/public" 2>/dev/null && \
        echo -e "  ${GREEN}Document root ustawiony automatycznie.${NC}" || \
        echo -e "  ${YELLOW}Nie udało się automatycznie. Ustaw ręcznie (patrz niżej).${NC}"
else
    echo -e "  ${YELLOW}Plesk CLI niedostępny. Document root ustaw ręcznie.${NC}"
fi

# Nginx directives
NGINX_CONF="location ~ /\\.(env|git|htaccess) {
    deny all;
    return 404;
}

location ~ /(config|src|sql|storage|templates|lang|vendor)/ {
    deny all;
    return 404;
}

location ~ /(composer\\.json|composer\\.lock|cron\\.php) {
    deny all;
    return 404;
}"

if command -v plesk &>/dev/null; then
    echo "  Dodaję dyrektywy nginx..."
    plesk bin site --update "${DOMAIN}" -nginx-additional-directives "${NGINX_CONF}" 2>/dev/null && \
        echo -e "  ${GREEN}Dyrektywy nginx dodane automatycznie.${NC}" || \
        echo -e "  ${YELLOW}Nie udało się automatycznie. Dodaj ręcznie (patrz niżej).${NC}"
fi

# ── Cron ──────────────────────────────────────────────
CRON_CMD="${PHP_BIN} ${HTTPDOCS}/cron.php"
echo ""
echo -e "${YELLOW}══════ CRON JOB ══════${NC}"
echo "Dodaj w Plesk -> Scheduled Tasks:"
echo ""
echo -e "  Komenda:  ${GREEN}${CRON_CMD}${NC}"
echo -e "  Schemat:  ${GREEN}0 8 * * *${NC}"
echo ""

# ── Konfiguracja ręczna jeśli Plesk CLI nie zadziałał ─
echo -e "${YELLOW}══════ JEŚLI TRZEBA USTAWIĆ RĘCZNIE ══════${NC}"
echo ""
echo "1. Document root:"
echo -e "   Plesk -> domena -> Hosting Settings"
echo -e "   Document root: ${GREEN}${HTTPDOCS}/public${NC}"
echo ""
echo "2. Dyrektywy nginx:"
echo "   Plesk -> domena -> Apache & nginx Settings"
echo "   -> Additional nginx directives:"
echo -e "${GREEN}${NGINX_CONF}${NC}"
echo ""

# ── GOTOWE ────────────────────────────────────────────
echo -e "${BLUE}╔═══════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║          INSTALACJA ZAKOŃCZONA!                   ║${NC}"
echo -e "${BLUE}╚═══════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "  URL:     ${GREEN}${APP_URL}${NC}"
echo -e "  Login:   ${GREEN}admin${NC}"
echo -e "  Email:   ${GREEN}${ADMIN_EMAIL}${NC}"
echo ""
echo -e "  Baza:    ${GREEN}${DB_NAME}${NC}"
echo -e "  PHP:     ${GREEN}${PHP_BIN}${NC}"
echo -e "  Storage: ${GREEN}${HTTPDOCS}/storage/${NC}"
echo ""
if [[ "$SETUP_MAIL" != "t" && "$SETUP_MAIL" != "T" ]]; then
    echo -e "${YELLOW}Pamiętaj skonfigurować SMTP w: ${HTTPDOCS}/config/mail.php${NC}"
    echo ""
fi
echo -e "Otwórz ${GREEN}${APP_URL}${NC} i zaloguj się."
echo ""
