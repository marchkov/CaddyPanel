CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'manager',
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS modules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    enabled INTEGER NOT NULL DEFAULT 1,
    config_json TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    key TEXT NOT NULL UNIQUE,
    value TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS schema_migrations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    migration TEXT NOT NULL UNIQUE,
    applied_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS sites (
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

CREATE TABLE IF NOT EXISTS site_aliases (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    site_id INTEGER NOT NULL,
    domain TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL,
    FOREIGN KEY (site_id) REFERENCES sites(id)
);

CREATE TABLE IF NOT EXISTS databases (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    site_id INTEGER,
    name TEXT NOT NULL UNIQUE,
    username TEXT NOT NULL,
    password_encrypted TEXT,
    host TEXT NOT NULL DEFAULT 'localhost',
    status TEXT NOT NULL DEFAULT 'active',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    deleted_at TEXT,
    FOREIGN KEY (site_id) REFERENCES sites(id)
);

CREATE TABLE IF NOT EXISTS backup_runs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    site_id INTEGER NOT NULL,
    status TEXT NOT NULL,
    backup_file TEXT,
    file_size INTEGER,
    message TEXT,
    include_files INTEGER NOT NULL DEFAULT 1,
    include_database INTEGER NOT NULL DEFAULT 1,
    include_caddy_config INTEGER NOT NULL DEFAULT 1,
    started_at TEXT NOT NULL,
    completed_at TEXT,
    created_by_user_id INTEGER,
    FOREIGN KEY (site_id) REFERENCES sites(id),
    FOREIGN KEY (created_by_user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS backup_jobs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    site_id INTEGER NOT NULL,
    enabled INTEGER NOT NULL DEFAULT 1,
    schedule_type TEXT NOT NULL DEFAULT 'daily',
    schedule_time TEXT NOT NULL DEFAULT '03:00',
    include_files INTEGER NOT NULL DEFAULT 1,
    include_database INTEGER NOT NULL DEFAULT 1,
    include_caddy_config INTEGER NOT NULL DEFAULT 1,
    retention_days INTEGER NOT NULL DEFAULT 14,
    last_run_at TEXT,
    next_run_at TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (site_id) REFERENCES sites(id)
);

CREATE TABLE IF NOT EXISTS audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action TEXT NOT NULL,
    target_type TEXT,
    target_id INTEGER,
    status TEXT NOT NULL,
    message TEXT,
    ip_address TEXT,
    created_at TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS php_versions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    version TEXT NOT NULL,
    fpm_socket TEXT NOT NULL UNIQUE,
    is_default INTEGER NOT NULL DEFAULT 0,
    detected_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);
CREATE INDEX IF NOT EXISTS idx_modules_name ON modules(name);
CREATE INDEX IF NOT EXISTS idx_settings_key ON settings(key);
CREATE INDEX IF NOT EXISTS idx_sites_domain ON sites(domain);
CREATE INDEX IF NOT EXISTS idx_sites_status ON sites(status);
CREATE INDEX IF NOT EXISTS idx_site_aliases_site_id ON site_aliases(site_id);
CREATE INDEX IF NOT EXISTS idx_databases_site_id ON databases(site_id);
CREATE INDEX IF NOT EXISTS idx_backup_runs_site_id ON backup_runs(site_id);
CREATE INDEX IF NOT EXISTS idx_backup_runs_started_at ON backup_runs(started_at);
CREATE INDEX IF NOT EXISTS idx_backup_jobs_site_id ON backup_jobs(site_id);
CREATE INDEX IF NOT EXISTS idx_backup_jobs_next_run_at ON backup_jobs(next_run_at);
CREATE INDEX IF NOT EXISTS idx_audit_logs_created_at ON audit_logs(created_at);
