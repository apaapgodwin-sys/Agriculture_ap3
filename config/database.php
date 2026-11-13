<?php
declare(strict_types=1);

/**
 * AgriTrack Database Layer
 * Supports Supabase PostgreSQL REST API with automated local SQLite fallback.
 */
class Database {
    private static ?PDO $sqlitePdo = null;
    private static ?bool $supabaseAvailable = null;

    /**
     * Get SQLite PDO instance (auto-creates tables and seeds if file missing)
     */
    public static function getLocalPdo(): PDO {
        if (self::$sqlitePdo !== null) {
            return self::$sqlitePdo;
        }

        $dataDir = __DIR__ . '/../data';
        if (!is_dir($dataDir)) {
            @mkdir($dataDir, 0777, true);
        }

        $dbFile = $dataDir . '/agriculture.sqlite';
        $needsInit = !file_exists($dbFile) || filesize($dbFile) === 0;

        self::$sqlitePdo = new PDO('sqlite:' . $dbFile, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        self::$sqlitePdo->exec("PRAGMA foreign_keys = ON;");

        if ($needsInit) {
            self::initLocalDatabase(self::$sqlitePdo);
        }

        return self::$sqlitePdo;
    }

    /**
     * Check if Supabase REST API is reachable and ready
     */
    public static function isSupabaseReady(): bool {
        if (self::$supabaseAvailable !== null) {
            return self::$supabaseAvailable;
        }

        $url = rtrim(env('SUPABASE_URL'), '/');
        $key = env('SUPABASE_ANON_KEY');

        if (empty($url) || empty($key) || str_contains($url, 'YOUR_PROJECT') || str_contains($key, 'YOUR_SUPABASE')) {
            self::$supabaseAvailable = false;
            return false;
        }

        if (!function_exists('curl_init')) {
            self::$supabaseAvailable = false;
            return false;
        }

        // Test pinging the farmer table
        $ch = curl_init($url . '/rest/v1/farmer?select=farmer_id&limit=1');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $key,
                'Authorization: Bearer ' . $key,
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 3,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $resp = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // 200 means table exists and is accessible
        self::$supabaseAvailable = ($status === 200);
        return self::$supabaseAvailable;
    }

    /**
     * Call Supabase REST API
     */
    public static function supabaseRequest(string $endpoint, string $method = 'GET', ?array $body = null, array $headers = []): array {
        $url = rtrim(env('SUPABASE_URL'), '/') . '/rest/v1/' . ltrim($endpoint, '/');
        $key = env('SUPABASE_ANON_KEY');

        $ch = curl_init($url);
        $reqHeaders = [
            'apikey: ' . $key,
            'Authorization: Bearer ' . $key,
            'Accept: application/json'
        ];

        if ($method === 'POST' || $method === 'PATCH') {
            $reqHeaders[] = 'Content-Type: application/json';
            $reqHeaders[] = 'Prefer: return=representation';
        }

        foreach ($headers as $h) {
            $reqHeaders[] = $h;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $reqHeaders,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        }

        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException("Supabase request error: {$err}");
        }

        $data = json_decode($raw, true);
        if ($status >= 400) {
            $msg = is_array($data) ? ($data['message'] ?? $data['msg'] ?? $data['details'] ?? 'Supabase API error') : 'Supabase API error';
            throw new RuntimeException("Supabase returned {$status}: {$msg}");
        }

        return is_array($data) ? $data : [];
    }

