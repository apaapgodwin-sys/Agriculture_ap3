<?php
declare(strict_types=1);
$pageTitle = 'Farms Management';
require_once __DIR__ . '/config/config.php';
require_login();

// Ensure the farm table has the deleted_at column in SQLite (safe migration)
try {
    $pdo  = Database::getLocalPdo();
    $cols = $pdo->query("PRAGMA table_info(farm)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('deleted_at', $cols, true)) {
        $pdo->exec("ALTER TABLE farm ADD COLUMN deleted_at TEXT DEFAULT NULL");
    }
} catch (Throwable $e) {
    // Column may already exist or Supabase path — safe to ignore
}

// ─── POST Handlers ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    // ── Save (Create / Update) ───────────────────────────────────
    if ($action === 'save') {
        $id               = (int)($_POST['farm_id'] ?? 0);
        $farmerId         = (int)($_POST['farmer_id'] ?? 0);
        $farmName         = trim((string)($_POST['farm_name'] ?? ''));
        $location         = trim((string)($_POST['location'] ?? ''));
        $areaHectares     = (float)($_POST['area_hectares'] ?? 0);
        $soilTypeId       = (int)($_POST['soil_type_id'] ?? 0);
        $irrigationTypeId = (int)($_POST['irrigation_type_id'] ?? 0);

        if ($farmerId <= 0)       { flash('error', 'Please select a registered farmer as the owner.'); redirect('farms.php'); }
        if ($farmName === '')     { flash('error', 'Farm name is required.'); redirect('farms.php'); }
        if ($location === '')     { flash('error', 'Farm location is required.'); redirect('farms.php'); }
        if ($areaHectares <= 0)   { flash('error', 'Farm area must be greater than zero.'); redirect('farms.php'); }
        if ($soilTypeId <= 0)     { flash('error', 'Please select a soil type.'); redirect('farms.php'); }
        if ($irrigationTypeId<=0) { flash('error', 'Please select an irrigation type.'); redirect('farms.php'); }

        $data = [
            'farmer_id'         => $farmerId,
            'farm_name'         => $farmName,
            'location'          => $location,
            'area_hectares'     => $areaHectares,
            'soil_type_id'      => $soilTypeId,
            'irrigation_type_id'=> $irrigationTypeId,
        ];

        if ($id > 0) {
            Database::update('farm', 'farm_id', $id, $data);
            flash('success', "Farm '{$farmName}' updated successfully.");
        } else {
            Database::insert('farm', $data);
            flash('success', "Farm '{$farmName}' registered successfully.");
        }
        redirect('farms.php');
    }

    // ── Soft Delete ──────────────────────────────────────────────
    if ($action === 'soft_delete') {
        if (!is_admin()) { flash('error', 'Permission denied.'); redirect('farms.php'); }
        $id = (int)($_POST['farm_id'] ?? 0);
        if ($id <= 0) { flash('error', 'Invalid farm record.'); redirect('farms.php'); }

        // Block if linked plantings exist
        $cnt = (int)(Database::rawQuery(
            "SELECT COUNT(*) as cnt FROM planting WHERE farm_id = :id", [':id' => $id]
        )[0]['cnt'] ?? 0);
        if ($cnt > 0) {
            flash('error', "Cannot delete this farm: it has {$cnt} planting record(s) linked. Remove or reassign them first.");
            redirect('farms.php');
        }

        Database::rawQuery(
            "UPDATE farm SET deleted_at = datetime('now') WHERE farm_id = :id",
            [':id' => $id]
        );
        flash('success', 'Farm moved to Recently Deleted. You can restore it within 30 days.');
        redirect('farms.php?tab=deleted');
    }

    // ── Restore ──────────────────────────────────────────────────
    if ($action === 'restore') {
        if (!is_admin()) { flash('error', 'Permission denied.'); redirect('farms.php'); }
        $id = (int)($_POST['farm_id'] ?? 0);
        if ($id <= 0) { flash('error', 'Invalid farm record.'); redirect('farms.php'); }

        Database::rawQuery(
            "UPDATE farm SET deleted_at = NULL WHERE farm_id = :id",
            [':id' => $id]
        );
        flash('success', 'Farm restored successfully.');
        redirect('farms.php');
    }

    // ── Permanent Delete ─────────────────────────────────────────
    if ($action === 'permanent_delete') {
        if (!is_admin()) { flash('error', 'Permission denied.'); redirect('farms.php'); }
        $id = (int)($_POST['farm_id'] ?? 0);
        if ($id <= 0) { flash('error', 'Invalid farm record.'); redirect('farms.php'); }

        Database::delete('farm', 'farm_id', $id);
        flash('success', 'Farm permanently deleted.');
        redirect('farms.php?tab=deleted');
    }
}

