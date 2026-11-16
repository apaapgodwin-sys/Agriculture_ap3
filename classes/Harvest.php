<?php
declare(strict_types=1);

/**
 * Harvest Model Class
 * 
 * Manages yield output recordings, quality grading, commodity pricing,
 * gross valuation computations, and post-harvest audit records.
 */
class Harvest extends BaseModel {
    protected string $table = 'harvest';
    protected string $primaryKey = 'harvest_id';

    /**
     * Get paginated harvest transactions with joined Planting, Farm, Crop, Grade & Remarks
     */
    public function getAll(string $search = '', int $gradeId = 0, int $remarkId = 0, int $limit = 10, int $offset = 0): array {
        $sqlWhere = "1=1";
        $params   = [];

        if ($search !== '') {
            $sqlWhere .= " AND (fm.farm_name LIKE :q OR c.crop_name LIKE :q OR h.remarks_note LIKE :q)";
            $params[':q'] = '%' . $search . '%';
        }
        if ($gradeId > 0) {
            $sqlWhere .= " AND h.quality_grade_id = :grade";
            $params[':grade'] = $gradeId;
        }
        if ($remarkId > 0) {
            $sqlWhere .= " AND h.remark_id = :remark";
            $params[':remark'] = $remarkId;
        }

        $sql = "
            SELECT h.*,
                   (h.quantity_kg * h.selling_price_per_kg) as total_valuation,
                   (h.quantity_kg * h.selling_price_per_kg) as total_value,
                   p.planting_date, p.area_planted,
                   fm.farm_name,
                   fm.first_name, fm.last_name,
                   c.crop_name, c.variety,
                   COALESCE(qg.grade_name, 'Standard') as grade_name,
                   COALESCE(hr.remark_description, 'Normal') as remark_description
            FROM harvest h
            LEFT JOIN planting p ON p.planting_id = h.planting_id
            LEFT JOIN farm f ON f.farm_id = p.farm_id
            LEFT JOIN farmer fm ON fm.farmer_id = f.farmer_id
            LEFT JOIN crop c ON c.crop_id = p.crop_id
            LEFT JOIN harvest_quality_grades qg ON qg.quality_grade_id = h.quality_grade_id
            LEFT JOIN harvest_remarks hr ON hr.remark_id = h.remark_id
            WHERE {$sqlWhere}
            ORDER BY h.harvest_date DESC, h.harvest_id DESC
            LIMIT {$limit} OFFSET {$offset}
        ";

        return $this->rawQuery($sql, $params);
    }

    /**
     * Count harvest records matching filter criteria
     */
    public function countFiltered(string $search = '', int $gradeId = 0, int $remarkId = 0): int {
        $sqlWhere = "1=1";
        $params   = [];

        if ($search !== '') {
            $sqlWhere .= " AND (fm.farm_name LIKE :q OR c.crop_name LIKE :q OR h.remarks_note LIKE :q)";
            $params[':q'] = '%' . $search . '%';
        }
        if ($gradeId > 0) {
            $sqlWhere .= " AND h.quality_grade_id = :grade";
            $params[':grade'] = $gradeId;
        }
        if ($remarkId > 0) {
            $sqlWhere .= " AND h.remark_id = :remark";
            $params[':remark'] = $remarkId;
        }

        $sql = "
            SELECT COUNT(*) as total
            FROM harvest h
            LEFT JOIN planting p ON p.planting_id = h.planting_id
            LEFT JOIN farm fm ON fm.farm_id = p.farm_id
            LEFT JOIN crop c ON c.crop_id = p.crop_id
            WHERE {$sqlWhere}
        ";
        $row = $this->rawQueryOne($sql, $params);
        return (int)($row['total'] ?? 0);
    }

    /**
     * Get aggregate yield and monetary valuation totals
     */
    public function getTotals(): array {
        $sql = "
            SELECT COUNT(*) as total_count,
                   COALESCE(SUM(quantity_kg), 0) as total_kg,
                   COALESCE(SUM(quantity_kg * selling_price_per_kg), 0) as total_valuation,
                   COALESCE(AVG(selling_price_per_kg), 0) as avg_price
            FROM harvest
        ";
        $row = $this->rawQueryOne($sql);
        return [
            'count'           => (int)($row['total_count'] ?? 0),
            'total_kg'        => (float)($row['total_kg'] ?? 0.0),
            'total_valuation' => (float)($row['total_valuation'] ?? 0.0),
            'avg_price'       => (float)($row['avg_price'] ?? 0.0)
        ];
    }

    /**
     * Get lookup: Quality grades
     */
    public function getQualityGrades(): array {
        return Database::query('harvest_quality_grades', 'quality_grade_id, grade_name', [], 'quality_grade_id.asc');
    }

    /**
     * Get lookup: Harvest remarks
     */
    public function getRemarks(): array {
        return Database::query('harvest_remarks', 'remark_id, remark_description', [], 'remark_id.asc');
    }

    /**
     * Create new harvest transaction record
     */
    public function create(array $data): int {
        $res = Database::insert($this->table, $data);
        return (int)($res['id'] ?? $res['harvest_id'] ?? 0);
    }

    /**
     * Update harvest transaction record
     */
    public function update(int $harvestId, array $data): bool {
        return Database::update($this->table, $this->primaryKey, $harvestId, $data);
    }

    /**
     * Delete harvest record
     */
    public function deleteHarvest(int $harvestId): bool {
        return $this->delete($harvestId);
    }
}
