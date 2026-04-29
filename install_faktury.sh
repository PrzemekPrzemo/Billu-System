#!/bin/bash

#=====================================================
# BiLLU Financial Solutions - Skrypt instalacyjny
#
# Domena docelowa: portal.billu.pl
#
# Użycie:
#   1. Wgraj ten plik do katalogu httpdocs domeny
#   2. Połącz się przez SSH
#   3. cd /var/www/vhosts/portal.billu.pl/httpdocs
#   4. chmod +x install_faktury.sh
#   5. ./install_faktury.sh
#=====================================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

DOMAIN="portal.billu.pl"
INSTALL_DIR=$(pwd)

echo ""
echo -e "${BLUE}╔═══════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   BiLLU Financial Solutions               ║${NC}"
echo -e "${BLUE}║   Instalacja systemu                      ║${NC}"
echo -e "${BLUE}║   Domena: portal.billu.pl                 ║${NC}"
echo -e "${BLUE}╚═══════════════════════════════════════════╝${NC}"
echo ""
echo -e "Katalog instalacji: ${YELLOW}${INSTALL_DIR}${NC}"
echo -e "Domena:             ${YELLOW}${DOMAIN}${NC}"
echo ""

# ── Sprawdzenie czy jesteśmy w httpdocs ──────────────
if [[ ! "$INSTALL_DIR" == *"httpdocs"* ]]; then
    echo -e "${RED}UWAGA: Nie jesteś w katalogu httpdocs!${NC}"
    echo "Oczekiwany katalog: /var/www/vhosts/${DOMAIN}/httpdocs"
    echo "Aktualny katalog: $INSTALL_DIR"
    read -p "Czy na pewno chcesz kontynuować? (t/n): " CONFIRM
    if [[ "$CONFIRM" != "t" && "$CONFIRM" != "T" ]]; then
        echo "Przerwano."
        exit 1
    fi
fi

# ── Sprawdzenie wymagań ──────────────────────────────
echo -e "${BLUE}[1/9] Sprawdzanie wymagań...${NC}"

# PHP
if ! command -v php &> /dev/null; then
    echo -e "${RED}BŁĄD: PHP nie jest dostępne w CLI${NC}"
    echo "Spróbuj: /opt/plesk/php/8.3/bin/php"
    read -p "Podaj pełną ścieżkę do PHP (lub Enter aby przerwać): " PHP_PATH
    if [[ -z "$PHP_PATH" ]]; then
        exit 1
    fi
    PHP_BIN="$PHP_PATH"
else
    PHP_BIN=$(which php)
fi

