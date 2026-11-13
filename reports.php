<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
require_login();

$reportType = (string)($_GET['type'] ?? 'harvest');
$startDate = trim((string)($_GET['start_date'] ?? ''));
$endDate = trim((string)($_GET['end_date'] ?? ''));
$filterGrade = (int)($_GET['grade'] ?? 0);
$filterStatus = (int)($_GET['status'] ?? 0);

// Export to CSV handler
if (isset($_GET['export'])) {
    $exportType = (string)$_GET['export'];
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="agritrack_' . $exportType . '_report_' . date('Ymd_His') . '.csv"');
    $output = fopen('php://output', 'w');

    if ($exportType === 'harvest') {
        fputcsv($output, ['Harvest ID', 'Harvest Date', 'Crop', 'Variety', 'Farm Name', 'Farmer', 'Quantity (kg)', 'Selling Price/kg (PHP)', 'Estimated Value (PHP)', 'Quality Grade', 'Remark', 'Notes']);
        $sql = "
            SELECT h.harvest_id, h.harvest_date, c.crop_name, c.variety, f.farm_name,
                   fm.first_name || ' ' || fm.last_name as farmer_name,
                   h.quantity_kg, h.selling_price_per_kg,
                   (h.quantity_kg * h.selling_price_per_kg) as est_value,
                   qg.grade_name, hr.remark_description, h.remarks_note
            FROM harvest h
            JOIN planting p ON p.planting_id = h.planting_id
            JOIN farm f ON f.farm_id = p.farm_id
            JOIN farmer fm ON fm.farmer_id = f.farmer_id
            JOIN crop c ON c.crop_id = p.crop_id
            JOIN harvest_quality_grades qg ON qg.quality_grade_id = h.quality_grade_id
            JOIN harvest_remarks hr ON hr.remark_id = h.remark_id
            ORDER BY h.harvest_id ASC
        ";
        $rows = Database::rawQuery($sql);
        foreach ($rows as $r) {
            fputcsv($output, [
                $r['harvest_id'], $r['harvest_date'], $r['crop_name'], $r['variety'],
                $r['farm_name'], $r['farmer_name'], $r['quantity_kg'], $r['selling_price_per_kg'],
                $r['est_value'], $r['grade_name'], $r['remark_description'], $r['remarks_note']
            ]);
        }
    } elseif ($exportType === 'farmers_farms') {
        fputcsv($output, ['Farmer ID', 'Farmer Name', 'Contact Number', 'Address', 'Farm ID', 'Farm Name', 'Location', 'Area (ha)', 'Soil Type', 'Irrigation Type']);
        $sql = "
            SELECT fm.farmer_id, fm.first_name || ' ' || fm.last_name as farmer_name, fm.contact_number, fm.address,
                   f.farm_id, f.farm_name, f.location, f.area_hectares, st.soil_type_name, it.irrigation_type_name
            FROM farmer fm
            LEFT JOIN farm f ON f.farmer_id = fm.farmer_id
            LEFT JOIN soil_types st ON st.soil_type_id = f.soil_type_id
            LEFT JOIN irrigation_types it ON it.irrigation_type_id = f.irrigation_type_id
            ORDER BY fm.farmer_id ASC, f.farm_id ASC
        ";
        $rows = Database::rawQuery($sql);
        foreach ($rows as $r) {
            fputcsv($output, [
                $r['farmer_id'], $r['farmer_name'], $r['contact_number'], $r['address'],
                $r['farm_id'] ?? '—', $r['farm_name'] ?? '—', $r['location'] ?? '—',
                $r['area_hectares'] ?? '0.00', $r['soil_type_name'] ?? '—', $r['irrigation_type_name'] ?? '—'
            ]);
        }
    } elseif ($exportType === 'plantings_status') {
        fputcsv($output, ['Planting ID', 'Status', 'Farm Name', 'Location', 'Farmer', 'Crop', 'Variety', 'Planting Date', 'Area Planted (ha)', 'Seed Qty', 'Expected Harvest Date']);
        $sql = "
            SELECT p.planting_id, ps.status_name, f.farm_name, f.location,
                   fm.first_name || ' ' || fm.last_name as farmer_name,
                   c.crop_name, c.variety, p.planting_date, p.area_planted, p.seed_quantity, p.expected_harvest_date
            FROM planting p
            JOIN farm f ON f.farm_id = p.farm_id
            JOIN farmer fm ON fm.farmer_id = f.farmer_id
            JOIN crop c ON c.crop_id = p.crop_id
            JOIN planting_statuses ps ON ps.status_id = p.status_id
            ORDER BY ps.status_name ASC, p.planting_id ASC
        ";
        $rows = Database::rawQuery($sql);
        foreach ($rows as $r) {
            fputcsv($output, [
                $r['planting_id'], $r['status_name'], $r['farm_name'], $r['location'],
                $r['farmer_name'], $r['crop_name'], $r['variety'], $r['planting_date'],
                $r['area_planted'], $r['seed_quantity'], $r['expected_harvest_date']
            ]);
        }
    }

    fclose($output);
    exit;
}

