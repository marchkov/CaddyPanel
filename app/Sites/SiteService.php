<?php

namespace CaddyPanel\Sites;

use CaddyPanel\Caddy\CaddyConfigRenderer;
use CaddyPanel\Caddy\CaddyConfigApplier;
use CaddyPanel\Core\Database;
use CaddyPanel\Databases\DatabaseRepository;
use CaddyPanel\Databases\DatabaseService;
use CaddyPanel\PhpVersions\PhpVersionRepository;
use CaddyPanel\Support\Validator;

class SiteService
{
    private const BASE_PATH = '/var/www/sites';
    private const CADDY_PATH = '/etc/caddy/sites';

    public function __construct(
        private SiteRepository $sites,
        private Database $database,
        private SiteProvisioner $provisioner,
        private CaddyConfigRenderer $caddyRenderer,
        private CaddyConfigApplier $caddyApplier,
        private PhpVersionRepository $phpVersions,
        private DatabaseService $databaseService,
        private DatabaseRepository $databases
    ) {
    }

    public function all(): array
    {
        $rows = $this->sites->all();

        foreach ($rows as &$row) {
            $row['aliases'] = $this->sites->aliases((int) $row['id']);
        }

        return $rows;
    }

    public function findWithAliases(int $id): ?array
    {
        $site = $this->sites->find($id);

        if (!$site) {
            return null;
        }

        $site['aliases'] = $this->sites->aliases($id);
        $site['databases'] = $this->databases->forSite($id);
        $site['caddy_config_preview'] = $this->caddyRenderer->render($site, $site['aliases']);

        return $site;
    }

