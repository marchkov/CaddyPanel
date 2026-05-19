# CaddyPanel — Project Brief v0.1

_Last updated: 2026-05-18_

## 1. Overview

**CaddyPanel** is a minimal self-hosted VPS control panel for managing a single trusted server stack:

- **Caddy**;
- **PHP-FPM**;
- **MariaDB**;
- **SQLite** for panel metadata;
- **Adminer** for database UI;
- **FileGator** for file management.

CaddyPanel is not a full hosting platform. It is a small personal/team VPS workbench for developers, freelancers, small operators, and technically capable users who want a clean web interface for common repetitive server tasks.

The panel should make server changes transparent, inspectable, and manually repairable.

A user should be able to look at generated files, understand what changed, and fix the system manually if needed.

---

## 2. Product Positioning

CaddyPanel is **not** intended to replace:

- cPanel;
- Plesk;
- Hestia;
- aaPanel;
- full reseller hosting panels;
- billing/hosting automation platforms.

It is closer to a **personal VPS workbench**:

- one VPS;
- one admin or a small trusted team;
- no reseller model;
- no client isolation;
- no billing;
- no mail stack;
- no DNS hosting;
- no hidden orchestration layer.

CaddyPanel should manage the stack clearly and predictably:

```text
Caddy + PHP-FPM + MariaDB + Adminer + FileGator
```

---

## 3. Core Principles

### Simplicity

Use plain PHP and straightforward server scripts.

Avoid in the first version:

- heavy frameworks;
- complex dependency graphs;
- background daemons;
- plugin marketplaces;
- hidden orchestration layers.

The codebase should stay small, readable, and easy to inspect.

### Transparency

Generated configs and managed files should live in predictable locations:

```text
/etc/caddy/sites/                 # generated Caddy site configs
/var/www/sites/                   # managed websites
/var/backups/caddypanel/          # local backup archives
/var/log/caddypanel/              # panel logs
/opt/caddypanel/                  # panel code
```

A technically capable user should be able to inspect and edit generated configuration manually.

### Safety

The PHP web app must not run as root.

Privileged actions must go through small, auditable helper scripts with restricted sudo permissions.

Important safety rules:

- validate all input before system operations;
- validate Caddy config before reload;
- use prepared SQL statements;
- protect POST actions with CSRF tokens;
- hash panel passwords with `password_hash()`;
- verify passwords with `password_verify()`;
- avoid shell interpolation;
- use `escapeshellarg()` where shell execution is unavoidable;
- keep sudoers narrowly scoped;
- never expose Adminer/FileGator without panel authentication.

### Built-in Modules, Not Plugins

CaddyPanel supports built-in modules that can be enabled or disabled.

This is **not** a third-party plugin system and not a marketplace.

Modules are built-in features controlled by panel settings.

### Confirmation Before Dangerous Actions

Dangerous or destructive operations must always use a confirmation screen.

This includes:

- **Disable host**;
- **Delete files**;
- **Delete database**;
- **Restore backup**.

No destructive action should be executed directly from a list page by a single button click.

---

## 4. Target Platform

Initial target:

- Debian 12/13 or Ubuntu 24.04;
- Caddy 2;
- PHP-FPM;
- PHP 8.4 for initial installation;
- MariaDB;
- SQLite for panel data;
- Bash/PHP CLI helper scripts;
- simple server-rendered UI;
- Bootstrap or lightweight custom CSS.

---

## 5. PHP Version Policy

The installer installs only:

```text
PHP 8.4
```

Additional PHP versions are not installed by CaddyPanel.

If the administrator needs legacy or alternative PHP versions, they must install them manually at the OS level.

CaddyPanel can detect installed PHP-FPM sockets and, if the `php_versions` module is enabled, allow:

- admin to choose the default PHP version;
- a site to choose a PHP version.

The panel does not install PHP versions from the web UI.

Example detected sockets:

```text
/run/php/php8.4-fpm.sock
/run/php/php8.3-fpm.sock
/run/php/php7.4-fpm.sock
```

Default after install:

```text
PHP version: 8.4
PHP-FPM socket: /run/php/php8.4-fpm.sock
```

---

## 6. MVP Scope

MVP v0.1 should include:

1. Auth;
2. Roles;
3. Dashboard;
4. Built-in module registry;
5. Sites;
6. Databases;
7. Adminer integration;
8. FileGator integration;
9. Manual and scheduled backups;
10. Restore;
11. Logs;
12. Settings;
13. `install.sh` installer.

