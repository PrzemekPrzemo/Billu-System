#!/usr/bin/env bash
#
# BiLLU Financial Solutions - Fresh Installation Script
# Installs a new instance with database, config, and dependencies.
#
# Usage: bash scripts/FreshInstall.sh
#

set -euo pipefail

# ── Colors ──────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m' # No Color

print_header() {
    echo ""
    echo -e "${CYAN}${BOLD}╔══════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}${BOLD}║       BiLLU - Fresh Installation             ║${NC}"
    echo -e "${CYAN}${BOLD}╚══════════════════════════════════════════════╝${NC}"
    echo ""
}

print_step() {
    echo -e "${GREEN}[✓]${NC} $1"
}

print_warn() {
    echo -e "${YELLOW}[!]${NC} $1"
}

print_error() {
    echo -e "${RED}[✗]${NC} $1"
}

print_info() {
    echo -e "${CYAN}[i]${NC} $1"
}

# ── Determine script and project root ──────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

print_header

# ── 1. Check PHP version and extensions ────────────────
echo -e "${BOLD}1. Checking system requirements...${NC}"

# Auto-detect PHP 8.x binary (Plesk, cPanel, system)
# Override: export PHP_BIN=/opt/plesk/php/8.3/bin/php
PHP_BIN="${PHP_BIN:-}"
PLESK_PATHS=(
    "/opt/plesk/php/8.3/bin/php"
    "/opt/plesk/php/8.2/bin/php"
    "/opt/plesk/php/8.1/bin/php"
)
CPANEL_PATHS=(
    "/opt/cpanel/ea-php83/root/usr/bin/php"
    "/opt/cpanel/ea-php82/root/usr/bin/php"
    "/opt/cpanel/ea-php81/root/usr/bin/php"
)
GENERIC_PATHS=(
    "/usr/bin/php8.3"
    "/usr/bin/php8.2"
    "/usr/bin/php8.1"
)

for candidate in "${PLESK_PATHS[@]}" "${CPANEL_PATHS[@]}" "${GENERIC_PATHS[@]}"; do
    if [ -x "$candidate" ]; then
        PHP_BIN="$candidate"
        break
    fi
done

# Fallback to system php
if [ -z "$PHP_BIN" ]; then
    if command -v php &>/dev/null; then
        PHP_BIN="$(command -v php)"
    else
        print_error "PHP is not installed. Please install PHP >= 8.1"
        exit 1
    fi
fi