// ─── Lookup Dropdowns ─────────────────────────────────────────────
$allFarmers       = Database::rawQuery("SELECT farmer_id, first_name, last_name FROM farmer ORDER BY last_name ASC, first_name ASC");
$allSoilTypes     = Database::query('soil_types',      'soil_type_id, soil_type_name',           [], 'soil_type_id.asc');
$allIrrigationTypes= Database::query('irrigation_types','irrigation_type_id, irrigation_type_name', [], 'irrigation_type_id.asc');

// ─── Active Farms (not deleted) ───────────────────────────────────
$search          = trim((string)($_GET['q'] ?? ''));
$filterSoil      = (int)($_GET['soil'] ?? 0);
$filterIrrigation= (int)($_GET['irrigation'] ?? 0);
$pageNo          = max(1, (int)($_GET['p'] ?? 1));
$perPage         = 10;
$offset          = ($pageNo - 1) * $perPage;
$activeTab       = $_GET['tab'] ?? 'active';

$sqlWhere = "(fm.deleted_at IS NULL)";
$params   = [];
if ($search !== '') {
    $sqlWhere .= " AND (fm.farm_name LIKE :q OR fm.location LIKE :q OR f.first_name LIKE :q OR f.last_name LIKE :q)";
    $params[':q'] = '%' . $search . '%';
}
if ($filterSoil > 0) {
    $sqlWhere .= " AND fm.soil_type_id = :soil";
    $params[':soil'] = $filterSoil;
}
if ($filterIrrigation > 0) {
    $sqlWhere .= " AND fm.irrigation_type_id = :irrigation";
    $params[':irrigation'] = $filterIrrigation;
}

$countSql  = "SELECT COUNT(*) as total FROM farm fm JOIN farmer f ON f.farmer_id = fm.farmer_id WHERE {$sqlWhere}";
$totalRows = (int)(Database::rawQuery($countSql, $params)[0]['total'] ?? 0);
$totalPages= max(1, (int)ceil($totalRows / $perPage));

$selectSql = "
    SELECT fm.*, f.first_name, f.last_name, f.contact_number as farmer_phone,
           st.soil_type_name, it.irrigation_type_name,
           (SELECT COUNT(*) FROM planting p WHERE p.farm_id = fm.farm_id) as planting_count
    FROM farm fm
    JOIN farmer f  ON f.farmer_id  = fm.farmer_id
    JOIN soil_types st ON st.soil_type_id = fm.soil_type_id
    JOIN irrigation_types it ON it.irrigation_type_id = fm.irrigation_type_id
    WHERE {$sqlWhere}
    ORDER BY fm.farm_id ASC
    LIMIT {$perPage} OFFSET {$offset}
";
$farmsList = Database::rawQuery($selectSql, $params);

