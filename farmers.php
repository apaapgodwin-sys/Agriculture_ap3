<?php
declare(strict_types=1);
$pageTitle = 'Farmers Management';
require_once __DIR__ . '/config/config.php';
require_login();

// Handle POST actions: Save (Create/Update), Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save') {
        $id = (int)($_POST['farmer_id'] ?? 0);
        $firstName = trim((string)($_POST['first_name'] ?? ''));
        $lastName = trim((string)($_POST['last_name'] ?? ''));
        $contactNumber = trim((string)($_POST['contact_number'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));
        $dateRegistered = trim((string)($_POST['date_registered'] ?? ''));

        // Validation
        if ($firstName === '' || $lastName === '') {
            flash('error', 'First name and last name are required.');
            redirect('farmers.php');
        }
        if ($address === '') {
            flash('error', 'Address is required.');
            redirect('farmers.php');
        }
        if ($dateRegistered === '') {
            $dateRegistered = date('Y-m-d');
        }

        $data = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'contact_number' => $contactNumber,
            'address' => $address,
            'date_registered' => $dateRegistered
        ];

        if ($id > 0) {
            Database::update('farmer', 'farmer_id', $id, $data);
            flash('success', "Farmer {$firstName} {$lastName} updated successfully.");
        } else {
            Database::insert('farmer', $data);
            flash('success', "Farmer {$firstName} {$lastName} registered successfully.");
        }
        redirect('farmers.php');
    }

    if ($action === 'delete') {
        if (!is_admin()) {
            flash('error', 'Permission denied. Only Administrators can delete records.');
            redirect('farmers.php');
        }

        $id = (int)($_POST['farmer_id'] ?? 0);
        if ($id <= 0) {
            flash('error', 'Invalid farmer record.');
            redirect('farmers.php');
        }

        // Referential integrity check: check if farmer has farms
        $linkedFarms = Database::rawQuery("SELECT COUNT(*) as cnt FROM farm WHERE farmer_id = :id", [':id' => $id]);
        $farmCount = (int)($linkedFarms[0]['cnt'] ?? 0);
        if ($farmCount > 0) {
            flash('error', "Cannot delete this farmer: this farmer owns {$farmCount} registered farm(s). You must reassign or remove the farms first.");
            redirect('farmers.php');
        }

        Database::delete('farmer', 'farmer_id', $id);
        flash('success', 'Farmer record deleted successfully.');
        redirect('farmers.php');
    }
}

// Search & Filter & Pagination
$search = trim((string)($_GET['q'] ?? ''));
$sort = (string)($_GET['sort'] ?? 'id_asc');
$pageNo = max(1, (int)($_GET['p'] ?? 1));
$perPage = 10;
$offset = ($pageNo - 1) * $perPage;

$orderClause = match ($sort) {
    'name_asc' => 'f.last_name ASC, f.first_name ASC',
    'name_desc' => 'f.last_name DESC, f.first_name DESC',
    'date_desc' => 'f.date_registered DESC',
    'date_asc' => 'f.date_registered ASC',
    'id_desc' => 'f.farmer_id DESC',
    default => 'f.farmer_id ASC'
};

$sqlWhere = "1=1";
$params = [];
if ($search !== '') {
    $sqlWhere .= " AND (f.first_name LIKE :q OR f.last_name LIKE :q OR f.address LIKE :q OR f.contact_number LIKE :q)";
    $params[':q'] = '%' . $search . '%';
}

$countSql = "SELECT COUNT(*) as total FROM farmer f WHERE {$sqlWhere}";
$totalRows = (int)(Database::rawQuery($countSql, $params)[0]['total'] ?? 0);
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$selectSql = "
    SELECT f.*, COUNT(fm.farm_id) as farm_count
    FROM farmer f
    LEFT JOIN farm fm ON fm.farmer_id = f.farmer_id
    WHERE {$sqlWhere}
    GROUP BY f.farmer_id
    ORDER BY {$orderClause}
    LIMIT {$perPage} OFFSET {$offset}
";
$farmersList = Database::rawQuery($selectSql, $params);

// Check if an edit record is requested
$editRecord = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $res = Database::rawQuery("SELECT * FROM farmer WHERE farmer_id = :id", [':id' => $editId]);
    if (!empty($res)) {
        $editRecord = $res[0];
    }
}

