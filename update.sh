#!/bin/bash

#=====================================================
# BiLLU - Aktualizacja kodu (bez reinstalacji)
# Uruchom jako root:
#   chmod +x update.sh && ./update.sh
#=====================================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo ""
echo -e "${BLUE}╔═══════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   BiLLU - Aktualizacja                           ║${NC}"
echo -e "${BLUE}╚═══════════════════════════════════════════════════╝${NC}"
echo ""

# ── Sprawdzenie root ──────────────────────────────────
if [[ "$EUID" -ne 0 ]]; then
    echo -e "${RED}Ten skrypt wymaga uprawnień root!${NC}"
    echo "Uruchom: sudo ./update.sh"
    exit 1
fi

# ── Konfiguracja domeny ───────────────────────────────
DEFAULT_DOMAIN="flamboyant-jones.46-242-128-26.plesk.page"

# Domena może być podana jako pierwszy argument: ./update.sh mojadomena.pl
DOMAIN="${1:-}"

# Jeśli nie podano argumentu, spróbuj read (działa tylko w trybie interaktywnym)
if [[ -z "$DOMAIN" ]]; then
    if [[ -t 0 ]]; then
        read -p "Domena [$DEFAULT_DOMAIN]: " DOMAIN
    fi
    DOMAIN=${DOMAIN:-$DEFAULT_DOMAIN}
fi

# Wykryj katalog httpdocs
HTTPDOCS=""
if [[ -d "/var/www/vhosts/${DOMAIN}/httpdocs" ]]; then
    HTTPDOCS="/var/www/vhosts/${DOMAIN}/httpdocs"
elif [[ -d "/var/www/vhosts/${DOMAIN}" ]]; then
    HTTPDOCS="/var/www/vhosts/${DOMAIN}"
fi

