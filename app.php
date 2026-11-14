<?php
declare(strict_types=1);
$pageTitle = 'Field User App';
require_once __DIR__ . '/config/config.php';
require_login();

$currentUser = current_user();

// Handle Quick Actions submitted from the Field App
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    // ── Quick Log Planting ──────────────────────────────────────────
    if ($action === 'quick_planting') {
        $farmId      = (int)($_POST['farm_id'] ?? 0);
        $cropId      = (int)($_POST['crop_id'] ?? 0);
        $plantingDate= trim((string)($_POST['planting_date'] ?? date('Y-m-d')));
        $areaPlanted = (float)($_POST['area_planted'] ?? 0);
        $seedQty     = (float)($_POST['seed_quantity'] ?? 0);

        if ($farmId <= 0 || $cropId <= 0 || $plantingDate === '' || $areaPlanted <= 0) {
            flash('error', 'Please complete all required fields for the planting record.');
            redirect('app.php?tab=field_log');
        }

        // Fetch crop growing days to calculate expected harvest date
        $cropData = Database::query('crop', 'growing_period_days', ['crop_id' => $cropId]);
        $days = (int)($cropData[0]['growing_period_days'] ?? 90);
        $expectedDate = date('Y-m-d', strtotime("{$plantingDate} + {$days} days"));

        $res = Database::insert('planting', [
            'farm_id'               => $farmId,
            'crop_id'               => $cropId,
            'planting_date'         => $plantingDate,
            'area_planted'          => $areaPlanted,
            'seed_quantity'         => $seedQty > 0 ? $seedQty : null,
            'expected_harvest_date' => $expectedDate,
            'status_id'             => 1 // Growing
        ]);
        $newId = (int)($res['id'] ?? $res['planting_id'] ?? 0);
        log_activity('CREATE', 'PLANTING', "Quick-logged planting cycle #{$newId} ({$areaPlanted} ha) on farm #{$farmId} via Field User App.", $newId);

        flash('success', "New planting logged successfully! Expected harvest on {$expectedDate}.");
        redirect('app.php?tab=schedule');
    }

    // ── Quick Log Harvest ───────────────────────────────────────────
    if ($action === 'quick_harvest') {
        $plantingId = (int)($_POST['planting_id'] ?? 0);
        $harvestDate= trim((string)($_POST['harvest_date'] ?? date('Y-m-d')));
        $quantityKg = (float)($_POST['quantity_kg'] ?? 0);
        $priceKg    = (float)($_POST['selling_price_per_kg'] ?? 0);
        $gradeId    = (int)($_POST['quality_grade_id'] ?? 1);
        $remarkId   = (int)($_POST['remark_id'] ?? 1);
        $notes      = trim((string)($_POST['remarks_note'] ?? 'Logged via Field User App'));

        if ($plantingId <= 0 || $harvestDate === '' || $quantityKg <= 0 || $priceKg < 0) {
            flash('error', 'Please fill in valid harvest details (quantity and price).');
            redirect('app.php?tab=field_log');
        }

        // Insert harvest transaction
        $res = Database::insert('harvest', [
            'planting_id'          => $plantingId,
            'harvest_date'         => $harvestDate,
            'quantity_kg'          => $quantityKg,
            'selling_price_per_kg' => $priceKg,
            'quality_grade_id'     => $gradeId,
            'remark_id'            => $remarkId,
            'remarks_note'         => $notes
        ]);
        $newId = (int)($res['id'] ?? $res['harvest_id'] ?? 0);

        // Automatically update the planting status to Harvested (status_id = 2)
        Database::update('planting', 'planting_id', $plantingId, [
            'status_id' => 2
        ]);

        $valuation = $quantityKg * $priceKg;
        log_activity('CREATE', 'HARVEST', "Quick-logged harvest #{$newId} of {$quantityKg} kg at ₱{$priceKg}/kg (Valuation: ₱" . number_format($valuation, 2) . ") via Field User App.", $newId);
        flash('success', "Harvest recorded! Yield: " . format_number($quantityKg) . " kg valued at " . format_currency($valuation) . ".");
        redirect('app.php?tab=schedule');
    }
}