// Check if a view record is requested
$viewRecord = null;
$viewFarms = [];
if (isset($_GET['view'])) {
    $viewId = (int)$_GET['view'];
    $res = Database::rawQuery("SELECT * FROM farmer WHERE farmer_id = :id", [':id' => $viewId]);
    if (!empty($res)) {
        $viewRecord = $res[0];
        $viewFarms = Database::rawQuery("
            SELECT fm.*, st.soil_type_name, it.irrigation_type_name
            FROM farm fm
            LEFT JOIN soil_types st ON st.soil_type_id = fm.soil_type_id
            LEFT JOIN irrigation_types it ON it.irrigation_type_id = fm.irrigation_type_id
            WHERE fm.farmer_id = :id
            ORDER BY fm.farm_id ASC
        ", [':id' => $viewId]);
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Header & Actions -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0 text-dark">Farmers Module</h4>
        <small class="text-muted">Master list of agricultural landowners and farm operators.</small>
    </div>
    <button class="btn btn-agri shadow-sm" data-bs-toggle="modal" data-bs-target="#farmerFormModal" onclick="prepareAddModal()">
        <i class="bi bi-person-plus-fill me-1"></i> Register New Farmer
    </button>
</div>

<!-- Search & Sort Filter Bar -->
<div class="card-panel mb-3 p-3">
    <form method="get" class="row g-2 align-items-center">
        <div class="col-md-6 col-lg-5">
            <div class="input-group">
                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                <input type="text" name="q" class="form-control" placeholder="Search by name, address, or phone..." value="<?= h($search) ?>">
            </div>
        </div>
        <div class="col-md-4 col-lg-3">
            <select name="sort" class="form-select" onchange="this.form.submit()">
                <option value="id_asc" <?= $sort === 'id_asc' ? 'selected' : '' ?>>Sort by: ID (1 to 15)</option>
                <option value="id_desc" <?= $sort === 'id_desc' ? 'selected' : '' ?>>Sort by: ID (Descending)</option>
                <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Sort by: Name (A - Z)</option>
                <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Sort by: Name (Z - A)</option>
                <option value="date_desc" <?= $sort === 'date_desc' ? 'selected' : '' ?>>Sort by: Registration Date (Newest)</option>
                <option value="date_asc" <?= $sort === 'date_asc' ? 'selected' : '' ?>>Sort by: Registration Date (Oldest)</option>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary"><i class="bi bi-filter"></i> Apply</button>
            <?php if ($search !== '' || $sort !== 'id_asc'): ?>
                <a href="farmers.php" class="btn btn-outline-secondary">Reset</a>
            <?php endif; ?>
        </div>
        <div class="col text-end text-muted small d-none d-md-block">
            Showing <?= count($farmersList) ?> of <?= $totalRows ?> farmers
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
                    <th>Farmer Name</th>
                    <th>Contact Number</th>
                    <th>Address</th>
                    <th>Date Registered</th>
                    <th class="text-center">Farms Owned</th>
                    <th class="text-end" style="width: 170px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($farmersList)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">No farmer records found matching your criteria.</td></tr>
                <?php else: ?>
                    <?php foreach ($farmersList as $f): ?>
                        <tr>
                            <td class="fw-bold text-secondary">#<?= (int)$f['farmer_id'] ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= h($f['first_name'] . ' ' . $f['last_name']) ?></div>
                                <small class="text-muted"><?= h($f['address']) ?></small>
                            </td>
                            <td>
                                <i class="bi bi-telephone-fill text-muted me-1 small"></i>
                                <?= h($f['contact_number'] ?: '—') ?>
                            </td>
                            <td><?= h($f['address']) ?></td>
                            <td><?= format_date($f['date_registered']) ?></td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-geo-alt-fill text-primary me-1"></i> <?= (int)$f['farm_count'] ?> Farm(s)
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="farmers.php?view=<?= $f['farmer_id'] ?>" class="btn btn-sm btn-outline-info" title="View Details">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="openEditModal(<?= htmlspecialchars(json_encode($f), ENT_QUOTES) ?>)" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <?php if (is_admin()): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?= $f['farmer_id'] ?>, '<?= h($f['first_name'] . ' ' . $f['last_name']) ?>')" title="Delete">
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
            <span class="text-muted small">Page <?= $pageNo ?> of <?= $totalPages ?> (<?= $totalRows ?> total farmers)</span>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $pageNo <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?p=<?= $pageNo - 1 ?>&q=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i === $pageNo ? 'active' : '' ?>">
                            <a class="page-link" href="?p=<?= $i ?>&q=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $pageNo >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?p=<?= $pageNo + 1 ?>&q=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- Add / Edit Modal -->
<div class="modal fade" id="farmerFormModal" tabindex="-1" aria-labelledby="farmerModalTitle" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" action="farmers.php" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="farmer_id" id="modal_farmer_id" value="0">

            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="farmerModalTitle">Register Farmer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label for="modal_first_name" class="form-label small fw-semibold">First Name <span class="text-danger">*</span></label>
                        <input type="text" name="first_name" id="modal_first_name" class="form-control" required placeholder="e.g. Juan">
                    </div>
                    <div class="col-6">
                        <label for="modal_last_name" class="form-label small fw-semibold">Last Name <span class="text-danger">*</span></label>
                        <input type="text" name="last_name" id="modal_last_name" class="form-control" required placeholder="e.g. Dela Cruz">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="modal_contact_number" class="form-label small fw-semibold">Contact Number</label>
                    <input type="text" name="contact_number" id="modal_contact_number" class="form-control" placeholder="e.g. 09171234567">
                </div>

                <div class="mb-3">
                    <label for="modal_address" class="form-label small fw-semibold">Address / Barangay <span class="text-danger">*</span></label>
                    <input type="text" name="address" id="modal_address" class="form-control" required placeholder="e.g. Cebu City, Cebu">
                </div>

                <div class="mb-3">
                    <label for="modal_date_registered" class="form-label small fw-semibold">Date Registered <span class="text-danger">*</span></label>
                    <input type="date" name="date_registered" id="modal_date_registered" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-agri"><i class="bi bi-save me-1"></i> Save Farmer</button>
            </div>
        </form>
    </div>
</div>

<!-- View Details Modal -->
<?php if ($viewRecord): ?>
<div class="modal fade show" id="viewFarmerModal" tabindex="-1" style="display: block; background: rgba(0,0,0,0.5);" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-circle text-primary me-2"></i>Farmer Profile: <?= h($viewRecord['first_name'] . ' ' . $viewRecord['last_name']) ?></h5>
                <a href="farmers.php" class="btn-close"></a>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-4">
                    <div class="col-sm-6">
                        <div class="text-muted small">Farmer ID:</div>
                        <div class="fw-bold">#<?= $viewRecord['farmer_id'] ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small">Contact Number:</div>
                        <div class="fw-bold"><?= h($viewRecord['contact_number'] ?: 'None') ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small">Residential Address:</div>
                        <div class="fw-bold"><?= h($viewRecord['address']) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small">Registration Date:</div>
                        <div class="fw-bold"><?= format_date($viewRecord['date_registered']) ?></div>
                    </div>
                </div>

                <h6 class="fw-bold border-bottom pb-2 mb-3"><i class="bi bi-geo-alt-fill text-success me-1"></i>Registered Agricultural Landholdings (<?= count($viewFarms) ?>)</h6>
                <?php if (empty($viewFarms)): ?>
                    <p class="text-muted small">This farmer does not currently have any registered farms.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Farm Name</th>
                                    <th>Location</th>
                                    <th>Area (Hectares)</th>
                                    <th>Soil Type</th>
                                    <th>Irrigation</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($viewFarms as $vfm): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= h($vfm['farm_name']) ?></td>
                                        <td><?= h($vfm['location']) ?></td>
                                        <td><?= format_number($vfm['area_hectares']) ?> ha</td>
                                        <td><span class="badge bg-secondary-subtle text-secondary"><?= h($vfm['soil_type_name']) ?></span></td>
                                        <td><span class="badge bg-info-subtle text-info"><?= h($vfm['irrigation_type_name']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <a href="farmers.php" class="btn btn-secondary">Close</a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Delete Confirmation Form (Hidden) -->
<form id="deleteForm" method="post" action="farmers.php" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="farmer_id" id="delete_farmer_id" value="0">
</form>

<script>
function prepareAddModal() {
    document.getElementById('farmerModalTitle').textContent = 'Register New Farmer';
    document.getElementById('modal_farmer_id').value = '0';
    document.getElementById('modal_first_name').value = '';
    document.getElementById('modal_last_name').value = '';
    document.getElementById('modal_contact_number').value = '';
    document.getElementById('modal_address').value = '';
    document.getElementById('modal_date_registered').value = '<?= date('Y-m-d') ?>';
}

function openEditModal(farmer) {
    document.getElementById('farmerModalTitle').textContent = 'Edit Farmer #' + farmer.farmer_id;
    document.getElementById('modal_farmer_id').value = farmer.farmer_id;
    document.getElementById('modal_first_name').value = farmer.first_name || '';
    document.getElementById('modal_last_name').value = farmer.last_name || '';
    document.getElementById('modal_contact_number').value = farmer.contact_number || '';
    document.getElementById('modal_address').value = farmer.address || '';
    document.getElementById('modal_date_registered').value = farmer.date_registered || '';
    new bootstrap.Modal(document.getElementById('farmerFormModal')).show();
}

function confirmDelete(id, name) {
    if (confirm(`Are you sure you want to permanently delete farmer record #${id} (${name})?\n\nReferenced farms will prevent deletion.`)) {
        document.getElementById('delete_farmer_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
