#!/usr/bin/env bash

set -euo pipefail

APP_DIR="/opt/caddypanel"
ADMINER_URL="${ADMINER_URL:-https://www.adminer.org/latest-mysql-en.php}"
FILEGATOR_API_URL="${FILEGATOR_API_URL:-https://api.github.com/repos/filegator/filegator/releases/latest}"
PANEL_DOMAIN=""
ADMIN_EMAIL=""
ADMIN_USER="admin"
ADMIN_PASSWORD=""
MARIADB_SERVICE_USER="caddypanel_admin"

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
    read -r -s -p "Admin password (min 12 chars): " ADMIN_PASSWORD
    echo

    if [[ -z "$PANEL_DOMAIN" || -z "$ADMIN_EMAIL" || -z "$ADMIN_USER" || -z "$ADMIN_PASSWORD" ]]; then
        echo "All fields are required." >&2
        exit 2
    fi

    if [[ ${#ADMIN_PASSWORD} -lt 12 ]]; then
        echo "Admin password must be at least 12 characters." >&2
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
        php8.4-cli \
        php8.4-fpm \
        php8.4-sqlite3 \
        php8.4-mysql \
        php8.4-mbstring \
        php8.4-xml \
        php8.4-curl \
        php8.4-zip \
        php8.4-gd \
        php8.4-intl
}

create_directories() {
    mkdir -p "$APP_DIR" \
        "$APP_DIR/data" \
        "$APP_DIR/config" \
        "$APP_DIR/apps/adminer" \
        "$APP_DIR/apps/filegator" \
        /etc/caddy/sites \
        /var/www/sites \
        /var/backups/caddypanel \
        /var/log/caddypanel
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

    FILEGATOR_DOWNLOAD_URL="$(
        FILEGATOR_API_URL="$FILEGATOR_API_URL" php -r '
            $json = file_get_contents(getenv("FILEGATOR_API_URL"));
            if ($json === false) {
                fwrite(STDERR, "Unable to fetch FileGator release metadata.\n");
                exit(2);
            }
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
            fwrite(STDERR, "No FileGator ZIP download URL found.\n");
            exit(2);
        '
    )"

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

    if [[ ! -f "$APP_DIR/apps/filegator/private/users.json" && -f "$APP_DIR/apps/filegator/private/users.json.blank" ]]; then
        cp "$APP_DIR/apps/filegator/private/users.json.blank" "$APP_DIR/apps/filegator/private/users.json"
    fi

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
    APP_ENV=production \
    php "$APP_DIR/bin/init-production.php"

    chown root:www-data "$APP_DIR/config/secret.key"
    chmod 640 "$APP_DIR/config/secret.key"
    chown -R www-data:www-data "$APP_DIR/data" "$APP_DIR/var"
}

configure_caddy() {
    if [[ ! -f /etc/caddy/Caddyfile ]]; then
        cat > /etc/caddy/Caddyfile <<EOF
{
    email $ADMIN_EMAIL
}

import /etc/caddy/sites/*.caddy
EOF
    elif ! grep -q "import /etc/caddy/sites/\\*.caddy" /etc/caddy/Caddyfile; then
        printf '\nimport /etc/caddy/sites/*.caddy\n' >> /etc/caddy/Caddyfile
    fi

    sed "s/{panel_domain}/$PANEL_DOMAIN/g" "$APP_DIR/caddy/templates/panel.caddy.tpl" > /etc/caddy/sites/caddypanel.caddy
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
    systemctl enable php8.4-fpm mariadb caddy
    systemctl restart php8.4-fpm
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
    create_directories
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