PHP_VERSION=$($PHP_BIN -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
echo -e "  PHP: ${GREEN}$PHP_VERSION${NC} ($PHP_BIN)"

if [[ $(echo "$PHP_VERSION < 8.1" | bc -l 2>/dev/null || echo "0") == "1" ]]; then
    echo -e "${RED}BŁĄD: Wymagane PHP >= 8.1${NC}"
    echo "Dostępne wersje PHP na serwerze:"
    ls /opt/plesk/php/*/bin/php 2>/dev/null || echo "Brak"
    exit 1
fi

# Rozszerzenia PHP
echo "  Sprawdzanie rozszerzeń PHP..."
MISSING_EXT=""
for EXT in pdo pdo_mysql mbstring gd zip; do
    if $PHP_BIN -m 2>/dev/null | grep -qi "^${EXT}$"; then
        echo -e "    $EXT: ${GREEN}OK${NC}"
    else
        echo -e "    $EXT: ${RED}BRAK${NC}"
        MISSING_EXT="$MISSING_EXT $EXT"
    fi
done

if [[ -n "$MISSING_EXT" ]]; then
    echo -e "${YELLOW}UWAGA: Brakujące rozszerzenia:${MISSING_EXT}${NC}"
    echo "Włącz je w Plesk -> domena -> PHP Settings -> PHP extensions"
    read -p "Czy kontynuować mimo to? (t/n): " CONFIRM
    if [[ "$CONFIRM" != "t" && "$CONFIRM" != "T" ]]; then
        exit 1
    fi
fi

# Git
if command -v git &> /dev/null; then
    echo -e "  git: ${GREEN}OK${NC}"
    HAS_GIT=1
else
    echo -e "  git: ${YELLOW}BRAK - pliki zostaną pobrane jako ZIP${NC}"
    HAS_GIT=0
fi

# ── Czyszczenie katalogu instalacji ──────────────────
echo ""
echo -e "${BLUE}[2/9] Czyszczenie katalogu instalacji...${NC}"

FILE_COUNT=$(ls -A | grep -v "install_faktury.sh" | wc -l)
if [[ "$FILE_COUNT" -gt 0 ]]; then
    echo -e "${YELLOW}Znaleziono ${FILE_COUNT} plików/folderów w katalogu instalacji.${NC}"
    echo -e "${YELLOW}Wszystkie istniejące pliki zostaną usunięte przed instalacją.${NC}"
    read -p "Kontynuować? (t/n): " CONFIRM
    if [[ "$CONFIRM" != "t" && "$CONFIRM" != "T" ]]; then
        echo "Przerwano."
        exit 1
    fi
    echo "  Usuwanie istniejących plików..."
    find . -mindepth 1 -maxdepth 1 ! -name "install_faktury.sh" -exec rm -rf {} +
    echo -e "  ${GREEN}Katalog wyczyszczony.${NC}"
else
    echo -e "  ${GREEN}Katalog pusty - OK.${NC}"
fi

# ── Pobieranie plików ────────────────────────────────
echo ""
echo -e "${BLUE}[3/9] Pobieranie plików aplikacji...${NC}"

if [[ "$HAS_GIT" == "1" ]]; then
    git clone -b rebranding https://github.com/PrzemekPrzemo/Faktury.git tmp_repo
    cd tmp_repo
    cd ..
    # Przenieś pliki (włącznie z ukrytymi, ale bez .git)
    shopt -s dotglob
    mv tmp_repo/* . 2>/dev/null || true
    shopt -u dotglob
    rm -rf tmp_repo/.git tmp_repo
else
    echo "Pobieranie ZIP z GitHub..."
    curl -L -o repo.zip "https://github.com/PrzemekPrzemo/Faktury/archive/refs/heads/rebranding.zip"
    unzip -o repo.zip
    EXTRACTED_DIR=$(ls -d Faktury-* 2>/dev/null | head -1)
    if [[ -n "$EXTRACTED_DIR" ]]; then
        shopt -s dotglob
        mv "$EXTRACTED_DIR"/* . 2>/dev/null || true
        shopt -u dotglob
        rm -rf "$EXTRACTED_DIR"
    fi
    rm -f repo.zip
fi

echo -e "  ${GREEN}Pliki pobrane.${NC}"

# ── Composer ─────────────────────────────────────────
echo ""
echo -e "${BLUE}[4/9] Instalacja zależności Composer...${NC}"

if ! command -v composer &> /dev/null; then
    echo "  Composer nie znaleziony. Instaluję lokalnie..."
    curl -sS https://getcomposer.org/installer | $PHP_BIN
    COMPOSER_BIN="$PHP_BIN composer.phar"
else
    COMPOSER_BIN="composer"
fi

$COMPOSER_BIN install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction 2>&1
echo -e "  ${GREEN}Zależności zainstalowane.${NC}"

# ── Konfiguracja bazy danych ─────────────────────────
echo ""
echo -e "${BLUE}[5/9] Konfiguracja bazy danych...${NC}"
echo ""
echo "Podaj dane bazy MySQL (z Plesk -> Databases):"
echo ""

read -p "  Host bazy danych [localhost]: " DB_HOST
DB_HOST=${DB_HOST:-localhost}

read -p "  Port [3306]: " DB_PORT
DB_PORT=${DB_PORT:-3306}

read -p "  Nazwa bazy danych [billu]: " DB_NAME
DB_NAME=${DB_NAME:-billu}

read -p "  Użytkownik bazy: " DB_USER
while [[ -z "$DB_USER" ]]; do
    echo -e "${RED}  Użytkownik jest wymagany!${NC}"
    read -p "  Użytkownik bazy: " DB_USER
done

read -sp "  Hasło bazy: " DB_PASS
echo ""
while [[ -z "$DB_PASS" ]]; do
    echo -e "${RED}  Hasło jest wymagane!${NC}"
    read -sp "  Hasło bazy: " DB_PASS
    echo ""
done

# Test połączenia
echo "  Testowanie połączenia z bazą..."
DB_TEST=$(DB_HOST_ENV="$DB_HOST" DB_PORT_ENV="$DB_PORT" DB_USER_ENV="$DB_USER" DB_PASS_ENV="$DB_PASS" $PHP_BIN -r "
try {
    new PDO('mysql:host=' . getenv('DB_HOST_ENV') . ';port=' . getenv('DB_PORT_ENV'), getenv('DB_USER_ENV'), getenv('DB_PASS_ENV'));
    echo 'OK';
} catch (Exception \$e) {
    echo 'ERROR: ' . \$e->getMessage();
}
" 2>&1)

if [[ "$DB_TEST" == "OK" ]]; then
    echo -e "  ${GREEN}Połączenie OK${NC}"
else
    echo -e "  ${RED}$DB_TEST${NC}"
    read -p "  Kontynuować mimo błędu? (t/n): " CONFIRM
    if [[ "$CONFIRM" != "t" && "$CONFIRM" != "T" ]]; then
        exit 1
    fi
fi

# Zapisz config/database.php
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

echo -e "  ${GREEN}config/database.php zapisany.${NC}"

# ── Czyszczenie bazy danych ──────────────────────────
echo ""
echo -e "${BLUE}[6/9] Czyszczenie bazy danych...${NC}"

# Sprawdź czy baza istnieje, jeśli nie - utwórz
DB_HOST_ENV="$DB_HOST" DB_PORT_ENV="$DB_PORT" DB_NAME_ENV="$DB_NAME" DB_USER_ENV="$DB_USER" DB_PASS_ENV="$DB_PASS" $PHP_BIN -r "
try {
    \$pdo = new PDO('mysql:host=' . getenv('DB_HOST_ENV') . ';port=' . getenv('DB_PORT_ENV'), getenv('DB_USER_ENV'), getenv('DB_PASS_ENV'));
    \$pdo->exec('CREATE DATABASE IF NOT EXISTS \`' . getenv('DB_NAME_ENV') . '\` CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci');
    echo 'DB_OK';
} catch (Exception \$e) {
    echo 'DB_ERR: ' . \$e->getMessage();
}
" 2>&1

# Usunięcie istniejących tabel z bazy
echo "  Usuwanie istniejących tabel..."
DROP_RESULT=$(DB_HOST_ENV="$DB_HOST" DB_PORT_ENV="$DB_PORT" DB_NAME_ENV="$DB_NAME" DB_USER_ENV="$DB_USER" DB_PASS_ENV="$DB_PASS" $PHP_BIN -r "
try {
    \$pdo = new PDO('mysql:host=' . getenv('DB_HOST_ENV') . ';port=' . getenv('DB_PORT_ENV') . ';dbname=' . getenv('DB_NAME_ENV') . ';charset=utf8mb4', getenv('DB_USER_ENV'), getenv('DB_PASS_ENV'));
    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    \$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    \$tables = \$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    \$count = 0;
    foreach (\$tables as \$table) {
        \$pdo->exec('DROP TABLE IF EXISTS \`' . \$table . '\`');
        \$count++;
    }
    \$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    echo \"DROPPED:\$count\";
} catch (Exception \$e) {
    echo 'DROP_ERR: ' . \$e->getMessage();
}
" 2>&1)

if [[ "$DROP_RESULT" == DROPPED:* ]]; then
    TABLE_COUNT=${DROP_RESULT#DROPPED:}
    if [[ "$TABLE_COUNT" -gt 0 ]]; then
        echo -e "  ${GREEN}Usunięto ${TABLE_COUNT} tabel.${NC}"
    else
        echo -e "  ${GREEN}Baza pusta - OK.${NC}"
    fi
else
    echo -e "  ${YELLOW}${DROP_RESULT}${NC}"
fi

# ── Import bazy ──────────────────────────────────────
echo ""
echo -e "${BLUE}[7/9] Import struktury bazy danych...${NC}"

if command -v mysql &> /dev/null; then
    mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < sql/schema.sql 2>&1
    echo -e "  ${GREEN}Baza danych zaimportowana.${NC}"
else
    echo -e "  ${YELLOW}Polecenie 'mysql' niedostępne. Importuję przez PHP...${NC}"
    DB_HOST_ENV="$DB_HOST" DB_PORT_ENV="$DB_PORT" DB_NAME_ENV="$DB_NAME" DB_USER_ENV="$DB_USER" DB_PASS_ENV="$DB_PASS" $PHP_BIN -r "
    try {
        \$pdo = new PDO('mysql:host=' . getenv('DB_HOST_ENV') . ';port=' . getenv('DB_PORT_ENV') . ';dbname=' . getenv('DB_NAME_ENV') . ';charset=utf8mb4', getenv('DB_USER_ENV'), getenv('DB_PASS_ENV'));
        \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        \$sql = file_get_contents('sql/schema.sql');
        // Usuń nagłówek (SET, CREATE DATABASE, USE) - wszystko przed pierwszym CREATE TABLE
        \$pos = strpos(\$sql, 'CREATE TABLE');
        if (\$pos !== false) \$sql = substr(\$sql, \$pos);
        \$pdo->exec(\$sql);
        echo 'IMPORT_OK';
    } catch (Exception \$e) {
        echo 'IMPORT_ERR: ' . \$e->getMessage();
    }
    " 2>&1
    echo -e "  ${GREEN}Baza zaimportowana przez PHP.${NC}"
fi

# ── Hasło admina ─────────────────────────────────────
echo ""
echo -e "${BLUE}[8/9] Ustawienie konta administratora...${NC}"
echo ""
echo "Ustaw hasło administratora systemu (login: admin)."
echo -e "${YELLOW}Wymagania: min. 12 znaków, wielkie/małe litery, cyfry, znaki specjalne${NC}"
echo ""

VALID_PASS=0
while [[ "$VALID_PASS" == "0" ]]; do
    read -sp "  Hasło admina: " ADMIN_PASS
    echo ""

    if [[ ${#ADMIN_PASS} -lt 12 ]]; then
        echo -e "  ${RED}Za krótkie! Min. 12 znaków.${NC}"
        continue
    fi
    if ! [[ "$ADMIN_PASS" =~ [a-z] ]]; then
        echo -e "  ${RED}Brak małej litery!${NC}"
        continue
    fi
    if ! [[ "$ADMIN_PASS" =~ [A-Z] ]]; then
        echo -e "  ${RED}Brak wielkiej litery!${NC}"
        continue
    fi
    if ! [[ "$ADMIN_PASS" =~ [0-9] ]]; then
        echo -e "  ${RED}Brak cyfry!${NC}"
        continue
    fi
    if ! [[ "$ADMIN_PASS" =~ [^a-zA-Z0-9] ]]; then
        echo -e "  ${RED}Brak znaku specjalnego!${NC}"
        continue
    fi

    read -sp "  Powtórz hasło: " ADMIN_PASS2
    echo ""
    if [[ "$ADMIN_PASS" != "$ADMIN_PASS2" ]]; then
        echo -e "  ${RED}Hasła nie są identyczne!${NC}"
        continue
    fi

    VALID_PASS=1
done

read -p "  Email admina: " ADMIN_EMAIL
while [[ -z "$ADMIN_EMAIL" ]]; do
    read -p "  Email admina (wymagany): " ADMIN_EMAIL
done

# Generuj hash i aktualizuj w bazie (bezpieczne przekazanie przez zmienne środowiskowe)
ADMIN_HASH=$(ADMIN_PASS_ENV="$ADMIN_PASS" $PHP_BIN -r "echo password_hash(getenv('ADMIN_PASS_ENV'), PASSWORD_BCRYPT, ['cost' => 12]);")

DB_HOST_ENV="$DB_HOST" DB_PORT_ENV="$DB_PORT" DB_NAME_ENV="$DB_NAME" DB_USER_ENV="$DB_USER" DB_PASS_ENV="$DB_PASS" ADMIN_HASH_ENV="$ADMIN_HASH" ADMIN_EMAIL_ENV="$ADMIN_EMAIL" $PHP_BIN -r "
try {
    \$pdo = new PDO('mysql:host=' . getenv('DB_HOST_ENV') . ';port=' . getenv('DB_PORT_ENV') . ';dbname=' . getenv('DB_NAME_ENV') . ';charset=utf8mb4', getenv('DB_USER_ENV'), getenv('DB_PASS_ENV'));
    \$stmt = \$pdo->prepare('UPDATE users SET password_hash = ?, email = ? WHERE username = ?');
    \$stmt->execute([getenv('ADMIN_HASH_ENV'), getenv('ADMIN_EMAIL_ENV'), 'admin']);
    echo 'ADMIN_OK';
} catch (Exception \$e) {
    echo 'ERROR: ' . \$e->getMessage();
}
" 2>&1

echo -e "  ${GREEN}Konto admina skonfigurowane.${NC}"

# ── Konfiguracja aplikacji ───────────────────────────
echo ""
echo -e "${BLUE}[9/9] Konfiguracja aplikacji...${NC}"
echo ""

DEFAULT_URL="https://${DOMAIN}"
read -p "  Adres URL systemu [${DEFAULT_URL}]: " APP_URL
APP_URL=${APP_URL:-$DEFAULT_URL}
# Usuń trailing slash
APP_URL=${APP_URL%/}

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

echo -e "  ${GREEN}config/app.php zapisany.${NC}"

# SMTP
echo ""
echo "Konfiguracja poczty SMTP (do wysyłki powiadomień i raportów):"
echo -e "${YELLOW}Możesz to skonfigurować później edytując config/mail.php${NC}"
echo ""
read -p "  Czy chcesz teraz skonfigurować SMTP? (t/n): " SETUP_MAIL

if [[ "$SETUP_MAIL" == "t" || "$SETUP_MAIL" == "T" ]]; then
    read -p "  SMTP host (np. mail.firma.pl): " SMTP_HOST
    read -p "  SMTP port [587]: " SMTP_PORT
    SMTP_PORT=${SMTP_PORT:-587}
    read -p "  SMTP szyfrowanie (tls/ssl) [tls]: " SMTP_ENC
    SMTP_ENC=${SMTP_ENC:-tls}
    read -p "  SMTP login (email): " SMTP_USER
    read -sp "  SMTP hasło: " SMTP_PASS
    echo ""
    read -p "  Email nadawcy [${SMTP_USER}]: " SMTP_FROM
    SMTP_FROM=${SMTP_FROM:-$SMTP_USER}
    read -p "  Nazwa nadawcy [BiLLU]: " SMTP_NAME
    SMTP_NAME=${SMTP_NAME:-BiLLU}

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

    echo -e "  ${GREEN}config/mail.php zapisany.${NC}"
else
    echo -e "  ${YELLOW}Pominięto. Edytuj config/mail.php ręcznie przed użyciem.${NC}"
fi

# ── Uprawnienia i finalizacja ────────────────────────
echo ""
echo -e "${BLUE}Ustawienie uprawnień i finalizacja...${NC}"

# Tworzenie podkatalogów storage
mkdir -p storage/{imports,exports,logs,jpk,messages,client_files,ksef_send/xml,ksef_send/upo,tasks} 2>/dev/null || true

# Uprawnienia storage
chmod -R 775 storage/ 2>/dev/null || true

# Wykryj użytkownika web serwera
WEB_USER=""
if id -u www-data &>/dev/null; then
    WEB_USER="www-data"
elif id -u apache &>/dev/null; then
    WEB_USER="apache"
elif id -u nginx &>/dev/null; then
    WEB_USER="nginx"
fi

CURRENT_USER=$(whoami)
if [[ -n "$WEB_USER" && "$CURRENT_USER" == "root" ]]; then
    chown -R ${WEB_USER}:${WEB_USER} storage/ 2>/dev/null || true
    echo "  storage/ -> owner: ${WEB_USER}"
else
    # Plesk - próbuj ustawić grupę psacln
    chown -R ${CURRENT_USER}:psacln storage/ 2>/dev/null || true
    echo "  storage/ -> owner: ${CURRENT_USER}"
fi

echo -e "  ${GREEN}Uprawnienia ustawione.${NC}"

# Usuń plik instalacyjny install.php
rm -f install.php 2>/dev/null

# Oczyść composer.phar jeśli był pobrany
rm -f composer.phar 2>/dev/null

# ── Konfiguracja CRON ────────────────────────────────
echo ""
echo -e "${YELLOW}═══ KONFIGURACJA CRONA ═══${NC}"
echo ""
echo "Dodaj w Plesk -> Scheduled Tasks (Cron Jobs):"
echo ""
echo -e "  Komenda:  ${GREEN}${PHP_BIN} ${INSTALL_DIR}/cron.php${NC}"
echo -e "  Schemat:  ${GREEN}0 8 * * *${NC}  (codziennie o 8:00)"
echo ""

# ── Konfiguracja Document Root ───────────────────────
echo -e "${YELLOW}═══ WAŻNE - DOCUMENT ROOT ═══${NC}"
echo ""
echo "W Plesk -> domena -> Hosting Settings zmień:"
echo ""
echo -e "  Document root:  ${GREEN}${INSTALL_DIR}/public${NC}"
echo ""
echo "Bez tej zmiany strona nie będzie działać!"
echo ""

# ── Konfiguracja nginx ───────────────────────────────
echo -e "${YELLOW}═══ ZABEZPIECZENIA NGINX ═══${NC}"
echo ""
echo "W Plesk -> domena -> Apache & nginx Settings"
echo "-> Additional nginx directives wklej:"
echo ""
echo -e "${GREEN}location ~ /\\.(env|git|htaccess) {"
echo "    deny all;"
echo "    return 404;"
echo "}"
echo ""
echo "location ~ /(config|src|sql|storage|templates|lang|vendor)/ {"
echo "    deny all;"
echo "    return 404;"
echo "}"
echo ""
echo "location ~ /(composer\\.json|composer\\.lock|cron\\.php) {"
echo "    deny all;"
echo "    return 404;"
echo -e "}${NC}"
echo ""

# ── Podsumowanie ─────────────────────────────────────
echo -e "${BLUE}╔═══════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║          INSTALACJA ZAKOŃCZONA!               ║${NC}"
echo -e "${BLUE}╚═══════════════════════════════════════════════╝${NC}"
echo ""
echo -e "  URL:          ${GREEN}${APP_URL}${NC}"
echo -e "  Login admina: ${GREEN}admin${NC}"
echo -e "  Email admina: ${GREEN}${ADMIN_EMAIL}${NC}"
echo ""
echo -e "${YELLOW}Pozostało do zrobienia ręcznie w Plesk:${NC}"
echo "  1. Zmień Document root na: ${INSTALL_DIR}/public"
echo "  2. Włącz SSL/TLS (Let's Encrypt) dla ${DOMAIN}"
echo "  3. Dodaj cron job (patrz wyżej)"
echo "  4. Wklej dyrektywy nginx (patrz wyżej)"
if [[ "$SETUP_MAIL" != "t" && "$SETUP_MAIL" != "T" ]]; then
    echo "  5. Skonfiguruj SMTP w config/mail.php"
fi
echo ""
echo -e "${RED}WAŻNE: Usuń ten skrypt!${NC}"
echo "  rm ${INSTALL_DIR}/install_faktury.sh"
echo ""
echo "Po zmianie Document root otwórz https://${DOMAIN} i zaloguj się."
echo ""