$pageTitle = 'Agricultural Reports & Valuation';
require_once __DIR__ . '/includes/header.php';

// Fetch lookups for report filtering
$allGrades = Database::query('harvest_quality_grades', 'quality_grade_id, grade_name', [], 'quality_grade_id.asc');
$allStatuses = Database::query('planting_statuses', 'status_id, status_name', [], 'status_id.asc');
?>

<!-- Header -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0 text-dark">Agricultural Intelligence Reports</h4>
        <small class="text-muted">Dynamic production audits, yield valuations, landholdings, and operational summaries.</small>
    </div>
    <div class="d-flex gap-2 no-print">
        <button class="btn btn-outline-secondary shadow-sm" onclick="window.print()">
            <i class="bi bi-printer-fill me-1"></i> Print Report
        </button>
        <a href="reports.php?export=<?= urlencode($reportType) ?>" class="btn btn-success shadow-sm">
            <i class="bi bi-file-earmark-spreadsheet-fill me-1"></i> Export to CSV
        </a>
    </div>
</div>

<!-- Report Navigation Tabs -->
<ul class="nav nav-pills mb-3 no-print">
    <li class="nav-item">
        <a class="nav-link <?= $reportType === 'harvest' ? 'active' : '' ?>" href="reports.php?type=harvest">
            <i class="bi bi-basket3-fill me-1"></i> Harvest & Financial Valuation
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $reportType === 'farmers_farms' ? 'active' : '' ?>" href="reports.php?type=farmers_farms">
            <i class="bi bi-person-lines-fill me-1"></i> Farmers & Farms Overview
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $reportType === 'crops_per_farm' ? 'active' : '' ?>" href="reports.php?type=crops_per_farm">
            <i class="bi bi-tree-fill me-1"></i> Crops Planted per Farm
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $reportType === 'plantings_status' ? 'active' : '' ?>" href="reports.php?type=plantings_status">
            <i class="bi bi-calendar2-check-fill me-1"></i> Plantings by Status
        </a>
    </li>
</ul>

