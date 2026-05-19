#!/usr/bin/env bash

set -euo pipefail

APP_DIR="/opt/caddypanel"
ADMINER_URL="${ADMINER_URL:-https://www.adminer.org/latest-mysql-en.php}"
FILEGATOR_API_URL="${FILEGATOR_API_URL:-https://api.github.com/repos/filegator/filegator/releases/latest}"
FILEGATOR_ZIP_URL="${FILEGATOR_ZIP_URL:-https://github.com/filegator/filegator/archive/refs/heads/master.zip}"
PANEL_DOMAIN=""
ADMIN_EMAIL=""
ADMIN_USER="admin"
ADMIN_PASSWORD=""
MARIADB_SERVICE_USER="caddypanel_admin"
PHP_VERSION=""
PHP_FPM_SOCKET=""
PHP_FPM_SERVICE=""

require_root() {
    if [[ "${EUID}" -ne 0 ]]; then
        echo "This installer must be run as root." >&2
        exit 1
    fi
}

read_config() {
    read -r -p "Panel domain (example: panel.example.com): " PANEL_DOMAIN
    read -r -p "Admin email for Caddy TLS: " ADMIN_EMAIL
    read -r -p "Admin username [admin]: " ADMIN_USER_INPUT
    ADMIN_USER="${ADMIN_USER_INPUT:-admin}"
    read -r -s -p "Admin password (min 8 chars): " ADMIN_PASSWORD
    echo

    if [[ -z "$PANEL_DOMAIN" || -z "$ADMIN_EMAIL" || -z "$ADMIN_USER" || -z "$ADMIN_PASSWORD" ]]; then
        echo "All fields are required." >&2
        exit 2
    fi

    if [[ ${#ADMIN_PASSWORD} -lt 8 ]]; then
        echo "Admin password must be at least 8 characters." >&2
        exit 2
    fi

    if [[ ! "$PANEL_DOMAIN" =~ ^([a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$ ]]; then
        echo "Invalid panel domain." >&2
        exit 2
    fi
}

install_packages() {
    apt-get update
    apt-get install -y \
        ca-certificates \
        curl \
        git \
        composer \
        rsync \
        unzip \
        sqlite3 \
        mariadb-server \
        caddy \
        php-cli \
        php-fpm \
        php-sqlite3 \
        php-mysql \
        php-mbstring \
        php-xml \
        php-curl \
        php-zip \
        php-gd \
        php-intl
}

detect_php_runtime() {
    PHP_VERSION="$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')"
    PHP_FPM_SOCKET="/run/php/php${PHP_VERSION}-fpm.sock"
    PHP_FPM_SERVICE="php${PHP_VERSION}-fpm"

    if ! systemctl list-unit-files "${PHP_FPM_SERVICE}.service" --no-legend | grep -q "${PHP_FPM_SERVICE}.service"; then
        PHP_FPM_SERVICE="$(systemctl list-unit-files 'php*-fpm.service' --no-legend | awk '{print $1}' | sed 's/\\.service$//' | sort -V | tail -n 1)"
    fi

    if [[ -z "$PHP_FPM_SERVICE" ]]; then
        echo "Unable to detect PHP-FPM service." >&2
        exit 2
    fi

    if [[ "$PHP_FPM_SERVICE" =~ php([0-9]+\.[0-9]+)-fpm ]]; then
        PHP_VERSION="${BASH_REMATCH[1]}"
        PHP_FPM_SOCKET="/run/php/php${PHP_VERSION}-fpm.sock"
    fi

    echo "Detected PHP ${PHP_VERSION}, PHP-FPM service ${PHP_FPM_SERVICE}, socket ${PHP_FPM_SOCKET}"
}

create_directories() {
    mkdir -p "$APP_DIR" \
        "$APP_DIR/config" \
        "$APP_DIR/var/data" \
        "$APP_DIR/var/generated/caddy" \
        "$APP_DIR/var/backups" \
        "$APP_DIR/var/logs" \
        "$APP_DIR/apps/adminer" \
        "$APP_DIR/apps/filegator" \
        /etc/caddy/sites \
        /var/www/sites \
        /var/backups/caddypanel \
        /var/log/caddypanel
}

configure_log_directory() {
    local caddy_user="caddy"

    if ! id -u "$caddy_user" >/dev/null 2>&1; then
        caddy_user="www-data"
    fi

    chown "$caddy_user:$caddy_user" /var/log/caddypanel
    chmod 750 /var/log/caddypanel
}

configure_caddy_php_access() {
    if id -u caddy >/dev/null 2>&1 && getent group www-data >/dev/null 2>&1; then
        usermod -aG www-data caddy
    fi
}

copy_application() {
    rsync -a --delete \
        --exclude ".git" \
        --exclude "var/data/*.sqlite" \
        --exclude "var/generated/caddy/*.pending" \
        --exclude "var/backups/*" \
        --exclude "config/secret.key" \
        ./ "$APP_DIR/"

    find "$APP_DIR/bin" -type f -exec chmod 755 {} \;
    chown -R www-data:www-data "$APP_DIR"
    chown root:www-data "$APP_DIR/config"
    chmod 750 "$APP_DIR/config"
}

install_integrated_apps() {
    install_adminer
    install_filegator
}

install_adminer() {
    mkdir -p "$APP_DIR/apps/adminer"
    curl -fsSL "$ADMINER_URL" -o "$APP_DIR/apps/adminer/adminer.php"
    chown -R www-data:www-data "$APP_DIR/apps/adminer"
}

install_filegator() {
    mkdir -p "$APP_DIR/apps/filegator"

    FILEGATOR_ZIP="$(mktemp)"
    FILEGATOR_WORK="$(mktemp -d)"

    cleanup_filegator_install() {
        rm -f "$FILEGATOR_ZIP"
        rm -rf "$FILEGATOR_WORK"
    }
    trap cleanup_filegator_install RETURN

    FILEGATOR_RELEASE_JSON="$(curl -fsSL -H 'User-Agent: CaddyPanel-Installer' -H 'Accept: application/vnd.github+json' "$FILEGATOR_API_URL" || true)"

    if [[ -n "$FILEGATOR_RELEASE_JSON" ]]; then
        FILEGATOR_DOWNLOAD_URL="$(
            printf '%s' "$FILEGATOR_RELEASE_JSON" | php -r '
            $json = stream_get_contents(STDIN);
            $release = json_decode($json, true);
            foreach (($release["assets"] ?? []) as $asset) {
                $name = $asset["name"] ?? "";
                $url = $asset["browser_download_url"] ?? "";
                if ($url !== "" && preg_match("/\\.zip$/i", $name)) {
                    echo $url;
                    exit(0);
                }
            }
            if (!empty($release["zipball_url"])) {
                echo $release["zipball_url"];
                exit(0);
            }
            exit(0);
            '
        )"
    fi

    if [[ -z "${FILEGATOR_DOWNLOAD_URL:-}" ]]; then
        echo "Using FileGator fallback ZIP URL because release metadata is unavailable or incomplete."
        FILEGATOR_DOWNLOAD_URL="$FILEGATOR_ZIP_URL"
    fi

    echo "Downloading FileGator from $FILEGATOR_DOWNLOAD_URL"

    curl -fsSL "$FILEGATOR_DOWNLOAD_URL" -o "$FILEGATOR_ZIP"
    unzip -q "$FILEGATOR_ZIP" -d "$FILEGATOR_WORK"

    FILEGATOR_SOURCE="$(find "$FILEGATOR_WORK" -mindepth 1 -maxdepth 1 -type d | head -n 1)"

    if [[ -z "$FILEGATOR_SOURCE" || ! -f "$FILEGATOR_SOURCE/index.php" ]]; then
        echo "Downloaded FileGator archive does not contain index.php." >&2
        exit 2
    fi

    rsync -a --delete "$FILEGATOR_SOURCE/" "$APP_DIR/apps/filegator/"
    mkdir -p "$APP_DIR/apps/filegator/repository"

    if [[ -f "$APP_DIR/apps/filegator/composer.json" ]]; then
        composer install \
            --working-dir="$APP_DIR/apps/filegator" \
            --no-dev \
            --prefer-dist \
            --no-interaction \
            --optimize-autoloader
    fi

    if [[ ! -f "$APP_DIR/apps/filegator/configuration.php" && -f "$APP_DIR/apps/filegator/configuration_sample.php" ]]; then
        cp "$APP_DIR/apps/filegator/configuration_sample.php" "$APP_DIR/apps/filegator/configuration.php"
    fi

    mkdir -p "$APP_DIR/apps/filegator/private"
    cat > "$APP_DIR/apps/filegator/private/users.json" <<'JSON'
{
  "1": {
    "username": "guest",
    "name": "CaddyPanel",
    "role": "guest",
    "homedir": "/",
    "permissions": "read|write|upload|download|batchdownload|zip|chmod",
    "password": ""
  }
}
JSON

    cat > "$APP_DIR/apps/filegator/dist/caddypanel.php" <<'PHP'
<?php

define('APP_ENV', 'production');
define('APP_PUBLIC_PATH', '/files/');

require __DIR__ . '/index.php';
PHP

    if [[ -f "$APP_DIR/apps/filegator/configuration.php" ]]; then
        APP_DIR="$APP_DIR" php -r '
            $path = getenv("APP_DIR") . "/apps/filegator/configuration.php";
            $config = file_get_contents($path);
            $config = str_replace("__DIR__ . '/repository'", "'/var/www/sites'", $config);
            $config = str_replace("__DIR__.'/repository'", "'/var/www/sites'", $config);
            file_put_contents($path, $config);
        '
    fi

    chown -R www-data:www-data "$APP_DIR/apps/filegator"
}

initialize_panel() {
    CADDYPANEL_ADMIN_USER="$ADMIN_USER" \
    CADDYPANEL_ADMIN_PASSWORD="$ADMIN_PASSWORD" \
    CADDYPANEL_PANEL_DOMAIN="$PANEL_DOMAIN" \
    CADDYPANEL_ADMIN_EMAIL="$ADMIN_EMAIL" \
    CADDYPANEL_DEFAULT_PHP_VERSION="$PHP_VERSION" \
    CADDYPANEL_DEFAULT_PHP_FPM_SOCKET="$PHP_FPM_SOCKET" \
    APP_ENV=production \
    php "$APP_DIR/bin/init-production.php"

    chown root:www-data "$APP_DIR/config/secret.key"
    chmod 640 "$APP_DIR/config/secret.key"
    mkdir -p "$APP_DIR/var/data" "$APP_DIR/var/generated/caddy" "$APP_DIR/var/backups" "$APP_DIR/var/logs"
    chown -R www-data:www-data "$APP_DIR/var"
}

configure_caddy() {
    if [[ -f /etc/caddy/Caddyfile ]] && grep -q "root \\* /usr/share/caddy" /etc/caddy/Caddyfile; then
        cp /etc/caddy/Caddyfile "/etc/caddy/Caddyfile.caddypanel-backup.$(date +%Y%m%d%H%M%S)"
        cat > /etc/caddy/Caddyfile <<EOF
{
    email $ADMIN_EMAIL
}

import /etc/caddy/sites/*.caddy
EOF
    elif [[ ! -f /etc/caddy/Caddyfile ]]; then
        cat > /etc/caddy/Caddyfile <<EOF
{
    email $ADMIN_EMAIL
}

import /etc/caddy/sites/*.caddy
EOF
    elif ! grep -q "import /etc/caddy/sites/\\*.caddy" /etc/caddy/Caddyfile; then
        printf '\nimport /etc/caddy/sites/*.caddy\n' >> /etc/caddy/Caddyfile
    fi

    sed \
        -e "s/{panel_domain}/$PANEL_DOMAIN/g" \
        -e "s#{panel_php_fpm_socket}#$PHP_FPM_SOCKET#g" \
        "$APP_DIR/caddy/templates/panel.caddy.tpl" > /etc/caddy/sites/caddypanel.caddy
    caddy validate --config /etc/caddy/Caddyfile
}

configure_sudoers() {
    cp "$APP_DIR/install/sudoers.tpl" /etc/sudoers.d/caddypanel
    chmod 440 /etc/sudoers.d/caddypanel
    visudo -cf /etc/sudoers.d/caddypanel
}

configure_cron() {
    cat > /etc/cron.d/caddypanel <<EOF
* * * * * www-data php $APP_DIR/bin/backup-scheduler.php >/dev/null 2>&1
0 * * * * www-data php $APP_DIR/bin/update-cron.php >/dev/null 2>&1
EOF
    chmod 644 /etc/cron.d/caddypanel
}

configure_mariadb_service_user() {
    SERVICE_PASSWORD="$(openssl rand -base64 32 | tr -d '\n')"

    mysql <<SQL
CREATE USER IF NOT EXISTS '$MARIADB_SERVICE_USER'@'localhost' IDENTIFIED BY '$SERVICE_PASSWORD';
GRANT CREATE, DROP, ALTER, INDEX, SELECT, INSERT, UPDATE, DELETE, CREATE USER, RELOAD ON *.* TO '$MARIADB_SERVICE_USER'@'localhost' WITH GRANT OPTION;
FLUSH PRIVILEGES;
SQL

    cat > "$APP_DIR/config/mariadb-service.cnf" <<EOF
[client]
user=$MARIADB_SERVICE_USER
password=$SERVICE_PASSWORD
host=localhost
EOF

    chown root:www-data "$APP_DIR/config/mariadb-service.cnf"
    chmod 640 "$APP_DIR/config/mariadb-service.cnf"
}

start_services() {
    systemctl enable "$PHP_FPM_SERVICE" mariadb caddy
    systemctl restart "$PHP_FPM_SERVICE"
    systemctl restart mariadb
    systemctl reload caddy || systemctl restart caddy
}

print_summary() {
    cat <<EOF

CaddyPanel installation complete.

URL:
  https://$PANEL_DOMAIN

Admin user:
  $ADMIN_USER

IMPORTANT:
  Back up $APP_DIR/config/secret.key safely.
  Without it, encrypted database passwords cannot be recovered.

EOF
}

main() {
    require_root
    read_config
    install_packages
    detect_php_runtime
    create_directories
    configure_log_directory
    configure_caddy_php_access
    copy_application
    install_integrated_apps
    initialize_panel
    configure_caddy
    configure_sudoers
    configure_cron
    configure_mariadb_service_user
    start_services
    print_summary
}

main "$@"