    /**
     * SELECT query helper
     */
    public static function query(string $table, string $select = '*', array $filter = [], string $order = '', int $limit = 1000, int $offset = 0): array {
        if (self::isSupabaseReady()) {
            try {
                $params = ['select' => $select];
                foreach ($filter as $col => $val) {
                    if (str_starts_with($col, 'like_')) {
                        $actualCol = substr($col, 5);
                        $params[$actualCol] = 'ilike.*' . rawurlencode((string)$val) . '*';
                    } else {
                        $params[$col] = 'eq.' . rawurlencode((string)$val);
                    }
                }
                if ($order !== '') {
                    $params['order'] = $order;
                }
                if ($limit > 0) {
                    $params['limit'] = (string)$limit;
                }
                if ($offset > 0) {
                    $params['offset'] = (string)$offset;
                }
                $qs = http_build_query($params);
                return self::supabaseRequest($table . '?' . $qs, 'GET');
            } catch (Throwable $e) {
                error_log("Supabase error: " . $e->getMessage() . ", falling back to local SQLite.");
            }
        }

        // Local SQLite execution
        $pdo = self::getLocalPdo();
        $sql = "SELECT {$select} FROM {$table}";
        $where = [];
        $bindings = [];
        foreach ($filter as $col => $val) {
            if (str_starts_with($col, 'like_')) {
                $actualCol = substr($col, 5);
                $where[] = "{$actualCol} LIKE :{$actualCol}";
                $bindings[":{$actualCol}"] = '%' . $val . '%';
            } else {
                $where[] = "{$col} = :{$col}";
                $bindings[":{$col}"] = $val;
            }
        }
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        if ($order !== '') {
            $sqlOrder = str_replace(['.asc', '.desc'], [' ASC', ' DESC'], $order);
            $sql .= " ORDER BY {$sqlOrder}";
        }
        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
        }
        if ($offset > 0) {
            $sql .= " OFFSET {$offset}";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->fetchAll();
    }

    /**
     * INSERT query helper
     */
    public static function insert(string $table, array $data): array {
        if (self::isSupabaseReady()) {
            try {
                $res = self::supabaseRequest($table, 'POST', $data);
                // Also mirror to local SQLite for backup
                try {
                    self::sqliteInsert($table, $data);
                } catch (Throwable) {}
                return $res[0] ?? $res;
            } catch (Throwable $e) {
                error_log("Supabase insert error: " . $e->getMessage() . ", saving to local SQLite.");
            }
        }

        return self::sqliteInsert($table, $data);
    }

    /**
     * UPDATE query helper
     */
    public static function update(string $table, string $pkCol, mixed $pkVal, array $data): bool {
        if (self::isSupabaseReady()) {
            try {
                self::supabaseRequest($table . '?' . $pkCol . '=eq.' . rawurlencode((string)$pkVal), 'PATCH', $data);
                // Also mirror to local SQLite
                try {
                    self::sqliteUpdate($table, $pkCol, $pkVal, $data);
                } catch (Throwable) {}
                return true;
            } catch (Throwable $e) {
                error_log("Supabase update error: " . $e->getMessage() . ", updating local SQLite.");
            }
        }

        return self::sqliteUpdate($table, $pkCol, $pkVal, $data);
    }

    /**
     * DELETE query helper
     */
    public static function delete(string $table, string $pkCol, mixed $pkVal): bool {
        if (self::isSupabaseReady()) {
            try {
                self::supabaseRequest($table . '?' . $pkCol . '=eq.' . rawurlencode((string)$pkVal), 'DELETE');
                try {
                    self::sqliteDelete($table, $pkCol, $pkVal);
                } catch (Throwable) {}
                return true;
            } catch (Throwable $e) {
                error_log("Supabase delete error: " . $e->getMessage() . ", deleting in local SQLite.");
            }
        }

        return self::sqliteDelete($table, $pkCol, $pkVal);
    }

    /**
     * Raw execute on SQLite (for joins and complex aggregation)
     */
    public static function rawQuery(string $sql, array $params = []): array {
        $pdo = self::getLocalPdo();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private static function sqliteInsert(string $table, array $data): array {
        $pdo = self::getLocalPdo();
        $cols = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $cols);
        $sql = "INSERT INTO {$table} (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $pdo->prepare($sql);
        $bindings = [];
        foreach ($data as $k => $v) {
            $bindings[':' . $k] = $v;
        }
        $stmt->execute($bindings);
        $lastId = $pdo->lastInsertId();
        return array_merge($data, ['id' => $lastId]);
    }

    private static function sqliteUpdate(string $table, string $pkCol, mixed $pkVal, array $data): bool {
        $pdo = self::getLocalPdo();
        $sets = [];
        $bindings = [":pk" => $pkVal];
        foreach ($data as $k => $v) {
            $sets[] = "{$k} = :{$k}";
            $bindings[":{$k}"] = $v;
        }
        $sql = "UPDATE {$table} SET " . implode(', ', $sets) . " WHERE {$pkCol} = :pk";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($bindings);
    }