// Fetch Crops with 3NF categories and recommended seasons
$cropsCatalog = Database::rawQuery("
    SELECT c.*, cc.category_name, rs.season_name
    FROM crop c
    JOIN crop_categories cc ON cc.category_id = c.category_id
    JOIN recommended_seasons rs ON rs.season_id = c.season_id
    ORDER BY c.crop_name ASC
");

// Fetch active/growing plantings with farm, farmer, and crop info
$plantingsList = Database::rawQuery("
    SELECT p.*, f.farm_name, f.location as farm_location,
           fm.first_name, fm.last_name,
           c.crop_name, c.variety, c.growing_period_days,
           ps.status_name
    FROM planting p
    JOIN farm f ON f.farm_id = p.farm_id
    JOIN farmer fm ON fm.farmer_id = f.farmer_id
    JOIN crop c ON c.crop_id = p.crop_id
    JOIN planting_statuses ps ON ps.status_id = p.status_id
    WHERE f.deleted_at IS NULL
    ORDER BY (p.status_id = 1) DESC, p.expected_harvest_date ASC
");

// Filter lookups for quick forms
$allFarms = Database::rawQuery("SELECT farm_id, farm_name, location FROM farm WHERE deleted_at IS NULL ORDER BY farm_name ASC");
$allGrades = Database::query('harvest_quality_grades', '*', [], 'quality_grade_id.asc');
$allRemarks = Database::query('harvest_remarks', '*', [], 'remark_id.asc');

// Active App Tab
$appTab = $_GET['tab'] ?? 'crops';

// User Initials
$nameParts = array_filter(explode(' ', $currentUser['full_name'] ?? 'User'));
$initials = '';
foreach ($nameParts as $part) { $initials .= strtoupper($part[0]); }
$initials = substr($initials, 0, 2) ?: 'U';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Field App Top Banner -->
<div class="card-panel p-4 mb-4 border-0 text-white shadow-sm" style="background: linear-gradient(135deg, #0d3b1e 0%, #15803d 50%, #166534 100%); border-radius: 16px;">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="brand-icon-box shadow" style="width: 52px; height: 52px; font-size: 1.6rem; border: 2px solid rgba(255,255,255,0.3);">
                <i class="bi bi-phone-fill"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2">
                    <h3 class="fw-bold mb-0 text-white">AgriTrack Field User App</h3>
                    <span class="badge bg-success border border-light text-white" style="font-size: 0.72rem;">FIELD UI v2.0</span>
                </div>
                <div class="text-white-50 small mt-1">
                    Cebu Province Agro-Informatics • Connected as <strong><?= h($currentUser['full_name']) ?></strong> (<?= h(strtoupper($currentUser['role'])) ?>)
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-white text-dark py-2 px-3 shadow-sm d-none d-sm-inline-flex align-items-center gap-1">
                <i class="bi bi-geo-alt-fill text-danger"></i> Central Visayas • Wet/Dry Cycle
            </span>
            <a href="dashboard.php" class="btn btn-sm btn-outline-light px-3">
                <i class="bi bi-speedometer2 me-1"></i> Management Console
            </a>
            <a href="profile.php" class="btn btn-sm btn-outline-light">
                <i class="bi bi-person-gear"></i>
            </a>
        </div>
    </div>
</div>

<!-- App Navigation Bar (Pill Tabs) -->
<div class="card-panel p-2 mb-4">
    <ul class="nav nav-pills nav-fill gap-2" id="appNavTabs">
        <li class="nav-item">
            <a class="nav-link <?= $appTab === 'crops' ? 'active' : '' ?> fw-semibold" href="app.php?tab=crops">
                <i class="bi bi-tree-fill me-1"></i> Crop Catalog & Guide
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $appTab === 'calculator' ? 'active' : '' ?> fw-semibold" href="app.php?tab=calculator">
                <i class="bi bi-calculator-fill me-1"></i> Yield & Profit Calculator
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $appTab === 'schedule' ? 'active' : '' ?> fw-semibold" href="app.php?tab=schedule">
                <i class="bi bi-calendar-check-fill me-1"></i> Field Plantings & Tracker
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $appTab === 'field_log' ? 'active' : '' ?> fw-semibold" href="app.php?tab=field_log">
                <i class="bi bi-pencil-square me-1"></i> Quick Field Logger
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $appTab === 'advisory' ? 'active' : '' ?> fw-semibold" href="app.php?tab=advisory">
                <i class="bi bi-cloud-sun-fill me-1"></i> Weather & Agro-Advisory
            </a>
        </li>
    </ul>
</div>

<!-- ====================================================================== -->
<!-- TAB 1: CROP CATALOG & ADVISOR                                          -->
<!-- ====================================================================== -->
<?php if ($appTab === 'crops'): ?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-tree text-success me-2"></i>Master Agricultural Crop Directory</h5>
        <small class="text-muted">Standard normalized crop profiles, harvest duration, and seasonal compatibility.</small>
    </div>
    <div class="col-md-4">
        <div class="input-group input-group-sm">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" id="cropSearchInput" class="form-control" placeholder="Search crop or variety..." oninput="filterCropCards()">
        </div>
    </div>
</div>

<div class="row g-3" id="cropCardsContainer">
    <?php foreach ($cropsCatalog as $crop): ?>
        <?php
            // Assign icon according to category
            $catIcon = match((int)$crop['category_id']) {
                1 => 'bi-flower2 text-warning',      // Cereal
                2 => 'bi-carrot text-danger',        // Vegetable
                3 => 'bi-apple text-success',        // Fruit
                4 => 'bi-circle-half text-primary',  // Legume
                5 => 'bi-globe-central-south text-secondary', // Root Crop
                default => 'bi-tree text-success'
            };
        ?>
        <div class="col-md-6 col-lg-4 col-xl-3 crop-card-item" data-name="<?= h(strtolower($crop['crop_name'] . ' ' . ($crop['variety'] ?? ''))) ?>">
            <div class="card-panel p-3 h-100 position-relative border hover-lift">
                <div class="d-flex align-items-start justify-content-between mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="stat-icon-green rounded p-2" style="font-size: 1.4rem;">
                            <i class="bi <?= $catIcon ?>"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0 text-dark"><?= h($crop['crop_name']) ?></h6>
                            <small class="text-muted"><?= h($crop['variety'] ?: 'Standard Variety') ?></small>
                        </div>
                    </div>
                    <span class="badge bg-secondary-subtle text-secondary border">
                        <?= h($crop['category_name']) ?>
                    </span>
                </div>

                <div class="small mt-2 pt-2 border-top">
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted"><i class="bi bi-clock-history me-1"></i> Growing Period:</span>
                        <strong class="text-success"><?= (int)$crop['growing_period_days'] ?> Days</strong>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted"><i class="bi bi-sun me-1"></i> Best Season:</span>
                        <span class="badge bg-light text-dark border"><?= h($crop['season_name']) ?></span>
                    </div>
                </div>

                <div class="mt-3 pt-2 border-top">
                    <button type="button" class="btn btn-sm btn-outline-success w-100" 
                            onclick="preloadCalculator(<?= (int)$crop['crop_id'] ?>, '<?= h($crop['crop_name']) ?>', <?= (int)$crop['growing_period_days'] ?>)">
                        <i class="bi bi-calculator me-1"></i> Estimate Yield & Profit
                    </button>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
function filterCropCards() {
    const term = document.getElementById('cropSearchInput').value.toLowerCase().trim();
    const items = document.querySelectorAll('.crop-card-item');
    items.forEach(el => {
        const name = el.getAttribute('data-name') || '';
        el.style.display = name.includes(term) ? '' : 'none';
    });
}
</script>

<!-- ====================================================================== -->
<!-- TAB 2: YIELD & PROFIT CALCULATOR                                       -->
<!-- ====================================================================== -->
<?php elseif ($appTab === 'calculator'): ?>
<div class="row g-4">
    <!-- Calculator Input Box -->
    <div class="col-lg-5">
        <div class="card-panel p-4 h-100">
            <h5 class="fw-bold mb-3 text-dark"><i class="bi bi-calculator-fill text-success me-2"></i>Field Harvest & Profit Calculator</h5>
            <p class="text-muted small">Simulate crop yields, harvest dates, and expected gross sales in Philippine Pesos (₱).</p>

            <form id="yieldCalcForm" onsubmit="event.preventDefault(); computeYield();">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Select Crop Variety <span class="text-danger">*</span></label>
                    <select id="calc_crop" class="form-select" onchange="computeYield()" required>
                        <?php foreach ($cropsCatalog as $c): ?>
                            <option value="<?= $c['crop_id'] ?>" 
                                    data-days="<?= (int)$c['growing_period_days'] ?>"
                                    data-name="<?= h($c['crop_name']) ?>">
                                <?= h($c['crop_name']) ?> (<?= h($c['variety'] ?: 'Standard') ?>) — <?= (int)$c['growing_period_days'] ?> days
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Farm Land Area (Hectares) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="number" id="calc_hectares" class="form-control" value="1.00" step="0.1" min="0.05" oninput="computeYield()" required>
                        <span class="input-group-text">Hectares</span>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Target Selling Price per Kg (₱) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="number" id="calc_price" class="form-control" value="30.00" step="0.5" min="1.0" oninput="computeYield()" required>
                        <span class="input-group-text">/ kg</span>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Planned Planting Date</label>
                    <input type="date" id="calc_date" class="form-control" value="<?= date('Y-m-d') ?>" onchange="computeYield()">
                </div>

                <button type="button" class="btn btn-agri w-100" onclick="computeYield()">
                    <i class="bi bi-arrow-repeat me-1"></i> Re-Calculate Valuation
                </button>
            </form>
        </div>
    </div>

    <!-- Live Calculation Results -->
    <div class="col-lg-7">
        <div class="card-panel p-4 h-100">
            <h5 class="fw-bold mb-3 text-dark"><i class="bi bi-graph-up-arrow text-primary me-2"></i>Projection Summary</h5>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="p-3 rounded border" style="background: var(--bg-table-head);">
                        <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.72rem;">Estimated Yield Quantity</small>
                        <div class="display-6 fw-bold text-success my-1" id="res_yield">3,500 kg</div>
                        <small class="text-muted">Based on Visayas regional agro-yield norms</small>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="p-3 rounded border" style="background: var(--bg-table-head);">
                        <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.72rem;">Gross Estimated Revenue</small>
                        <div class="display-6 fw-bold text-primary my-1" id="res_money">₱105,000.00</div>
                        <small class="text-muted">Estimated farm-gate valuation</small>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="p-3 rounded border" style="background: var(--bg-table-head);">
                        <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.72rem;">Growing Duration</small>
                        <h4 class="fw-bold my-1 text-dark" id="res_days">115 Days</h4>
                        <small class="text-muted" id="res_crop_name">Rice</small>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="p-3 rounded border" style="background: var(--bg-table-head);">
                        <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.72rem;">Projected Harvest Date</small>
                        <h4 class="fw-bold my-1 text-warning" id="res_harvest_date">—</h4>
                        <small class="text-muted">Auto-computed target date</small>
                    </div>
                </div>
            </div>

            <div class="alert alert-success d-flex align-items-center gap-2 mb-0">
                <i class="bi bi-check-circle-fill fs-4 flex-shrink-0"></i>
                <div class="small">
                    <strong>Ready to plant?</strong> You can jump directly to the Field Logger tab to register this crop cycle with your preferred farm parcel.
                </div>
                <a href="app.php?tab=field_log" class="btn btn-sm btn-success ms-auto flex-shrink-0 text-nowrap">
                    Go to Logger <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Benchmark yields per hectare (approximate kg)
const YIELD_BENCHMARKS = {
    1: 4200,  // Rice
    2: 3800,  // Corn
    3: 16000, // Tomato
    4: 14000, // Eggplant
    5: 22000, // Banana
    6: 8000,  // Mango
    7: 1800,  // Peanut
    8: 20000, // Cassava
    9: 12000, // Sweet Potato
    10: 25000 // Watermelon
};

function computeYield() {
    const cropSelect = document.getElementById('calc_crop');
    const cropId = parseInt(cropSelect.value, 10);
    const selectedOpt = cropSelect.options[cropSelect.selectedIndex];
    const days = parseInt(selectedOpt.getAttribute('data-days') || '90', 10);
    const cropName = selectedOpt.getAttribute('data-name') || 'Crop';

    const hectares = parseFloat(document.getElementById('calc_hectares').value) || 1.0;
    const price = parseFloat(document.getElementById('calc_price').value) || 0.0;
    const plantDateVal = document.getElementById('calc_date').value || new Date().toISOString().split('T')[0];

    const benchmarkPerHa = YIELD_BENCHMARKS[cropId] || 3500;
    const totalYieldKg = hectares * benchmarkPerHa;
    const totalMoney = totalYieldKg * price;

    // Compute harvest date
    const plantDate = new Date(plantDateVal);
    plantDate.setDate(plantDate.getDate() + days);
    const harvestDateStr = plantDate.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });

    // Update DOM
    document.getElementById('res_yield').textContent = totalYieldKg.toLocaleString('en-US', { maximumFractionDigits: 1 }) + ' kg';
    document.getElementById('res_money').textContent = '₱' + totalMoney.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('res_days').textContent = days + ' Days';
    document.getElementById('res_crop_name').textContent = cropName;
    document.getElementById('res_harvest_date').textContent = harvestDateStr;
}