PHP_VERSION=$("$PHP_BIN" -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')
PHP_MAJOR=$("$PHP_BIN" -r 'echo PHP_MAJOR_VERSION;')
PHP_MINOR=$("$PHP_BIN" -r 'echo PHP_MINOR_VERSION;')

if [ "$PHP_MAJOR" -lt 8 ] || { [ "$PHP_MAJOR" -eq 8 ] && [ "$PHP_MINOR" -lt 1 ]; }; then
    print_error "PHP >= 8.1 is required. Found: PHP $PHP_VERSION ($PHP_BIN)"
    print_info "Plesk: check /opt/plesk/php/8.x/bin/php"
    print_info "Or set: export PHP_BIN=/path/to/php8.x before running this script"
    exit 1
fi
print_step "PHP $PHP_VERSION detected ($PHP_BIN)"

REQUIRED_EXTENSIONS=(pdo_mysql mbstring json gd zip openssl)
MISSING_EXTENSIONS=()

for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    if ! "$PHP_BIN" -m 2>/dev/null | grep -qi "^${ext}$"; then
        MISSING_EXTENSIONS+=("$ext")
    fi
done

if [ ${#MISSING_EXTENSIONS[@]} -gt 0 ]; then
    print_error "Missing PHP extensions: ${MISSING_EXTENSIONS[*]}"
    print_info "Enable them in Plesk → PHP Settings → Extensions"
    exit 1
fi
print_step "All required PHP extensions present"

if ! command -v composer &>/dev/null; then
    print_error "Composer is not installed. Please install Composer first."
    print_info "See: https://getcomposer.org/download/"
    exit 1
fi
print_step "Composer detected"

if ! command -v mysql &>/dev/null; then
    print_error "MySQL client is not installed."
    exit 1
fi
print_step "MySQL client detected"
echo ""

# ── 2. Database configuration ──────────────────────────
echo -e "${BOLD}2. Database configuration${NC}"

read -rp "   Database host [localhost]: " DB_HOST
DB_HOST=${DB_HOST:-localhost}

read -rp "   Database port [3306]: " DB_PORT
DB_PORT=${DB_PORT:-3306}

read -rp "   Database name [faktury_ksef]: " DB_NAME
DB_NAME=${DB_NAME:-faktury_ksef}

read -rp "   Database username [root]: " DB_USER
DB_USER=${DB_USER:-root}

read -rsp "   Database password: " DB_PASS
echo ""

# Test connection
if ! mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" ${DB_PASS:+-p"$DB_PASS"} -e "SELECT 1;" &>/dev/null; then
    print_error "Cannot connect to MySQL with provided credentials."
    exit 1
fi
print_step "Database connection successful"
echo ""

# ── 3. Application configuration ──────────────────────
echo -e "${BOLD}3. Application configuration${NC}"

read -rp "   Application URL [http://localhost]: " APP_URL
APP_URL=${APP_URL:-http://localhost}
# Remove trailing slash
APP_URL="${APP_URL%/}"

read -rp "   System name [BiLLU]: " APP_NAME
APP_NAME=${APP_NAME:-BiLLU}

read -rp "   Admin username [admin]: " ADMIN_USER
ADMIN_USER=${ADMIN_USER:-admin}

read -rp "   Admin email [admin@localhost]: " ADMIN_EMAIL
ADMIN_EMAIL=${ADMIN_EMAIL:-admin@localhost}

while true; do
    read -rsp "   Admin password (min 8 chars): " ADMIN_PASS
    echo ""
    if [ ${#ADMIN_PASS} -ge 8 ]; then
        break
    fi
    print_warn "Password must be at least 8 characters."
done

echo ""

# ── 4. SMTP configuration (optional) ──────────────────
echo -e "${BOLD}4. SMTP configuration (optional, press Enter to skip)${NC}"

read -rp "   SMTP host [smtp.example.com]: " SMTP_HOST
SMTP_HOST=${SMTP_HOST:-smtp.example.com}

read -rp "   SMTP port [587]: " SMTP_PORT
SMTP_PORT=${SMTP_PORT:-587}

read -rp "   SMTP encryption (tls/ssl) [tls]: " SMTP_ENC
SMTP_ENC=${SMTP_ENC:-tls}

read -rp "   SMTP username: " SMTP_USER

read -rsp "   SMTP password: " SMTP_PASS
echo ""

read -rp "   From email [noreply@example.com]: " SMTP_FROM
SMTP_FROM=${SMTP_FROM:-noreply@example.com}

read -rp "   From name [$APP_NAME]: " SMTP_FROM_NAME
SMTP_FROM_NAME=${SMTP_FROM_NAME:-$APP_NAME}
echo ""

# ── 5. Generate secret key ────────────────────────────
SECRET_KEY=$("$PHP_BIN" -r 'echo bin2hex(random_bytes(32));')
print_step "Generated secret key"

# ── 6. Create database and run schema ─────────────────
echo -e "${BOLD}5. Setting up database...${NC}"

MYSQL_CMD="mysql -h $DB_HOST -P $DB_PORT -u $DB_USER ${DB_PASS:+-p$DB_PASS}"

# Create database if not exists
$MYSQL_CMD -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;" 2>/dev/null
print_step "Database '$DB_NAME' created/verified"

# Run schema (modify to use our DB name)
SCHEMA_SQL=$(cat "$PROJECT_ROOT/sql/schema.sql" | sed "s/faktury_ksef/$DB_NAME/g")
echo "$SCHEMA_SQL" | $MYSQL_CMD 2>/dev/null
print_step "Schema applied"

# Create migration tracking table
$MYSQL_CMD "$DB_NAME" < "$PROJECT_ROOT/sql/migration_v6.0_schema_migrations.sql" 2>/dev/null
print_step "Migration tracking table created"

# Record schema as applied
$MYSQL_CMD "$DB_NAME" -e "INSERT IGNORE INTO schema_migrations (filename) VALUES ('schema.sql');" 2>/dev/null

# ── 7. Run all migrations in order ────────────────────
echo -e "${BOLD}6. Applying migrations...${NC}"

MIGRATION_COUNT=0
for migration_file in $(ls "$PROJECT_ROOT/sql/migration_v"*.sql 2>/dev/null | sort); do
    filename=$(basename "$migration_file")

    # Check if already applied
    APPLIED=$($MYSQL_CMD "$DB_NAME" -N -e "SELECT COUNT(*) FROM schema_migrations WHERE filename='$filename';" 2>/dev/null)
    if [ "$APPLIED" -gt 0 ]; then
        print_info "Skipping (already applied): $filename"
        continue
    fi

    # Apply migration (replace DB name if hardcoded)
    MIGRATION_SQL=$(cat "$migration_file" | sed "s/faktury_ksef/$DB_NAME/g")
    echo "$MIGRATION_SQL" | $MYSQL_CMD "$DB_NAME" 2>/dev/null
    $MYSQL_CMD "$DB_NAME" -e "INSERT INTO schema_migrations (filename) VALUES ('$filename');" 2>/dev/null
    print_step "Applied: $filename"
    MIGRATION_COUNT=$((MIGRATION_COUNT + 1))
done

if [ "$MIGRATION_COUNT" -eq 0 ]; then
    print_info "No new migrations to apply"
else
    print_step "$MIGRATION_COUNT migration(s) applied"
fi

# ── 8. Create admin user ──────────────────────────────
echo -e "${BOLD}7. Creating admin user...${NC}"

ADMIN_HASH=$("$PHP_BIN" -r "echo password_hash('$ADMIN_PASS', PASSWORD_BCRYPT);")
$MYSQL_CMD "$DB_NAME" -e "
    INSERT INTO users (username, email, password_hash, role, is_active)
    VALUES ('$ADMIN_USER', '$ADMIN_EMAIL', '$ADMIN_HASH', 'superadmin', 1)
    ON DUPLICATE KEY UPDATE password_hash='$ADMIN_HASH', role='superadmin', is_active=1;
" 2>/dev/null
print_step "Admin user '$ADMIN_USER' created"

# Set system name in settings
$MYSQL_CMD "$DB_NAME" -e "
    INSERT INTO settings (setting_key, setting_value) VALUES ('system_name', '$APP_NAME')
    ON DUPLICATE KEY UPDATE setting_value='$APP_NAME';
" 2>/dev/null

# ── 9. Generate config files ─────────────────────────
echo -e "${BOLD}8. Generating configuration files...${NC}"

cat > "$PROJECT_ROOT/config/database.php" << DBEOF
<?php

return [
    'host'     => '$DB_HOST',
    'port'     => $DB_PORT,
    'database' => '$DB_NAME',
    'username' => '$DB_USER',
    'password' => '$DB_PASS',
    'charset'  => 'utf8mb4',
];
DBEOF
print_step "config/database.php generated"

cat > "$PROJECT_ROOT/config/app.php" << APPEOF
<?php

return [
    'name'       => '$APP_NAME',
    'url'        => '$APP_URL',
    'debug'      => false,
    'timezone'   => 'Europe/Warsaw',
    'locale'     => 'pl',
    'storage'    => __DIR__ . '/../storage',
    'secret_key' => '$SECRET_KEY',
];
APPEOF
print_step "config/app.php generated"

cat > "$PROJECT_ROOT/config/mail.php" << MAILEOF
<?php

return [
    'host'       => '$SMTP_HOST',
    'port'       => $SMTP_PORT,
    'encryption' => '$SMTP_ENC',
    'username'   => '$SMTP_USER',
    'password'   => '$SMTP_PASS',
    'from_email' => '$SMTP_FROM',
    'from_name'  => '$SMTP_FROM_NAME',
];
MAILEOF
print_step "config/mail.php generated"
echo ""

# ── 10. Install Composer dependencies ─────────────────
echo -e "${BOLD}9. Installing dependencies...${NC}"

cd "$PROJECT_ROOT"
composer install --no-dev --no-interaction --optimize-autoloader 2>&1 | tail -5
print_step "Composer dependencies installed"
echo ""

# ── 11. Set permissions ───────────────────────────────
echo -e "${BOLD}10. Setting permissions...${NC}"

STORAGE_DIR="$PROJECT_ROOT/storage"
mkdir -p "$STORAGE_DIR"/{invoices,exports,jpk,logs,cache,temp}
chmod -R 775 "$STORAGE_DIR"
print_step "Storage directories created with correct permissions"

# Protect config files
chmod 640 "$PROJECT_ROOT/config/database.php" 2>/dev/null || true
chmod 640 "$PROJECT_ROOT/config/mail.php" 2>/dev/null || true
print_step "Config files secured (640)"
echo ""

# ── Summary ───────────────────────────────────────────
echo -e "${CYAN}${BOLD}╔══════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}${BOLD}║         Installation Complete!                ║${NC}"
echo -e "${CYAN}${BOLD}╚══════════════════════════════════════════════╝${NC}"
echo ""
echo -e "   ${BOLD}Application URL:${NC}  $APP_URL"
echo -e "   ${BOLD}Admin login:${NC}      $ADMIN_USER"
echo -e "   ${BOLD}Admin email:${NC}      $ADMIN_EMAIL"
echo -e "   ${BOLD}Database:${NC}         $DB_NAME @ $DB_HOST:$DB_PORT"
echo ""
echo -e "   ${BOLD}Login at:${NC}         ${GREEN}$APP_URL/master-login${NC}"
echo ""
print_warn "Remember to configure your web server (Apache/Nginx)"
print_warn "to point the document root to: $PROJECT_ROOT/public"
echo ""
print_info "For Apache, enable mod_rewrite and add AllowOverride All"
print_info "For Nginx, see the docs for PHP-FPM + URL rewrite config"
echo ""
