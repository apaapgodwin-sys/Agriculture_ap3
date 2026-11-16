<?php
declare(strict_types=1);

/**
 * ActivityLog Model Class
 * 
 * Manages administrative audit logging, chronological action trails,
 * user event accountability, and security monitoring.
 */
class ActivityLog extends BaseModel {
    protected string $table = 'activity_logs';
    protected string $primaryKey = 'log_id';

    /**
     * Write an audit record
     */
    public function log(string $action, string $module, string $description, ?int $recordId = null, ?array $details = null, ?array $userContext = null): bool {
        try {
            $user = $userContext ?? (function_exists('current_user') ? current_user() : null);
            $userId = $user['user_id'] ?? null;
            $username = $user['username'] ?? 'system';
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

            $data = [
                'user_id'     => $userId ? (int)$userId : null,
                'username'    => (string)$username,
                'action'      => strtoupper(trim($action)),
                'module'      => strtoupper(trim($module)),
                'record_id'   => $recordId ? (int)$recordId : null,
                'description' => trim($description),
                'details'     => $details !== null ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
                'ip_address'  => substr((string)$ip, 0, 45),
                'created_at'  => date('Y-m-d H:i:s')
            ];

            Database::insert($this->table, $data);
            return true;
        } catch (Throwable $e) {
            error_log("ActivityLog failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get paginated logs matching filter criteria
     */
    public function getAll(string $search = '', string $action = '', string $module = '', int $limit = 15, int $offset = 0): array {
        $sqlWhere = "1=1";
        $params   = [];

        if ($search !== '') {
            $sqlWhere .= " AND (description LIKE :q OR username LIKE :q OR ip_address LIKE :q OR details LIKE :q)";
            $params[':q'] = '%' . $search . '%';
        }
        if ($action !== '') {
            $sqlWhere .= " AND action = :act";
            $params[':act'] = $action;
        }
        if ($module !== '') {
            $sqlWhere .= " AND module = :mod";
            $params[':mod'] = $module;
        }

        $sql = "
            SELECT * FROM {$this->table}
            WHERE {$sqlWhere}
            ORDER BY log_id DESC
            LIMIT {$limit} OFFSET {$offset}
        ";

        return $this->rawQuery($sql, $params);
    }

    /**
     * Count logs matching filter criteria
     */
    public function countFiltered(string $search = '', string $action = '', string $module = ''): int {
        $sqlWhere = "1=1";
        $params   = [];

        if ($search !== '') {
            $sqlWhere .= " AND (description LIKE :q OR username LIKE :q OR ip_address LIKE :q OR details LIKE :q)";
            $params[':q'] = '%' . $search . '%';
        }
        if ($action !== '') {
            $sqlWhere .= " AND action = :act";
            $params[':act'] = $action;
        }
        if ($module !== '') {
            $sqlWhere .= " AND module = :mod";
            $params[':mod'] = $module;
        }

        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE {$sqlWhere}";
        $row = $this->rawQueryOne($sql, $params);
        return (int)($row['total'] ?? 0);
    }

    /**
     * Get recent logs (e.g. For dashboard preview)
     */
    public function getRecent(int $limit = 5): array {
        return $this->rawQuery("SELECT * FROM {$this->table} ORDER BY log_id DESC LIMIT {$limit}");
    }

    /**
     * Clear all logs (Admin action)
     */
    public function clearAll(): bool {
        return $this->rawExecute("DELETE FROM {$this->table}");
    }
}
