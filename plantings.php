<?php
declare(strict_types=1);
$pageTitle = 'Planting Records Management';
require_once __DIR__ . '/config/config.php';
require_login();

$plantingModel = new Planting();
$farmModel     = new Farm();
$cropModel     = new Crop();

// Handle POST actions: Save (Create/Update), Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save') {
        $id = (int)($_POST['planting_id'] ?? 0);
        $farmId = (int)($_POST['farm_id'] ?? 0);
        $cropId = (int)($_POST['crop_id'] ?? 0);
        $plantingDate = trim((string)($_POST['planting_date'] ?? ''));
        $areaPlanted = (float)($_POST['area_planted'] ?? 0);
        $seedQuantity = (float)($_POST['seed_quantity'] ?? 0);
        $expectedHarvestDate = trim((string)($_POST['expected_harvest_date'] ?? ''));
        $statusId = (int)($_POST['status_id'] ?? 1);

        // Validation
        if ($farmId <= 0) {
            flash('error', 'Please select a farm parcel.');
            redirect('plantings.php');
        }
        if ($cropId <= 0) {
            flash('error', 'Please select a crop.');
            redirect('plantings.php');
        }
        if ($plantingDate === '') {
            flash('error', 'Planting date is required.');
            redirect('plantings.php');
        }
        if ($areaPlanted <= 0) {
            flash('error', 'Area planted in hectares must be greater than zero.');
            redirect('plantings.php');
        }
        if ($seedQuantity < 0) {
            flash('error', 'Seed quantity cannot be negative.');
            redirect('plantings.php');
        }
        if ($expectedHarvestDate !== '' && $expectedHarvestDate < $plantingDate) {
            flash('error', 'Expected harvest date must be on or after the planting date.');
            redirect('plantings.php');
        }

        $data = [
            'farm_id' => $farmId,
            'crop_id' => $cropId,
            'planting_date' => $plantingDate,
            'area_planted' => $areaPlanted,
            'seed_quantity' => $seedQuantity,
            'expected_harvest_date' => $expectedHarvestDate ?: null,
            'status_id' => $statusId
        ];

        if ($id > 0) {
            $plantingModel->update($id, $data);
            log_activity('UPDATE', 'PLANTING', "Updated planting cycle #{$id} (Farm #{$farmId}, Crop #{$cropId}).", $id, $data);
            flash('success', "Planting record #{$id} updated successfully.");
        } else {
            $newId = $plantingModel->create($data);
            log_activity('CREATE', 'PLANTING', "Logged new planting cycle #{$newId} ({$areaPlanted} ha) on farm #{$farmId}.", $newId, $data);
            flash('success', "New planting cycle logged successfully.");
        }
        redirect('plantings.php');
    }

    if ($action === 'delete') {
        if (!is_admin()) {
            flash('error', 'Permission denied. Only Administrators can delete records.');
            redirect('plantings.php');
        }

        $id = (int)($_POST['planting_id'] ?? 0);
        if ($id <= 0) {
            flash('error', 'Invalid planting record.');
            redirect('plantings.php');
        }

        $delResult = $plantingModel->deletePlanting($id);
        if (!$delResult['success']) {
            flash('error', $delResult['message']);
            redirect('plantings.php');
        }

        log_activity('DELETE', 'PLANTING', "Deleted planting cycle record #{$id}.", $id);
        flash('success', $delResult['message']);
        redirect('plantings.php');
    }
}

// Fetch Lookups via Models
$allFarms    = $farmModel->getDropdownList();
$allCrops    = $cropModel->getDropdownList();
$allStatuses = $plantingModel->getStatuses();

// Filters & Search & Pagination via Planting Model
$search       = trim((string)($_GET['q'] ?? ''));
$filterStatus = (int)($_GET['status'] ?? 0);
$filterFarm   = (int)($_GET['farm'] ?? 0);
$filterCrop   = (int)($_GET['crop'] ?? 0);
$pageNo       = max(1, (int)($_GET['p'] ?? 1));
$perPage      = 10;
$offset       = ($pageNo - 1) * $perPage;

$totalRows     = $plantingModel->countFiltered($search, $filterStatus, $filterFarm, $filterCrop);
$totalPages    = max(1, (int)ceil($totalRows / $perPage));
$plantingsList = $plantingModel->getAll($search, $filterStatus, $filterFarm, $filterCrop, $perPage, $offset);

require_once __DIR__ . '/includes/header.php';
?>

<!-- Header & Actions -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0 text-dark">Planting Records Module</h4>
        <small class="text-muted">Field planting cycles, planted area, seed quantities, and harvest projections.</small>
    </div>
    <button class="btn btn-agri shadow-sm" data-bs-toggle="modal" data-bs-target="#plantingFormModal" onclick="prepareAddModal()">
        <i class="bi bi-calendar-plus-fill me-1"></i> Log Planting Cycle
    </button>
