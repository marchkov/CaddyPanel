#!/usr/bin/env bash

set -euo pipefail

APP_DIR="/opt/caddypanel"
MARIADB_SERVICE_USER="caddypanel_admin"
ASSUME_YES="0"
PURGE_SITES="0"
PURGE_BACKUPS="0"
PURGE_PACKAGES="0"

usage() {
    cat <<EOF
Usage: sudo bash uninstall.sh [options]

Removes CaddyPanel files, panel Caddy config, cron, sudoers, and MariaDB service user.

Options:
  --yes             do not prompt for confirmation
  --purge-sites     also remove /var/www/sites
  --purge-backups   also remove /var/backups/caddypanel
  --purge-packages  also apt purge CaddyPanel runtime packages
  --help            show this help

By default, user sites, backups, and system packages are kept.
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --yes|-y) ASSUME_YES="1"; shift ;;
        --purge-sites) PURGE_SITES="1"; shift ;;
        --purge-backups) PURGE_BACKUPS="1"; shift ;;
        --purge-packages) PURGE_PACKAGES="1"; shift ;;
        --help|-h) usage; exit 0 ;;
        *) echo "Unknown option: $1" >&2; usage >&2; exit 2 ;;
    esac
done

require_root() {
    if [[ "${EUID}" -ne 0 ]]; then
        echo "This uninstaller must be run as root." >&2
        exit 1
    fi
}

confirm() {
    if [[ "$ASSUME_YES" == "1" ]]; then
        return
    fi

    cat <<EOF
This will remove CaddyPanel from this VPS:

  remove: $APP_DIR
  remove: /etc/caddy/sites/caddypanel.caddy
  remove: /etc/cron.d/caddypanel
  remove: /etc/sudoers.d/caddypanel
  remove: MariaDB user '$MARIADB_SERVICE_USER'@'localhost'
  remove: /var/log/caddypanel

Kept by default:
  /var/www/sites
  /var/backups/caddypanel
  apt runtime packages

EOF

    if [[ "$PURGE_SITES" == "1" ]]; then
        echo "Also removing: /var/www/sites"
    fi

    if [[ "$PURGE_BACKUPS" == "1" ]]; then
        echo "Also removing: /var/backups/caddypanel"
    fi

    if [[ "$PURGE_PACKAGES" == "1" ]]; then
        echo "Also purging CaddyPanel runtime packages."
    fi

    read -r -p "Type REMOVE CADDYPANEL to continue: " answer

    if [[ "$answer" != "REMOVE CADDYPANEL" ]]; then
        echo "Aborted."
        exit 1
    fi
}

remove_caddy_config() {
    rm -f /etc/caddy/sites/caddypanel.caddy

    if [[ -d /etc/caddy/sites ]] && ! find /etc/caddy/sites -maxdepth 1 -type f -name '*.caddy' | grep -q .; then
        if [[ -f /etc/caddy/Caddyfile ]]; then
            sed -i '\#^import /etc/caddy/sites/\*.caddy$#d' /etc/caddy/Caddyfile
        fi
    fi

    if command -v caddy >/dev/null 2>&1 && [[ -f /etc/caddy/Caddyfile ]]; then
        caddy validate --config /etc/caddy/Caddyfile
    fi

    if systemctl list-unit-files caddy.service --no-legend >/dev/null 2>&1; then
        systemctl reload caddy || systemctl restart caddy || true
    fi
}

remove_mariadb_service_user() {
    if command -v mysql >/dev/null 2>&1; then
        mysql <<SQL || true
DROP USER IF EXISTS '$MARIADB_SERVICE_USER'@'localhost';
FLUSH PRIVILEGES;
SQL
    fi
}

remove_panel_files() {
    rm -f /etc/cron.d/caddypanel
    rm -f /etc/sudoers.d/caddypanel
    rm -rf "$APP_DIR"
    rm -rf /var/log/caddypanel

    if [[ "$PURGE_SITES" == "1" ]]; then
        rm -rf /var/www/sites
    fi

    if [[ "$PURGE_BACKUPS" == "1" ]]; then
        rm -rf /var/backups/caddypanel
    fi
}

purge_packages() {
    if [[ "$PURGE_PACKAGES" != "1" ]]; then
        return
    fi

    apt-get purge -y \
        composer \
        rsync \
        sqlite3 \
        caddy \
        mariadb-server \
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
    apt-get autoremove -y
}

print_summary() {
    cat <<EOF

CaddyPanel uninstall complete.

Kept unless explicitly purged:
  /var/www/sites
  /var/backups/caddypanel

If DNS pointed to this panel, remove or repoint that DNS record.

EOF
}

main() {
    require_root
    confirm
    remove_caddy_config
    remove_mariadb_service_user
    remove_panel_files
    purge_packages
    print_summary
}

main "$@"