document.addEventListener('DOMContentLoaded', computeYield);
</script>

<!-- ====================================================================== -->
<!-- TAB 3: FIELD PLANTINGS & HARVEST TRACKER                               -->
<!-- ====================================================================== -->
<?php elseif ($appTab === 'schedule'): ?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-calendar-check text-success me-2"></i>Active Plantings & Maturity Countdown</h5>
        <small class="text-muted">Real-time status of current field operations across registered parcels.</small>
    </div>
    <a href="app.php?tab=field_log" class="btn btn-sm btn-agri">
        <i class="bi bi-plus-circle me-1"></i> Log New Planting
    </a>
</div>

<div class="row g-3">
    <?php if (empty($plantingsList)): ?>
        <div class="col-12">
            <div class="card-panel p-5 text-center text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                No active planting records found in the database.
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($plantingsList as $p): ?>
            <?php
                $isGrowing = ((int)$p['status_id'] === 1);
                $expectedTs = strtotime($p['expected_harvest_date']);
                $todayTs = strtotime(date('Y-m-d'));
                $diffDays = (int)ceil(($expectedTs - $todayTs) / 86400);

                if (!$isGrowing) {
                    $badgeClass = 'bg-secondary';
                    $countdownText = 'Harvested / Completed';
                } elseif ($diffDays < 0) {
                    $badgeClass = 'bg-danger';
                    $countdownText = '🚨 Overdue by ' . abs($diffDays) . ' days';
                } elseif ($diffDays === 0) {
                    $badgeClass = 'bg-warning text-dark';
                    $countdownText = '⚡ Ready for Harvest Today!';
                } else {
                    $badgeClass = 'bg-success';
                    $countdownText = '🌾 Harvest in ' . $diffDays . ' days';
                }
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="card-panel p-3 h-100 border position-relative hover-lift">
                    <div class="d-flex align-items-start justify-content-between mb-2">
                        <div>
                            <span class="badge <?= $badgeClass ?> mb-1"><?= $countdownText ?></span>
                            <h6 class="fw-bold mb-0 text-dark"><?= h($p['crop_name']) ?> <small class="text-muted">(<?= h($p['variety'] ?: 'Standard') ?>)</small></h6>
                        </div>
                        <span class="fw-bold text-muted small">#<?= (int)$p['planting_id'] ?></span>
                    </div>

                    <div class="small mt-2 pt-2 border-top">
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted"><i class="bi bi-geo-alt me-1"></i> Farm Parcel:</span>
                            <strong class="text-truncate" style="max-width: 160px;"><?= h($p['farm_name']) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted"><i class="bi bi-person me-1"></i> Farmer / Owner:</span>
                            <span><?= h($p['first_name'] . ' ' . $p['last_name']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted"><i class="bi bi-calendar-event me-1"></i> Planted Date:</span>
                            <span><?= format_date($p['planting_date']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted"><i class="bi bi-flag me-1"></i> Expected Date:</span>
                            <strong class="text-success"><?= format_date($p['expected_harvest_date']) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted"><i class="bi bi-rulers me-1"></i> Area Planted:</span>
                            <span><?= format_number($p['area_planted']) ?> ha</span>
                        </div>
                    </div>

                    <div class="mt-3 pt-2 border-top d-flex gap-2">
                        <?php if ($isGrowing): ?>
                            <button type="button" class="btn btn-sm btn-agri w-100" 
                                    onclick="preloadHarvestLog(<?= (int)$p['planting_id'] ?>, '<?= h($p['crop_name']) ?>', '<?= h($p['farm_name']) ?>')">
                                <i class="bi bi-basket3-fill me-1"></i> Log Harvest Now
                            </button>
                        <?php else: ?>
                            <span class="btn btn-sm btn-light w-100 text-muted disabled">
                                <i class="bi bi-check2-all me-1"></i> Completed
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ====================================================================== -->
<!-- TAB 4: QUICK FIELD LOGGER                                              -->
<!-- ====================================================================== -->
<?php elseif ($appTab === 'field_log'): ?>
<div class="row g-4">
    <!-- Log Planting Form -->
    <div class="col-lg-6">
        <div class="card-panel p-4 h-100">
            <h5 class="fw-bold mb-3 text-dark"><i class="bi bi-tree-fill text-success me-2"></i>Quick Log: Planting Cycle</h5>
            <p class="text-muted small">Record a new planting event straight from the field with auto-calculated harvest date.</p>

            <form method="post" action="app.php">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="quick_planting">

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Target Farm Parcel <span class="text-danger">*</span></label>
                    <select name="farm_id" class="form-select" required>
                        <option value="">-- Choose Farm --</option>
                        <?php foreach ($allFarms as $fm): ?>
                            <option value="<?= $fm['farm_id'] ?>">
                                <?= h($fm['farm_name']) ?> (<?= h($fm['location']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Crop Variety <span class="text-danger">*</span></label>
                    <select name="crop_id" class="form-select" required>
                        <option value="">-- Choose Crop --</option>
                        <?php foreach ($cropsCatalog as $c): ?>
                            <option value="<?= $c['crop_id'] ?>">
                                <?= h($c['crop_name']) ?> (<?= h($c['variety'] ?: 'Standard') ?>) • <?= (int)$c['growing_period_days'] ?>d
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Planting Date <span class="text-danger">*</span></label>
                        <input type="date" name="planting_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Area (Hectares) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="area_planted" class="form-control" placeholder="e.g. 1.50" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Seed Quantity Used (kg)</label>
                    <input type="number" step="0.1" min="0" name="seed_quantity" class="form-control" placeholder="e.g. 20.0">
                </div>

                <button type="submit" class="btn btn-agri w-100">
                    <i class="bi bi-check-circle me-1"></i> Register Planting Cycle
                </button>
            </form>
        </div>
    </div>

    <!-- Log Harvest Form -->
    <div class="col-lg-6">
        <div class="card-panel p-4 h-100" id="harvestFormBox">
            <h5 class="fw-bold mb-3 text-dark"><i class="bi bi-basket3-fill text-primary me-2"></i>Quick Log: Harvest Yield</h5>
            <p class="text-muted small">Record yield tonnage, price, quality grade, and controlled 3NF harvest remarks.</p>

            <form method="post" action="app.php">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="quick_harvest">

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Active Planting Cycle <span class="text-danger">*</span></label>
                    <select name="planting_id" id="quick_harvest_planting_id" class="form-select" required>
                        <option value="">-- Choose Planting --</option>
                        <?php foreach ($plantingsList as $p): ?>
                            <?php if ((int)$p['status_id'] === 1): ?>
                                <option value="<?= $p['planting_id'] ?>">
                                    #<?= $p['planting_id'] ?>: <?= h($p['crop_name']) ?> @ <?= h($p['farm_name']) ?> (Expected: <?= format_date($p['expected_harvest_date']) ?>)
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Harvest Date <span class="text-danger">*</span></label>
                        <input type="date" name="harvest_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Yield (kg) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.1" name="quantity_kg" class="form-control" placeholder="e.g. 1200.00" required>
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Selling Price (₱/kg) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" name="selling_price_per_kg" class="form-control" placeholder="e.g. 32.50" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Quality Grade (3NF) <span class="text-danger">*</span></label>
                        <select name="quality_grade_id" class="form-select" required>
                            <?php foreach ($allGrades as $g): ?>
                                <option value="<?= $g['quality_grade_id'] ?>"><?= h($g['grade_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Harvest Remarks (3NF Controlled) <span class="text-danger">*</span></label>
                    <select name="remark_id" class="form-select" required>
                        <?php foreach ($allRemarks as $r): ?>
                            <option value="<?= $r['remark_id'] ?>"><?= h($r['remark_description']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-cash-stack me-1"></i> Record Harvest Transaction
                </button>
            </form>
        </div>
    </div>
</div>

<!-- ====================================================================== -->
<!-- TAB 5: WEATHER & AGRO-ADVISORY                                         -->
<!-- ====================================================================== -->
<?php elseif ($appTab === 'advisory'): ?>
<div class="row g-4">
    <!-- Current Weather Conditions -->
    <div class="col-lg-6">
        <div class="card-panel p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-cloud-sun-fill text-warning me-2"></i>Cebu Agri-Climatic Advisory</h5>
                <span class="badge bg-primary">Live Advisory</span>
            </div>

            <div class="p-3 rounded border mb-3 text-center" style="background: linear-gradient(135deg, rgba(2,132,199,0.1) 0%, rgba(34,197,94,0.1) 100%);">
                <i class="bi bi-sun-fill text-warning display-3 d-block my-2"></i>
                <h3 class="fw-bold mb-0 text-dark">31°C</h3>
                <div class="text-muted small">Partly Cloudy • Intermittent Showers Expected in Southern Cebu</div>
                <div class="d-flex justify-content-center gap-4 mt-3 pt-2 border-top">
                    <div><span class="text-muted small d-block">Humidity</span><strong>78%</strong></div>
                    <div><span class="text-muted small d-block">Soil Moisture</span><strong class="text-success">Optimal (65%)</strong></div>
                    <div><span class="text-muted small d-block">Wind Speed</span><strong>14 km/h ENE</strong></div>
                </div>
            </div>

            <h6 class="fw-bold text-dark mb-2">Seasonal Recommendations:</h6>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item bg-transparent px-0 py-2">
                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                    <strong>Rice & Cereals:</strong> Ideal conditions for land preparation and transplanting in rainfed and irrigated zones.
                </li>
                <li class="list-group-item bg-transparent px-0 py-2">
                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                    <strong>Vegetable Crops (Tomato, Eggplant):</strong> Ensure drainage channels are cleared to prevent root rot during afternoon downpours.
                </li>
                <li class="list-group-item bg-transparent px-0 py-2">
                    <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>
                    <strong>Pest & Disease Advisory:</strong> High relative humidity encourages fungal blight in lowland tomato parcels. Apply organic neem spray.
                </li>
            </ul>
        </div>
    </div>

    <!-- Soil & Irrigation Reference (3NF) -->
    <div class="col-lg-6">
        <div class="card-panel p-4 h-100">
            <h5 class="fw-bold mb-3 text-dark"><i class="bi bi-droplet-fill text-info me-2"></i>Soil & Irrigation Compatibility Guide</h5>
            <p class="text-muted small">Optimized practices based on AgriTrack's normalized 3NF classification standards.</p>

            <div class="table-responsive">
                <table class="table table-custom align-middle">
                    <thead>
                        <tr>
                            <th>Soil Type (3NF)</th>
                            <th>Best Irrigation Method</th>
                            <th>Recommended Crops</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Loam</strong></td>
                            <td><span class="badge bg-info-subtle text-info border">Drip Irrigation</span></td>
                            <td>Vegetables, Tomatoes, Eggplant</td>
                        </tr>
                        <tr>
                            <td><strong>Clay Loam</strong></td>
                            <td><span class="badge bg-primary-subtle text-primary border">Irrigated (Canal)</span></td>
                            <td>Rice, Corn, Sweet Potato</td>
                        </tr>
                        <tr>
                            <td><strong>Sandy Loam</strong></td>
                            <td><span class="badge bg-warning-subtle text-warning border">Drip / Sprinkler</span></td>
                            <td>Peanuts, Watermelon, Root Crops</td>
                        </tr>
                        <tr>
                            <td><strong>Clay</strong></td>
                            <td><span class="badge bg-success-subtle text-success border">Rainfed / Flooded</span></td>
                            <td>Lowland Rice, Banana</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-3 p-3 rounded border small bg-light">
                <i class="bi bi-lightbulb-fill text-warning me-1"></i>
                <strong>Farmer Tip:</strong> Drip irrigation saves up to 40% more water compared to furrow irrigation while ensuring consistent soil moisture for high-value vegetable crops.
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function preloadCalculator(cropId, cropName, days) {
    window.location.href = `app.php?tab=calculator&crop_id=${cropId}`;
}

function preloadHarvestLog(plantingId, cropName, farmName) {
    window.location.href = `app.php?tab=field_log&planting_id=${plantingId}`;
}

// On page load, if planting_id is passed in URL for field_log tab, auto-select it
document.addEventListener('DOMContentLoaded', function() {
    const params = new URLSearchParams(window.location.search);
    const pId = params.get('planting_id');
    if (pId) {
        const select = document.getElementById('quick_harvest_planting_id');
        if (select) {
            select.value = pId;
            const target = document.getElementById('harvestFormBox');
            if (target) target.scrollIntoView({ behavior: 'smooth' });
        }
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