// ─── Deleted Farms ────────────────────────────────────────────────
$deletedFarms = Database::rawQuery("
    SELECT fm.*, f.first_name, f.last_name,
           st.soil_type_name, it.irrigation_type_name
    FROM farm fm
    JOIN farmer f  ON f.farmer_id  = fm.farmer_id
    JOIN soil_types st ON st.soil_type_id = fm.soil_type_id
    JOIN irrigation_types it ON it.irrigation_type_id = fm.irrigation_type_id
    WHERE fm.deleted_at IS NOT NULL
    ORDER BY fm.deleted_at DESC
");
$deletedCount = count($deletedFarms);

require_once __DIR__ . '/includes/header.php';
?>

<!-- Header & Actions -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0 text-dark">Farms Module</h4>
        <small class="text-muted">Agricultural land parcels, soil attributes, and irrigation infrastructure.</small>
    </div>
    <button class="btn btn-agri shadow-sm" data-bs-toggle="modal" data-bs-target="#farmFormModal" onclick="prepareAddModal()">
        <i class="bi bi-plus-circle-fill me-1"></i> Register New Farm
    </button>
</div>

<!-- Tab Navigation -->
<ul class="nav nav-tabs mb-3" id="farmTabs">
    <li class="nav-item">
        <a class="nav-link <?= $activeTab !== 'deleted' ? 'active' : '' ?>" href="farms.php">
            <i class="bi bi-geo-alt-fill me-1"></i> Active Farms
            <span class="badge bg-success ms-1"><?= $totalRows ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'deleted' ? 'active' : '' ?>" href="farms.php?tab=deleted">
            <i class="bi bi-trash3-fill me-1"></i> Recently Deleted
            <?php if ($deletedCount > 0): ?>
                <span class="badge bg-danger ms-1"><?= $deletedCount ?></span>
            <?php endif; ?>
        </a>
    </li>
</ul>

<?php if ($activeTab !== 'deleted'): ?>
<!-- ══ ACTIVE FARMS TAB ══════════════════════════════════════════ -->

<!-- Filters Bar -->
<div class="card-panel mb-3 p-3">
    <form method="get" class="row g-2 align-items-center">
        <input type="hidden" name="tab" value="active">
        <div class="col-md-5 col-lg-4">
            <div class="input-group">
                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                <input type="text" name="q" class="form-control" placeholder="Search farm, owner, or location..." value="<?= h($search) ?>">
            </div>
        </div>
        <div class="col-md-3 col-lg-2">
            <select name="soil" class="form-select" onchange="this.form.submit()">
                <option value="0">All Soil Types</option>
                <?php foreach ($allSoilTypes as $st): ?>
                    <option value="<?= $st['soil_type_id'] ?>" <?= $filterSoil === (int)$st['soil_type_id'] ? 'selected' : '' ?>>
                        <?= h($st['soil_type_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 col-lg-3">
            <select name="irrigation" class="form-select" onchange="this.form.submit()">
                <option value="0">All Irrigation Types</option>
                <?php foreach ($allIrrigationTypes as $it): ?>
                    <option value="<?= $it['irrigation_type_id'] ?>" <?= $filterIrrigation === (int)$it['irrigation_type_id'] ? 'selected' : '' ?>>
                        <?= h($it['irrigation_type_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Filter</button>
            <?php if ($search !== '' || $filterSoil > 0 || $filterIrrigation > 0): ?>
                <a href="farms.php" class="btn btn-outline-secondary">Reset</a>
            <?php endif; ?>
        </div>
        <div class="col text-end text-muted small d-none d-lg-block">
            Showing <?= count($farmsList) ?> of <?= $totalRows ?> farms
        </div>
    </form>
</div>

<!-- Data Table -->
<div class="card-panel mb-3">
    <div class="table-responsive">
        <table class="table table-custom align-middle">
            <thead>
                <tr>
                    <th style="width: 60px;">ID</th>
                    <th>Farm Name</th>
                    <th>Owner (Farmer)</th>
                    <th>Location</th>
                    <th>Area (ha)</th>
                    <th>Soil Type</th>
                    <th>Irrigation</th>
                    <th class="text-end" style="width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($farmsList)): ?>
                    <tr><td colspan="8" class="text-center py-4 text-muted">No farm records found matching your filters.</td></tr>
                <?php else: ?>
                    <?php foreach ($farmsList as $f): ?>
                        <tr>
                            <td class="fw-bold text-secondary">#<?= (int)$f['farm_id'] ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= h($f['farm_name']) ?></div>
                                <small class="text-muted"><i class="bi bi-geo-alt"></i> <?= h($f['location']) ?></small>
                            </td>
                            <td>
                                <a href="farmers.php?view=<?= $f['farmer_id'] ?>" class="text-decoration-none fw-semibold">
                                    <?= h($f['first_name'] . ' ' . $f['last_name']) ?>
                                </a>
                            </td>
                            <td><?= h($f['location']) ?></td>
                            <td class="fw-bold"><?= format_number($f['area_hectares']) ?> ha</td>
                            <td><span class="badge bg-secondary-subtle text-secondary border"><?= h($f['soil_type_name']) ?></span></td>
                            <td><span class="badge bg-info-subtle text-info border"><?= h($f['irrigation_type_name']) ?></span></td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        onclick="openEditModal(<?= htmlspecialchars(json_encode($f), ENT_QUOTES) ?>)" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <?php if (is_admin()): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                            onclick="confirmSoftDelete(<?= $f['farm_id'] ?>, '<?= h($f['farm_name']) ?>')" title="Move to Trash">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="card-panel-footer d-flex align-items-center justify-content-between p-3 border-top">
            <span class="text-muted small">Page <?= $pageNo ?> of <?= $totalPages ?></span>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $pageNo <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?p=<?= $pageNo - 1 ?>&q=<?= urlencode($search) ?>&soil=<?= $filterSoil ?>&irrigation=<?= $filterIrrigation ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i === $pageNo ? 'active' : '' ?>">
                            <a class="page-link" href="?p=<?= $i ?>&q=<?= urlencode($search) ?>&soil=<?= $filterSoil ?>&irrigation=<?= $filterIrrigation ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $pageNo >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?p=<?= $pageNo + 1 ?>&q=<?= urlencode($search) ?>&soil=<?= $filterSoil ?>&irrigation=<?= $filterIrrigation ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- ══ RECENTLY DELETED TAB ══════════════════════════════════════ -->

<div class="card-panel mb-3">
    <div class="card-panel-header">
        <h5 class="card-panel-title"><i class="bi bi-clock-history me-2 text-danger"></i>Recently Deleted Farms</h5>
        <small class="text-muted">Records moved to trash. Admins can restore or permanently delete them.</small>
    </div>
    <div class="table-responsive">
        <table class="table table-custom align-middle">
            <thead>
                <tr>
                    <th style="width:60px;">ID</th>
                    <th>Farm Name</th>
                    <th>Owner</th>
                    <th>Location</th>
                    <th>Area (ha)</th>
                    <th>Soil Type</th>
                    <th>Deleted At</th>
                    <th class="text-end" style="width:170px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($deletedFarms)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-trash3 fs-2 d-block mb-2 opacity-25"></i>
                            No deleted farms — trash is empty.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($deletedFarms as $f): ?>
                        <tr class="text-muted" style="opacity: 0.82;">
                            <td class="fw-bold">#<?= (int)$f['farm_id'] ?></td>
                            <td>
                                <div class="fw-semibold"><?= h($f['farm_name']) ?></div>
                                <small><i class="bi bi-geo-alt"></i> <?= h($f['location']) ?></small>
                            </td>
                            <td><?= h($f['first_name'] . ' ' . $f['last_name']) ?></td>
                            <td><?= h($f['location']) ?></td>
                            <td><?= format_number($f['area_hectares']) ?> ha</td>
                            <td><span class="badge bg-secondary-subtle text-secondary border"><?= h($f['soil_type_name']) ?></span></td>
                            <td>
                                <small class="text-danger">
                                    <i class="bi bi-clock me-1"></i>
                                    <?= date('M d, Y g:i A', strtotime($f['deleted_at'])) ?>
                                </small>
                            </td>
                            <td class="text-end">
                                <?php if (is_admin()): ?>
                                    <!-- Restore -->
                                    <form method="post" action="farms.php" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                        <input type="hidden" name="action"  value="restore">
                                        <input type="hidden" name="farm_id" value="<?= $f['farm_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-success" title="Restore">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i>Restore
                                        </button>
                                    </form>
                                    <!-- Permanent Delete -->
                                    <button type="button" class="btn btn-sm btn-danger ms-1"
                                            onclick="confirmPermDelete(<?= $f['farm_id'] ?>, '<?= h($f['farm_name']) ?>')" title="Delete permanently">
                                        <i class="bi bi-trash3-fill"></i>
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted small">Admin only</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<!-- ══ ADD / EDIT MODAL ══════════════════════════════════════════ -->
<div class="modal fade" id="farmFormModal" tabindex="-1" aria-labelledby="farmModalTitle" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" action="farms.php" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action"  value="save">
            <input type="hidden" name="farm_id" id="modal_farm_id" value="0">

            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="farmModalTitle">Register Farm Parcel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="modal_farmer_id" class="form-label small fw-semibold">Owner (Farmer) <span class="text-danger">*</span></label>
                    <select name="farmer_id" id="modal_farmer_id" class="form-select" required>
                        <option value="">-- Choose Registered Farmer --</option>
                        <?php foreach ($allFarmers as $fm): ?>
                            <option value="<?= $fm['farmer_id'] ?>">
                                <?= h($fm['last_name'] . ', ' . $fm['first_name']) ?> (#<?= $fm['farmer_id'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="modal_farm_name" class="form-label small fw-semibold">Farm Name <span class="text-danger">*</span></label>
                    <input type="text" name="farm_name" id="modal_farm_name" class="form-control" placeholder="e.g. Dela Cruz Farm" required>
                </div>
                <div class="mb-3">
                    <label for="modal_location" class="form-label small fw-semibold">Location / Address <span class="text-danger">*</span></label>
                    <input type="text" name="location" id="modal_location" class="form-control" placeholder="e.g. Carcar City, Cebu" required>
                </div>
                <div class="mb-3">
                    <label for="modal_area_hectares" class="form-label small fw-semibold">Area in Hectares <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0.01" name="area_hectares" id="modal_area_hectares" class="form-control" placeholder="e.g. 2.50" required>
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label for="modal_soil_type_id" class="form-label small fw-semibold">Soil Type (3NF) <span class="text-danger">*</span></label>
                        <select name="soil_type_id" id="modal_soil_type_id" class="form-select" required>
                            <option value="">-- Choose --</option>
                            <?php foreach ($allSoilTypes as $st): ?>
                                <option value="<?= $st['soil_type_id'] ?>"><?= h($st['soil_type_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label for="modal_irrigation_type_id" class="form-label small fw-semibold">Irrigation (3NF) <span class="text-danger">*</span></label>
                        <select name="irrigation_type_id" id="modal_irrigation_type_id" class="form-select" required>
                            <option value="">-- Choose --</option>
                            <?php foreach ($allIrrigationTypes as $it): ?>
                                <option value="<?= $it['irrigation_type_id'] ?>"><?= h($it['irrigation_type_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-agri"><i class="bi bi-save me-1"></i> Save Farm</button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden: Soft Delete Form -->
<form id="softDeleteForm" method="post" action="farms.php" style="display:none;">
    <input type="hidden" name="csrf_token"  value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action"      value="soft_delete">
    <input type="hidden" name="farm_id"     id="soft_delete_farm_id" value="0">
</form>

<!-- Hidden: Permanent Delete Form -->
<form id="permDeleteForm" method="post" action="farms.php" style="display:none;">
    <input type="hidden" name="csrf_token"  value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action"      value="permanent_delete">
    <input type="hidden" name="farm_id"     id="perm_delete_farm_id" value="0">
</form>

<script>
function prepareAddModal() {
    document.getElementById('farmModalTitle').textContent = 'Register New Farm Parcel';
    document.getElementById('modal_farm_id').value = '0';
    ['modal_farmer_id','modal_farm_name','modal_location','modal_area_hectares',
     'modal_soil_type_id','modal_irrigation_type_id'].forEach(function(id){
        document.getElementById(id).value = '';
    });
}

function openEditModal(farm) {
    document.getElementById('farmModalTitle').textContent = 'Edit Farm #' + farm.farm_id;
    document.getElementById('modal_farm_id').value           = farm.farm_id;
    document.getElementById('modal_farmer_id').value         = farm.farmer_id || '';
    document.getElementById('modal_farm_name').value         = farm.farm_name || '';
    document.getElementById('modal_location').value          = farm.location || '';
    document.getElementById('modal_area_hectares').value     = farm.area_hectares || '';
    document.getElementById('modal_soil_type_id').value      = farm.soil_type_id || '';
    document.getElementById('modal_irrigation_type_id').value= farm.irrigation_type_id || '';
    new bootstrap.Modal(document.getElementById('farmFormModal')).show();
}

function confirmSoftDelete(id, name) {
    if (confirm('Move farm "' + name + '" (#' + id + ') to the Recently Deleted bin?\n\nYou can restore it later from the Recently Deleted tab.')) {
        document.getElementById('soft_delete_farm_id').value = id;
        document.getElementById('softDeleteForm').submit();
    }
}

function confirmPermDelete(id, name) {
    if (confirm('⚠️ PERMANENTLY DELETE farm "' + name + '" (#' + id + ')?\n\nThis action CANNOT be undone.')) {
        document.getElementById('perm_delete_farm_id').value = id;
        document.getElementById('permDeleteForm').submit();
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
