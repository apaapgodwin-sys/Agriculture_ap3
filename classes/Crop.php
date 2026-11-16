<?php
declare(strict_types=1);

/**
 * Crop Model Class
 * 
 * Manages master crop catalog, botanical classifications,
 * growing periods, and recommended seasonal cycles.
 */
class Crop extends BaseModel {
    protected string $table = 'crop';
    protected string $primaryKey = 'crop_id';

    /**
     * Get paginated crops with joined category and season lookups
     */
    public function getAll(string $search = '', int $catId = 0, int $seasonId = 0, int $limit = 10, int $offset = 0): array {
        $sqlWhere = "1=1";
        $params   = [];

        if ($search !== '') {
            $sqlWhere .= " AND (c.crop_name LIKE :q OR c.variety LIKE :q)";
            $params[':q'] = '%' . $search . '%';
        }
        if ($catId > 0) {
            $sqlWhere .= " AND c.category_id = :cat";
            $params[':cat'] = $catId;
        }
        if ($seasonId > 0) {
            $sqlWhere .= " AND c.season_id = :season";
            $params[':season'] = $seasonId;
        }

        $sql = "
            SELECT c.*,
                   COALESCE(cc.category_name, 'Uncategorized') as category_name,
                   COALESCE(rs.season_name, 'Year Round') as season_name,
                   (SELECT COUNT(*) FROM planting p WHERE p.crop_id = c.crop_id) as planting_count
            FROM crop c
            LEFT JOIN crop_categories cc ON cc.category_id = c.category_id
            LEFT JOIN recommended_seasons rs ON rs.season_id = c.season_id
            WHERE {$sqlWhere}
            ORDER BY c.crop_id ASC
            LIMIT {$limit} OFFSET {$offset}
        ";

        return $this->rawQuery($sql, $params);
    }

    /**
     * Count crops matching filter criteria
     */
    public function countFiltered(string $search = '', int $catId = 0, int $seasonId = 0): int {
        $sqlWhere = "1=1";
        $params   = [];

        if ($search !== '') {
            $sqlWhere .= " AND (c.crop_name LIKE :q OR c.variety LIKE :q)";
            $params[':q'] = '%' . $search . '%';
        }
        if ($catId > 0) {
            $sqlWhere .= " AND c.category_id = :cat";
            $params[':cat'] = $catId;
        }
        if ($seasonId > 0) {
            $sqlWhere .= " AND c.season_id = :season";
            $params[':season'] = $seasonId;
        }

        $sql = "SELECT COUNT(*) as total FROM crop c WHERE {$sqlWhere}";
        $row = $this->rawQueryOne($sql, $params);
        return (int)($row['total'] ?? 0);
    }

    /**
     * Get all crops for dropdown lists
     */
    public function getDropdownList(): array {
        return $this->rawQuery("SELECT crop_id, crop_name, variety, growing_period_days FROM crop ORDER BY crop_name ASC, variety ASC");
    }

    /**
     * Get lookup: Crop categories
     */
    public function getCategories(): array {
        return Database::query('crop_categories', 'category_id, category_name', [], 'category_id.asc');
    }

    /**
     * Get lookup: Recommended seasons
     */
    public function getSeasons(): array {
        return Database::query('recommended_seasons', 'season_id, season_name', [], 'season_id.asc');
    }

    /**
     * Create new crop in master catalog
     */
    public function create(array $data): int {
        $res = Database::insert($this->table, $data);
        return (int)($res['id'] ?? $res['crop_id'] ?? 0);
    }

    /**
     * Update crop details
     */
    public function update(int $cropId, array $data): bool {
        return Database::update($this->table, $this->primaryKey, $cropId, $data);
    }

    /**
     * Count plantings linked to this crop
     */
    public function countLinkedPlantings(int $cropId): int {
        $row = $this->rawQueryOne(
            "SELECT COUNT(*) as cnt FROM planting WHERE crop_id = :id",
            [':id' => $cropId]
        );
        return (int)($row['cnt'] ?? 0);
    }

    /**
     * Safely delete crop checking foreign key constraints
     */
    public function deleteCrop(int $cropId): array {
        $linked = $this->countLinkedPlantings($cropId);
        if ($linked > 0) {
            return [
                'success' => false,
                'message' => "Cannot delete this crop: it is currently used in {$linked} planting record(s). Delete or reassign the planting records first."
            ];
        }

        $success = $this->delete($cropId);
        return [
            'success' => $success,
            'message' => $success ? 'Crop record deleted successfully.' : 'Failed to delete crop.'
        ];
    }
}
