# CaddyPanel

CaddyPanel is a minimal self-hosted VPS control panel for a trusted single-server stack:

- Caddy
- PHP-FPM
- MariaDB
- SQLite for panel metadata
- Adminer
- FileGator

The current implementation is a fresh foundation based on `CaddyPanel_Project_Brief_v0.1.md`.

## Local Development

Requirements:

- PHP 8.2+
- PHP SQLite extension
- PHP OpenSSL extension for encrypted database passwords

Initialize the local database and development admin user:

```bash
php bin/init-dev.php
```

Run the development server:

```bash
php -S 127.0.0.1:8080 -t public
```

Open:

```text
http://127.0.0.1:8080
```

Development credentials:

```text
admin / password123
```

These credentials are for local development only.

On Windows PHP builds, enable OpenSSL in `C:\php\php.ini` before using database creation/password reveal:

```ini
extension=openssl
```

## Production Helper Scripts

The web app does not run as root. Production filesystem actions are routed through small helper scripts:

```text
bin/site-create-dirs
bin/site-delete-files
```

In `APP_ENV=local`, these commands are skipped by `CommandRunner` and no `/var/www` paths are touched.

## Caddy Config Preview

Site creation renders a pending Caddy config preview into:

```text
var/generated/caddy/
```

This preview is passed to the safe Caddy apply helper in production mode. In local mode, apply is skipped by `CommandRunner`.

Safe Caddy helpers:

```text
bin/caddy-apply-site-config
bin/caddy-disable-site
bin/caddy-validate
bin/caddy-reload
```

In production, the apply helper validates, backs up the previous config, applies the pending config, validates the full Caddyfile, reloads Caddy, and rolls back on failure.

## Database Helpers

Database provisioning is routed through:

```text
bin/db-create
bin/db-delete
```

In `APP_ENV=local`, these commands are skipped. In production, they use:

```text
/opt/caddypanel/config/mariadb-service.cnf
```

That file is created later by the installer and belongs to the MariaDB service-user flow.

Site creation can create a linked MariaDB database immediately when the form option is selected. Site deletion with `Delete database` removes linked database records through the same provisioning flow.

## Integrated Apps

Protected routes exist for built-in app integrations:

```text
/db     Adminer status page
/files  FileGator status page
```

Both routes require login and respect module toggles. The installer will place the actual app files under:

```text
apps/adminer/adminer.php
apps/filegator/index.php
```

Installer defaults:

```text
Adminer: https://www.adminer.org/latest-mysql-en.php
FileGator: latest GitHub release zipball from filegator/filegator
```

FileGator is configured to use `/var/www/sites` as its storage root.

Adminer is handed off directly by `/db` after CaddyPanel authentication when `apps/adminer/adminer.php` exists.

FileGator is protected at the Caddy route level in production. Requests to `/files/*` run a FastCGI auth probe before Caddy serves FileGator from:

```text
/opt/caddypanel/apps/filegator/dist
```

The auth probe checks the CaddyPanel session and the `filegator` module state:

```text
/filegator-auth.php
```

An HTTP auth probe also exists:

```text
/auth/check
```

It returns `204` for authenticated sessions and `401` otherwise.

## Backups

Manual backups are available at:

```text
/backups
```

In local mode, CaddyPanel creates a JSON manifest under `var/backups`. In production, provisioning uses:

```text
bin/backup-create
```

Scheduled backups use:

```text
bin/backup-scheduler.php
```

Cron example:

```cron
* * * * * php /opt/caddypanel/bin/backup-scheduler.php >/dev/null 2>&1
```

When a site has an active linked database record, production backups include a `mysqldump` SQL file under `database/` inside the archive.

Scheduled backup jobs respect their component flags: files, linked database, and Caddy config can be included or skipped per job.

## Restore

Restore foundation is available at:

```text
/restore
```

It inspects successful backup runs and can apply selected restore modes. Before applying a restore, CaddyPanel creates a pre-restore backup of the current site. File restore switches the site directory after preparing restored files in staging. Host config restore validates the full Caddyfile and reloads Caddy, rolling the config back if validation or reload fails.

Database restore requires an active linked database record for the site and a SQL dump in the selected backup archive. The dump is restored into the currently linked database.

Production staging helper:

```text
bin/backup-restore
```

## Updates

Admin-only update UI:

```text
/updates
```

Helper scripts:

```text
bin/update-check
bin/update-apply
bin/update-cron.php
```

`update-check` fetches the configured Git remote/branch and reports whether the local checkout is behind. `update-apply` refuses dirty worktrees and applies only fast-forward updates.

Automatic checks can be scheduled with cron:

