#!/bin/bash

#=====================================================
# BiLLU Financial Solutions - Pobierz i zaktualizuj
#
# Domena docelowa: portal.billu.pl
#
# Użycie:
#   curl -sSL https://raw.githubusercontent.com/PrzemekPrzemo/Faktury/rebranding/deploy.sh | sudo bash
#
# Lub z podaną domeną:
#   curl -sSL https://raw.githubusercontent.com/PrzemekPrzemo/Faktury/rebranding/deploy.sh | sudo bash -s -- portal.billu.pl
#
#=====================================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

echo ""
echo -e "${CYAN}╔═══════════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║   BiLLU v4.0 - Automatyczna aktualizacja             ║${NC}"
echo -e "${CYAN}║   KSeF Certificate Enrollment + XAdES Auth Fix        ║${NC}"
echo -e "${CYAN}╚═══════════════════════════════════════════════════════╝${NC}"
echo ""

# ── Sprawdzenie root ──────────────────────────────────
if [[ "$EUID" -ne 0 ]]; then
    echo -e "${RED}Ten skrypt wymaga uprawnień root!${NC}"
    echo "Uruchom: curl -sSL URL | sudo bash"
    exit 1
fi

# ── Sprawdzenie wymagań ──────────────────────────────
for CMD in git mysql; do
    if ! command -v $CMD &>/dev/null; then
        echo -e "${RED}Brak komendy: ${CMD}${NC}"
        exit 1
    fi
done

# ── Konfiguracja domeny ───────────────────────────────
DOMAIN="${1:-portal.billu.pl}"

