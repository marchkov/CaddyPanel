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
Debian 12/13 or Ubuntu 24.04 with PHP 8.4 packages available
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