The first usable release does not need every advanced option, but each included module should work end to end.

---

## 7. Out of Scope for MVP

Do not include in v0.1:

- mail server;
- DNS management;
- FTP server;
- reseller accounts;
- hosting plans;
- billing;
- quotas;
- firewall UI;
- Docker orchestration;
- Kubernetes;
- marketplace/plugins;
- complex role system;
- webmail;
- phpMyAdmin if Adminer is used;
- automatic PHP version installation from the UI.

---

## 8. High-Level Architecture

```text
Internet
   |
   v
Caddy
   |
   v
CaddyPanel PHP app
   |
   +-- Auth
   +-- Dashboard
   +-- Sites manager
   +-- Database manager
   +-- Backup/restore manager
   +-- Logs viewer
   +-- Settings
   +-- Module registry
   +-- Adminer integration
   +-- FileGator integration
   |
   v
System services
   +-- Caddy
   +-- PHP-FPM
   +-- MariaDB
```

The panel itself is a normal PHP-FPM application served by Caddy.

---

## 9. Planned Server Paths

```text
/opt/caddypanel/                    # panel code
/opt/caddypanel/public/             # panel web root
/opt/caddypanel/app/                # PHP application code
/opt/caddypanel/config/             # panel config
/opt/caddypanel/database/           # schema/migrations
/opt/caddypanel/data/               # SQLite database and local state
/opt/caddypanel/bin/                # privileged helper scripts
/opt/caddypanel/apps/adminer/       # Adminer installation
/opt/caddypanel/apps/filegator/     # FileGator installation

/var/www/sites/                     # managed websites
/var/backups/caddypanel/            # local backup archives
/var/log/caddypanel/                # panel logs

/etc/caddy/Caddyfile                # main Caddy config
/etc/caddy/sites/                   # generated site configs
```

---

## 10. Website Directory Structure

Each managed site should use this structure:

```text
/var/www/sites/example.com/
    public/
    private/
    logs/
    tmp/
```

Meaning:

```text
public/   # web root
private/  # private files outside web root
logs/     # site-specific logs
tmp/      # temporary files
```

Ownership for MVP:

```text
www-data:www-data
```

FileGator should operate only inside:

```text
/var/www/sites
```

It must not have access to:

```text
/etc
/opt
/root
/var/backups
```

---

## 11. Main Caddy Design

The main Caddyfile should be managed by CaddyPanel installer/config:

```caddyfile
{
    email admin@example.com
}

import /etc/caddy/sites/*.caddy
```

The email comes from CaddyPanel config/settings:

```text
admin_email
```

The installer asks:

```text
Panel domain: panel.example.com
Admin email: admin@example.com
```

---

## 12. Site Caddy Config Templates

### PHP Site

Default PHP sites should use Caddy's standard `php_fastcgi` directive.

Do not write manual `try_files` rules by default.

```caddyfile
example.com, www.example.com {
    root * /var/www/sites/example.com/public

    encode zstd gzip

    php_fastcgi unix//run/php/php8.4-fpm.sock
    file_server

    log {
        output file /var/www/sites/example.com/logs/access.log {
            roll_size 10MB
            roll_keep 10
            roll_keep_for 720h
        }
    }
}
```

### Static Site

```caddyfile
example.com, www.example.com {
    root * /var/www/sites/example.com/public

    encode zstd gzip
    file_server

    log {
        output file /var/www/sites/example.com/logs/access.log {
            roll_size 10MB
            roll_keep 10
            roll_keep_for 720h
        }
    }
}
```

### Log Paths

Each site reserves:

```text
/var/www/sites/example.com/logs/access.log
/var/www/sites/example.com/logs/error.log
```

For MVP:

- Caddy writes `access.log`;
- `error.log` path is reserved;
- PHP error log integration can come later.

Log rotation must be configurable by admin.

Default values:

```text
roll_size: 10MB
roll_keep: 10
roll_keep_for: 720h
```

---

## 13. Safe Caddy Config Apply Flow

Generated configs should be applied cautiously.

Preferred flow:

```text
1. generate temporary/pending site config;
2. validate generated config;
3. backup existing site config if present;
4. move pending config to final .caddy file;
5. validate full /etc/caddy/Caddyfile;
6. reload Caddy;
7. rollback if final validation fails;
8. never reload broken config.
```

A specialized helper script should handle this operation:

```text
/opt/caddypanel/bin/caddy-apply-site-config
```

It does one thing: safely apply one generated site config.

---

## 14. Auth Model

Initial auth should be simple:

- PHP sessions;
- one admin user created by installer;
- optional manager users;
- `password_hash()` / `password_verify()`;
- CSRF tokens on all POST requests;
- logout;
- password change;
- basic brute-force protection;
- secure session cookies in production.

---

## 15. Roles

CaddyPanel is not a multi-tenant hosting panel.

Managers are trusted server operators, not hosting customers.

### Admin

Admin has full access:

- users;
- settings;
- modules;
- sites;
- databases;
- Adminer;
- FileGator;
- backups;
- restore;
- logs;
- PHP version settings;
- installer/system-level actions.

### Manager

Manager has operational access to the whole managed server:

- sees all sites;
- manages all sites;
- manages databases;
- uses Adminer;
- uses FileGator;
- creates backups;
- restores backups;
- views site access logs;
- views backup logs.

Manager cannot:

- manage users;
- change global settings;
- enable/disable modules;
- alter privileged paths;
- change global PHP settings;
- perform sensitive system configuration changes;
- view sensitive panel/system logs.

There is no per-site ownership model in MVP.

There is no reseller-style isolation.

---

## 16. Module System

CaddyPanel should support a small built-in module registry.

Modules can be enabled or disabled by admin.

Disabled modules:

- disappear from navigation;
- deny direct route access;
- show a clear `Module disabled` page to logged-in users.

Unauthenticated users should still be redirected to login first.

### Built-in Modules

Default module state after installation:

```text
sites        enabled
databases    enabled
adminer      enabled
filegator    enabled
backups      enabled
logs         enabled
settings     enabled
php_versions enabled
```

There is no plugin marketplace in MVP.

---

## 17. Disabled Module Behavior

If a module is disabled and a logged-in user opens its route directly, show:

```text
Module disabled

The requested module is currently disabled by administrator.
```

For example:

```text
/db     -> Module disabled, if Adminer module is off
/files  -> Module disabled, if FileGator module is off
```

If user is not logged in, redirect to login first.

---

## 18. UI Language

The MVP user interface is **English only**.

All labels, buttons, validation messages, system messages, and logs visible in the UI should be written in English.

Internationalization can be added later.

---

## 19. UI Themes

CaddyPanel should support two built-in themes from the beginning:

- dark;
- light.

Default theme:

```text
dark
```

Theme is stored as a global panel setting:

```text
ui_theme = dark
```

MVP does not need per-user themes.

The UI should use CSS variables so both themes can share the same templates and layout.

Example:

```css
:root,
[data-theme="dark"] {
    --bg: #0f1115;
    --panel: #171a21;
    --text: #f2f4f8;
    --muted: #9aa4b2;
    --border: #2a2f3a;
    --accent: #4f8cff;
    --danger: #ff5c5c;
    --success: #4cc38a;
}

[data-theme="light"] {
    --bg: #f5f7fb;
    --panel: #ffffff;
    --text: #171a21;
    --muted: #667085;
    --border: #d8dee9;
    --accent: #2563eb;
    --danger: #dc2626;
    --success: #16a34a;
}
```

---

## 20. Adminer and FileGator Integration

Adminer and FileGator must not be exposed directly without panel login.

Preferred MVP URL model:

```text
panel.example.com/db
panel.example.com/files
```

Not subdomains.

The panel owns the login/session.

Routes:

```text
/db     # Adminer
/files  # FileGator
```

If module is disabled:

```text
/db     -> Module disabled
/files  -> Module disabled
```

Admin and manager can access Adminer/FileGator if the modules are enabled.

---

## 21. Database Model

Panel metadata lives in SQLite.

MariaDB is only for managed site/application databases.

Core SQLite tables:

- `users`;
- `sites`;
- `site_aliases`;
- `databases`;
- `modules`;
- `settings`;
- `backup_runs`;
- `audit_logs`;
- optionally `php_versions`.

---

## 22. Suggested SQLite Schema

### users

```sql
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'manager',
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
```

Allowed roles:

```text
admin
manager
```

### sites

