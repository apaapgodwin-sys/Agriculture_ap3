<?php
declare(strict_types=1);
$pageTitle = 'Harvest Records Management';
require_once __DIR__ . '/config/config.php';
require_login();

$harvestModel  = new Harvest();
$plantingModel = new Planting();

// Handle POST actions: Save (Create/Update), Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save') {
        $id = (int)($_POST['harvest_id'] ?? 0);
        $plantingId = (int)($_POST['planting_id'] ?? 0);
        $harvestDate = trim((string)($_POST['harvest_date'] ?? ''));
        $quantityKg = (float)($_POST['quantity_kg'] ?? 0);
        $sellingPricePerKg = (float)($_POST['selling_price_per_kg'] ?? 0);
        $qualityGradeId = (int)($_POST['quality_grade_id'] ?? 0);
        $remarkId = (int)($_POST['remark_id'] ?? 0);
        $remarksNote = trim((string)($_POST['remarks_note'] ?? ''));

        // Validation
        if ($plantingId <= 0) {
            flash('error', 'Please select a planting cycle record.');
            redirect('harvests.php');
        }
        if ($harvestDate === '') {
            flash('error', 'Harvest date is required.');
            redirect('harvests.php');
        }
        if ($quantityKg <= 0) {
            flash('error', 'Harvest quantity in kg must be greater than zero.');
            redirect('harvests.php');
        }
        if ($sellingPricePerKg < 0) {
            flash('error', 'Selling price per kg cannot be negative.');
            redirect('harvests.php');
        }
        if ($qualityGradeId <= 0) {
            flash('error', 'Please select a harvest quality grade.');
            redirect('harvests.php');
        }
        if ($remarkId <= 0) {
            flash('error', 'Please select a harvest remark (controlled lookup).');
            redirect('harvests.php');
        }

        // Logical date relationship check: harvest_date >= planting_date
        $plantingInfo = $plantingModel->find($plantingId);
        if (!$plantingInfo) {
            flash('error', 'Selected planting record does not exist.');
            redirect('harvests.php');
        }
        $pDate = $plantingInfo['planting_date'];
        if ($harvestDate < $pDate) {
            flash('error', "Harvest date ({$harvestDate}) cannot be before the planting date ({$pDate}).");
            redirect('harvests.php');
        }

        $data = [
            'planting_id' => $plantingId,
            'harvest_date' => $harvestDate,
            'quantity_kg' => $quantityKg,
            'selling_price_per_kg' => $sellingPricePerKg,
            'quality_grade_id' => $qualityGradeId,
            'remark_id' => $remarkId,
            'remarks_note' => $remarksNote ?: null
        ];

        if ($id > 0) {
            $harvestModel->update($id, $data);
            log_activity('UPDATE', 'HARVEST', "Updated harvest transaction #{$id} ({$quantityKg} kg at ₱{$sellingPricePerKg}/kg).", $id, $data);
            flash('success', "Harvest record #{$id} updated successfully.");
        } else {
            $newId = $harvestModel->create($data);
            $val = $quantityKg * $sellingPricePerKg;
            log_activity('CREATE', 'HARVEST', "Recorded harvest #{$newId} of {$quantityKg} kg at ₱{$sellingPricePerKg}/kg (Valuation: ₱" . number_format($val, 2) . ") for planting #{$plantingId}.", $newId, $data);
            // Auto-update linked planting status to Harvested (status_id = 2) if it was Growing
            $plantingModel->update($plantingId, ['status_id' => 2]);
            flash('success', "Harvest transaction recorded successfully! Planting cycle #{$plantingId} marked as Harvested.");
        }
        redirect('harvests.php');
    }

    if ($action === 'delete') {
        if (!is_admin()) {
            flash('error', 'Permission denied. Only Administrators can delete records.');
            redirect('harvests.php');
        }

        $id = (int)($_POST['harvest_id'] ?? 0);
        if ($id <= 0) {
            flash('error', 'Invalid harvest record.');
            redirect('harvests.php');
        }

        $harvestModel->deleteHarvest($id);
        log_activity('DELETE', 'HARVEST', "Deleted harvest record #{$id}.", $id);
        flash('success', 'Harvest record deleted successfully.');
        redirect('harvests.php');
    }
}