    public function create(array $input, int $userId, string $ipAddress): int
    {
        $domain = strtolower(trim((string) ($input['domain'] ?? '')));
        $type = (string) ($input['type'] ?? 'php');
        $phpVersion = trim((string) ($input['php_version'] ?? '8.4'));
        $createDatabase = !empty($input['create_database']);

        if (!Validator::domain($domain)) {
            throw new \InvalidArgumentException('Invalid domain.');
        }

        if (!Validator::siteType($type)) {
            throw new \InvalidArgumentException('Invalid site type.');
        }

        if ($type === 'php' && preg_match('/^\d+\.\d+$/', $phpVersion) !== 1) {
            throw new \InvalidArgumentException('Invalid PHP version.');
        }

        $phpVersionRow = null;

        if ($type === 'php') {
            $phpVersionRow = $this->phpVersions->findByVersion($phpVersion);

            if (!$phpVersionRow) {
                throw new \InvalidArgumentException('PHP version is not available.');
            }
        }

        $existingSite = $this->sites->findAnyByDomain($domain);

        if ($existingSite && $existingSite['deleted_at'] === null) {
            throw new \InvalidArgumentException('Domain already exists as a ' . $existingSite['status'] . ' site.');
        }

        $aliases = $this->normalizeAliases((string) ($input['aliases'] ?? ''), !empty($input['add_www_alias']), $domain);

        foreach ($aliases as $alias) {
            if ($this->sites->aliasExists($alias)) {
                throw new \InvalidArgumentException('Alias already exists: ' . $alias);
            }
        }

        $rootPath = self::BASE_PATH . '/' . $domain;
        $now = date('Y-m-d H:i:s');

        $siteData = [
            'domain' => $domain,
            'type' => $type,
            'root_path' => $rootPath,
            'public_path' => $rootPath . '/public',
            'private_path' => $rootPath . '/private',
            'logs_path' => $rootPath . '/logs',
            'tmp_path' => $rootPath . '/tmp',
            'php_enabled' => $type === 'php' ? 1 : 0,
            'php_version' => $type === 'php' ? $phpVersion : null,
            'php_fpm_socket' => $type === 'php' ? $phpVersionRow['fpm_socket'] : null,
            'caddy_config_path' => self::CADDY_PATH . '/' . $domain . '.caddy',
            'status' => 'draft',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if ($existingSite && $existingSite['deleted_at'] !== null) {
            $siteId = $this->sites->restoreDeleted((int) $existingSite['id'], $siteData, $aliases);
        } else {
            $siteId = $this->sites->create($siteData, $aliases);
        }

        $message = 'Created site record.';

        try {
            $provisionOutput = $this->provisioner->createDirectories($siteData);
            $message .= ' Directory provisioning: ' . $provisionOutput;
            $previewPath = $this->caddyRenderer->writePreview($siteData, $aliases);
            $message .= ' Caddy config preview: ' . $previewPath;
            $applyOutput = $this->caddyApplier->applySiteConfig($domain, $previewPath, $siteData['caddy_config_path']);
            $message .= ' Caddy apply: ' . $applyOutput;
            $this->sites->updateStatus($siteId, 'active', null);
        } catch (\Throwable $exception) {
            $this->sites->updateStatus($siteId, 'error', $exception->getMessage());
            $this->audit($userId, 'site_create', $siteId, 'failed', $exception->getMessage(), $ipAddress);
            throw $exception;
        }

        if ($createDatabase) {
            try {
                $createdDatabase = $this->databaseService->create([
                    'site_id' => $siteId,
                    'name' => '',
                    'domain_hint' => $domain,
                ], $userId, $ipAddress);
                $message .= ' Created linked database: ' . $createdDatabase['name'] . '.';
            } catch (\Throwable $exception) {
                $this->sites->updateStatus($siteId, 'error', $exception->getMessage());
                $this->audit($userId, 'site_create', $siteId, 'failed', 'Linked database creation failed: ' . $exception->getMessage(), $ipAddress);
                throw $exception;
            }
        }

        $this->audit($userId, 'site_create', $siteId, 'success', $message, $ipAddress);

        return $siteId;
    }

    public function update(int $siteId, array $input, int $userId, string $ipAddress): void
    {
        $site = $this->sites->find($siteId);

        if (!$site || $site['deleted_at'] !== null) {
            throw new \InvalidArgumentException('Site not found.');
        }

        $type = (string) ($input['type'] ?? $site['type']);
        $phpVersion = trim((string) ($input['php_version'] ?? ($site['php_version'] ?? '')));

        if (!Validator::siteType($type)) {
            throw new \InvalidArgumentException('Invalid site type.');
        }

        if ($type === 'php' && preg_match('/^\d+\.\d+$/', $phpVersion) !== 1) {
            throw new \InvalidArgumentException('Invalid PHP version.');
        }

        $phpVersionRow = null;

        if ($type === 'php') {
            $phpVersionRow = $this->phpVersions->findByVersion($phpVersion);

            if (!$phpVersionRow) {
                throw new \InvalidArgumentException('PHP version is not available.');
            }
        }

        $aliases = $this->normalizeAliases((string) ($input['aliases'] ?? ''), !empty($input['add_www_alias']), $site['domain']);

        foreach ($aliases as $alias) {
            if ($this->sites->aliasExistsForOtherSite($alias, $siteId)) {
                throw new \InvalidArgumentException('Alias already exists: ' . $alias);
            }
        }

        $now = date('Y-m-d H:i:s');
        $siteData = array_merge($site, [
            'type' => $type,
            'php_enabled' => $type === 'php' ? 1 : 0,
            'php_version' => $type === 'php' ? $phpVersion : null,
            'php_fpm_socket' => $type === 'php' ? $phpVersionRow['fpm_socket'] : null,
            'status' => 'draft',
            'updated_at' => $now,
        ]);

        $this->sites->updateRuntime($siteId, $siteData, $aliases);
        $message = 'Updated site settings.';

        try {
            $message .= ' Directory provisioning: ' . $this->provisioner->createDirectories($siteData);
            $previewPath = $this->caddyRenderer->writePreview($siteData, $aliases);
            $message .= ' Caddy config preview: ' . $previewPath;
            $message .= ' Caddy apply: ' . $this->caddyApplier->applySiteConfig($site['domain'], $previewPath, $site['caddy_config_path']);
            $this->sites->updateStatus($siteId, 'active', null);
        } catch (\Throwable $exception) {
            $this->sites->updateStatus($siteId, 'error', $exception->getMessage());
            $this->audit($userId, 'site_update', $siteId, 'failed', $exception->getMessage(), $ipAddress);
            throw $exception;
        }

        $this->audit($userId, 'site_update', $siteId, 'success', $message, $ipAddress);
    }

    public function delete(int $siteId, array $input, int $userId, string $ipAddress): void
    {
        $site = $this->sites->find($siteId);

        if (!$site || $site['deleted_at'] !== null) {
            throw new \InvalidArgumentException('Site not found.');
        }

        $disableHost = !empty($input['disable_host']);
        $deleteFiles = !empty($input['delete_files']);
        $deleteDatabase = !empty($input['delete_database']);

        if (!$disableHost && !$deleteFiles && !$deleteDatabase) {
            throw new \InvalidArgumentException('Choose at least one action.');
        }

        $status = $deleteFiles || $deleteDatabase ? 'deleted' : 'disabled';
        $actions = [];

        if ($disableHost) {
            $actions[] = 'Disable host';
        }

        if ($deleteFiles) {
            $actions[] = 'Delete files';
        }

        if ($deleteDatabase) {
            $actions[] = 'Delete database';
        }

        $message = 'Site delete confirmation: ' . implode(', ', $actions);

        if ($disableHost) {
            try {
                $message .= '. Caddy disable: ' . $this->caddyApplier->disableSite($site['domain'], $site['caddy_config_path']);
            } catch (\Throwable $exception) {
                $this->sites->updateStatus($siteId, 'error', $exception->getMessage());
                $this->audit($userId, 'site_delete_confirmed', $siteId, 'failed', $exception->getMessage(), $ipAddress);
                throw $exception;
            }
        }

        if ($deleteFiles) {
            try {
                $message .= '. File deletion: ' . $this->provisioner->deleteFiles($site);
            } catch (\Throwable $exception) {
                $this->sites->updateStatus($siteId, 'error', $exception->getMessage());
                $this->audit($userId, 'site_delete_confirmed', $siteId, 'failed', $exception->getMessage(), $ipAddress);
                throw $exception;
            }
        }

        if ($deleteDatabase) {
            try {
                $linkedDatabases = $this->databases->forSite($siteId);

                if ($linkedDatabases === []) {
                    $message .= '. No linked databases to delete';
                }

                foreach ($linkedDatabases as $database) {
                    $this->databaseService->delete((int) $database['id'], $userId, $ipAddress);
                    $message .= '. Deleted database: ' . $database['name'];
                }
            } catch (\Throwable $exception) {
                $this->sites->updateStatus($siteId, 'error', $exception->getMessage());
                $this->audit($userId, 'site_delete_confirmed', $siteId, 'failed', $exception->getMessage(), $ipAddress);
                throw $exception;
            }
        }

        $this->sites->updateStatus($siteId, $status, $message);
        $this->audit($userId, 'site_delete_confirmed', $siteId, 'success', $message, $ipAddress);
    }

    private function normalizeAliases(string $rawAliases, bool $addWww, string $primaryDomain): array
    {
        $aliases = [];

        if ($addWww && !str_starts_with($primaryDomain, 'www.')) {
            $aliases[] = 'www.' . $primaryDomain;
        }

        $lines = preg_split('/\r\n|\r|\n/', $rawAliases) ?: [];

        foreach ($lines as $line) {
            $alias = strtolower(trim($line));

            if ($alias === '') {
                continue;
            }

            if (!Validator::domain($alias)) {
                throw new \InvalidArgumentException('Invalid alias: ' . $alias);
            }

            if ($alias === $primaryDomain) {
                throw new \InvalidArgumentException('Alias cannot match primary domain.');
            }

            $aliases[] = $alias;
        }

        return array_values(array_unique($aliases));
    }

    private function audit(int $userId, string $action, int $siteId, string $status, string $message, string $ipAddress): void
    {
        $this->database->execute(
            'INSERT INTO audit_logs (user_id, action, target_type, target_id, status, message, ip_address, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$userId, $action, 'site', $siteId, $status, $message, $ipAddress, date('Y-m-d H:i:s')]
        );
    }
}
