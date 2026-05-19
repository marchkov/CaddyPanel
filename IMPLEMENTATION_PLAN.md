# CaddyPanel — Implementation Plan

This plan is based on `CaddyPanel_Project_Brief_v0.1.md`.

Current repository state:

- git repository exists on `main`;
- PHP foundation exists;
- auth, roles, modules, theme foundation exists;
- PHP CLI is available at `/mnt/c/php/php.exe`;
- PHP syntax lint passes with PHP 8.5.1;
- deeper runtime/VPS verification is still pending.

## Progress

```text
Milestone 0 — Project Foundation              mostly done
Milestone 1 — Auth, Roles, Modules, Theme     mostly done
Milestone 2 — Dashboard                       basic placeholder done
Milestone 3 — Sites SQLite-Only               implemented, pending runtime verification
Milestone 4 — Site Filesystem Helpers         implemented, pending runtime verification
Milestone 5 — Caddy Config Generation         implemented, pending runtime verification
Milestone 6 — Safe Caddy Apply Flow           implemented, pending runtime verification
Milestone 7 — Databases                       implemented, pending runtime verification
Milestone 8 — Adminer and FileGator           installer download foundation implemented
Milestone 9 — Backups                         manual/scheduled backup foundation implemented
Milestone 10 — Scheduled backups              scheduler foundation implemented
Milestone 11 — Restore                        file/config/database restore implemented, pending VPS verification
Milestone 12 — Logs                           audit and bounded site log viewer implemented
Milestone 13 — Settings                       partially implemented, pending runtime verification
PHP versions module                           implemented, pending runtime verification
Updates module                                implemented, pending runtime verification
Milestone 14 — Installer                      foundation implemented, pending VPS verification
Dashboard system status                       implemented, pending VPS verification
Users management                              implemented, pending runtime verification
```

## Milestone 0 — Project Foundation

Goal: create a clean, runnable local PHP foundation.

Deliverables:

- initialize git repository;
- create project structure;
- add `README.md`;
- add `.gitignore`;
- add `config/app.php`;
- add `database/schema.sql`;
- add simple autoloader;
- add front controller `public/index.php`;
- add core classes:
  - `Request`;
  - `Response`;
  - `Router`;
  - `Database`;
  - `Csrf`;
- add base layout templates;
- add `bin/init-dev.php`;
- verify local startup with:

```bash
php bin/init-dev.php
php -S 127.0.0.1:8080 -t public
```

Definition of done:

- login page opens locally;
- SQLite database initializes;
- default admin user is created;
- no production system paths are touched in development mode.

## Milestone 1 — Auth, Roles, Modules, Theme

Goal: build the access-control and UI foundation before feature modules.

Deliverables:

- implement `AuthService`;
- implement `AuthController`;
- add login/logout;
- add roles:
  - `admin`;
  - `manager`;
- add active/inactive users;
- add `modules` table and seed built-in modules;
- implement:
  - `ModuleRepository`;
  - `ModuleService`;
- implement authorization helpers:
  - `requireLogin()`;
  - `requireAdmin()`;
  - `requireManagerOrAdmin()`;
  - `requireModule($moduleName)`;
- add disabled module page;
- add dark/light theme support;
- default theme: `dark`;
- add navigation that respects module state and user role.

Definition of done:

- unauthenticated users are redirected to login;
- admin and manager can log in;
- disabled modules disappear from navigation;
- direct access to disabled module routes shows `Module disabled`;
- UI uses shared CSS variables for dark/light themes.

## Milestone 2 — Dashboard

Goal: show a useful but non-invasive dashboard.

Deliverables:

- dashboard route and template;
- basic widgets:
  - Caddy status placeholder;
  - PHP default version placeholder;
  - MariaDB status placeholder;
  - disk usage placeholder;
  - site count;
  - database count;
  - last backup placeholder;
- no arbitrary shell execution from PHP.

Definition of done:

- dashboard loads after login;
- values that require system integration are clearly placeholder/mocked in development;
- dashboard does not touch Caddy, MariaDB, or system services yet.