// Fetch Lookups via Models
$allPlantings = $plantingModel->getEligibleForHarvest();
$allGrades    = $harvestModel->getQualityGrades();
$allRemarks   = $harvestModel->getRemarks();

// Filters & Search & Pagination via Harvest Model
$search       = trim((string)($_GET['q'] ?? ''));
$filterGrade  = (int)($_GET['grade'] ?? 0);
$filterRemark = (int)($_GET['remark'] ?? 0);
$pageNo       = max(1, (int)($_GET['p'] ?? 1));
$perPage      = 10;
$offset       = ($pageNo - 1) * $perPage;

$totalRows    = $harvestModel->countFiltered($search, $filterGrade, $filterRemark);
$totalPages   = max(1, (int)ceil($totalRows / $perPage));
$harvestsList = $harvestModel->getAll($search, $filterGrade, $filterRemark, $perPage, $offset);

// Check if prefill requested from planting cycle
$prefillPlantingId = (int)($_GET['planting_id'] ?? 0);

require_once __DIR__ . '/includes/header.php';
?>

<!-- Header & Actions -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0 text-dark">Harvest Records Module</h4>
        <small class="text-muted">Yield quantities, market prices, financial revenue, quality classification, and controlled harvest remarks.</small>
    </div>
    <button class="btn btn-agri shadow-sm" data-bs-toggle="modal" data-bs-target="#harvestFormModal" onclick="prepareAddModal(<?= $prefillPlantingId ?>)">
        <i class="bi bi-plus-circle-fill me-1"></i> Record New Harvest
    </button>
</div>