    private static function sqliteDelete(string $table, string $pkCol, mixed $pkVal): bool {
        $pdo = self::getLocalPdo();
        $stmt = $pdo->prepare("DELETE FROM {$table} WHERE {$pkCol} = :pk");
        return $stmt->execute([':pk' => $pkVal]);
    }

    /**
     * Initialize Local SQLite Database with 3NF Schema & Complete Seed Data
     */
    public static function initLocalDatabase(PDO $pdo): void {
        $schemaSql = <<<SQL
        CREATE TABLE IF NOT EXISTS users (
            user_id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            full_name TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'staff',
            status TEXT NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS crop_categories (
            category_id INTEGER PRIMARY KEY AUTOINCREMENT,
            category_name TEXT NOT NULL UNIQUE
        );

        CREATE TABLE IF NOT EXISTS recommended_seasons (
            season_id INTEGER PRIMARY KEY AUTOINCREMENT,
            season_name TEXT NOT NULL UNIQUE
        );

        CREATE TABLE IF NOT EXISTS soil_types (
            soil_type_id INTEGER PRIMARY KEY AUTOINCREMENT,
            soil_type_name TEXT NOT NULL UNIQUE
        );

        CREATE TABLE IF NOT EXISTS irrigation_types (
            irrigation_type_id INTEGER PRIMARY KEY AUTOINCREMENT,
            irrigation_type_name TEXT NOT NULL UNIQUE
        );

        CREATE TABLE IF NOT EXISTS planting_statuses (
            status_id INTEGER PRIMARY KEY AUTOINCREMENT,
            status_name TEXT NOT NULL UNIQUE
        );

        CREATE TABLE IF NOT EXISTS harvest_quality_grades (
            quality_grade_id INTEGER PRIMARY KEY AUTOINCREMENT,
            grade_name TEXT NOT NULL UNIQUE
        );

        CREATE TABLE IF NOT EXISTS harvest_remarks (
            remark_id INTEGER PRIMARY KEY AUTOINCREMENT,
            remark_description TEXT NOT NULL UNIQUE
        );

        CREATE TABLE IF NOT EXISTS farmer (
            farmer_id INTEGER PRIMARY KEY AUTOINCREMENT,
            first_name TEXT NOT NULL,
            last_name TEXT NOT NULL,
            contact_number TEXT,
            address TEXT NOT NULL,
            date_registered DATE NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS farm (
            farm_id INTEGER PRIMARY KEY AUTOINCREMENT,
            farmer_id INTEGER NOT NULL REFERENCES farmer(farmer_id) ON DELETE RESTRICT,
            farm_name TEXT NOT NULL,
            location TEXT NOT NULL,
            area_hectares REAL NOT NULL,
            soil_type_id INTEGER NOT NULL REFERENCES soil_types(soil_type_id) ON DELETE RESTRICT,
            irrigation_type_id INTEGER NOT NULL REFERENCES irrigation_types(irrigation_type_id) ON DELETE RESTRICT,
            deleted_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS crop (
            crop_id INTEGER PRIMARY KEY AUTOINCREMENT,
            crop_name TEXT NOT NULL,
            variety TEXT,
            category_id INTEGER NOT NULL REFERENCES crop_categories(category_id) ON DELETE RESTRICT,
            growing_period_days INTEGER NOT NULL,
            season_id INTEGER NOT NULL REFERENCES recommended_seasons(season_id) ON DELETE RESTRICT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS planting (
            planting_id INTEGER PRIMARY KEY AUTOINCREMENT,
            farm_id INTEGER NOT NULL REFERENCES farm(farm_id) ON DELETE RESTRICT,
            crop_id INTEGER NOT NULL REFERENCES crop(crop_id) ON DELETE RESTRICT,
            planting_date DATE NOT NULL,
            area_planted REAL NOT NULL,
            seed_quantity REAL,
            expected_harvest_date DATE,
            status_id INTEGER NOT NULL REFERENCES planting_statuses(status_id) ON DELETE RESTRICT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS harvest (
            harvest_id INTEGER PRIMARY KEY AUTOINCREMENT,
            planting_id INTEGER NOT NULL REFERENCES planting(planting_id) ON DELETE RESTRICT,
            harvest_date DATE NOT NULL,
            quantity_kg REAL NOT NULL,
            selling_price_per_kg REAL NOT NULL,
            quality_grade_id INTEGER NOT NULL REFERENCES harvest_quality_grades(quality_grade_id) ON DELETE RESTRICT,
            remark_id INTEGER NOT NULL REFERENCES harvest_remarks(remark_id) ON DELETE RESTRICT,
            remarks_note TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
SQL;
        $pdo->exec($schemaSql);

        // Seed Lookups
        $pdo->exec("
            INSERT OR IGNORE INTO crop_categories (category_id, category_name) VALUES
            (1, 'Cereal'), (2, 'Vegetable'), (3, 'Fruit'), (4, 'Legume'), (5, 'Root Crop');

            INSERT OR IGNORE INTO recommended_seasons (season_id, season_name) VALUES
            (1, 'Wet Season'), (2, 'Dry Season'), (3, 'Year Round');

            INSERT OR IGNORE INTO soil_types (soil_type_id, soil_type_name) VALUES
            (1, 'Loam'), (2, 'Clay Loam'), (3, 'Sandy Loam'), (4, 'Clay');

            INSERT OR IGNORE INTO irrigation_types (irrigation_type_id, irrigation_type_name) VALUES
            (1, 'Rainfed'), (2, 'Irrigated'), (3, 'Drip Irrigation');

            INSERT OR IGNORE INTO planting_statuses (status_id, status_name) VALUES
            (1, 'Growing'), (2, 'Harvested'), (3, 'Cancelled'), (4, 'Planned');

            INSERT OR IGNORE INTO harvest_quality_grades (quality_grade_id, grade_name) VALUES
            (1, 'Grade A'), (2, 'Grade B'), (3, 'Grade C');

            INSERT OR IGNORE INTO harvest_remarks (remark_id, remark_description) VALUES
            (1, 'Good Harvest'), (2, 'Acceptable Quality'), (3, 'Low Yield'), (4, 'Poor Quality'), (5, 'Damaged Harvest');
        ");

        // Seed Users (Admin: admin / admin123, Staff: staff / staff123)
        $adminHash = '$2y$10$oBNwNiMOYG7ZuPNKH/0YsO8j3At3HBXYUQieUh2c6dtjUpuS4U4NC';
        $staffHash = '$2y$10$cpSvk9tv.WM9ZIvuKB/rTeoVT8bs7Bn0oXa946O2Wx7qLefqECfEq';
        $pdo->exec("
            INSERT OR IGNORE INTO users (user_id, username, email, password_hash, full_name, role, status) VALUES
            (1, 'admin', 'admin@agritrack.com', '{$adminHash}', 'System Administrator', 'admin', 'active'),
            (2, 'staff', 'staff@agritrack.com', '{$staffHash}', 'Agricultural Field Officer', 'staff', 'active');
        ");

        // Seed Crops (10 rows)
        $pdo->exec("
            INSERT OR IGNORE INTO crop (crop_id, crop_name, variety, category_id, growing_period_days, season_id) VALUES
            (1, 'Rice', 'NSIC Rc 222', 1, 115, 1),
            (2, 'Corn', 'Yellow Corn', 1, 100, 2),
            (3, 'Tomato', 'Diamante Max', 2, 90, 2),
            (4, 'Eggplant', 'Morena', 2, 100, 3),
            (5, 'Banana', 'Lakatan', 3, 300, 3),
            (6, 'Mango', 'Carabao', 3, 150, 2),
            (7, 'Peanut', 'BPI Pn 9', 4, 110, 2),
            (8, 'Cassava', 'Golden Yellow', 5, 240, 3),
            (9, 'Sweet Potato', 'NSIC Sp 30', 5, 120, 3),
            (10, 'Watermelon', 'Sweet 16', 3, 85, 2);
        ");

        // Seed Farmers (15 rows)
        $pdo->exec("
            INSERT OR IGNORE INTO farmer (farmer_id, first_name, last_name, contact_number, address, date_registered) VALUES
            (1, 'Juan', 'Dela Cruz', '09171234567', 'Cebu City', '2026-10-26'),
            (2, 'Maria', 'Santos', '09181234567', 'Carcar City, Cebu', '2026-10-26'),
            (3, 'Pedro', 'Reyes', '09191234567', 'Danao City, Cebu', '2026-10-26'),
            (4, 'Ana', 'Garcia', '09201234567', 'Argao, Cebu', '2026-10-26'),
            (5, 'Jose', 'Mendoza', '09211234567', 'Bogo City, Cebu', '2026-10-26'),
            (6, 'Ramon', 'Flores', '09221234567', 'Toledo City, Cebu', '2026-10-26'),
            (7, 'Elena', 'Cruz', '09231234567', 'Naga City, Cebu', '2026-10-26'),
            (8, 'Antonio', 'Lim', '09241234567', 'Minglanilla, Cebu', '2026-10-26'),
            (9, 'Rosa', 'Fernandez', '09251234567', 'Liloan, Cebu', '2026-10-26'),
            (10, 'Mario', 'Villanueva', '09261234567', 'Compostela, Cebu', '2026-10-26'),
            (11, 'Teresa', 'Navarro', '09271234567', 'Talisay City, Cebu', '2026-10-26'),
            (12, 'Carlos', 'Ramos', '09281234567', 'Consolacion, Cebu', '2026-10-26'),
            (13, 'Liza', 'Torres', '09291234567', 'Dalaguete, Cebu', '2026-10-26'),
            (14, 'Roberto', 'Castillo', '09301234567', 'Barili, Cebu', '2026-10-26'),
            (15, 'Susan', 'Aquino', '09311234567', 'Sibonga, Cebu', '2026-10-26');
        ");

        // Seed Farms (15 rows)
        $pdo->exec("
            INSERT OR IGNORE INTO farm (farm_id, farmer_id, farm_name, location, area_hectares, soil_type_id, irrigation_type_id) VALUES
            (1, 1, 'Dela Cruz Farm', 'Cebu City', 2.50, 1, 1),
            (2, 2, 'Santos Rice Farm', 'Carcar City, Cebu', 4.20, 2, 2),
            (3, 3, 'Reyes Agricultural Farm', 'Danao City, Cebu', 3.80, 3, 1),
            (4, 4, 'Garcia Vegetable Farm', 'Argao, Cebu', 1.75, 1, 3),
            (5, 5, 'Mendoza Farm', 'Bogo City, Cebu', 5.00, 4, 2),
            (6, 6, 'Flores Corn Farm', 'Toledo City, Cebu', 3.40, 3, 1),
            (7, 7, 'Cruz Family Farm', 'Naga City, Cebu', 2.80, 1, 2),
            (8, 8, 'Lim Agricultural Farm', 'Minglanilla, Cebu', 2.25, 2, 3),
            (9, 9, 'Fernandez Farm', 'Liloan, Cebu', 3.10, 3, 1),
            (10, 10, 'Villanueva Green Farm', 'Compostela, Cebu', 4.50, 1, 2),
            (11, 11, 'Navarro Rice Field', 'Talisay City, Cebu', 3.75, 4, 2),
            (12, 12, 'Ramos Farm', 'Consolacion, Cebu', 2.60, 1, 1),
            (13, 13, 'Torres Vegetable Farm', 'Dalaguete, Cebu', 1.80, 3, 3),
            (14, 14, 'Castillo Agricultural Farm', 'Barili, Cebu', 5.50, 2, 2),
            (15, 15, 'Aquino Farm', 'Sibonga, Cebu', 3.20, 1, 1);
        ");

        // Seed Plantings (28 rows)
        $pdo->exec("
            INSERT OR IGNORE INTO planting (planting_id, farm_id, crop_id, planting_date, area_planted, seed_quantity, expected_harvest_date, status_id) VALUES
            (1, 1, 1, '2026-01-10', 0.50, 10.00, '2026-05-05', 2),
            (2, 2, 2, '2026-01-13', 0.60, 15.00, '2026-04-23', 2),
            (3, 3, 3, '2026-01-16', 0.70, 20.00, '2026-04-16', 2),
            (4, 4, 4, '2026-01-19', 0.80, 25.00, '2026-04-29', 2),
            (5, 5, 5, '2026-01-22', 0.90, 30.00, '2026-11-18', 2),
            (6, 6, 6, '2026-01-25', 1.00, 35.00, '2026-06-24', 2),
            (7, 7, 7, '2026-01-28', 1.10, 40.00, '2026-05-18', 2),
            (8, 8, 8, '2026-01-31', 1.20, 45.00, '2026-09-28', 2),
            (9, 9, 9, '2026-02-03', 1.30, 10.00, '2026-06-03', 2),
            (10, 10, 10, '2026-02-06', 1.40, 15.00, '2026-05-02', 2),
            (11, 11, 1, '2026-02-09', 0.50, 20.00, '2026-06-04', 2),
            (12, 12, 2, '2026-02-12', 0.60, 25.00, '2026-05-23', 2),
            (13, 13, 3, '2026-02-15', 0.70, 30.00, '2026-05-16', 2),
            (14, 14, 4, '2026-02-18', 0.80, 35.00, '2026-05-29', 2),
            (15, 15, 5, '2026-02-21', 0.90, 40.00, '2026-12-18', 2),
            (16, 1, 6, '2026-02-24', 1.00, 45.00, '2026-07-24', 2),
            (17, 2, 7, '2026-02-27', 1.10, 10.00, '2026-06-17', 2),
            (18, 3, 8, '2026-03-02', 1.20, 15.00, '2026-10-28', 2),
            (19, 4, 9, '2026-03-05', 1.30, 20.00, '2026-07-03', 2),
            (20, 5, 10, '2026-03-08', 1.40, 25.00, '2026-06-01', 2),
            (21, 6, 1, '2026-03-11', 0.50, 30.00, '2026-07-04', 1),
            (22, 7, 2, '2026-03-14', 0.60, 35.00, '2026-06-22', 1),
            (23, 8, 3, '2026-03-17', 0.70, 40.00, '2026-06-15', 1),
            (24, 9, 4, '2026-03-20', 0.80, 45.00, '2026-06-28', 1),
            (25, 10, 5, '2026-03-23', 0.90, 10.00, '2027-01-17', 1),
            (26, 11, 6, '2026-03-26', 1.00, 15.00, '2026-08-23', 1),
            (27, 12, 7, '2026-03-29', 1.10, 20.00, '2026-07-17', 1),
            (28, 13, 8, '2026-04-01', 1.20, 25.00, '2026-11-27', 1);
        ");

        // Seed Harvests (20 rows)
        $pdo->exec("
            INSERT OR IGNORE INTO harvest (harvest_id, planting_id, harvest_date, quantity_kg, selling_price_per_kg, quality_grade_id, remark_id, remarks_note) VALUES
            (1, 1, '2026-05-07', 900.00, 22.00, 1, 1, 'High quality harvest'),
            (2, 2, '2026-04-25', 1025.00, 25.50, 2, 1, 'Good harvest'),
            (3, 3, '2026-04-18', 1150.00, 29.00, 3, 2, 'Acceptable quality'),
            (4, 4, '2026-05-01', 1275.00, 32.50, 1, 1, 'High quality harvest'),
            (5, 5, '2026-11-20', 1400.00, 36.00, 2, 1, 'Good harvest'),
            (6, 6, '2026-06-26', 1525.00, 39.50, 3, 2, 'Acceptable quality'),
            (7, 7, '2026-05-20', 1650.00, 43.00, 1, 1, 'High quality harvest'),
            (8, 8, '2026-09-30', 1775.00, 46.50, 2, 1, 'Good harvest'),
            (9, 9, '2026-06-05', 1900.00, 22.00, 3, 2, 'Acceptable quality'),
            (10, 10, '2026-05-04', 2025.00, 25.50, 1, 1, 'High quality harvest'),
            (11, 11, '2026-06-06', 2150.00, 29.00, 2, 1, 'Good harvest'),
            (12, 12, '2026-05-25', 2275.00, 32.50, 3, 2, 'Acceptable quality'),
            (13, 13, '2026-05-18', 2400.00, 36.00, 1, 1, 'High quality harvest'),
            (14, 14, '2026-05-31', 2525.00, 39.50, 2, 1, 'Good harvest'),
            (15, 15, '2026-12-20', 2650.00, 43.00, 3, 2, 'Acceptable quality'),
            (16, 16, '2026-07-26', 2775.00, 46.50, 1, 1, 'High quality harvest'),
            (17, 17, '2026-06-19', 2900.00, 22.00, 2, 1, 'Good harvest'),
            (18, 18, '2026-10-30', 3025.00, 25.50, 3, 2, 'Acceptable quality'),
            (19, 19, '2026-07-05', 3150.00, 29.00, 1, 1, 'High quality harvest'),
            (20, 20, '2026-06-03', 3275.00, 32.50, 2, 1, 'Good harvest');
        ");
    }
}