```sql
CREATE TABLE sites (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    domain TEXT NOT NULL UNIQUE,
    type TEXT NOT NULL,
    root_path TEXT NOT NULL,
    public_path TEXT NOT NULL,
    private_path TEXT NOT NULL,
    logs_path TEXT NOT NULL,
    tmp_path TEXT NOT NULL,
    php_enabled INTEGER NOT NULL DEFAULT 0,
    php_version TEXT,
    php_fpm_socket TEXT,
    caddy_config_path TEXT,
    status TEXT NOT NULL DEFAULT 'draft',
    last_error TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    deleted_at TEXT
);
```

### site_aliases

```sql
CREATE TABLE site_aliases (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    site_id INTEGER NOT NULL,
    domain TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL
);
```

### databases

```sql
CREATE TABLE databases (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    site_id INTEGER,
    name TEXT NOT NULL UNIQUE,
    username TEXT NOT NULL,
    password_encrypted TEXT,
    host TEXT NOT NULL DEFAULT 'localhost',
    status TEXT NOT NULL DEFAULT 'active',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    deleted_at TEXT
);
```

### modules

```sql
CREATE TABLE modules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    enabled INTEGER NOT NULL DEFAULT 1,
    config_json TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
```

### audit_logs

```sql
CREATE TABLE audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action TEXT NOT NULL,
    target_type TEXT,
    target_id INTEGER,
    status TEXT NOT NULL,
    message TEXT,
    ip_address TEXT,
    created_at TEXT NOT NULL
);
```

### php_versions

Optional:

```sql
CREATE TABLE php_versions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    version TEXT NOT NULL,
    fpm_socket TEXT NOT NULL UNIQUE,
    is_default INTEGER NOT NULL DEFAULT 0,
    detected_at TEXT NOT NULL
);
```

---

## 23. Sites Module

The Sites module should support:

- list sites;
- create PHP site;
- create static site;
- add `www` alias;
- add custom aliases;
- view site details;
- delete/disable site through confirmation screen;
- link to related database, backups, logs, and file manager.

---

## 24. Site Creation

Creating a site means creating the host and website directory.

MariaDB database creation is optional.

Create site form:

```text
Domain: example.com

[x] Add www alias

Aliases:
alias1.com
alias2.example.com

Type:
( ) Static
( ) PHP

PHP version:
[ Default PHP 8.4 ]   # only relevant if php_versions module is enabled

[ ] Create MariaDB database
```

Creating a site should:

1. validate domain;
2. validate aliases;
3. create SQLite site record;
4. create directory structure;
5. create starter `index.php` or `index.html`;
6. generate Caddy config;
7. safely apply Caddy config;
8. validate Caddy;
9. reload Caddy;
10. write audit log.

If the database checkbox is enabled, additionally:

1. generate database name;
2. generate database user;
3. generate strong password;
4. create MariaDB database;
5. create MariaDB user;
6. grant privileges;
7. store encrypted password;
8. link database to site.

If database creation fails after the site was created successfully, the site may remain active and the database error should be shown in UI.

---

## 25. Site Statuses

Allowed site statuses:

```text
draft       # record exists but system provisioning is not complete
active      # Caddy host config is active
disabled    # host is disabled, files/database may remain
deleted     # soft-deleted/hidden from normal list
error       # last operation failed
```

---

## 26. Delete Site / Disable Host Flow

Deleting a site must use a confirmation screen.

Button:

```text
Delete site
```

Confirmation screen:

```text
Delete site: example.com

Choose what should be removed:

[x] Disable host
[ ] Delete files
[ ] Delete database

This action will be logged.
```

Default selection:

```text
[x] Disable host
[ ] Delete files
[ ] Delete database
```

Action names:

```text
Disable host
Delete files
Delete database
```

Meaning:

### Disable host

Disable/remove Caddy site config and reload Caddy safely.

This should not delete website files or database.

### Delete files

Delete:

```text
/var/www/sites/example.com/
```

This must be explicitly selected.

### Delete database

Delete linked MariaDB database and user.

This must be explicitly selected.

All selected actions must be written to audit log.

---

## 27. Domain and Alias Validation

Initial validation should allow normal DNS names:

```text
example.com
www.example.com
sub.example.com
```

Reject:

```text
../
/
;
&
|
$
`
spaces
raw shell metacharacters
```

Internationalized domains can be added later.

For MVP, keep validation strict and predictable.

---

## 28. MariaDB Module

The Databases module should support:

- list databases;
- create database;
- create MariaDB user;
- generate strong password;
- grant privileges;
- associate database with a site optionally;
- show connection information;
- show password on request;
- delete database/user;
- later: rotate password.

---

## 29. MariaDB Service User

CaddyPanel uses a dedicated MariaDB service user for database operations.

This is not the human panel admin user.

Example:

```text
caddypanel_admin
```

The service user is used for:

- `CREATE DATABASE`;
- `CREATE USER`;
- `GRANT`;
- `DROP DATABASE`;
- `DROP USER`;
- `mysqldump`;
- restore database dump.

Installer flow:

1. installer receives MariaDB root/admin access;
2. creates service user `caddypanel_admin`;
3. generates strong password;
4. stores credentials safely;
5. CaddyPanel uses this service user afterwards.

Rule:

```text
Human panel admin != MariaDB service user
```

---

## 30. MariaDB Database Name Rule

Database names and database usernames should be short and predictable.

Maximum length:

```text
12 characters
```

Format:

```text
{base8}_{suffix3}
```

Examples:

```text
example.com       -> example_a1f
example-shop.com  -> example_9c2
superproject.cz   -> superpro_b81
```

Rules:

- lowercase;
- allowed characters: `a-z`, `0-9`, `_`;
- first character must be a letter;
- max length 12;
- editable by admin/manager before creation;
- validate strictly.

Suggested regex:

```text
^[a-z][a-z0-9_]{0,11}$
```

Suffix can be generated from a hash of the domain:

```text
substr(sha1(domain), 0, 3)
```

If the name already exists, retry with a counter/hash variant.

---

## 31. MariaDB Password Storage

Database passwords:

- are generated by the panel;
- are not stored in plain text;
- are stored encrypted;
- can be shown to the user on request;
- are needed for backup/restore automation.

Show password behavior:

```text
- user clicks “Show database password”;
- password is decrypted using secret.key;
- password is shown for 30 seconds;
- action is written to audit log.
```

Optional later improvement:

- require re-authentication before showing password.

---

## 32. Secret Key

CaddyPanel needs a secret key for encryption.

Path:

```text
/opt/caddypanel/config/secret.key
```

Recommended permissions:

```text
root:www-data
640
```

Used for:

- encrypting/decrypting MariaDB database passwords;
- decrypting credentials during backup/restore;
- migration to another VPS if secrets match.

Important rule:

```text
If secret.key is lost, encrypted database passwords cannot be recovered.
```

Installer must warn the admin:

```text
IMPORTANT:
Backup /opt/caddypanel/config/secret.key safely.
Without it, encrypted database passwords cannot be recovered.
```

---

## 33. FileGator Integration

FileGator root:

```text
/var/www/sites
```

FileGator must not access:

```text
/etc
/opt
/root
/var/backups
```

Access:

```text
admin   -> yes
manager -> yes
```

Only if the `filegator` module is enabled.

---

## 34. Adminer Integration

Adminer URL:

```text
panel.example.com/db
```

Access:

```text
admin   -> yes
manager -> yes
```

Only if the `adminer` module is enabled.

Adminer must not be exposed without panel login.

---

## 35. Backups

Backups are important but should be implemented carefully and not rushed.

Backup goals:

- quick restore;
- migration to another VPS;
- restore files, database, and host config;
- allow transfer from old VPS to new VPS if secrets match.

Backups should be created by cron using helper scripts.

No permanent background daemon is required for MVP.

Backup storage:

```text
/var/backups/caddypanel/
```

Example:

```text
/var/backups/caddypanel/example.com/
    example.com-2026-05-18-030000.tar.gz
    example.com-2026-05-19-030000.tar.gz
```

---

## 36. Backup Contents

Backup archive should include:

```text
manifest.json
files/
    public/
    private/
    logs/
    tmp/
database/
    example_a1f.sql
caddy/
    example.com.caddy
metadata/
    site.json
    aliases.json
    database.json.enc
```

Backup should include:

- site files;
- linked database dump;
- Caddy host config;
- aliases;
- PHP version setting;
- site metadata;
- encrypted database metadata if needed.

---

## 37. Backup Permissions

```text
backup create -> admin + manager
restore       -> admin + manager
```

Managers are trusted server operators, so they may restore backups.

---

## 38. Restore Flow

Restore must use a confirmation screen.

Action name:

```text
Restore backup
```

Restore flow:

1. select/upload backup archive;
2. inspect `manifest.json`;
3. show what is inside;
4. show what will be affected;
5. choose restore mode:
   - restore files;
   - restore database;
   - restore host config;
   - full restore;
6. create pre-restore backup before overwriting existing data;
7. apply restore;
8. validate Caddy if host config changed;
9. reload Caddy if validation passes;
10. write audit log.

Example confirmation screen:

```text
Restore backup