<!-- Filters Bar -->
<div class="card-panel mb-3 p-3">
    <form method="get" class="row g-2 align-items-center">
        <div class="col-md-5 col-lg-4">
            <div class="input-group">
                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                <input type="text" name="q" class="form-control" placeholder="Search farm, crop, or notes..." value="<?= h($search) ?>">
            </div>
        </div>
        <div class="col-md-3 col-lg-3">
            <select name="grade" class="form-select" onchange="this.form.submit()">
                <option value="0">All Quality Grades</option>
                <?php foreach ($allGrades as $g): ?>
                    <option value="<?= $g['quality_grade_id'] ?>" <?= $filterGrade === (int)$g['quality_grade_id'] ? 'selected' : '' ?>>
                        <?= h($g['grade_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 col-lg-3">
            <select name="remark" class="form-select" onchange="this.form.submit()">
                <option value="0">All Harvest Remarks</option>
                <?php foreach ($allRemarks as $rem): ?>
                    <option value="<?= $rem['remark_id'] ?>" <?= $filterRemark === (int)$rem['remark_id'] ? 'selected' : '' ?>>
                        <?= h($rem['remark_description']) ?> (ID: <?= $rem['remark_id'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Filter</button>
            <?php if ($search !== '' || $filterGrade > 0 || $filterRemark > 0): ?>
                <a href="harvests.php" class="btn btn-outline-secondary">Reset</a>
            <?php endif; ?>
        </div>
        <div class="col text-end text-muted small d-none d-lg-block">
            Showing <?= count($harvestsList) ?> of <?= $totalRows ?> records
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
                    <th>Crop & Variety</th>
                    <th>Origin Farm</th>
                    <th>Harvest Date</th>
                    <th>Yield (kg)</th>
                    <th>Price/kg</th>
                    <th>Estimated Value</th>
                    <th>Quality Grade (3NF)</th>
                    <th>Controlled Remark (3NF)</th>
                    <th class="text-end" style="width: 130px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($harvestsList)): ?>
                    <tr><td colspan="10" class="text-center py-4 text-muted">No harvest records found matching your filters.</td></tr>
                <?php else: ?>
                    <?php foreach ($harvestsList as $h): ?>
                        <tr>
                            <td class="fw-bold text-secondary">#<?= (int)$h['harvest_id'] ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= h($h['crop_name']) ?></div>
                                <small class="text-muted"><?= h($h['variety'] ?: 'General') ?></small>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= h($h['farm_name']) ?></div>
                                <small class="text-muted"><?= h($h['first_name'] . ' ' . $h['last_name']) ?></small>
                            </td>
                            <td><?= format_date($h['harvest_date']) ?></td>
                            <td class="fw-bold"><?= format_number($h['quantity_kg']) ?> kg</td>
                            <td><?= format_currency($h['selling_price_per_kg']) ?></td>
                            <td class="fw-bold text-success fs-6"><?= format_currency($h['total_value']) ?></td>
                            <td>
                                <span class="badge <?= ($h['grade_name'] ?? '') === 'Grade A' ? 'bg-success' : (($h['grade_name'] ?? '') === 'Grade B' ? 'bg-primary' : 'bg-warning text-dark') ?>">
                                    <?= h($h['grade_name']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border" title="Stored Numeric ID: <?= $h['remark_id'] ?>">
                                    <?= h($h['remark_description']) ?>
                                </span>
                                <?php if (!empty($h['remarks_note'])): ?>
                                    <div class="text-muted" style="font-size: 0.72rem;"><?= h($h['remarks_note']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="openEditModal(<?= htmlspecialchars(json_encode($h), ENT_QUOTES) ?>)" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <?php if (is_admin()): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?= $h['harvest_id'] ?>)" title="Delete">
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
            <span class="text-muted small">Page <?= $pageNo ?> of <?= $totalPages ?> (<?= $totalRows ?> harvests)</span>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $pageNo <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?p=<?= $pageNo - 1 ?>&q=<?= urlencode($search) ?>&grade=<?= $filterGrade ?>&remark=<?= $filterRemark ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i === $pageNo ? 'active' : '' ?>">
                            <a class="page-link" href="?p=<?= $i ?>&q=<?= urlencode($search) ?>&grade=<?= $filterGrade ?>&remark=<?= $filterRemark ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $pageNo >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?p=<?= $pageNo + 1 ?>&q=<?= urlencode($search) ?>&grade=<?= $filterGrade ?>&remark=<?= $filterRemark ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- Add / Edit Modal -->
<div class="modal fade" id="harvestFormModal" tabindex="-1" aria-labelledby="harvestModalTitle" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" action="harvests.php" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="harvest_id" id="modal_harvest_id" value="0">

            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="harvestModalTitle">Record Harvest Yield</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="modal_planting_id" class="form-label small fw-semibold">Planting Cycle Origin <span class="text-danger">*</span></label>
                    <select name="planting_id" id="modal_planting_id" class="form-select" required>
                        <option value="">-- Choose Planting Record --</option>
                        <?php foreach ($allPlantings as $pl): ?>
                            <option value="<?= $pl['planting_id'] ?>" <?= $prefillPlantingId === (int)$pl['planting_id'] ? 'selected' : '' ?>>
                                Cycle #<?= $pl['planting_id'] ?>: <?= h($pl['crop_name']) ?> @ <?= h($pl['farm_name']) ?> (Planted: <?= format_date($pl['planting_date']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="modal_harvest_date" class="form-label small fw-semibold">Harvest Date <span class="text-danger">*</span></label>
                    <input type="date" name="harvest_date" id="modal_harvest_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label for="harvest_quantity_kg" class="form-label small fw-semibold">Quantity Yield (kg) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="quantity_kg" id="harvest_quantity_kg" class="form-control" placeholder="e.g. 900.00" required>
                    </div>
                    <div class="col-6">
                        <label for="harvest_selling_price" class="form-label small fw-semibold">Price per kg (₱) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" name="selling_price_per_kg" id="harvest_selling_price" class="form-control" placeholder="e.g. 22.00" required>
                    </div>
                </div>

                <!-- Total Value dynamic display -->
                <div class="alert alert-success d-flex align-items-center justify-content-between p-2 mb-3">
                    <span class="small fw-semibold"><i class="bi bi-calculator me-1"></i> Total Estimated Value:</span>
                    <span class="fw-bold fs-6" id="harvest_total_value_display">₱0.00</span>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label for="modal_quality_grade_id" class="form-label small fw-semibold">Quality Grade (3NF) <span class="text-danger">*</span></label>
                        <select name="quality_grade_id" id="modal_quality_grade_id" class="form-select" required>
                            <option value="">-- Choose Grade --</option>
                            <?php foreach ($allGrades as $g): ?>
                                <option value="<?= $g['quality_grade_id'] ?>"><?= h($g['grade_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label for="modal_remark_id" class="form-label small fw-semibold">Harvest Remark (3NF) <span class="text-danger">*</span></label>
                        <select name="remark_id" id="modal_remark_id" class="form-select" required>
                            <option value="">-- Choose Remark --</option>
                            <?php foreach ($allRemarks as $rem): ?>
                                <option value="<?= $rem['remark_id'] ?>"><?= h($rem['remark_description']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="modal_remarks_note" class="form-label small fw-semibold">Additional Remarks / Quality Notes</label>
                    <input type="text" name="remarks_note" id="modal_remarks_note" class="form-control" placeholder="e.g. High quality harvest, Grade A certified">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-agri"><i class="bi bi-save me-1"></i> Save Harvest Record</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Form (Hidden) -->
<form id="deleteForm" method="post" action="harvests.php" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="harvest_id" id="delete_harvest_id" value="0">
</form>

<script>
function prepareAddModal(plantingId = 0) {
    document.getElementById('harvestModalTitle').textContent = 'Record Harvest Yield';
    document.getElementById('modal_harvest_id').value = '0';
    if (plantingId > 0) {
        document.getElementById('modal_planting_id').value = plantingId;
    } else {
        document.getElementById('modal_planting_id').value = '';
    }
    document.getElementById('modal_harvest_date').value = '<?= date('Y-m-d') ?>';
    document.getElementById('harvest_quantity_kg').value = '';
    document.getElementById('harvest_selling_price').value = '';
    document.getElementById('modal_quality_grade_id').value = '1';
    document.getElementById('modal_remark_id').value = '1';
    document.getElementById('modal_remarks_note').value = '';
    document.getElementById('harvest_total_value_display').textContent = '₱0.00';
}

function openEditModal(h) {
    document.getElementById('harvestModalTitle').textContent = 'Edit Harvest #' + h.harvest_id;
    document.getElementById('modal_harvest_id').value = h.harvest_id;
    document.getElementById('modal_planting_id').value = h.planting_id || '';
    document.getElementById('modal_harvest_date').value = h.harvest_date || '';
    document.getElementById('harvest_quantity_kg').value = h.quantity_kg || '';
    document.getElementById('harvest_selling_price').value = h.selling_price_per_kg || '';
    document.getElementById('modal_quality_grade_id').value = h.quality_grade_id || '1';
    document.getElementById('modal_remark_id').value = h.remark_id || '1';
    document.getElementById('modal_remarks_note').value = h.remarks_note || '';

    const qty = parseFloat(h.quantity_kg) || 0;
    const price = parseFloat(h.selling_price_per_kg) || 0;
    const total = qty * price;
    document.getElementById('harvest_total_value_display').textContent = '₱' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    new bootstrap.Modal(document.getElementById('harvestFormModal')).show();
}

function confirmDelete(id) {
    if (confirm(`Are you sure you want to permanently delete harvest record #${id}?`)) {
        document.getElementById('delete_harvest_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}

<?php if ($prefillPlantingId > 0): ?>
document.addEventListener('DOMContentLoaded', () => {
    prepareAddModal(<?= $prefillPlantingId ?>);
    new bootstrap.Modal(document.getElementById('harvestFormModal')).show();
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