</div>

<!-- Filters Bar -->
<div class="card-panel mb-3 p-3">
    <form method="get" class="row g-2 align-items-center">
        <div class="col-md-4 col-lg-3">
            <div class="input-group">
                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                <input type="text" name="q" class="form-control" placeholder="Search farm, crop, farmer..." value="<?= h($search) ?>">
            </div>
        </div>
        <div class="col-md-3 col-lg-2">
            <select name="status" class="form-select" onchange="this.form.submit()">
                <option value="0">All Statuses</option>
                <?php foreach ($allStatuses as $st): ?>
                    <option value="<?= $st['status_id'] ?>" <?= $filterStatus === (int)$st['status_id'] ? 'selected' : '' ?>>
                        <?= h($st['status_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 col-lg-3">
            <select name="farm" class="form-select" onchange="this.form.submit()">
                <option value="0">All Farms</option>
                <?php foreach ($allFarms as $fm): ?>
                    <option value="<?= $fm['farm_id'] ?>" <?= $filterFarm === (int)$fm['farm_id'] ? 'selected' : '' ?>>
                        <?= h($fm['farm_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 col-lg-2">
            <select name="crop" class="form-select" onchange="this.form.submit()">
                <option value="0">All Crops</option>
                <?php foreach ($allCrops as $cp): ?>
                    <option value="<?= $cp['crop_id'] ?>" <?= $filterCrop === (int)$cp['crop_id'] ? 'selected' : '' ?>>
                        <?= h($cp['crop_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Filter</button>
            <?php if ($search !== '' || $filterStatus > 0 || $filterFarm > 0 || $filterCrop > 0): ?>
                <a href="plantings.php" class="btn btn-outline-secondary">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Data Table -->
<div class="card-panel mb-3">
    <div class="table-responsive">
        <table class="table table-custom align-middle">
            <thead>
                <tr>
                    <th style="width: 50px;">ID</th>
                    <th>Farm</th>
                    <th>Crop Placed</th>
                    <th>Planting Date</th>
                    <th>Planted (ha)</th>
                    <th>Seed Qty</th>
                    <th>Expected Harvest</th>
                    <th>Status</th>
                    <th class="text-end" style="width: 170px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($plantingsList)): ?>
                    <tr><td colspan="9" class="text-center py-4 text-muted">No planting records found matching your filters.</td></tr>
                <?php else: ?>
                    <?php foreach ($plantingsList as $p): ?>
                        <tr>
                            <td class="fw-bold text-secondary">#<?= (int)$p['planting_id'] ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= h($p['farm_name']) ?></div>
                                <small class="text-muted"><i class="bi bi-person"></i> <?= h($p['first_name'] . ' ' . $p['last_name']) ?></small>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?= h($p['crop_name']) ?></div>
                                <small class="badge bg-light text-muted border"><?= h($p['variety'] ?: 'Standard') ?></small>
                            </td>
                            <td><?= format_date($p['planting_date']) ?></td>
                            <td class="fw-bold"><?= format_number($p['area_planted']) ?> ha</td>
                            <td><?= format_number($p['seed_quantity']) ?></td>
                            <td>
                                <span class="text-secondary fw-semibold"><?= format_date($p['expected_harvest_date']) ?></span>
                            </td>
                            <td>
                                <span class="badge <?= ($p['status_name'] ?? '') === 'Growing' ? 'bg-primary' : (($p['status_name'] ?? '') === 'Harvested' ? 'bg-success' : 'bg-warning text-dark') ?>">
                                    <?= h($p['status_name']) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <?php if (($p['status_name'] ?? '') === 'Growing'): ?>
                                    <a href="harvests.php?action=new&planting_id=<?= $p['planting_id'] ?>" class="btn btn-sm btn-outline-success" title="Log Harvest for this cycle">
                                        <i class="bi bi-basket"></i>
                                    </a>
                                <?php endif; ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="openEditModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <?php if (is_admin()): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?= $p['planting_id'] ?>)" title="Delete">
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
            <span class="text-muted small">Page <?= $pageNo ?> of <?= $totalPages ?> (<?= $totalRows ?> plantings)</span>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $pageNo <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?p=<?= $pageNo - 1 ?>&q=<?= urlencode($search) ?>&status=<?= $filterStatus ?>&farm=<?= $filterFarm ?>&crop=<?= $filterCrop ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i === $pageNo ? 'active' : '' ?>">
                            <a class="page-link" href="?p=<?= $i ?>&q=<?= urlencode($search) ?>&status=<?= $filterStatus ?>&farm=<?= $filterFarm ?>&crop=<?= $filterCrop ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $pageNo >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?p=<?= $pageNo + 1 ?>&q=<?= urlencode($search) ?>&status=<?= $filterStatus ?>&farm=<?= $filterFarm ?>&crop=<?= $filterCrop ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- Add / Edit Modal -->
<div class="modal fade" id="plantingFormModal" tabindex="-1" aria-labelledby="plantingModalTitle" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" action="plantings.php" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="planting_id" id="modal_planting_id" value="0">

            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="plantingModalTitle">Log Planting Cycle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="planting_farm_id" class="form-label small fw-semibold">Target Farm <span class="text-danger">*</span></label>
                    <select name="farm_id" id="planting_farm_id" class="form-select" required>
                        <option value="">-- Choose Farm Parcel --</option>
                        <?php foreach ($allFarms as $fm): ?>
                            <option value="<?= $fm['farm_id'] ?>">
                                <?= h($fm['farm_name']) ?> (<?= h($fm['first_name'] . ' ' . $fm['last_name']) ?> - <?= h($fm['location']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="planting_crop_id" class="form-label small fw-semibold">Selected Crop <span class="text-danger">*</span></label>
                    <select name="crop_id" id="planting_crop_id" class="form-select" required>
                        <option value="">-- Choose Crop --</option>
                        <?php foreach ($allCrops as $cp): ?>
                            <option value="<?= $cp['crop_id'] ?>" data-days="<?= $cp['growing_period_days'] ?>">
                                <?= h($cp['crop_name']) ?> (<?= h($cp['variety'] ?: 'Standard') ?> - <?= $cp['growing_period_days'] ?> growth days)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label for="planting_date" class="form-label small fw-semibold">Planting Date <span class="text-danger">*</span></label>
                        <input type="date" name="planting_date" id="planting_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-6">
                        <label for="planting_expected_date" class="form-label small fw-semibold">Expected Harvest</label>
                        <input type="date" name="expected_harvest_date" id="planting_expected_date" class="form-control">
                        <div class="form-text" style="font-size: 0.72rem;">Auto-computed based on crop</div>
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label for="modal_area_planted" class="form-label small fw-semibold">Area Planted (ha) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="area_planted" id="modal_area_planted" class="form-control" placeholder="e.g. 0.50" required>
                    </div>
                    <div class="col-6">
                        <label for="modal_seed_quantity" class="form-label small fw-semibold">Seed Quantity</label>
                        <input type="number" step="0.01" min="0" name="seed_quantity" id="modal_seed_quantity" class="form-control" placeholder="e.g. 20.00">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="modal_status_id" class="form-label small fw-semibold">Planting Status (3NF) <span class="text-danger">*</span></label>
                    <select name="status_id" id="modal_status_id" class="form-select" required>
                        <?php foreach ($allStatuses as $st): ?>
                            <option value="<?= $st['status_id'] ?>" <?= (int)$st['status_id'] === 1 ? 'selected' : '' ?>>
                                <?= h($st['status_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-agri"><i class="bi bi-save me-1"></i> Save Planting Record</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Form (Hidden) -->
<form id="deleteForm" method="post" action="plantings.php" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="planting_id" id="delete_planting_id" value="0">
</form>

<script>
function prepareAddModal() {
    document.getElementById('plantingModalTitle').textContent = 'Log Planting Cycle';
    document.getElementById('modal_planting_id').value = '0';
    document.getElementById('planting_farm_id').value = '';
    document.getElementById('planting_crop_id').value = '';
    document.getElementById('planting_date').value = '<?= date('Y-m-d') ?>';
    document.getElementById('planting_expected_date').value = '';
    document.getElementById('modal_area_planted').value = '';
    document.getElementById('modal_seed_quantity').value = '';
    document.getElementById('modal_status_id').value = '1';
}

function openEditModal(p) {
    document.getElementById('plantingModalTitle').textContent = 'Edit Planting #' + p.planting_id;
    document.getElementById('modal_planting_id').value = p.planting_id;
    document.getElementById('planting_farm_id').value = p.farm_id || '';
    document.getElementById('planting_crop_id').value = p.crop_id || '';
    document.getElementById('planting_date').value = p.planting_date || '';
    document.getElementById('planting_expected_date').value = p.expected_harvest_date || '';
    document.getElementById('modal_area_planted').value = p.area_planted || '';
    document.getElementById('modal_seed_quantity').value = p.seed_quantity || '';
    document.getElementById('modal_status_id').value = p.status_id || '1';
    new bootstrap.Modal(document.getElementById('plantingFormModal')).show();
}

function confirmDelete(id) {
    if (confirm(`Are you sure you want to permanently delete planting record #${id}?\n\nReferenced harvest records will prevent deletion.`)) {
        document.getElementById('delete_planting_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
