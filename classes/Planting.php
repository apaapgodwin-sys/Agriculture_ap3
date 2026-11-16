<?php
declare(strict_types=1);

/**
 * Planting Model Class
 * 
 * Manages agricultural planting cycles, field area allocations,
 * seed tracking, expected harvest dates, and lifecycle statuses.
 */
class Planting extends BaseModel {
    protected string $table = 'planting';
    protected string $primaryKey = 'planting_id';

    /**
     * Get paginated planting cycles with joined Farm, Farmer, Crop, and Status lookups
     */
    public function getAll(string $search = '', int $statusId = 0, int $farmId = 0, int $cropId = 0, int $limit = 10, int $offset = 0): array {
        $sqlWhere = "1=1";
        $params   = [];

        if ($search !== '') {
            $sqlWhere .= " AND (fm.farm_name LIKE :q OR c.crop_name LIKE :q OR f.first_name LIKE :q OR f.last_name LIKE :q)";
            $params[':q'] = '%' . $search . '%';
        }
        if ($statusId > 0) {
            $sqlWhere .= " AND p.status_id = :status";
            $params[':status'] = $statusId;
        }
        if ($farmId > 0) {
            $sqlWhere .= " AND p.farm_id = :farm";
            $params[':farm'] = $farmId;
        }
        if ($cropId > 0) {
            $sqlWhere .= " AND p.crop_id = :crop";
            $params[':crop'] = $cropId;
        }

        $sql = "
            SELECT p.*,
                   fm.farm_name, fm.location as farm_location,
                   f.first_name, f.last_name,
                   c.crop_name, c.variety,
                   COALESCE(ps.status_name, 'Unknown') as status_name,
                   (SELECT COUNT(*) FROM harvest h WHERE h.planting_id = p.planting_id) as harvest_count
            FROM planting p
            LEFT JOIN farm fm ON fm.farm_id = p.farm_id
            LEFT JOIN farmer f ON f.farmer_id = fm.farmer_id
            LEFT JOIN crop c ON c.crop_id = p.crop_id
            LEFT JOIN planting_statuses ps ON ps.status_id = p.status_id
            WHERE {$sqlWhere}
            ORDER BY p.planting_id DESC
            LIMIT {$limit} OFFSET {$offset}
        ";

        return $this->rawQuery($sql, $params);
    }

    /**
     * Count planting records matching filter criteria
     */
    public function countFiltered(string $search = '', int $statusId = 0, int $farmId = 0, int $cropId = 0): int {
        $sqlWhere = "1=1";
        $params   = [];

        if ($search !== '') {
            $sqlWhere .= " AND (fm.farm_name LIKE :q OR c.crop_name LIKE :q OR f.first_name LIKE :q OR f.last_name LIKE :q)";
            $params[':q'] = '%' . $search . '%';
        }
        if ($statusId > 0) {
            $sqlWhere .= " AND p.status_id = :status";
            $params[':status'] = $statusId;
        }
        if ($farmId > 0) {
            $sqlWhere .= " AND p.farm_id = :farm";
            $params[':farm'] = $farmId;
        }
        if ($cropId > 0) {
            $sqlWhere .= " AND p.crop_id = :crop";
            $params[':crop'] = $cropId;
        }

        $sql = "
            SELECT COUNT(*) as total
            FROM planting p
            LEFT JOIN farm fm ON fm.farm_id = p.farm_id
            LEFT JOIN farmer f ON f.farmer_id = fm.farmer_id
            LEFT JOIN crop c ON c.crop_id = p.crop_id
            WHERE {$sqlWhere}
        ";
        $row = $this->rawQueryOne($sql, $params);
        return (int)($row['total'] ?? 0);
    }

    /**
     * Get lookup: Planting statuses
     */
    public function getStatuses(): array {
        return Database::query('planting_statuses', 'status_id, status_name', [], 'status_id.asc');
    }

    /**
     * Get plantings eligible for harvest logging
     */
    public function getEligibleForHarvest(): array {
        $sql = "
            SELECT p.planting_id, p.planting_date, p.area_planted,
                   fm.farm_name, c.crop_name, c.variety
            FROM planting p
            LEFT JOIN farm fm ON fm.farm_id = p.farm_id
            LEFT JOIN crop c ON c.crop_id = p.crop_id
            ORDER BY p.planting_id DESC
        ";
        return $this->rawQuery($sql);
    }

    /**
     * Create new planting cycle
     */
    public function create(array $data): int {
        $res = Database::insert($this->table, $data);
        return (int)($res['id'] ?? $res['planting_id'] ?? 0);
    }

    /**
     * Update planting cycle details
     */
    public function update(int $plantingId, array $data): bool {
        return Database::update($this->table, $this->primaryKey, $plantingId, $data);
    }

    /**
     * Count linked harvest transactions
     */
    public function countLinkedHarvests(int $plantingId): int {
        $row = $this->rawQueryOne(
            "SELECT COUNT(*) as cnt FROM harvest WHERE planting_id = :id",
            [':id' => $plantingId]
        );
        return (int)($row['cnt'] ?? 0);
    }

    /**
     * Safely delete planting record checking foreign key constraints
     */
    public function deletePlanting(int $plantingId): array {
        $cnt = $this->countLinkedHarvests($plantingId);
        if ($cnt > 0) {
            return [
                'success' => false,
                'message' => "Cannot delete this planting record: it is linked to {$cnt} recorded harvest(s). Delete the harvest records first."
            ];
        }

        $success = $this->delete($plantingId);
        return [
            'success' => $success,
            'message' => $success ? 'Planting record deleted successfully.' : 'Failed to delete planting record.'
        ];
    }
}
