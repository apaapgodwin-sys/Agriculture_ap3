<?php
declare(strict_types=1);

/**
 * Farm Model Class
 * 
 * Manages agricultural land parcels, soil & irrigation classifications,
 * soft-delete (trash bin), restoration, and permanent purge operations.
 */
class Farm extends BaseModel {
    protected string $table = 'farm';
    protected string $primaryKey = 'farm_id';

    /**
     * Get paginated active farms (deleted_at IS NULL)
     */
    public function getActive(string $search = '', int $soilId = 0, int $irrigationId = 0, int $limit = 10, int $offset = 0): array {
        $sqlWhere = "(fm.deleted_at IS NULL)";
        $params   = [];

        if ($search !== '') {
            $sqlWhere .= " AND (fm.farm_name LIKE :q OR fm.location LIKE :q OR f.first_name LIKE :q OR f.last_name LIKE :q)";
            $params[':q'] = '%' . $search . '%';
        }
        if ($soilId > 0) {
            $sqlWhere .= " AND fm.soil_type_id = :soil";
            $params[':soil'] = $soilId;
        }
        if ($irrigationId > 0) {
            $sqlWhere .= " AND fm.irrigation_type_id = :irrigation";
            $params[':irrigation'] = $irrigationId;
        }

        $sql = "
            SELECT fm.*, f.first_name, f.last_name, f.contact_number as farmer_phone,
                   COALESCE(st.soil_type_name, 'Unknown') as soil_type_name,
                   COALESCE(it.irrigation_type_name, 'Unknown') as irrigation_type_name,
                   (SELECT COUNT(*) FROM planting p WHERE p.farm_id = fm.farm_id) as planting_count
            FROM farm fm
            LEFT JOIN farmer f  ON f.farmer_id  = fm.farmer_id
            LEFT JOIN soil_types st ON st.soil_type_id = fm.soil_type_id
            LEFT JOIN irrigation_types it ON it.irrigation_type_id = fm.irrigation_type_id
            WHERE {$sqlWhere}
            ORDER BY fm.farm_id ASC
            LIMIT {$limit} OFFSET {$offset}
        ";

        return $this->rawQuery($sql, $params);
    }

    /**
     * Count active farms matching filter criteria
     */
    public function countActive(string $search = '', int $soilId = 0, int $irrigationId = 0): int {
        $sqlWhere = "(fm.deleted_at IS NULL)";
        $params   = [];

        if ($search !== '') {
            $sqlWhere .= " AND (fm.farm_name LIKE :q OR fm.location LIKE :q OR f.first_name LIKE :q OR f.last_name LIKE :q)";
            $params[':q'] = '%' . $search . '%';
        }
        if ($soilId > 0) {
            $sqlWhere .= " AND fm.soil_type_id = :soil";
            $params[':soil'] = $soilId;
        }
        if ($irrigationId > 0) {
            $sqlWhere .= " AND fm.irrigation_type_id = :irrigation";
            $params[':irrigation'] = $irrigationId;
        }

        $sql = "SELECT COUNT(*) as total FROM farm fm LEFT JOIN farmer f ON f.farmer_id = fm.farmer_id WHERE {$sqlWhere}";
        $row = $this->rawQueryOne($sql, $params);
        return (int)($row['total'] ?? 0);
    }

    /**
     * Get all recently deleted farms (deleted_at IS NOT NULL)
     */
    public function getDeleted(): array {
        $sql = "
            SELECT fm.*, f.first_name, f.last_name,
                   COALESCE(st.soil_type_name, 'Unknown') as soil_type_name,
                   COALESCE(it.irrigation_type_name, 'Unknown') as irrigation_type_name
            FROM farm fm
            LEFT JOIN farmer f  ON f.farmer_id  = fm.farmer_id
            LEFT JOIN soil_types st ON st.soil_type_id = fm.soil_type_id
            LEFT JOIN irrigation_types it ON it.irrigation_type_id = fm.irrigation_type_id
            WHERE fm.deleted_at IS NOT NULL
            ORDER BY fm.deleted_at DESC
        ";
        return $this->rawQuery($sql);
    }

    /**
     * Count recently deleted farms in trash
     */
    public function countDeleted(): int {
        $row = $this->rawQueryOne("SELECT COUNT(*) as total FROM farm WHERE deleted_at IS NOT NULL");
        return (int)($row['total'] ?? 0);
    }

    /**
     * Get active farms for dropdown selections (e.g. In Planting form)
     */
    public function getDropdownList(): array {
        $sql = "
            SELECT fm.farm_id, fm.farm_name, fm.location, f.first_name, f.last_name
            FROM farm fm
            LEFT JOIN farmer f ON f.farmer_id = fm.farmer_id
            WHERE fm.deleted_at IS NULL
            ORDER BY fm.farm_name ASC
        ";
        return $this->rawQuery($sql);
    }

    /**
     * Get lookup: Soil types
     */
    public function getSoilTypes(): array {
        return Database::query('soil_types', 'soil_type_id, soil_type_name', [], 'soil_type_id.asc');
    }

    /**
     * Get lookup: Irrigation types
     */
    public function getIrrigationTypes(): array {
        return Database::query('irrigation_types', 'irrigation_type_id, irrigation_type_name', [], 'irrigation_type_id.asc');
    }

    /**
     * Create new farm parcel
     */
    public function create(array $data): int {
        $res = Database::insert($this->table, $data);
        return (int)($res['id'] ?? $res['farm_id'] ?? 0);
    }

    /**
     * Update existing farm parcel
     */
    public function update(int $farmId, array $data): bool {
        return Database::update($this->table, $this->primaryKey, $farmId, $data);
    }

    /**
     * Soft delete: archive farm to Recently Deleted trash bin
     */
    public function softDelete(int $farmId): bool {
        $now = date('Y-m-d H:i:s');
        Database::update($this->table, $this->primaryKey, $farmId, ['deleted_at' => $now]);
        return $this->rawExecute(
            "UPDATE farm SET deleted_at = :ts WHERE farm_id = :id",
            [':ts' => $now, ':id' => $farmId]
        );
    }

    /**
     * Restore farm from Recently Deleted trash bin
     */
    public function restore(int $farmId): bool {
        Database::update($this->table, $this->primaryKey, $farmId, ['deleted_at' => null]);
        return $this->rawExecute(
            "UPDATE farm SET deleted_at = NULL WHERE farm_id = :id",
            [':id' => $farmId]
        );
    }

    /**
     * Count historical plantings linked to this farm
     */
    public function countLinkedPlantings(int $farmId): int {
        $row = $this->rawQueryOne(
            "SELECT COUNT(*) as cnt FROM planting WHERE farm_id = :id",
            [':id' => $farmId]
        );
        return (int)($row['cnt'] ?? 0);
    }

    /**
     * Permanent delete: physically delete from database if no child records exist
     */
    public function permanentDelete(int $farmId): array {
        $cnt = $this->countLinkedPlantings($farmId);
        if ($cnt > 0) {
            return [
                'success' => false,
                'message' => "Cannot permanently delete this farm: it has {$cnt} historical planting record(s) linked to it. You must delete or reassign those planting records first."
            ];
        }

        $success = $this->delete($farmId);
        return [
            'success' => $success,
            'message' => $success ? 'Farm permanently deleted.' : 'Failed to delete farm.'
        ];
    }
}
