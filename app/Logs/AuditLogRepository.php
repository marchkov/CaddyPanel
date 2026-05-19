<?php

namespace CaddyPanel\Logs;

use CaddyPanel\Core\Database;

class AuditLogRepository
{
    public function __construct(private Database $database)
    {
    }

    public function latest(int $limit = 200): array
    {
        return $this->database->fetchAll(
            'SELECT l.*, u.username
             FROM audit_logs l
             LEFT JOIN users u ON u.id = l.user_id
             ORDER BY l.created_at DESC
             LIMIT ?',
            [$limit]
        );
    }
}
