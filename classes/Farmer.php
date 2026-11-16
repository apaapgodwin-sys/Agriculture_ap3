<?php
declare(strict_types=1);

/**
 * Farmer Model Class
 * 
 * Manages agricultural farmer profiles, contact records,
 * and relational associations with registered farm parcels.
 */
class Farmer extends BaseModel {
    protected string $table = 'farmer';
    protected string $primaryKey = 'farmer_id';

    /**
     * Get paginated & filtered list of farmers with farm counts
     */
    public function getAll(string $search = '', string $sort = 'id_asc', int $limit = 10, int $offset = 0): array {
        $orderClause = match ($sort) {
            'name_asc'  => 'f.last_name ASC, f.first_name ASC',
            'name_desc' => 'f.last_name DESC, f.first_name DESC',
            'date_desc' => 'f.date_registered DESC',
            'date_asc'  => 'f.date_registered ASC',
            'id_desc'   => 'f.farmer_id DESC',
            default     => 'f.farmer_id ASC'
        };

        $sqlWhere = "1=1";
        $params = [];
        if ($search !== '') {
            $sqlWhere .= " AND (f.first_name LIKE :q OR f.last_name LIKE :q OR f.address LIKE :q OR f.contact_number LIKE :q)";
            $params[':q'] = '%' . $search . '%';
        }

        $sql = "
            SELECT f.*,
                   (SELECT COUNT(*) FROM farm fm WHERE fm.farmer_id = f.farmer_id AND fm.deleted_at IS NULL) as farm_count
            FROM farmer f
            WHERE {$sqlWhere}
            ORDER BY {$orderClause}
            LIMIT {$limit} OFFSET {$offset}
        ";

        return $this->rawQuery($sql, $params);
    }

    /**
     * Count total farmers matching search filters
     */
    public function countFiltered(string $search = ''): int {
        $sqlWhere = "1=1";
        $params = [];
        if ($search !== '') {
            $sqlWhere .= " AND (f.first_name LIKE :q OR f.last_name LIKE :q OR f.address LIKE :q OR f.contact_number LIKE :q)";
            $params[':q'] = '%' . $search . '%';
        }

        $sql = "SELECT COUNT(*) as total FROM farmer f WHERE {$sqlWhere}";
        $row = $this->rawQueryOne($sql, $params);
        return (int)($row['total'] ?? 0);
    }

    /**
     * Get all farmers for dropdown selections (ordered by last name)
     */
    public function getDropdownList(): array {
        return $this->rawQuery("SELECT farmer_id, first_name, last_name, contact_number, address FROM farmer ORDER BY last_name ASC, first_name ASC");
    }

    /**
     * Get single farmer with full profile details
     */
    public function getById(int $farmerId): ?array {
        return $this->find($farmerId);
    }

    /**
     * Get all active farms owned by a specific farmer
     */
    public function getFarms(int $farmerId): array {
        $sql = "
            SELECT fm.*, st.soil_type_name, it.irrigation_type_name
            FROM farm fm
            LEFT JOIN soil_types st ON st.soil_type_id = fm.soil_type_id
            LEFT JOIN irrigation_types it ON it.irrigation_type_id = fm.irrigation_type_id
            WHERE fm.farmer_id = :fid AND fm.deleted_at IS NULL
            ORDER BY fm.farm_name ASC
        ";
        return $this->rawQuery($sql, [':fid' => $farmerId]);
    }

    /**
     * Count linked active farms
     */
    public function countLinkedFarms(int $farmerId): int {
        $row = $this->rawQueryOne(
            "SELECT COUNT(*) as cnt FROM farm WHERE farmer_id = :id AND deleted_at IS NULL",
            [':id' => $farmerId]
        );
        return (int)($row['cnt'] ?? 0);
    }

    /**
     * Create new farmer profile
     */
    public function create(array $data): int {
        $res = Database::insert($this->table, $data);
        return (int)($res['id'] ?? $res['farmer_id'] ?? 0);
    }

    /**
     * Update existing farmer profile
     */
    public function update(int $farmerId, array $data): bool {
        return Database::update($this->table, $this->primaryKey, $farmerId, $data);
    }

    /**
     * Safely delete farmer checking foreign key constraints
     */
    public function deleteFarmer(int $farmerId): array {
        $linked = $this->countLinkedFarms($farmerId);
        if ($linked > 0) {
            return [
                'success' => false,
                'message' => "Cannot delete this farmer: this farmer owns {$linked} registered farm(s). You must reassign or remove the farms first."
            ];
        }

        $success = $this->delete($farmerId);
        return [
            'success' => $success,
            'message' => $success ? 'Farmer record deleted successfully.' : 'Failed to delete farmer record.'
        ];
    }
}