## Milestone 3 — Sites SQLite-Only

Goal: implement site management in panel data only, without touching the server filesystem.

Deliverables:

- `app/Sites/SiteRepository.php`;
- `app/Sites/SiteService.php`;
- `app/Sites/SiteController.php`;
- templates:
  - `templates/sites/index.php`;
  - `templates/sites/create.php`;
  - `templates/sites/show.php`;
  - `templates/sites/delete.php`;
- routes:

```text
GET  /sites
GET  /sites/create
POST /sites/create
GET  /sites/{id}
GET  /sites/{id}/delete
POST /sites/{id}/delete
```

Create form fields:

- domain;
- add `www` alias;
- aliases textarea;
- type: `static` or `php`;
- PHP version placeholder;
- create MariaDB database placeholder.

Delete confirmation options:

- Disable host;
- Delete files;
- Delete database.

Definition of done:

- admin and manager can list all sites;
- site records are stored in SQLite;
- aliases are stored in SQLite;
- validation rejects unsafe domains and aliases;
- delete is confirmation-based;
- delete action updates status and writes audit log;
- no files, Caddy configs, or databases are created yet.

## Milestone 4 — Site Filesystem Helpers

Goal: add controlled site directory creation/deletion scripts.

Deliverables:

- helper scripts:
  - `bin/site-create-dirs`;
  - `bin/site-delete-files`;
- PHP command wrapper with allowlist;
- development mode mock/no-op behavior;
- strict validation in scripts;
- directory structure:

```text
/var/www/sites/example.com/
    public/
    private/
    logs/
    tmp/
```

Definition of done:

- production mode can create site directories through sudo-limited helpers;
- development mode does not touch `/var/www`;
- scripts validate inputs independently;
- failed helper calls are shown clearly in the UI and audit log.

## Milestone 5 — Caddy Config Generation

Goal: generate safe Caddy site configs but apply them cautiously.

Deliverables:

- Caddy template renderer;
- PHP site template;
- static site template;
- support primary domain and aliases;
- support selected PHP-FPM socket;
- write pending config in development-safe location first;
- audit generated config events.

Definition of done:

- config preview is available before system apply;
- generated config matches brief templates;
- no broken config is reloaded.

## Milestone 6 — Safe Caddy Apply Flow

Goal: safely apply generated configs to `/etc/caddy/sites`.

Deliverables:

- helper scripts:
  - `bin/caddy-apply-site-config`;
  - `bin/caddy-disable-site`;
  - `bin/caddy-validate`;
  - `bin/caddy-reload`;
- apply flow:
  - generate pending config;
  - validate generated config;
  - backup existing config;
  - move to final path;
  - validate full Caddyfile;
  - reload Caddy;
  - rollback on failure.

Definition of done:

- a PHP/static site can be made active in Caddy;
- invalid config never reloads Caddy;
- disabling host removes/disables config without deleting files;
- all actions are audited.

## Milestone 7 — Databases

Goal: manage MariaDB databases and users.

Deliverables:

- MariaDB service user model;
- `DatabaseRepository`;
- `DatabaseService`;
- `DatabaseController`;
- database list/create/show/delete templates;
- database name generation rule:
  - max 12 characters;
  - regex `^[a-z][a-z0-9_]{0,11}$`;
- encrypted password storage using `secret.key`;
- show password action with audit log.

Definition of done:

- database/user can be created;
- privileges are granted;
- password can be shown on request;
- database/user can be deleted through confirmation;
- database can be linked to a site.
- site creation can create a linked database when selected;
- site deletion can delete linked databases when selected.

## Milestone 8 — Adminer and FileGator

Goal: integrate tools behind panel authentication.

Deliverables:

- Adminer available at `/db`;
- FileGator available at `/files`;
- module checks for both;
- access only for logged-in admin/manager;
- FileGator restricted to `/var/www/sites`.
- installer downloads Adminer;
- installer downloads FileGator and runs Composer install.
- `/db` hands off to Adminer after panel authentication;
- `/files/*` is protected by Caddy `forward_auth` and serves FileGator in production;
- `/auth/check` exists for HTTP-level auth checks.

