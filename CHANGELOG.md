# Changelog

## v0.1.0-alpha.1 - 2026-05-20

First public alpha release of CaddyPanel.

### Tested

- Ubuntu 24.04
- Debian 13

### Added

- Caddy site management with PHP/static site support.
- Real Caddy config viewing and guarded manual editing per site.
- Caddy config updates that preserve existing manual directives where possible.
- PHP-FPM socket/version detection and default PHP-FPM selection.
- MariaDB database provisioning, password reveal, delete, and site attach/detach.
- Adminer integration behind CaddyPanel authentication.
- FileGator integration rooted at `/var/www/sites`.
- Manual backup queue and scheduled backup jobs.
- Backup download, delete, retry, and restore actions.
- Automatic backup retention by count for scheduled backups.
- Panel self-update from Git with post-update maintenance.
- Installer and uninstaller for Debian/Ubuntu style systems.

### Changed

- Restore is treated as a backup action, not a standalone navigation section.
- Backups are stored in a flat backup directory with dated sequence filenames.
- Runtime directories and secrets are preserved during panel updates.

### Known Limitations

- Alpha software: no warranty, no security guarantee, and no support SLA.
- Intended for trusted single-server use on a fresh VPS.
- RHEL/Fedora/Alpine installers are planned but not supported yet.
- Restore UX is still early.
- Caddy config preservation is line-based, not a full Caddyfile parser.
- Test coverage is limited.