<?php if ($reportType === 'harvest'): ?>
    <!-- 1. Harvest & Financial Valuation Report -->
    <?php
    $hWhere = "1=1";
    $hParams = [];
    if ($startDate !== '') {
        $hWhere .= " AND h.harvest_date >= :sdate";
        $hParams[':sdate'] = $startDate;
    }
    if ($endDate !== '') {
        $hWhere .= " AND h.harvest_date <= :edate";
        $hParams[':edate'] = $endDate;
    }
    if ($filterGrade > 0) {
        $hWhere .= " AND h.quality_grade_id = :grade";
        $hParams[':grade'] = $filterGrade;
    }

    $harvestReportSql = "
        SELECT h.*, (h.quantity_kg * h.selling_price_per_kg) as total_val,
               f.farm_name, fm.first_name, fm.last_name,
               c.crop_name, c.variety,
               qg.grade_name, hr.remark_description
        FROM harvest h
        JOIN planting p ON p.planting_id = h.planting_id
        JOIN farm f ON f.farm_id = p.farm_id
        JOIN farmer fm ON fm.farmer_id = f.farmer_id
        JOIN crop c ON c.crop_id = p.crop_id
        JOIN harvest_quality_grades qg ON qg.quality_grade_id = h.quality_grade_id
        JOIN harvest_remarks hr ON hr.remark_id = h.remark_id
        WHERE {$hWhere}
        ORDER BY h.harvest_id ASC
    ";
    $harvestData = Database::rawQuery($harvestReportSql, $hParams);

    $sumKg = 0.0;
    $sumValue = 0.0;
    foreach ($harvestData as $hd) {
        $sumKg += (float)$hd['quantity_kg'];
        $sumValue += (float)$hd['total_val'];
    }
    $avgPrice = $sumKg > 0 ? ($sumValue / $sumKg) : 0;
    ?>

    <!-- Filter Form -->
    <div class="card-panel mb-3 p-3 no-print">
        <form method="get" class="row g-2 align-items-center">
            <input type="hidden" name="type" value="harvest">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">From Harvest Date</label>
                <input type="date" name="start_date" class="form-control" value="<?= h($startDate) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">To Harvest Date</label>
                <input type="date" name="end_date" class="form-control" value="<?= h($endDate) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Quality Grade</label>
                <select name="grade" class="form-select">
                    <option value="0">All Grades</option>
                    <?php foreach ($allGrades as $g): ?>
                        <option value="<?= $g['quality_grade_id'] ?>" <?= $filterGrade === (int)$g['quality_grade_id'] ? 'selected' : '' ?>>
                            <?= h($g['grade_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Apply Filter</button>
                <a href="reports.php?type=harvest" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>

    <!-- Executive Metrics -->
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-label">Total Harvests</div>
                <div class="stat-number fs-3"><?= count($harvestData) ?></div>
                <div class="text-muted small">Recorded transactions</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-label">Total Harvested Quantity</div>
                <div class="stat-number fs-3 text-primary"><?= format_number($sumKg) ?> kg</div>
                <div class="text-muted small">Net agricultural yield</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-label">Estimated Harvest Value</div>
                <div class="stat-number fs-3 text-success"><?= format_currency($sumValue) ?></div>
                <div class="text-muted small">Gross projected revenue</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-label">Avg Selling Price</div>
                <div class="stat-number fs-3 text-secondary"><?= format_currency($avgPrice) ?>/kg</div>
                <div class="text-muted small">Weighted market average</div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="card-panel">
        <div class="card-panel-header">
            <h2 class="card-panel-title">Harvest Transactions & Valuation Statement</h2>
            <span class="badge bg-light text-secondary border"><?= count($harvestData) ?> entries</span>
        </div>
        <div class="table-responsive">
            <table class="table table-custom align-middle">
                <thead>
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th>Harvest Date</th>
                        <th>Crop & Variety</th>
                        <th>Farm & Landowner</th>
                        <th>Yield (kg)</th>
                        <th>Price/kg</th>
                        <th>Estimated Value</th>
                        <th>Quality Grade</th>
                        <th>Remark</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($harvestData)): ?>
                        <tr><td colspan="9" class="text-center py-4 text-muted">No harvest records found matching filter criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($harvestData as $h): ?>
                            <tr>
                                <td class="fw-bold">#<?= (int)$h['harvest_id'] ?></td>
                                <td><?= format_date($h['harvest_date']) ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= h($h['crop_name']) ?></div>
                                    <small class="text-muted"><?= h($h['variety'] ?: 'General') ?></small>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= h($h['farm_name']) ?></div>
                                    <small class="text-muted"><?= h($h['first_name'] . ' ' . $h['last_name']) ?></small>
                                </td>
                                <td class="fw-bold"><?= format_number($h['quantity_kg']) ?> kg</td>
                                <td><?= format_currency($h['selling_price_per_kg']) ?></td>
                                <td class="fw-bold text-success"><?= format_currency($h['total_val']) ?></td>
                                <td>
                                    <span class="badge <?= ($h['grade_name'] ?? '') === 'Grade A' ? 'bg-success' : (($h['grade_name'] ?? '') === 'Grade B' ? 'bg-primary' : 'bg-warning text-dark') ?>">
                                        <?= h($h['grade_name']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= h($h['remark_description']) ?></span>
                                    <?php if (!empty($h['remarks_note'])): ?>
                                        <div class="text-muted" style="font-size: 0.72rem;"><?= h($h['remarks_note']) ?></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot class="table-light fw-bold border-top">
                    <tr>
                        <td colspan="4" class="text-end">Summary Totals:</td>
                        <td class="text-primary"><?= format_number($sumKg) ?> kg</td>
                        <td>—</td>
                        <td class="text-success fs-6"><?= format_currency($sumValue) ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

<?php elseif ($reportType === 'farmers_farms'): ?>
    <!-- 2. Farmers and their Farms Report -->
    <?php
    $farmersFarmsData = Database::rawQuery("
        SELECT fm.farmer_id, fm.first_name, fm.last_name, fm.contact_number, fm.address, fm.date_registered,
               f.farm_id, f.farm_name, f.location, f.area_hectares,
               st.soil_type_name, it.irrigation_type_name
        FROM farmer fm
        LEFT JOIN farm f ON f.farmer_id = fm.farmer_id
        LEFT JOIN soil_types st ON st.soil_type_id = f.soil_type_id
        LEFT JOIN irrigation_types it ON it.irrigation_type_id = f.irrigation_type_id
        ORDER BY fm.farmer_id ASC, f.farm_id ASC
    ");

    $groupedFarmers = [];
    foreach ($farmersFarmsData as $row) {
        $fid = (int)$row['farmer_id'];
        if (!isset($groupedFarmers[$fid])) {
            $groupedFarmers[$fid] = [
                'farmer_id' => $fid,
                'name' => $row['first_name'] . ' ' . $row['last_name'],
                'contact' => $row['contact_number'],
                'address' => $row['address'],
                'registered' => $row['date_registered'],
                'farms' => []
            ];
        }
        if (!empty($row['farm_id'])) {
            $groupedFarmers[$fid]['farms'][] = $row;
        }
    }
    ?>

    <div class="card-panel">
        <div class="card-panel-header">
            <h2 class="card-panel-title">Farmers and Landholdings Directory</h2>
            <span class="badge bg-light text-secondary border"><?= count($groupedFarmers) ?> registered farmers</span>
        </div>
        <div class="table-responsive">
            <table class="table table-custom align-middle">
                <thead>
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th>Farmer Name</th>
                        <th>Contact & Address</th>
                        <th>Registered Farms Owned</th>
                        <th>Total Land Area (ha)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groupedFarmers as $f): ?>
                        <?php
                        $farmTotalArea = 0.0;
                        foreach ($f['farms'] as $fitem) {
                            $farmTotalArea += (float)$fitem['area_hectares'];
                        }
                        ?>
                        <tr>
                            <td class="fw-bold">#<?= $f['farmer_id'] ?></td>
                            <td>
                                <div class="fw-bold text-dark fs-6"><?= h($f['name']) ?></div>
                                <small class="text-muted">Registered: <?= format_date($f['registered']) ?></small>
                            </td>
                            <td>
                                <div><i class="bi bi-telephone text-muted me-1"></i><?= h($f['contact'] ?: '—') ?></div>
                                <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= h($f['address']) ?></small>
                            </td>
                            <td>
                                <?php if (empty($f['farms'])): ?>
                                    <span class="text-muted small">No registered farms</span>
                                <?php else: ?>
                                    <ul class="list-unstyled mb-0 small">
                                        <?php foreach ($f['farms'] as $fitem): ?>
                                            <li class="mb-1">
                                                <i class="bi bi-caret-right-fill text-success"></i>
                                                <strong><?= h($fitem['farm_name']) ?></strong> (<?= h($fitem['location']) ?>)
                                                — <?= format_number($fitem['area_hectares']) ?> ha
                                                <span class="badge bg-light text-dark border"><?= h($fitem['soil_type_name']) ?></span>
                                                <span class="badge bg-light text-dark border"><?= h($fitem['irrigation_type_name']) ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </td>
                            <td class="fw-bold text-primary">
                                <?= format_number($farmTotalArea) ?> ha
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($reportType === 'crops_per_farm'): ?>
    <!-- 3. Crops Planted per Farm Report -->
    <?php
    $cropsPerFarmData = Database::rawQuery("
        SELECT f.farm_id, f.farm_name, f.location, fm.first_name, fm.last_name,
               c.crop_id, c.crop_name, c.variety, cc.category_name,
               COUNT(p.planting_id) as cycle_count,
               SUM(p.area_planted) as total_area_planted,
               SUM(p.seed_quantity) as total_seeds
        FROM farm f
        JOIN farmer fm ON fm.farmer_id = f.farmer_id
        JOIN planting p ON p.farm_id = f.farm_id
        JOIN crop c ON c.crop_id = p.crop_id
        JOIN crop_categories cc ON cc.category_id = c.category_id
        GROUP BY f.farm_id, c.crop_id
        ORDER BY f.farm_name ASC, c.crop_name ASC
    ");
    ?>

    <div class="card-panel">
        <div class="card-panel-header">
            <h2 class="card-panel-title">Crops Planted per Farm Parcel</h2>
            <span class="badge bg-light text-secondary border"><?= count($cropsPerFarmData) ?> allocations</span>
        </div>
        <div class="table-responsive">
            <table class="table table-custom align-middle">
                <thead>
                    <tr>
                        <th>Farm Name</th>
                        <th>Location</th>
                        <th>Owner</th>
                        <th>Crop Cultivated</th>
                        <th>Variety</th>
                        <th>Category</th>
                        <th class="text-center">Planting Cycles</th>
                        <th>Total Area Planted (ha)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cropsPerFarmData)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No crop planting allocations found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($cropsPerFarmData as $cp): ?>
                            <tr>
                                <td class="fw-bold text-dark"><?= h($cp['farm_name']) ?></td>
                                <td><?= h($cp['location']) ?></td>
                                <td><?= h($cp['first_name'] . ' ' . $cp['last_name']) ?></td>
                                <td>
                                    <span class="badge bg-success-subtle text-success fs-6"><?= h($cp['crop_name']) ?></span>
                                </td>
                                <td><?= h($cp['variety'] ?: 'General') ?></td>
                                <td><?= h($cp['category_name']) ?></td>
                                <td class="text-center fw-bold"><?= (int)$cp['cycle_count'] ?></td>
                                <td class="fw-bold text-primary"><?= format_number($cp['total_area_planted']) ?> ha</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($reportType === 'plantings_status'): ?>
    <!-- 4. Planting Records by Status Report -->
    <?php
    $pWhere = "1=1";
    $pParams = [];
    if ($filterStatus > 0) {
        $pWhere .= " AND p.status_id = :status";
        $pParams[':status'] = $filterStatus;
    }

    $plantingsStatusData = Database::rawQuery("
        SELECT p.*, ps.status_name,
               f.farm_name, f.location,
               fm.first_name, fm.last_name,
               c.crop_name, c.variety
        FROM planting p
        JOIN farm f ON f.farm_id = p.farm_id
        JOIN farmer fm ON fm.farmer_id = f.farmer_id
        JOIN crop c ON c.crop_id = p.crop_id
        JOIN planting_statuses ps ON ps.status_id = p.status_id
        WHERE {$pWhere}
        ORDER BY p.status_id ASC, p.planting_date DESC
    ", $pParams);

    // Grouping by status
    $byStatusSummary = [];
    foreach ($plantingsStatusData as $p) {
        $sname = $p['status_name'];
        if (!isset($byStatusSummary[$sname])) {
            $byStatusSummary[$sname] = [
                'count' => 0,
                'total_area' => 0.0
            ];
        }
        $byStatusSummary[$sname]['count']++;
        $byStatusSummary[$sname]['total_area'] += (float)$p['area_planted'];
    }
    ?>

    <!-- Status Filter -->
    <div class="card-panel mb-3 p-3 no-print">
        <form method="get" class="row g-2 align-items-center">
            <input type="hidden" name="type" value="plantings_status">
            <div class="col-md-4">
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="0">All Statuses</option>
                    <?php foreach ($allStatuses as $st): ?>
                        <option value="<?= $st['status_id'] ?>" <?= $filterStatus === (int)$st['status_id'] ? 'selected' : '' ?>>
                            <?= h($st['status_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Filter</button>
                <?php if ($filterStatus > 0): ?>
                    <a href="reports.php?type=plantings_status" class="btn btn-outline-secondary">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Status Summary Cards -->
    <div class="row g-3 mb-3">
        <?php foreach ($byStatusSummary as $sname => $info): ?>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-label">Status: <?= h($sname) ?></div>
                    <div class="stat-number fs-3"><?= $info['count'] ?> cycles</div>
                    <div class="text-muted small">Total area: <strong><?= format_number($info['total_area']) ?> ha</strong></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Table -->
    <div class="card-panel">
        <div class="card-panel-header">
            <h2 class="card-panel-title">Planting Operations Classified by Status</h2>
            <span class="badge bg-light text-secondary border"><?= count($plantingsStatusData) ?> records</span>
        </div>
        <div class="table-responsive">
            <table class="table table-custom align-middle">
                <thead>
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th>Status</th>
                        <th>Farm Parcel</th>
                        <th>Farmer</th>
                        <th>Crop Cultivated</th>
                        <th>Planting Date</th>
                        <th>Area Planted (ha)</th>
                        <th>Expected Harvest Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($plantingsStatusData)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No planting records found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($plantingsStatusData as $p): ?>
                            <tr>
                                <td class="fw-bold">#<?= (int)$p['planting_id'] ?></td>
                                <td>
                                    <span class="badge <?= ($p['status_name'] ?? '') === 'Growing' ? 'bg-primary' : (($p['status_name'] ?? '') === 'Harvested' ? 'bg-success' : 'bg-secondary') ?>">
                                        <?= h($p['status_name']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= h($p['farm_name']) ?></div>
                                    <small class="text-muted"><?= h($p['location']) ?></small>
                                </td>
                                <td><?= h($p['first_name'] . ' ' . $p['last_name']) ?></td>
                                <td>
                                    <strong><?= h($p['crop_name']) ?></strong>
                                    <small class="text-muted d-block"><?= h($p['variety'] ?: 'General') ?></small>
                                </td>
                                <td><?= format_date($p['planting_date']) ?></td>
                                <td class="fw-bold text-primary"><?= format_number($p['area_planted']) ?> ha</td>
                                <td><?= format_date($p['expected_harvest_date']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