Backup:
example.com-2026-05-18-030000.tar.gz

This backup contains:
- files
- database
- Caddy host config
- metadata

Restore mode:
[ ] Restore files
[ ] Restore database
[ ] Restore host config

A pre-restore backup will be created before overwriting existing data.
```

---

## 39. Migration to Another VPS

Backups should support migration to another VPS.

Requirements:

- CaddyPanel installed on new VPS;
- same `secret.key` copied if encrypted credentials must be decrypted;
- Caddy installed;
- MariaDB installed;
- required PHP-FPM socket available or remapped during restore.

If `secret.key` does not match:

- files may still be restored;
- Caddy config may still be restored;
- encrypted database credentials cannot be decrypted.

---

## 40. Logs Module

Logs should expose useful read-only views.

Manager can see:

- site access logs;
- backup logs.

Admin can see:

- panel error logs;
- audit logs;
- system operation logs;
- Caddy validation errors.

Avoid building a general-purpose log management system in MVP.

---

## 41. Dashboard

Dashboard should show simple server and panel status.

Initial widgets:

```text
Caddy status: active
PHP default: 8.4
MariaDB status: active
Disk usage: show
Sites: 5
Databases: 5
Last backup: yesterday
```

System checks should be done via small read-only helper commands, not arbitrary shell execution from PHP.

Possible checks:

```text
systemctl is-active caddy
systemctl is-active php8.4-fpm
systemctl is-active mariadb
df -h /
```

---

## 42. Settings Module

Settings should start small.

Settings include:

- panel domain;
- admin email;
- UI theme;
- base paths;
- default PHP-FPM socket;
- enabled modules;
- log rotation defaults;
- backup retention defaults.

Dangerous settings should be explicit and audited.

Some system paths may be read-only in MVP.

---

## 43. Helper Scripts

Use several small helper scripts.

Avoid one large orchestration script.

Suggested helpers:

```text
/opt/caddypanel/bin/site-create-dirs
/opt/caddypanel/bin/site-delete-files

/opt/caddypanel/bin/caddy-apply-site-config
/opt/caddypanel/bin/caddy-disable-site
/opt/caddypanel/bin/caddy-validate
/opt/caddypanel/bin/caddy-reload

/opt/caddypanel/bin/db-create
/opt/caddypanel/bin/db-delete
/opt/caddypanel/bin/db-dump
/opt/caddypanel/bin/db-restore

/opt/caddypanel/bin/backup-create
/opt/caddypanel/bin/backup-restore