```cron
0 * * * * php /opt/caddypanel/bin/update-cron.php >/dev/null 2>&1
```

## Users

Admin-only user management:

```text
/users
```

Admins can create `admin` and `manager` accounts, deactivate/reactivate users, and reset passwords. Self-deactivation is blocked.

## PHP Versions

Admin-only PHP-FPM detection:

```text
/php-versions
```

CaddyPanel detects installed sockets with:

```text
bin/php-fpm-detect
```

The panel stores detected versions in SQLite, lets an admin choose the default PHP-FPM socket, and uses detected versions in the site creation form. It does not install PHP versions from the web UI.

## Installer

Production installer foundation:

```bash
sudo bash install.sh
```

It installs packages, copies the app to `/opt/caddypanel`, initializes SQLite, creates `secret.key`, writes Caddy config, configures sudoers, creates a MariaDB service user, and starts services.

Target platform:

```text
Debian 12/13 or Ubuntu 24.04
```

The installer uses the PHP version provided by the distribution packages, then detects the matching PHP-FPM service and socket automatically.

## Production Installation

Use a fresh VPS. The installer assumes it can manage Caddy, PHP-FPM, MariaDB, sudoers, cron, and `/opt/caddypanel`.

Before installing:

- point a DNS `A` record for the panel domain to the VPS public IP;
- make sure ports `80` and `443` are open;
- log in as a sudo-capable user;
- decide the panel domain, admin email, admin username, and admin password.

Clone and install:

```bash
sudo apt-get update
sudo apt-get install -y git
git clone https://github.com/marchkov/CaddyPanel.git
cd CaddyPanel
sudo bash install.sh
```

The installer prompts for:

```text
Panel domain
Admin email for Caddy TLS
Admin username
Admin password, minimum 8 characters
```

After install, open:

```text
https://your-panel-domain.example
```

Important files and directories:

```text
/opt/caddypanel                         app installation
/opt/caddypanel/config/secret.key       encryption key, back this up
/opt/caddypanel/config/mariadb-service.cnf
/etc/caddy/Caddyfile
/etc/caddy/sites/caddypanel.caddy
/etc/sudoers.d/caddypanel
/etc/cron.d/caddypanel
/var/www/sites
/var/backups/caddypanel
/var/log/caddypanel
```

Post-install checks:

```bash
systemctl status caddy --no-pager
systemctl status 'php*-fpm' --no-pager
systemctl status mariadb --no-pager
caddy validate --config /etc/caddy/Caddyfile
sudo -u www-data php /opt/caddypanel/bin/backup-scheduler.php
sudo -u www-data php /opt/caddypanel/bin/update-cron.php
```

The installer uses generic PHP packages such as `php-cli`, `php-fpm`, `php-sqlite3`, and `php-mysql`. It then detects the installed PHP-FPM service, for example `php8.3-fpm`, and writes the matching socket into CaddyPanel settings.

If Caddy validation fails, inspect:

```bash
journalctl -u caddy -n 100 --no-pager
caddy validate --config /etc/caddy/Caddyfile
```

If FileGator install fails, verify outbound HTTPS access and Composer:

```bash
composer --version
curl -I https://api.github.com/repos/filegator/filegator/releases/latest
```

Do not delete `/opt/caddypanel/config/secret.key`. Without it, encrypted database passwords stored by the panel cannot be decrypted.

## Uninstall

To remove CaddyPanel from a VPS:

```bash
cd ~/CaddyPanel
sudo bash uninstall.sh
```

The default uninstall removes:

```text
/opt/caddypanel
/etc/caddy/sites/caddypanel.caddy
/etc/cron.d/caddypanel
/etc/sudoers.d/caddypanel
/var/log/caddypanel
MariaDB user caddypanel_admin@localhost
```

The default uninstall keeps user data:

```text
/var/www/sites
/var/backups/caddypanel
runtime packages installed by apt
```

For a fuller cleanup:

```bash
sudo bash uninstall.sh --purge-sites --purge-backups
```

To also purge CaddyPanel runtime packages:

```bash
sudo bash uninstall.sh --purge-sites --purge-backups --purge-packages
```

For non-interactive removal:

```bash
sudo bash uninstall.sh --yes
```

## System Status

Dashboard service checks use a read-only helper:

```text
bin/system-status
```

In local mode, status values are shown as `local`.

## Logs

Managers and admins can open:

```text
/logs
```

The logs screen links to bounded site log tails:

```text
/logs/sites/{id}?type=access
/logs/sites/{id}?type=error
```

Site log output is capped to the last 500 lines / 256 KB. Audit logs remain admin-only.
