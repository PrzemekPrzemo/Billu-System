#!/usr/bin/env bash
#
# BiLLU - Uniwersalny skrypt aktualizacji
#
# Użycie (jednolinijkowe z GitHub):
#   curl -sSL https://raw.githubusercontent.com/PrzemekPrzemo/Faktury/main/scripts/UpdateFaktury.sh | sudo bash
#
# Lub z podaną domeną:
#   curl -sSL https://raw.githubusercontent.com/PrzemekPrzemo/Faktury/main/scripts/UpdateFaktury.sh | sudo bash -s -- --domain portal.billu.pl
#
# Lub z podanym branchem:
#   curl -sSL ... | sudo bash -s -- --branch claude/invoice-verification-system-E8wzz
#
# Lub lokalnie (z katalogu projektu):
#   sudo bash scripts/UpdateFaktury.sh
#

set -euo pipefail

# ── Kolory ───────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

ok()   { echo -e "  ${GREEN}✓${NC} $1"; }
warn() { echo -e "  ${YELLOW}!${NC} $1"; }
fail() { echo -e "  ${RED}✗${NC} $1"; }
info() { echo -e "  ${CYAN}i${NC} $1"; }

# ── Argumenty ────────────────────────────────────────
BRANCH="main"
DOMAIN=""
HTTPDOCS=""
DRY_RUN=false

while [[ $# -gt 0 ]]; do
    case "$1" in
        --branch|-b)  BRANCH="$2"; shift 2 ;;
        --domain|-d)  DOMAIN="$2"; shift 2 ;;
        --path|-p)    HTTPDOCS="$2"; shift 2 ;;
        --dry-run)    DRY_RUN=true; shift ;;
        --help|-h)
            echo "Użycie: $0 [--branch BRANCH] [--domain DOMENA] [--path /sciezka/httpdocs] [--dry-run]"
            exit 0 ;;
        *) echo "Nieznany argument: $1"; exit 1 ;;
    esac
done

REPO="https://github.com/PrzemekPrzemo/Faktury.git"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

echo ""
echo -e "${CYAN}${BOLD}╔══════════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}${BOLD}║       BiLLU - Uniwersalna Aktualizacja               ║${NC}"
echo -e "${CYAN}${BOLD}╚══════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "   Data:   $(date '+%Y-%m-%d %H:%M:%S')"
echo -e "   Branch: ${GREEN}${BRANCH}${NC}"
echo ""

# ── Sprawdzenie root ─────────────────────────────────
if [[ "$EUID" -ne 0 ]]; then
    fail "Ten skrypt wymaga uprawnień root!"
    echo "   Uruchom: curl -sSL URL | sudo bash"
    exit 1
fi

# ── Wykryj PHP ───────────────────────────────────────
echo -e "${BOLD}[0/8] Wykrywanie środowiska...${NC}"

PHP_BIN="${PHP_BIN:-}"
if [[ -z "$PHP_BIN" ]]; then
    for candidate in \
        /opt/plesk/php/8.3/bin/php /opt/plesk/php/8.2/bin/php /opt/plesk/php/8.1/bin/php \
        /opt/cpanel/ea-php83/root/usr/bin/php /opt/cpanel/ea-php82/root/usr/bin/php \
        /usr/bin/php8.3 /usr/bin/php8.2 /usr/bin/php8.1; do
        if [[ -x "$candidate" ]]; then
            PHP_BIN="$candidate"
            break
        fi
    done
fi
if [[ -z "$PHP_BIN" ]] && command -v php &>/dev/null; then
    PHP_BIN="$(command -v php)"
fi
if [[ -z "$PHP_BIN" ]]; then
    fail "PHP nie znalezione!"
    exit 1