# Auto-detekcja: szukaj istniejącej instalacji BiLLU
HTTPDOCS=""
if [[ -z "$DOMAIN" ]]; then
    echo -e "${BLUE}Szukam istniejącej instalacji BiLLU...${NC}"
    for DIR in /var/www/vhosts/*/httpdocs; do
        if [[ -d "$DIR" && -f "$DIR/public/index.php" && -f "$DIR/config/database.php" ]]; then
            # Sprawdź czy to BiLLU (szukaj composer.json z naszym projektem)
            if grep -q "faktury-ksef\|FaktuPilot\|BiLLU" "$DIR/composer.json" 2>/dev/null; then
                HTTPDOCS="$DIR"
                DOMAIN=$(basename "$(dirname "$DIR")")
                echo -e "  Znaleziono: ${GREEN}${HTTPDOCS}${NC}"
                break
            fi
        fi
    done
fi

# Jeśli podano domenę, szukaj po niej
if [[ -z "$HTTPDOCS" && -n "$DOMAIN" ]]; then
    if [[ -d "/var/www/vhosts/${DOMAIN}/httpdocs" ]]; then
        HTTPDOCS="/var/www/vhosts/${DOMAIN}/httpdocs"
    elif [[ -d "/var/www/vhosts/${DOMAIN}" ]]; then
        HTTPDOCS="/var/www/vhosts/${DOMAIN}"
    fi
fi

# Ostatnia szansa - pytaj interaktywnie
if [[ -z "$HTTPDOCS" || ! -d "$HTTPDOCS" ]]; then
    if [[ -t 0 ]]; then
        echo -e "${YELLOW}Nie znaleziono instalacji automatycznie.${NC}"
        read -p "Podaj domenę lub pełną ścieżkę do httpdocs: " INPUT
        if [[ "$INPUT" == /* ]]; then
            HTTPDOCS="$INPUT"
        else
            DOMAIN="$INPUT"
            HTTPDOCS="/var/www/vhosts/${DOMAIN}/httpdocs"
        fi
    else
        echo -e "${RED}Nie znaleziono instalacji BiLLU!${NC}"
        echo ""
        echo "Uruchom z podaniem domeny:"
        echo "  curl -sSL URL | sudo bash -s -- twoja-domena.pl"
        echo ""
        echo "Lub pobierz skrypt i uruchom interaktywnie:"
        echo "  wget URL -O deploy.sh && sudo bash deploy.sh"
        exit 1
    fi
fi

# Nadpisanie zmienną środowiskową
HTTPDOCS="${HTTPDOCS_OVERRIDE:-$HTTPDOCS}"

if [[ ! -d "$HTTPDOCS" ]]; then
    echo -e "${RED}Katalog $HTTPDOCS nie istnieje!${NC}"
    exit 1
fi

if [[ ! -f "$HTTPDOCS/config/database.php" ]]; then
    echo -e "${RED}Brak config/database.php w $HTTPDOCS - to nie jest instalacja BiLLU!${NC}"
    exit 1
fi

echo -e "Instalacja: ${GREEN}${HTTPDOCS}${NC}"
[[ -n "$DOMAIN" ]] && echo -e "Domena:     ${GREEN}${DOMAIN}${NC}"

# ── Wykryj PHP ────────────────────────────────────────
PHP_BIN=""
for V in 8.3 8.2 8.1; do
    if [[ -x "/opt/plesk/php/${V}/bin/php" ]]; then
        PHP_BIN="/opt/plesk/php/${V}/bin/php"
        break
    fi
done
[[ -z "$PHP_BIN" ]] && command -v php &>/dev/null && PHP_BIN=$(which php)
if [[ -z "$PHP_BIN" ]]; then
    echo -e "${RED}PHP nie znalezione!${NC}"
    exit 1
fi
PHP_VER=$($PHP_BIN -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;" 2>/dev/null)
echo -e "PHP:        ${GREEN}${PHP_VER}${NC} (${PHP_BIN})"

# ── Wersja przed aktualizacją ────────────────────────
echo ""
CURRENT_VER="nieznana"
if [[ -f "$HTTPDOCS/src/Services/KsefApiService.php" ]]; then
    if grep -q "cert_ksef_pem\|ksef_cert" "$HTTPDOCS/src/Services/KsefApiService.php" 2>/dev/null; then
        CURRENT_VER="v4.x"
    elif grep -q "certPfxEncrypted\|authenticateWithCertificate" "$HTTPDOCS/src/Services/KsefApiService.php" 2>/dev/null; then
        CURRENT_VER="v3.x"
    elif grep -q "KsefApiService" "$HTTPDOCS/src/Services/KsefApiService.php" 2>/dev/null; then
        CURRENT_VER="v2.x"
    fi
fi
echo -e "Aktualna wersja: ${YELLOW}${CURRENT_VER}${NC} → ${GREEN}v4.0${NC}"

# ── [1/6] Backup ─────────────────────────────────────
echo ""
echo -e "${BLUE}[1/6] Tworzenie backupu...${NC}"

BACKUP_DIR="/tmp/faktury_backup_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

# Backup konfiguracji
for CFG in config/database.php config/app.php config/mail.php; do
    if [[ -f "${HTTPDOCS}/${CFG}" ]]; then
        mkdir -p "${BACKUP_DIR}/$(dirname $CFG)"
        cp "${HTTPDOCS}/${CFG}" "${BACKUP_DIR}/${CFG}"
        echo -e "  ${GREEN}✓${NC} ${CFG}"
    fi
done

# Backup uploads i storage
if [[ -d "${HTTPDOCS}/public/assets/uploads" ]]; then
    cp -r "${HTTPDOCS}/public/assets/uploads" "${BACKUP_DIR}/uploads"
    echo -e "  ${GREEN}✓${NC} uploads"
fi
if [[ -d "${HTTPDOCS}/storage" ]]; then
    cp -r "${HTTPDOCS}/storage" "${BACKUP_DIR}/storage"
    echo -e "  ${GREEN}✓${NC} storage (raporty, logi)"
fi

# Backup bazy danych
DB_HOST=$($PHP_BIN -r "\$c = require '${HTTPDOCS}/config/database.php'; echo \$c['host'] ?? 'localhost';" 2>/dev/null)
DB_NAME=$($PHP_BIN -r "\$c = require '${HTTPDOCS}/config/database.php'; echo \$c['database'] ?? '';" 2>/dev/null)
DB_USER=$($PHP_BIN -r "\$c = require '${HTTPDOCS}/config/database.php'; echo \$c['username'] ?? '';" 2>/dev/null)
DB_PASS=$($PHP_BIN -r "\$c = require '${HTTPDOCS}/config/database.php'; echo \$c['password'] ?? '';" 2>/dev/null)

if [[ -n "$DB_NAME" && -n "$DB_USER" ]]; then
    if mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "${BACKUP_DIR}/database_backup.sql" 2>/dev/null; then
        DUMP_SIZE=$(du -h "${BACKUP_DIR}/database_backup.sql" | cut -f1)
        echo -e "  ${GREEN}✓${NC} baza danych (${DUMP_SIZE})"
    else
        echo -e "  ${YELLOW}⚠${NC} mysqldump nie udał się (kontynuuję bez backupu DB)"
    fi
fi

echo -e "  Backup w: ${CYAN}${BACKUP_DIR}${NC}"

# ── [2/6] Pobranie nowej wersji ──────────────────────
echo ""
echo -e "${BLUE}[2/6] Pobieranie v4.0 z GitHub...${NC}"

BRANCH="rebranding"
REPO="https://github.com/PrzemekPrzemo/Faktury.git"
TEMP_DIR="/tmp/faktury_update_$$"

git config --global --add safe.directory '*' 2>/dev/null || true

RETRIES=0
while [[ $RETRIES -lt 4 ]]; do
    if git clone --depth 1 -b "$BRANCH" "$REPO" "$TEMP_DIR" 2>/dev/null; then
        echo -e "  ${GREEN}✓${NC} Pobrano (branch: ${BRANCH})"
        break
    fi
    RETRIES=$((RETRIES + 1))
    if [[ $RETRIES -lt 4 ]]; then
        WAIT=$((2 ** RETRIES))
        echo -e "  ${YELLOW}Retry ${RETRIES}/3 za ${WAIT}s...${NC}"
        sleep $WAIT
    else
        echo -e "${RED}Nie udało się pobrać z GitHub po 4 próbach!${NC}"
        echo "Sprawdź połączenie internetowe i dostęp do repozytorium."
        exit 1
    fi
done

# ── [3/6] Aktualizacja plików ────────────────────────
echo ""
echo -e "${BLUE}[3/6] Aktualizacja plików źródłowych...${NC}"

# Usunięcie starych plików źródłowych (ale NIE config, storage, vendor)
for DIR in src templates lang sql; do
    if [[ -d "${HTTPDOCS}/${DIR}" ]]; then
        rm -rf "${HTTPDOCS}/${DIR}"
    fi
done

# Kopiowanie nowych katalogów
for DIR in src templates lang sql; do
    if [[ -d "${TEMP_DIR}/${DIR}" ]]; then
        cp -r "${TEMP_DIR}/${DIR}" "${HTTPDOCS}/${DIR}"
        FILE_COUNT=$(find "${HTTPDOCS}/${DIR}" -type f | wc -l)
        echo -e "  ${GREEN}✓${NC} ${DIR}/ (${FILE_COUNT} plików)"
    fi
done

# Aktualizacja public (zachowaj uploads)
if [[ -d "${TEMP_DIR}/public" ]]; then
    # Kopiuj zawartość, nie cały katalog
    cp -r "${TEMP_DIR}/public/"* "${HTTPDOCS}/public/" 2>/dev/null || true
    cp "${TEMP_DIR}/public/.htaccess" "${HTTPDOCS}/public/.htaccess" 2>/dev/null || true
    echo -e "  ${GREEN}✓${NC} public/"
fi

# Kopiowanie plików root
for F in composer.json cron.php update.sh deploy.sh; do
    if [[ -f "${TEMP_DIR}/${F}" ]]; then
        cp "${TEMP_DIR}/${F}" "${HTTPDOCS}/${F}"
    fi
done
echo -e "  ${GREEN}✓${NC} composer.json, cron.php"

# ── [4/6] Przywrócenie konfiguracji ──────────────────
echo ""
echo -e "${BLUE}[4/6] Przywracanie konfiguracji...${NC}"

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
for SDIR in reports jpk imports logs/ksef ksef_certs; do
    mkdir -p "${HTTPDOCS}/storage/${SDIR}"
done
echo -e "  ${GREEN}✓${NC} storage/"

# ── [5/6] Composer & uprawnienia ─────────────────────
echo ""
echo -e "${BLUE}[5/6] Composer install & uprawnienia...${NC}"

cd "$HTTPDOCS"

# Pobierz composer.phar jeśli nie istnieje
if [[ ! -f composer.phar ]]; then
    curl -sS https://getcomposer.org/installer | $PHP_BIN -- --quiet 2>/dev/null
    echo -e "  ${GREEN}✓${NC} Composer zainstalowany"
fi

$PHP_BIN composer.phar install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -3
echo -e "  ${GREEN}✓${NC} Zależności PHP"

# Uprawnienia
VHOST_USER=$(stat -c '%U' "$HTTPDOCS" 2>/dev/null || echo "www-data")
chown -R "${VHOST_USER}:${VHOST_USER}" "$HTTPDOCS" 2>/dev/null || true
chmod -R 755 "$HTTPDOCS"
chmod -R 775 "${HTTPDOCS}/storage" 2>/dev/null || true
echo -e "  ${GREEN}✓${NC} Uprawnienia (${VHOST_USER})"

# ── [6/6] Migracja bazy danych ───────────────────────
echo ""
echo -e "${BLUE}[6/6] Migracja bazy danych...${NC}"

if [[ -n "$DB_NAME" && -n "$DB_USER" ]]; then
    MIGRATION_OK=0
    MIGRATION_SKIP=0

    for MIG in \
        sql/migration_v2.1.sql \
        sql/migration_v2.2.sql \
        sql/migration_v2.3.sql \
        sql/migration_v2.4_branding.sql \
        sql/migration_v2.5_ksef_autoimport.sql \
        sql/migration_v3.0_ksef_certificates.sql \
        sql/migration_v4.0_ksef_certificates.sql; do

        if [[ -f "${HTTPDOCS}/${MIG}" ]]; then
            MIG_NAME=$(basename "$MIG" .sql)
            if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "${HTTPDOCS}/${MIG}" 2>/dev/null; then
                echo -e "  ${GREEN}✓${NC} ${MIG_NAME}"
                MIGRATION_OK=$((MIGRATION_OK + 1))
            else
                echo -e "  ${YELLOW}~${NC} ${MIG_NAME} (już zastosowana)"
                MIGRATION_SKIP=$((MIGRATION_SKIP + 1))
            fi
        fi
    done

    echo -e "  Zastosowane: ${GREEN}${MIGRATION_OK}${NC}, pominięte: ${YELLOW}${MIGRATION_SKIP}${NC}"
else
    echo -e "  ${YELLOW}⚠${NC} Brak danych DB - pomiń migrację"
    echo -e "  ${YELLOW}  Uruchom ręcznie:${NC}"
    echo "    mysql -u USER -p BAZA < sql/migration_v3.0_ksef_certificates.sql"
    echo "    mysql -u USER -p BAZA < sql/migration_v4.0_ksef_certificates.sql"
fi

# ── Sprzątanie ───────────────────────────────────────
rm -rf "$TEMP_DIR"

# ── Podsumowanie ─────────────────────────────────────
echo ""
echo -e "${GREEN}╔═══════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║   Aktualizacja do v4.0 zakończona pomyślnie!         ║${NC}"
echo -e "${GREEN}╚═══════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "  Co nowego w v4.0:"
echo -e "  ${CYAN}•${NC} Certyfikaty KSeF - enrollment z panelu klienta"
echo -e "  ${CYAN}•${NC} 3 metody auth: token, certyfikat kwalifikowany, certyfikat KSeF"
echo -e "  ${CYAN}•${NC} Naprawiony XAdES (namespace, ContextIdentifier, C14N)"
echo -e "  ${CYAN}•${NC} Naprawiony prefix API (/v2 zamiast /api/v2)"
echo -e "  ${CYAN}•${NC} Fix błędu 500 na stronie MPK"
echo ""
echo -e "  Backup:  ${CYAN}${BACKUP_DIR}${NC}"
[[ -n "$DOMAIN" ]] && echo -e "  Strona:  ${CYAN}https://${DOMAIN}/${NC}"
echo ""
echo -e "  ${YELLOW}W razie problemów przywróć backup:${NC}"
echo "    cp ${BACKUP_DIR}/config/* ${HTTPDOCS}/config/"
[[ -f "${BACKUP_DIR}/database_backup.sql" ]] && \
echo "    mysql -u USER -p BAZA < ${BACKUP_DIR}/database_backup.sql"
echo ""