Definition of done:

- unauthenticated access redirects to login;
- disabled modules show disabled page;
- tools are not exposed directly outside panel auth.

## Milestone 9 — Backups

Goal: create portable backups for sites.

Deliverables:

- `bin/backup-create`;
- backup list/show templates;
- manual backup action;
- archive structure:

```text
manifest.json
files/
database/
caddy/
metadata/
```

- backup run records;
- backup logs.

Definition of done:

- user can create a manual backup for a site;
- backup includes files, linked database dump, Caddy config, and metadata where available;
- backup run status is visible in UI.

## Milestone 10 — Scheduled Backups

Goal: add cron-based scheduled backups without a permanent daemon.

Deliverables:

- backup schedule settings;
- scheduler PHP/CLI entrypoint;
- cron documentation or installer setup;
- retention rules.
- per-job include flags for files, linked database, and Caddy config.

Definition of done:

- scheduled backup can run from cron;
- old backups are retained/deleted according to settings;
- scheduled backup content respects include flags;
- run history is visible.

## Milestone 11 — Restore

Goal: restore backups cautiously.

Deliverables:

- backup inspection;
- restore confirmation page;
- restore modes:
  - files;
  - database;
  - host config;
  - full restore;
- pre-restore backup;
- Caddy validation/reload when host config changes.

Definition of done:

- backup can be inspected before restore;
- restore does not run without confirmation;
- pre-restore backup is created before overwrite;
- file restore switches prepared staged files into place;
- host config restore validates and reloads Caddy with rollback on failure;
- database restore applies SQL dumps into the currently linked active database;
- all restore actions are audited.

## Milestone 12 — Logs

Goal: provide useful read-only logs.

Deliverables:

- audit log view for admin;
- site access log view for admin/manager;
- backup log view;
- Caddy validation error view for admin.

Definition of done:

- audit logs are readable by admin;
- site access/error log tails are readable by admin/manager;
- manager cannot see sensitive panel/system logs;
- log views are bounded and do not load huge files into memory.

## Milestone 13 — Settings

Goal: expose safe global configuration.

Deliverables:

- settings view/edit;
- panel domain;
- admin email;
- UI theme;
- module toggles;
- default PHP-FPM socket;
- backup retention defaults;
- log rotation defaults.

Definition of done:

- dangerous settings are admin-only;
- changes are audited;
- module toggles affect navigation and direct route access.

## PHP Versions Module

Goal: detect installed PHP-FPM sockets without installing PHP versions from the UI.

Deliverables:

- `bin/php-fpm-detect`;
- `PhpVersionRepository`;
- `PhpVersionService`;
- `PhpVersionController`;
- admin-only `/php-versions` screen;
- default PHP version selection;
- detected PHP versions in the site creation form.

Definition of done:

- admin can refresh detected PHP-FPM sockets;
- admin can choose the default version;
- site creation uses detected versions;
- local mode falls back to PHP 8.4 without touching `/run/php`.

## Milestone 14 — Installer

Goal: install CaddyPanel on a clean target VPS.

Deliverables:

- `install.sh`;
- OS check;
- package installation;
- distro PHP setup with automatic PHP-FPM service/socket detection;
- Caddy setup;
- MariaDB setup;
- service user creation;
- `secret.key` generation;
- SQLite initialization;
- admin user creation;
- Adminer/FileGator installation;
- Caddy panel config;
- sudoers setup;
- permissions setup.

Definition of done:

- installer can bootstrap a new VPS;
- final URL and admin login are printed;
- installer warns about backing up `secret.key`;
- no unsafe shell interpolation.

## Suggested First Sprint

Build Milestones 0 and 1 only.

Reason:

- they establish the architecture;
- they keep the first code pass small;
- Sites depends on auth, roles, modules, CSRF, layout, and theme.

After Milestone 1 is committed, proceed to Milestone 3.
