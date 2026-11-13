<?php
declare(strict_types=1);
$pageTitle = 'Crops Master Catalog';
require_once __DIR__ . '/config/config.php';
require_login();

// Handle POST actions: Save (Create/Update), Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save') {
        $id = (int)($_POST['crop_id'] ?? 0);
        $cropName = trim((string)($_POST['crop_name'] ?? ''));
        $variety = trim((string)($_POST['variety'] ?? ''));
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $growingPeriodDays = (int)($_POST['growing_period_days'] ?? 0);
        $seasonId = (int)($_POST['season_id'] ?? 0);

        // Validation
        if ($cropName === '') {
            flash('error', 'Crop name is required.');
            redirect('crops.php');
        }
        if ($categoryId <= 0) {
            flash('error', 'Please select a crop category.');
            redirect('crops.php');
        }
        if ($growingPeriodDays <= 0) {
            flash('error', 'Growing period in days must be greater than zero.');
            redirect('crops.php');
        }
        if ($seasonId <= 0) {
            flash('error', 'Please select a recommended season.');
            redirect('crops.php');
        }

        $data = [
            'crop_name' => $cropName,
            'variety' => $variety,
            'category_id' => $categoryId,
            'growing_period_days' => $growingPeriodDays,
            'season_id' => $seasonId
        ];

        if ($id > 0) {
            Database::update('crop', 'crop_id', $id, $data);
            flash('success', "Crop '{$cropName}' updated successfully.");
        } else {
            Database::insert('crop', $data);
            flash('success', "Crop '{$cropName}' added to master catalog.");
        }
        redirect('crops.php');
    }

    if ($action === 'delete') {
        if (!is_admin()) {
            flash('error', 'Permission denied. Only Administrators can delete records.');
            redirect('crops.php');
        }

        $id = (int)($_POST['crop_id'] ?? 0);
        if ($id <= 0) {
            flash('error', 'Invalid crop record.');
            redirect('crops.php');
        }

        // Referential integrity check: check if crop has plantings
        $linkedPlantings = Database::rawQuery("SELECT COUNT(*) as cnt FROM planting WHERE crop_id = :id", [':id' => $id]);
        $plantingCount = (int)($linkedPlantings[0]['cnt'] ?? 0);
        if ($plantingCount > 0) {
            flash('error', "Cannot delete this crop: it is currently used in {$plantingCount} planting record(s). Delete or reassign the planting records first.");
            redirect('crops.php');
        }

        Database::delete('crop', 'crop_id', $id);
        flash('success', 'Crop record deleted successfully.');
        redirect('crops.php');
    }
}

// Fetch Lookups
$allCategories = Database::query('crop_categories', 'category_id, category_name', [], 'category_id.asc');
$allSeasons = Database::query('recommended_seasons', 'season_id, season_name', [], 'season_id.asc');

// Filters & Search & Pagination
$search = trim((string)($_GET['q'] ?? ''));
$filterCategory = (int)($_GET['cat'] ?? 0);
$filterSeason = (int)($_GET['season'] ?? 0);
$pageNo = max(1, (int)($_GET['p'] ?? 1));
$perPage = 10;
$offset = ($pageNo - 1) * $perPage;

$sqlWhere = "1=1";
$params = [];
if ($search !== '') {
    $sqlWhere .= " AND (c.crop_name LIKE :q OR c.variety LIKE :q)";
    $params[':q'] = '%' . $search . '%';
}
if ($filterCategory > 0) {
    $sqlWhere .= " AND c.category_id = :cat";
    $params[':cat'] = $filterCategory;
}
if ($filterSeason > 0) {
    $sqlWhere .= " AND c.season_id = :season";
    $params[':season'] = $filterSeason;
}