/opt/caddypanel/bin/php-detect
/opt/caddypanel/bin/system-status
```

Each helper should:

- do one thing;
- validate its own input;
- avoid raw shell interpolation;
- return clear exit codes;
- print machine-readable or simple parseable output where practical.

---

## 44. Installer

Installer command:

```bash
bash install.sh
```

Installer should ask:

```text
Panel domain: panel.example.com
Admin email: admin@example.com
Admin username: admin
Admin password: ********
```

Installer should:

1. check OS;
2. install packages;
3. install Caddy;
4. install PHP 8.4 FPM;
5. install needed PHP extensions;
6. install MariaDB;
7. create directories;
8. copy panel to `/opt/caddypanel`;
9. create `secret.key`;
10. initialize SQLite;
11. create admin user;
12. install Adminer;
13. install FileGator;
14. configure Caddy;
15. create MariaDB service user;
16. configure sudoers;
17. set ownership/permissions;
18. enable/start services;
19. print final login URL.

Suggested PHP packages:

```text
php8.4-fpm
php8.4-cli
php8.4-sqlite3
php8.4-mysql
php8.4-mbstring
php8.4-xml
php8.4-curl
php8.4-zip
php8.4-gd
php8.4-intl
```

Installer must avoid unsafe shell interpolation from user input.

---

## 45. Local Development

Local development must not require touching the host system.

Current/future local commands:

```bash
php bin/init-dev.php
php -S 127.0.0.1:8080 -t public
```

Development login:

```text
admin / password123
```

Development credentials are only for local development.

In dev mode:

- Sites module may write SQLite only;
- Caddy operations disabled or mocked;
- MariaDB operations disabled or mocked;
- filesystem operations disabled or mocked.

---

## 46. Current Fresh Restart State

The project was restarted from a clean foundation on 2026-05-18.

Currently implemented in the new base:

- `public/index.php` front controller;
- `vendor/autoload.php` simple autoloader;
- `config/app.php`;
- SQLite schema;
- `Request`;
- `Response`;
- `Router`;
- `Database`;
- `Csrf`;
- `AuthService`;
- `AuthController`;
- login template;
- dashboard template;
- placeholder pages for future modules;
- `bin/init-dev.php` for local database/admin bootstrap.

No production modules have been rebuilt yet.

---

## 47. Recommended Implementation Order

1. Stabilize local boot and auth.
2. Add roles: `admin`, `manager`.
3. Add modules table and module service.
4. Add dark/light theme support.
5. Build dashboard placeholders.
6. Implement Sites module SQLite-only:
   - domain;
   - aliases;
   - `www` alias;
   - type static/php;
   - PHP version field;
   - optional database checkbox placeholder;
   - delete confirmation screen with checkboxes.
7. Add helper scripts for site directories.
8. Add Caddy config generation.
9. Add safe Caddy config apply flow.
10. Add Caddy validation/reload flow.
11. Implement Databases module:
    - create db/user;
    - encrypt password;
    - show password for 30 seconds.
12. Add Adminer integration.
13. Add FileGator integration.
14. Add PHP-FPM detection.
15. Add manual backups.
16. Add cron backups.
17. Add restore.
18. Add logs.
19. Add settings.
20. Build `install.sh`.

---

## 48. Milestone 0.1-alpha

First technical milestone:

```text
Milestone 0.1-alpha
```

Includes:

- auth;
- roles: `admin` / `manager`;
- module registry;
- disabled module page;
- dark/light theme;
- dashboard placeholders;
- Sites SQLite-only;
- aliases/www support in database/UI;
- delete confirmation screen with:
  - Disable host;
  - Delete files;
  - Delete database.

Does not include yet:

- real Caddy writes;
- real directory creation;
- real MariaDB operations;
- Adminer/FileGator integration;
- backups/restore;
- `install.sh`.

---

## 49. Immediate Next Codex Task

Update the clean CaddyPanel foundation with roles, modules, theme support, and Sites SQLite-only.

### Implement

- `users.role` with allowed values:
  - `admin`;
  - `manager`.
- `users.is_active`.
- `modules` table with built-in module records.
- `ModuleRepository`.
- `ModuleService`.
- authorization helpers:
  - `requireLogin()`;
  - `requireAdmin()`;
  - `requireManagerOrAdmin()`;
  - `requireModule($moduleName)`.
- navigation visibility based on enabled modules and user role.
- disabled module page.
- global UI theme setting:
  - `dark`;
  - `light`;
  - default `dark`.
- Sites module SQLite-only:
  - `app/Sites/SiteRepository.php`;
  - `app/Sites/SiteService.php`;
  - `app/Sites/SiteController.php`;
  - `templates/sites/index.php`;
  - `templates/sites/create.php`;
  - `templates/sites/show.php`;
  - `templates/sites/delete.php`.

### Routes

```text
GET  /sites
GET  /sites/create
POST /sites/create
GET  /sites/{id}
GET  /sites/{id}/delete
POST /sites/{id}/delete
```

### Create Site Form

Fields:

```text
Domain
Add www alias
Aliases
Type: static/php
PHP version field placeholder
Create MariaDB database checkbox placeholder
```

### Delete Confirmation Form

Checkboxes:

```text
[x] Disable host
[ ] Delete files
[ ] Delete database
```

For now:

- do not call helper scripts;
- do not write filesystem;
- do not create MariaDB databases;
- save site records in SQLite;
- save aliases in SQLite;
- save intended delete options in audit log/message;
- use statuses: `draft`, `active`, `disabled`, `deleted`, `error`;
- admin and manager can access Sites;
- manager sees all sites.

### Do Not Implement Yet

- real Caddy integration;
- real MariaDB operations;
- real FileGator integration;
- real Adminer integration;
- backups;
- restore;
- installer.

---

## 50. Final Philosophy

CaddyPanel should stay:

```text
small
transparent
predictable
server-friendly
manual-repairable
```

It should not become a giant hosting panel.

It should be a practical, trusted VPS workbench for managing a Caddy + PHP-FPM + MariaDB server with clear generated configs, predictable paths, integrated file/database tools, and portable backups.
