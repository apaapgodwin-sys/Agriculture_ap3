<?php
declare(strict_types=1);
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

// Fetch dynamic counts & statistics
$farmers = Database::query('farmer', 'farmer_id');
$farms = Database::query('farm', 'farm_id');
$crops = Database::query('crop', 'crop_id');
$plantings = Database::query('planting', 'planting_id, status_id');
$harvests = Database::query('harvest', 'harvest_id, quantity_kg, selling_price_per_kg');

$totalFarmers = count($farmers);
$totalFarms = count($farms);
$totalCrops = count($crops);
$totalPlantings = count($plantings);
$totalHarvests = count($harvests);

$totalHarvestedKg = 0.0;
$totalEstimatedValue = 0.0;
foreach ($harvests as $h) {
    $qty = (float)($h['quantity_kg'] ?? 0);
    $price = (float)($h['selling_price_per_kg'] ?? 0);
    $totalHarvestedKg += $qty;
    $totalEstimatedValue += ($qty * $price);
}

// Plantings by Status breakdown for chart
$statusList = Database::query('planting_statuses', 'status_id, status_name');
$statusMap = [];
foreach ($statusList as $st) {
    $statusMap[$st['status_id']] = $st['status_name'];
}

$statusCounts = [];
foreach ($plantings as $p) {
    $sid = (int)($p['status_id'] ?? 0);
    $sName = $statusMap[$sid] ?? 'Unknown';
    $statusCounts[$sName] = ($statusCounts[$sName] ?? 0) + 1;
}