$countSql = "SELECT COUNT(*) as total FROM crop c WHERE {$sqlWhere}";
$totalRows = (int)(Database::rawQuery($countSql, $params)[0]['total'] ?? 0);
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$selectSql = "
    SELECT c.*, cc.category_name, rs.season_name,
           (SELECT COUNT(*) FROM planting p WHERE p.crop_id = c.crop_id) as planting_count
    FROM crop c
    JOIN crop_categories cc ON cc.category_id = c.category_id
    JOIN recommended_seasons rs ON rs.season_id = c.season_id
    WHERE {$sqlWhere}
    ORDER BY c.crop_id ASC
    LIMIT {$perPage} OFFSET {$offset}
";
$cropsList = Database::rawQuery($selectSql, $params);

require_once __DIR__ . '/includes/header.php';
?>

<!-- Header & Actions -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0 text-dark">Crops Catalog Module</h4>
        <small class="text-muted">Master catalogue of crop species, varieties, growth cycles, and recommended seasons.</small>
    </div>
    <button class="btn btn-agri shadow-sm" data-bs-toggle="modal" data-bs-target="#cropFormModal" onclick="prepareAddModal()">
        <i class="bi bi-plus-circle-fill me-1"></i> Add Master Crop
    </button>
</div>

<!-- Filters Bar -->
<div class="card-panel mb-3 p-3">
    <form method="get" class="row g-2 align-items-center">
        <div class="col-md-5 col-lg-4">
            <div class="input-group">
                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                <input type="text" name="q" class="form-control" placeholder="Search crop or variety..." value="<?= h($search) ?>">
            </div>
        </div>
        <div class="col-md-3 col-lg-3">
            <select name="cat" class="form-select" onchange="this.form.submit()">
                <option value="0">All Categories</option>
                <?php foreach ($allCategories as $cat): ?>
                    <option value="<?= $cat['category_id'] ?>" <?= $filterCategory === (int)$cat['category_id'] ? 'selected' : '' ?>>
                        <?= h($cat['category_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 col-lg-3">
            <select name="season" class="form-select" onchange="this.form.submit()">
                <option value="0">All Seasons</option>
                <?php foreach ($allSeasons as $sn): ?>
                    <option value="<?= $sn['season_id'] ?>" <?= $filterSeason === (int)$sn['season_id'] ? 'selected' : '' ?>>
                        <?= h($sn['season_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Filter</button>
            <?php if ($search !== '' || $filterCategory > 0 || $filterSeason > 0): ?>
                <a href="crops.php" class="btn btn-outline-secondary">Reset</a>
            <?php endif; ?>
        </div>
        <div class="col text-end text-muted small d-none d-lg-block">
            Showing <?= count($cropsList) ?> of <?= $totalRows ?> crops
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
                    <th>Crop Name</th>
                    <th>Variety</th>
                    <th>Category (3NF)</th>
                    <th>Growth Period (Days)</th>
                    <th>Recommended Season (3NF)</th>
                    <th class="text-center">Plantings</th>
                    <th class="text-end" style="width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($cropsList)): ?>
                    <tr><td colspan="8" class="text-center py-4 text-muted">No crop records found matching your filters.</td></tr>
                <?php else: ?>
                    <?php foreach ($cropsList as $c): ?>
                        <tr>
                            <td class="fw-bold text-secondary">#<?= (int)$c['crop_id'] ?></td>
                            <td>
                                <div class="fw-bold text-dark fs-6"><?= h($c['crop_name']) ?></div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?= h($c['variety'] ?: 'General') ?></span>
                            </td>
                            <td>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                    <?= h($c['category_name']) ?>
                                </span>
                            </td>
                            <td class="fw-bold">
                                <i class="bi bi-clock me-1 text-muted"></i> <?= (int)$c['growing_period_days'] ?> days
                            </td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                    <?= h($c['season_name']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-muted border">
                                    <?= (int)$c['planting_count'] ?> active
                                </span>
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="openEditModal(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <?php if (is_admin()): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?= $c['crop_id'] ?>, '<?= h($c['crop_name']) ?>')" title="Delete">
                                        <i class="bi bi-trash"></i>
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
                        <a class="page-link" href="?p=<?= $pageNo - 1 ?>&q=<?= urlencode($search) ?>&cat=<?= $filterCategory ?>&season=<?= $filterSeason ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i === $pageNo ? 'active' : '' ?>">
                            <a class="page-link" href="?p=<?= $i ?>&q=<?= urlencode($search) ?>&cat=<?= $filterCategory ?>&season=<?= $filterSeason ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $pageNo >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?p=<?= $pageNo + 1 ?>&q=<?= urlencode($search) ?>&cat=<?= $filterCategory ?>&season=<?= $filterSeason ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- Add / Edit Modal -->
<div class="modal fade" id="cropFormModal" tabindex="-1" aria-labelledby="cropModalTitle" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" action="crops.php" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="crop_id" id="modal_crop_id" value="0">

            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="cropModalTitle">Add Master Crop</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="modal_crop_name" class="form-label small fw-semibold">Crop Name <span class="text-danger">*</span></label>
                    <input type="text" name="crop_name" id="modal_crop_name" class="form-control" placeholder="e.g. Rice, Tomato, Corn" required>
                </div>

                <div class="mb-3">
                    <label for="modal_variety" class="form-label small fw-semibold">Variety / Cultivar</label>
                    <input type="text" name="variety" id="modal_variety" class="form-control" placeholder="e.g. NSIC Rc 222, Diamante Max">
                </div>

                <div class="mb-3">
                    <label for="modal_category_id" class="form-label small fw-semibold">Crop Category (3NF) <span class="text-danger">*</span></label>
                    <select name="category_id" id="modal_category_id" class="form-select" required>
                        <option value="">-- Choose Category --</option>
                        <?php foreach ($allCategories as $cat): ?>
                            <option value="<?= $cat['category_id'] ?>"><?= h($cat['category_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="modal_growing_period_days" class="form-label small fw-semibold">Growing Period in Days <span class="text-danger">*</span></label>
                    <input type="number" min="1" name="growing_period_days" id="modal_growing_period_days" class="form-control" placeholder="e.g. 115" required>
                </div>

                <div class="mb-3">
                    <label for="modal_season_id" class="form-label small fw-semibold">Recommended Season (3NF) <span class="text-danger">*</span></label>
                    <select name="season_id" id="modal_season_id" class="form-select" required>
                        <option value="">-- Choose Season --</option>
                        <?php foreach ($allSeasons as $sn): ?>
                            <option value="<?= $sn['season_id'] ?>"><?= h($sn['season_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-agri"><i class="bi bi-save me-1"></i> Save Crop</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Form (Hidden) -->
<form id="deleteForm" method="post" action="crops.php" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="crop_id" id="delete_crop_id" value="0">
</form>

<script>
function prepareAddModal() {
    document.getElementById('cropModalTitle').textContent = 'Add Master Crop';
    document.getElementById('modal_crop_id').value = '0';
    document.getElementById('modal_crop_name').value = '';
    document.getElementById('modal_variety').value = '';
    document.getElementById('modal_category_id').value = '';
    document.getElementById('modal_growing_period_days').value = '';
    document.getElementById('modal_season_id').value = '';
}

function openEditModal(crop) {
    document.getElementById('cropModalTitle').textContent = 'Edit Crop #' + crop.crop_id;
    document.getElementById('modal_crop_id').value = crop.crop_id;
    document.getElementById('modal_crop_name').value = crop.crop_name || '';
    document.getElementById('modal_variety').value = crop.variety || '';
    document.getElementById('modal_category_id').value = crop.category_id || '';
    document.getElementById('modal_growing_period_days').value = crop.growing_period_days || '';
    document.getElementById('modal_season_id').value = crop.season_id || '';
    new bootstrap.Modal(document.getElementById('cropFormModal')).show();
}

function confirmDelete(id, name) {
    if (confirm(`Are you sure you want to permanently delete crop record #${id} (${name})?\n\nReferenced planting records will prevent deletion.`)) {
        document.getElementById('delete_crop_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