# Jeśli nie znaleziono - szukaj automatycznie w /var/www/vhosts/
if [[ -z "$HTTPDOCS" ]]; then
    echo -e "${YELLOW}Szukam katalogu httpdocs automatycznie...${NC}"
    for DIR in /var/www/vhosts/*/httpdocs; do
        if [[ -d "$DIR" && -f "$DIR/public/index.php" ]]; then
            HTTPDOCS="$DIR"
            DOMAIN=$(basename "$(dirname "$DIR")")
            echo -e "  Znaleziono: ${GREEN}${HTTPDOCS}${NC} (domena: ${DOMAIN})"
            break
        fi
    done
fi

# Ostatnia szansa - pytaj interaktywnie
if [[ -z "$HTTPDOCS" || ! -d "$HTTPDOCS" ]]; then
    if [[ -t 0 ]]; then
        echo -e "${YELLOW}Nie znaleziono katalogu automatycznie.${NC}"
        read -p "Podaj pełną ścieżkę do httpdocs: " HTTPDOCS
    else
        echo -e "${RED}Nie znaleziono katalogu httpdocs!${NC}"
        echo "Uruchom skrypt z argumentem: ./update.sh mojadomena.pl"
        echo "lub podaj ścieżkę: HTTPDOCS=/sciezka/do/httpdocs ./update.sh"
        exit 1
    fi
fi

# Możliwość nadpisania przez zmienną środowiskową
HTTPDOCS="${HTTPDOCS_OVERRIDE:-$HTTPDOCS}"

echo -e "Domena:  ${GREEN}${DOMAIN}${NC}"
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
    exit 1
fi

PHP_VER=$($PHP_BIN -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;" 2>/dev/null)
echo -e "PHP: ${GREEN}${PHP_VER}${NC} (${PHP_BIN})"

# ── Backup konfiguracji ──────────────────────────────
echo ""
echo -e "${BLUE}[1/5] Backup konfiguracji...${NC}"

BACKUP_DIR="/tmp/faktury_backup_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

for CFG in config/database.php config/app.php config/mail.php; do
    if [[ -f "${HTTPDOCS}/${CFG}" ]]; then
        mkdir -p "${BACKUP_DIR}/$(dirname $CFG)"
        cp "${HTTPDOCS}/${CFG}" "${BACKUP_DIR}/${CFG}"
        echo -e "  ${GREEN}✓${NC} ${CFG}"
    fi
done

# Backup uploads
if [[ -d "${HTTPDOCS}/public/assets/uploads" ]]; then
    cp -r "${HTTPDOCS}/public/assets/uploads" "${BACKUP_DIR}/uploads"
    echo -e "  ${GREEN}✓${NC} uploads"
fi

echo -e "  Backup w: ${BACKUP_DIR}"

# ── Pobranie nowego kodu ──────────────────────────────
echo ""
echo -e "${BLUE}[2/5] Pobieranie nowej wersji...${NC}"

BRANCH="claude/invoice-verification-system-E8wzz"
TEMP_DIR="/tmp/faktury_update_$$"

git config --global --add safe.directory '*' 2>/dev/null || true

if git clone -b "$BRANCH" https://github.com/PrzemekPrzemo/Faktury.git "$TEMP_DIR" 2>/dev/null; then
    echo -e "  ${GREEN}✓${NC} Pobrano z GitHub (branch: ${BRANCH})"
else
    echo -e "${RED}Nie udało się pobrać z GitHub!${NC}"
    echo "Sprawdź dostęp do repozytorium."
    exit 1
fi

# ── Aktualizacja plików ──────────────────────────────
echo ""
echo -e "${BLUE}[3/5] Aktualizacja plików...${NC}"

# Usunięcie starych plików źródłowych (ale nie config, storage, vendor)
for DIR in src templates lang sql; do
    if [[ -e "${HTTPDOCS}/${DIR}" ]]; then
        rm -rf "${HTTPDOCS}/${DIR}"
    fi
done

# Kopiowanie nowych katalogów
for DIR in src templates lang sql; do
    if [[ -e "${TEMP_DIR}/${DIR}" ]]; then
        cp -r "${TEMP_DIR}/${DIR}" "${HTTPDOCS}/${DIR}"
        echo -e "  ${GREEN}✓${NC} ${DIR}"
    fi
done

# Aktualizacja public - kopiuj zawartość, nie cały katalog (by uniknąć public/public)
if [[ -d "${TEMP_DIR}/public" ]]; then
    mkdir -p "${HTTPDOCS}/public"
    cp -r "${TEMP_DIR}/public/"* "${HTTPDOCS}/public/" 2>/dev/null || true
    cp "${TEMP_DIR}/public/.htaccess" "${HTTPDOCS}/public/.htaccess" 2>/dev/null || true
    echo -e "  ${GREEN}✓${NC} public"
fi

# Kopiowanie plików root (composer.json, .htaccess itp.)
for F in composer.json composer.lock .htaccess; do
    if [[ -f "${TEMP_DIR}/${F}" ]]; then
        cp "${TEMP_DIR}/${F}" "${HTTPDOCS}/${F}"
        echo -e "  ${GREEN}✓${NC} ${F}"
    fi
done

# ── Przywrócenie konfiguracji ────────────────────────
echo ""
echo -e "${BLUE}[4/5] Przywracanie konfiguracji...${NC}"

for CFG in config/database.php config/app.php config/mail.php; do
    if [[ -f "${BACKUP_DIR}/${CFG}" ]]; then
        mkdir -p "${HTTPDOCS}/$(dirname $CFG)"
        cp "${BACKUP_DIR}/${CFG}" "${HTTPDOCS}/${CFG}"
        echo -e "  ${GREEN}✓${NC} ${CFG}"
    fi
done

# Przywrócenie uploads
if [[ -d "${BACKUP_DIR}/uploads" ]]; then
    mkdir -p "${HTTPDOCS}/public/assets/uploads"
    cp -r "${BACKUP_DIR}/uploads/"* "${HTTPDOCS}/public/assets/uploads/" 2>/dev/null || true
    echo -e "  ${GREEN}✓${NC} uploads"
fi

# Upewnij się, że katalogi storage istnieją
mkdir -p "${HTTPDOCS}/storage/reports"
mkdir -p "${HTTPDOCS}/storage/jpk"
mkdir -p "${HTTPDOCS}/storage/imports"
mkdir -p "${HTTPDOCS}/storage/logs/ksef"

# ── Composer & uprawnienia ───────────────────────────
echo ""
echo -e "${BLUE}[5/5] Composer install & uprawnienia...${NC}"

cd "$HTTPDOCS"

# Pobierz composer.phar
if [[ ! -f composer.phar ]]; then
    curl -sS https://getcomposer.org/installer | $PHP_BIN -- --quiet 2>/dev/null
fi

$PHP_BIN composer.phar install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -3
echo -e "  ${GREEN}✓${NC} Composer install"

# Uprawnienia
VHOST_USER=$(stat -c '%U' "$HTTPDOCS" 2>/dev/null || echo "www-data")
chown -R "${VHOST_USER}:${VHOST_USER}" "$HTTPDOCS" 2>/dev/null || true
chmod -R 755 "$HTTPDOCS"
chmod -R 775 "${HTTPDOCS}/storage" 2>/dev/null || true

echo -e "  ${GREEN}✓${NC} Uprawnienia (${VHOST_USER})"

# ── Migracja bazy danych ─────────────────────────────
echo ""
echo -e "${BLUE}Migracja bazy danych...${NC}"

if [[ -f "${BACKUP_DIR}/config/database.php" ]]; then
    DB_HOST=$($PHP_BIN -r "\$c = require '${HTTPDOCS}/config/database.php'; echo \$c['host'] ?? 'localhost';" 2>/dev/null)
    DB_NAME=$($PHP_BIN -r "\$c = require '${HTTPDOCS}/config/database.php'; echo \$c['database'] ?? '';" 2>/dev/null)
    DB_USER=$($PHP_BIN -r "\$c = require '${HTTPDOCS}/config/database.php'; echo \$c['username'] ?? '';" 2>/dev/null)
    DB_PASS=$($PHP_BIN -r "\$c = require '${HTTPDOCS}/config/database.php'; echo \$c['password'] ?? '';" 2>/dev/null)

    if [[ -n "$DB_NAME" && -n "$DB_USER" ]]; then
        # Uruchom migracje (bezpieczne - używają IF NOT EXISTS / idempotent checks)
        for MIG in sql/migration_v2.1.sql sql/migration_v2.2.sql sql/migration_v2.3.sql sql/migration_v2.4_branding.sql sql/migration_v2.5_ksef_autoimport.sql sql/migration_v3.0_ksef_certificates.sql sql/migration_v4.0_ksef_certificates.sql; do
            if [[ -f "${HTTPDOCS}/${MIG}" ]]; then
                mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "${HTTPDOCS}/${MIG}" 2>/dev/null && \
                    echo -e "  ${GREEN}✓${NC} ${MIG}" || \
                    echo -e "  ${YELLOW}⚠${NC} ${MIG} (już zastosowana lub pominięta)"
            fi
        done
    else
        echo -e "  ${YELLOW}⚠${NC} Brak danych DB - pomiń migrację"
    fi
else
    echo -e "  ${YELLOW}⚠${NC} Brak konfiguracji DB - pomiń migrację"
fi

# ── Sprzątanie ───────────────────────────────────────
rm -rf "$TEMP_DIR"

echo ""
echo -e "${GREEN}╔═══════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║   Aktualizacja zakończona!                        ║${NC}"
echo -e "${GREEN}╚═══════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "  Backup konfiguracji: ${BACKUP_DIR}"
echo -e "  Strona: https://${DOMAIN}/"
echo ""