// Recent Plantings with joined Farm and Crop
$recentPlantings = Database::rawQuery("
    SELECT p.planting_id, p.planting_date, p.area_planted, ps.status_name,
           f.farm_name, c.crop_name
    FROM planting p
    LEFT JOIN farm f ON f.farm_id = p.farm_id
    LEFT JOIN crop c ON c.crop_id = p.crop_id
    LEFT JOIN planting_statuses ps ON ps.status_id = p.status_id
    ORDER BY p.planting_date DESC
    LIMIT 6
");

// Recent Harvests with joined Planting, Farm, Crop, Remarks
$recentHarvests = Database::rawQuery("
    SELECT h.harvest_id, h.harvest_date, h.quantity_kg, h.selling_price_per_kg,
           (h.quantity_kg * h.selling_price_per_kg) as total_val,
           f.farm_name, c.crop_name, qg.grade_name, hr.remark_description
    FROM harvest h
    LEFT JOIN planting p ON p.planting_id = h.planting_id
    LEFT JOIN farm f ON f.farm_id = p.farm_id
    LEFT JOIN crop c ON c.crop_id = p.crop_id
    LEFT JOIN harvest_quality_grades qg ON qg.quality_grade_id = h.quality_grade_id
    LEFT JOIN harvest_remarks hr ON hr.remark_id = h.remark_id
    ORDER BY h.harvest_date DESC
    LIMIT 6
");

// Harvest production by Crop for Bar Chart
$cropHarvestData = Database::rawQuery("
    SELECT c.crop_name, SUM(h.quantity_kg) as total_kg
    FROM harvest h
    JOIN planting p ON p.planting_id = h.planting_id
    JOIN crop c ON c.crop_id = p.crop_id
    GROUP BY c.crop_name
    ORDER BY total_kg DESC
    LIMIT 6
");

$cropChartLabels = [];
$cropChartValues = [];
foreach ($cropHarvestData as $ch) {
    $cropChartLabels[] = $ch['crop_name'];
    $cropChartValues[] = (float)$ch['total_kg'];
}
?>

<!-- Metric Summary Cards Row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <a href="farmers.php" class="text-decoration-none">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <span class="stat-label">Farmers</span>
                    <div class="stat-icon stat-icon-green"><i class="bi bi-people-fill"></i></div>
                </div>
                <div class="stat-number"><?= format_number($totalFarmers, 0) ?></div>
                <div class="text-muted small mt-1"><i class="bi bi-arrow-right-short"></i> Manage farmers</div>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <a href="farms.php" class="text-decoration-none">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <span class="stat-label">Farms</span>
                    <div class="stat-icon stat-icon-blue"><i class="bi bi-geo-alt-fill"></i></div>
                </div>
                <div class="stat-number"><?= format_number($totalFarms, 0) ?></div>
                <div class="text-muted small mt-1"><i class="bi bi-arrow-right-short"></i> View parcels</div>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <a href="crops.php" class="text-decoration-none">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <span class="stat-label">Crops Master</span>
                    <div class="stat-icon stat-icon-amber"><i class="bi bi-tree-fill"></i></div>
                </div>
                <div class="stat-number"><?= format_number($totalCrops, 0) ?></div>
                <div class="text-muted small mt-1"><i class="bi bi-arrow-right-short"></i> Crop varieties</div>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <a href="plantings.php" class="text-decoration-none">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <span class="stat-label">Plantings</span>
                    <div class="stat-icon stat-icon-teal"><i class="bi bi-calendar2-range-fill"></i></div>
                </div>
                <div class="stat-number"><?= format_number($totalPlantings, 0) ?></div>
                <div class="text-muted small mt-1"><i class="bi bi-arrow-right-short"></i> Planting cycles</div>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <a href="harvests.php" class="text-decoration-none">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <span class="stat-label">Harvests</span>
                    <div class="stat-icon stat-icon-purple"><i class="bi bi-basket3-fill"></i></div>
                </div>
                <div class="stat-number"><?= format_number($totalHarvests, 0) ?></div>
                <div class="text-muted small mt-1"><i class="bi bi-arrow-right-short"></i> Yield records</div>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <a href="reports.php" class="text-decoration-none">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <span class="stat-label">Harvest Value</span>
                    <div class="stat-icon stat-icon-rose"><i class="bi bi-cash-stack"></i></div>
                </div>
                <div class="stat-number fs-4 text-truncate"><?= format_currency($totalEstimatedValue) ?></div>
                <div class="text-muted small mt-1" style="font-size: 0.72rem;"><?= format_number($totalHarvestedKg) ?> kg total</div>
            </div>
        </a>
    </div>
</div>

<!-- Quick Actions Banner -->
<div class="card-panel mb-4 p-3 bg-white border-start border-success border-4 shadow-sm">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <span class="fw-bold text-dark"><i class="bi bi-lightning-charge-fill text-warning me-1"></i> Quick Operations:</span>
            <span class="text-muted small ms-2">Easily create new transactions or view agricultural records.</span>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="farmers.php?action=new" class="btn btn-sm btn-outline-success"><i class="bi bi-plus-circle me-1"></i> New Farmer</a>
            <a href="farms.php?action=new" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-circle me-1"></i> New Farm</a>
            <a href="plantings.php?action=new" class="btn btn-sm btn-outline-secondary"><i class="bi bi-plus-circle me-1"></i> Log Planting</a>
            <a href="harvests.php?action=new" class="btn btn-sm btn-agri"><i class="bi bi-plus-circle me-1"></i> Record Harvest</a>
            <a href="reports.php" class="btn btn-sm btn-dark"><i class="bi bi-file-earmark-pdf me-1"></i> Reports</a>
        </div>
    </div>
</div>

<!-- Interactive Charts Row -->
<div class="row g-3 mb-4">
    <!-- Chart 1: Plantings by Status -->
    <div class="col-lg-5">
        <div class="card-panel h-100 mb-0">
            <div class="card-panel-header">
                <h2 class="card-panel-title"><i class="bi bi-pie-chart-fill text-success me-2"></i>Plantings by Status</h2>
                <span class="badge bg-light text-secondary border"><?= $totalPlantings ?> total records</span>
            </div>
            <div class="card-panel-body d-flex flex-column justify-content-center align-items-center">
                <div style="width: 100%; max-width: 280px; height: 260px;">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart 2: Top Harvest Yields by Crop -->
    <div class="col-lg-7">
        <div class="card-panel h-100 mb-0">
            <div class="card-panel-header">
                <h2 class="card-panel-title"><i class="bi bi-bar-chart-line-fill text-primary me-2"></i>Harvest Production by Crop (kg)</h2>
                <span class="badge bg-light text-secondary border">Top Yields</span>
            </div>
            <div class="card-panel-body">
                <div style="width: 100%; height: 260px;">
                    <canvas id="cropYieldChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Records Tables Row -->
<div class="row g-3 mb-4">
    <!-- Recent Plantings -->
    <div class="col-lg-6">
        <div class="card-panel mb-0">
            <div class="card-panel-header">
                <h2 class="card-panel-title"><i class="bi bi-clock-history text-secondary me-2"></i>Recent Planting Activities</h2>
                <a href="plantings.php" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-custom">
                    <thead>
                        <tr>
                            <th>Farm</th>
                            <th>Crop</th>
                            <th>Date Planted</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentPlantings)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">No recent planting records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentPlantings as $rp): ?>
                                <tr>
                                    <td class="fw-semibold"><?= h($rp['farm_name']) ?></td>
                                    <td><span class="badge bg-success-subtle text-success"><?= h($rp['crop_name']) ?></span></td>
                                    <td><?= format_date($rp['planting_date']) ?></td>
                                    <td>
                                        <span class="badge <?= ($rp['status_name'] ?? '') === 'Growing' ? 'bg-primary' : (($rp['status_name'] ?? '') === 'Harvested' ? 'bg-success' : 'bg-secondary') ?>">
                                            <?= h($rp['status_name'] ?? 'Unknown') ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Harvests -->
    <div class="col-lg-6">
        <div class="card-panel mb-0">
            <div class="card-panel-header">
                <h2 class="card-panel-title"><i class="bi bi-basket-fill text-warning me-2"></i>Recent Harvest Transactions</h2>
                <a href="harvests.php" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-custom">
                    <thead>
                        <tr>
                            <th>Crop & Farm</th>
                            <th>Harvest Date</th>
                            <th>Yield (kg)</th>
                            <th>Est. Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentHarvests)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">No recent harvest records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentHarvests as $rh): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= h($rh['crop_name']) ?></div>
                                        <small class="text-muted"><?= h($rh['farm_name']) ?></small>
                                    </td>
                                    <td><?= format_date($rh['harvest_date']) ?></td>
                                    <td class="fw-bold"><?= format_number($rh['quantity_kg']) ?> kg</td>
                                    <td class="text-success fw-bold"><?= format_currency($rh['total_val']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Chart Initialization Scripts -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. Status Donut Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    const statusLabels = <?= json_encode(array_keys($statusCounts)) ?>;
    const statusData = <?= json_encode(array_values($statusCounts)) ?>;

    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusData,
                backgroundColor: ['#16a34a', '#0284c7', '#f59e0b', '#64748b'],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    // 2. Crop Production Bar Chart
    const cropCtx = document.getElementById('cropYieldChart').getContext('2d');
    const cropLabels = <?= json_encode($cropChartLabels) ?>;
    const cropValues = <?= json_encode($cropChartValues) ?>;

    new Chart(cropCtx, {
        type: 'bar',
        data: {
            labels: cropLabels,
            datasets: [{
                label: 'Harvest Yield (kg)',
                data: cropValues,
                backgroundColor: 'rgba(21, 128, 61, 0.85)',
                borderColor: '#15803d',
                borderRadius: 6,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) { return value.toLocaleString() + ' kg'; }
                    }
                }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