fi
PHP_VER=$("$PHP_BIN" -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;" 2>/dev/null)
ok "PHP: ${PHP_VER} (${PHP_BIN})"

# ── Wykryj katalog instalacji ────────────────────────
if [[ -z "$HTTPDOCS" ]]; then
    # 1. Podano domenę
    if [[ -n "$DOMAIN" ]]; then
        for DIR in "/var/www/vhosts/${DOMAIN}/httpdocs" "/var/www/vhosts/${DOMAIN}" "/var/www/${DOMAIN}"; do
            if [[ -d "$DIR" && -f "$DIR/config/database.php" ]]; then
                HTTPDOCS="$DIR"
                break
            fi
        done
    fi

    # 2. Autodetekcja — szukaj w /var/www/vhosts
    if [[ -z "$HTTPDOCS" ]]; then
        for DIR in /var/www/vhosts/*/httpdocs; do
            if [[ -d "$DIR" && -f "$DIR/config/database.php" && -f "$DIR/src/Services/KsefApiService.php" ]]; then
                HTTPDOCS="$DIR"
                DOMAIN=$(basename "$(dirname "$DIR")")
                break
            fi
        done
    fi

    # 3. Tryb interaktywny
    if [[ -z "$HTTPDOCS" && -t 0 ]]; then
        echo ""
        read -p "Podaj domenę lub pełną ścieżkę do httpdocs: " INPUT
        if [[ "$INPUT" == /* ]]; then
            HTTPDOCS="$INPUT"
        else
            DOMAIN="$INPUT"
            HTTPDOCS="/var/www/vhosts/${DOMAIN}/httpdocs"
        fi
    fi
fi

HTTPDOCS="${HTTPDOCS_OVERRIDE:-$HTTPDOCS}"

if [[ -z "$HTTPDOCS" || ! -d "$HTTPDOCS" ]]; then
    fail "Nie znaleziono katalogu instalacji!"
    echo "   Uruchom z: --domain portal.billu.pl"
    echo "   Lub:       --path /var/www/vhosts/domena/httpdocs"
    exit 1
fi

if [[ ! -f "$HTTPDOCS/config/database.php" ]]; then
    fail "Brak config/database.php w $HTTPDOCS — to nie jest instalacja BiLLU!"
    exit 1
fi

ok "Instalacja: ${HTTPDOCS}"
[[ -n "$DOMAIN" ]] && ok "Domena: ${DOMAIN}"

# ── Odczytaj dane DB ─────────────────────────────────
DB_CFG="${HTTPDOCS}/config/database.php"
DB_HOST="localhost"
DB_PORT="3306"
DB_NAME=""
DB_USER=""
DB_PASS=""

if [[ -f "$DB_CFG" ]]; then
    # Extract values by matching 'key' => 'value' with sed (works everywhere, no PCRE needed)
    DB_HOST=$(sed -n "s/.*'host'[[:space:]]*=>[[:space:]]*'\\([^']*\\)'.*/\\1/p" "$DB_CFG" | tail -1 | tr -d '[:space:]')
    DB_PORT=$(sed -n "s/.*'port'[[:space:]]*=>[[:space:]]*'\\{0,1\\}\\([0-9]*\\).*/\\1/p" "$DB_CFG" | tail -1 | tr -d '[:space:]')
    DB_NAME=$(sed -n "s/.*'database'[[:space:]]*=>[[:space:]]*'\\([^']*\\)'.*/\\1/p" "$DB_CFG" | tail -1 | tr -d '[:space:]')
    DB_USER=$(sed -n "s/.*'username'[[:space:]]*=>[[:space:]]*'\\([^']*\\)'.*/\\1/p" "$DB_CFG" | tail -1 | tr -d '[:space:]')
    DB_PASS=$(sed -n "s/.*'password'[[:space:]]*=>[[:space:]]*'\\([^']*\\)'.*/\\1/p" "$DB_CFG" | tail -1)

    [[ -z "$DB_HOST" ]] && DB_HOST="localhost"
    [[ -z "$DB_PORT" ]] && DB_PORT="3306"
else
    warn "Brak pliku config/database.php"
fi

info "DB: host=$DB_HOST, port=$DB_PORT, name=$DB_NAME, user=$DB_USER, pass=$([ -n "$DB_PASS" ] && echo '***' || echo 'EMPTY')"

# ── Polaczenie z baza ────────────────────────────────
MYSQL_CNF=$(mktemp)
chmod 600 "$MYSQL_CNF"
trap "rm -f '$MYSQL_CNF'" EXIT

_write_mycnf() {
    printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\n' \
        "$DB_HOST" "$DB_PORT" "$DB_USER" "$DB_PASS" > "$MYSQL_CNF"
}

_test_db() {
    _write_mycnf
    mysql --defaults-extra-file="$MYSQL_CNF" -e "SELECT 1;" "$DB_NAME" &>/dev/null
}

DB_OK=false

# Auto-connect
if [[ -n "$DB_NAME" && -n "$DB_USER" ]] && _test_db; then
    ok "Baza danych: ${DB_NAME} @ ${DB_HOST}"
    DB_OK=true
fi

# Prompt if auto-connect failed
if [[ "$DB_OK" != "true" ]]; then
    warn "Nie mozna polaczyc z baza automatycznie."
    echo ""
    echo -e "   ${BOLD}Podaj dane bazy danych:${NC}"
    read -p "   Host [${DB_HOST}]: " _h </dev/tty 2>/dev/null || read -p "   Host [${DB_HOST}]: " _h
    [[ -n "$_h" ]] && DB_HOST="$_h"
    read -p "   Port [${DB_PORT}]: " _p </dev/tty 2>/dev/null || read -p "   Port [${DB_PORT}]: " _p
    [[ -n "$_p" ]] && DB_PORT="$_p"
    read -p "   Nazwa bazy [${DB_NAME}]: " _n </dev/tty 2>/dev/null || read -p "   Nazwa bazy [${DB_NAME}]: " _n
    [[ -n "$_n" ]] && DB_NAME="$_n"
    read -p "   User [${DB_USER}]: " _u </dev/tty 2>/dev/null || read -p "   User [${DB_USER}]: " _u
    [[ -n "$_u" ]] && DB_USER="$_u"
    read -s -p "   Haslo: " _pw </dev/tty 2>/dev/null || read -s -p "   Haslo: " _pw
    echo ""
    [[ -n "$_pw" ]] && DB_PASS="$_pw"

    if [[ -n "$DB_NAME" && -n "$DB_USER" ]] && _test_db; then
        ok "Baza danych: ${DB_NAME} @ ${DB_HOST}"
        DB_OK=true
    else
        warn "Nadal nie mozna polaczyc — migracje pominiete"
    fi
fi

if [[ "$DB_OK" != "true" ]]; then
    DB_NAME=""
fi

if $DRY_RUN; then
    echo ""
    info "DRY RUN — zakończono rozpoznanie środowiska"
    exit 0
fi

echo ""

# ══════════════════════════════════════════════════════
# [1/8] BACKUP KONFIGURACJI
# ══════════════════════════════════════════════════════
echo -e "${BOLD}[1/8] Backup konfiguracji...${NC}"

BACKUP_DIR="/tmp/billu_backup_${TIMESTAMP}"
mkdir -p "$BACKUP_DIR/config"

for CFG in database.php app.php mail.php; do
    if [[ -f "${HTTPDOCS}/config/${CFG}" ]]; then
        cp "${HTTPDOCS}/config/${CFG}" "${BACKUP_DIR}/config/${CFG}"
        ok "$CFG"
    fi
done

if [[ -d "${HTTPDOCS}/public/assets/uploads" ]]; then
    cp -r "${HTTPDOCS}/public/assets/uploads" "${BACKUP_DIR}/uploads"
    ok "uploads"
fi

info "Backup: ${BACKUP_DIR}"
echo ""

# ══════════════════════════════════════════════════════
# [2/8] BACKUP BAZY DANYCH
# ══════════════════════════════════════════════════════
echo -e "${BOLD}[2/8] Backup bazy danych...${NC}"

if [[ -n "$DB_NAME" ]]; then
    DB_BACKUP="${BACKUP_DIR}/db_${DB_NAME}.sql"
    if mysqldump --defaults-extra-file="$MYSQL_CNF" "$DB_NAME" > "$DB_BACKUP" 2>/dev/null; then
        DUMP_SIZE=$(du -h "$DB_BACKUP" | cut -f1)
        ok "Baza: ${DB_BACKUP} (${DUMP_SIZE})"
    else
        warn "mysqldump nie powiodl sie — kontynuuje bez backupu DB"
    fi
else
    warn "Brak polaczenia z DB — pominieto backup"
fi
echo ""

# ══════════════════════════════════════════════════════
# [3/8] POBRANIE NOWEJ WERSJI Z GITHUB
# ══════════════════════════════════════════════════════
echo -e "${BOLD}[3/8] Pobieranie z GitHub (branch: ${BRANCH})...${NC}"

TEMP_DIR="/tmp/billu_update_$$"
rm -rf "$TEMP_DIR"

git config --global --add safe.directory '*' 2>/dev/null || true

RETRIES=0
MAX_RETRIES=4
RETRY_DELAY=2
while [[ $RETRIES -lt $MAX_RETRIES ]]; do
    if git clone --depth 1 -b "$BRANCH" "$REPO" "$TEMP_DIR" 2>/dev/null; then
        ok "Pobrano z GitHub"
        break
    fi
    RETRIES=$((RETRIES + 1))
    if [[ $RETRIES -lt $MAX_RETRIES ]]; then
        WAIT=$((RETRY_DELAY * (2 ** (RETRIES - 1))))
        warn "Próba ${RETRIES}/${MAX_RETRIES} nie powiodła się, ponawiam za ${WAIT}s..."
        sleep "$WAIT"
    else
        fail "Nie udało się pobrać z GitHub po ${MAX_RETRIES} próbach!"
        exit 1
    fi
done
echo ""

# ══════════════════════════════════════════════════════
# [4/8] AKTUALIZACJA PLIKÓW
# ══════════════════════════════════════════════════════
echo -e "${BOLD}[4/8] Aktualizacja plików źródłowych...${NC}"

for DIR in src templates lang sql scripts; do
    if [[ -d "${TEMP_DIR}/${DIR}" ]]; then
        rm -rf "${HTTPDOCS}/${DIR}"
        cp -r "${TEMP_DIR}/${DIR}" "${HTTPDOCS}/${DIR}"
        FILE_COUNT=$(find "${HTTPDOCS}/${DIR}" -type f | wc -l)
        ok "${DIR}/ (${FILE_COUNT} plików)"
    fi
done

# public — zachowaj uploads
if [[ -d "${TEMP_DIR}/public" ]]; then
    mkdir -p "${HTTPDOCS}/public"
    cp -r "${TEMP_DIR}/public/"* "${HTTPDOCS}/public/" 2>/dev/null || true
    cp "${TEMP_DIR}/public/.htaccess" "${HTTPDOCS}/public/.htaccess" 2>/dev/null || true
    ok "public/"
fi

# Pliki root
for F in composer.json composer.lock cron.php .htaccess; do
    if [[ -f "${TEMP_DIR}/${F}" ]]; then
        cp "${TEMP_DIR}/${F}" "${HTTPDOCS}/${F}"
    fi
done
ok "pliki root (composer.json, ...)"
echo ""

# ══════════════════════════════════════════════════════
# [5/8] PRZYWRÓCENIE KONFIGURACJI
# ══════════════════════════════════════════════════════
echo -e "${BOLD}[5/8] Przywracanie konfiguracji...${NC}"

for CFG in database.php app.php mail.php; do
    if [[ -f "${BACKUP_DIR}/config/${CFG}" ]]; then
        mkdir -p "${HTTPDOCS}/config"
        cp "${BACKUP_DIR}/config/${CFG}" "${HTTPDOCS}/config/${CFG}"
        ok "${CFG}"
    fi
done

if [[ -d "${BACKUP_DIR}/uploads" ]]; then
    mkdir -p "${HTTPDOCS}/public/assets/uploads"
    cp -r "${BACKUP_DIR}/uploads/"* "${HTTPDOCS}/public/assets/uploads/" 2>/dev/null || true
    ok "uploads"
fi

# Katalogi storage
for SDIR in invoices exports jpk logs logs/ksef cache temp reports imports ksef_certs ksef_send upo; do
    mkdir -p "${HTTPDOCS}/storage/${SDIR}"
done
ok "storage/"
echo ""

# ══════════════════════════════════════════════════════
# [6/8] COMPOSER & UPRAWNIENIA
# ══════════════════════════════════════════════════════
echo -e "${BOLD}[6/8] Composer install & uprawnienia...${NC}"

cd "$HTTPDOCS"

if [[ ! -f composer.phar ]]; then
    curl -sS https://getcomposer.org/installer | "$PHP_BIN" -- --quiet 2>/dev/null
    ok "Composer zainstalowany"
fi

"$PHP_BIN" composer.phar install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -3
ok "Zależności PHP"

VHOST_USER=$(stat -c '%U' "$HTTPDOCS" 2>/dev/null || echo "www-data")
chown -R "${VHOST_USER}:${VHOST_USER}" "$HTTPDOCS" 2>/dev/null || true
chmod -R 755 "$HTTPDOCS"
chmod -R 775 "${HTTPDOCS}/storage" 2>/dev/null || true
ok "Uprawnienia (${VHOST_USER})"
echo ""

# ══════════════════════════════════════════════════════
# [7/8] MIGRACJE BAZY DANYCH
# ══════════════════════════════════════════════════════
echo -e "${BOLD}[7/8] Migracje bazy danych...${NC}"

if [[ -n "$DB_NAME" ]]; then
    # Utwórz tabelę śledzenia migracji
    mysql --defaults-extra-file="$MYSQL_CNF" "$DB_NAME" -e "
        CREATE TABLE IF NOT EXISTS schema_migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL UNIQUE,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    " 2>/dev/null || true

    MIGRATION_OK=0
    MIGRATION_SKIP=0
    MIGRATION_FAIL=0

    # Automatyczne wykrywanie migracji — sortowane po nazwie
    for MIG_FILE in $(ls "${HTTPDOCS}/sql/migration_v"*.sql 2>/dev/null | sort -V); do
        MIG_NAME=$(basename "$MIG_FILE")

        # Sprawdź czy już zastosowana
        APPLIED=$(mysql --defaults-extra-file="$MYSQL_CNF" "$DB_NAME" -N -e "SELECT COUNT(*) FROM schema_migrations WHERE filename='${MIG_NAME}';" 2>/dev/null || echo "0")
        if [[ "$APPLIED" -gt 0 ]]; then
            MIGRATION_SKIP=$((MIGRATION_SKIP + 1))
            continue
        fi

        # Zastosuj migrację (podmień nazwę bazy jeśli potrzeba)
        MIG_SQL=$(cat "$MIG_FILE" | sed "s/faktury_ksef/$DB_NAME/g")
        if echo "$MIG_SQL" | mysql --defaults-extra-file="$MYSQL_CNF" "$DB_NAME" 2>/dev/null; then
            mysql --defaults-extra-file="$MYSQL_CNF" "$DB_NAME" -e "INSERT INTO schema_migrations (filename) VALUES ('${MIG_NAME}');" 2>/dev/null
            ok "${MIG_NAME}"
            MIGRATION_OK=$((MIGRATION_OK + 1))
        else
            warn "${MIG_NAME} (blad lub juz zastosowana)"
            mysql --defaults-extra-file="$MYSQL_CNF" "$DB_NAME" -e "INSERT IGNORE INTO schema_migrations (filename) VALUES ('${MIG_NAME}');" 2>/dev/null || true
            MIGRATION_FAIL=$((MIGRATION_FAIL + 1))
        fi
    done

    if [[ $MIGRATION_OK -eq 0 && $MIGRATION_FAIL -eq 0 ]]; then
        info "Baza aktualna — brak nowych migracji (${MIGRATION_SKIP} juz zastosowanych)"
    else
        info "Nowe: ${MIGRATION_OK}, pominiete: ${MIGRATION_SKIP}, bledy: ${MIGRATION_FAIL}"
    fi
else
    warn "Brak polaczenia z DB — migracje pominiete"
    warn "Uruchom recznie: mysql -u USER -p BAZA < sql/migration_vX.sql"
fi
echo ""

# ══════════════════════════════════════════════════════
# [8/8] SPRAWDZENIE SKŁADNI PHP
# ══════════════════════════════════════════════════════
echo -e "${BOLD}[8/8] Sprawdzanie składni PHP...${NC}"

SYNTAX_ERRORS=0
for PHP_FILE in $(find "${HTTPDOCS}/src" "${HTTPDOCS}/public" -name "*.php" -type f 2>/dev/null); do
    if ! "$PHP_BIN" -l "$PHP_FILE" &>/dev/null; then
        fail "Błąd składni: $(basename $PHP_FILE)"
        SYNTAX_ERRORS=$((SYNTAX_ERRORS + 1))
    fi
done

if [[ $SYNTAX_ERRORS -gt 0 ]]; then
    fail "${SYNTAX_ERRORS} plik(ów) z błędami składni!"
else
    ok "Wszystkie pliki PHP poprawne"
fi
echo ""

# ── Sprzątanie ───────────────────────────────────────
rm -rf "$TEMP_DIR"

# ── Stare backupy — zachowaj 5 ostatnich ────────────
ls -1dt /tmp/billu_backup_* 2>/dev/null | tail -n +6 | xargs rm -rf 2>/dev/null || true

# ══════════════════════════════════════════════════════
# PODSUMOWANIE
# ══════════════════════════════════════════════════════
echo -e "${GREEN}${BOLD}╔══════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}${BOLD}║        Aktualizacja zakończona pomyślnie!            ║${NC}"
echo -e "${GREEN}${BOLD}╚══════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "   Backup:     ${CYAN}${BACKUP_DIR}${NC}"
echo -e "   Branch:     ${CYAN}${BRANCH}${NC}"
[[ -n "$DOMAIN" ]] && echo -e "   Strona:     ${CYAN}https://${DOMAIN}/${NC}"
echo ""

if [[ $SYNTAX_ERRORS -gt 0 ]]; then
    echo -e "   ${RED}${BOLD}⚠ UWAGA: ${SYNTAX_ERRORS} błąd(ów) składni PHP!${NC}"
    echo ""
fi

echo -e "   ${YELLOW}W razie problemów przywróć backup:${NC}"
echo "     cp ${BACKUP_DIR}/config/*.php ${HTTPDOCS}/config/"
[[ -f "${BACKUP_DIR}/db_${DB_NAME}.sql" ]] && \
echo "     mysql -h $DB_HOST -u $DB_USER -p $DB_NAME < ${BACKUP_DIR}/db_${DB_NAME}.sql"
echo ""
