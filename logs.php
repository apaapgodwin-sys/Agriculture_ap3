<?php
declare(strict_types=1);
$pageTitle = 'Activity & Audit Logs';
require_once __DIR__ . '/config/config.php';
require_login();

// Handle Clear Logs (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $postAction = (string)($_POST['action'] ?? '');

    if ($postAction === 'clear_logs') {
        if (!is_admin()) {
            flash('error', 'Permission denied. Only Administrators can clear audit logs.');
            redirect('logs.php');
        }

        $adminUser = current_user();
        Database::rawQuery("DELETE FROM activity_logs");
        log_activity('SECURITY', 'AUTH', "Audit logs were purged by Administrator '{$adminUser['username']}'.");
        flash('success', 'Activity and audit logs have been successfully cleared.');
        redirect('logs.php');
    }
}

// ── Search & Filter Parameters ─────────────────────────────────────
$search     = trim((string)($_GET['q'] ?? ''));
$filterAct  = trim((string)($_GET['action_type'] ?? ''));
$filterMod  = trim((string)($_GET['module'] ?? ''));
$filterUser = trim((string)($_GET['user'] ?? ''));
$dateFrom   = trim((string)($_GET['from'] ?? ''));
$dateTo     = trim((string)($_GET['to'] ?? ''));
$pageNo     = max(1, (int)($_GET['p'] ?? 1));
$perPage    = 15;
$offset     = ($pageNo - 1) * $perPage;

$whereClauses = ["1=1"];
$params = [];

if ($search !== '') {
    $whereClauses[] = "(description LIKE :q OR username LIKE :q OR module LIKE :q OR action LIKE :q OR ip_address LIKE :q)";
    $params[':q'] = '%' . $search . '%';
}

if ($filterAct !== '') {
    $whereClauses[] = "action = :act";
    $params[':act'] = $filterAct;
}

if ($filterMod !== '') {
    $whereClauses[] = "module = :mod";
    $params[':mod'] = $filterMod;
}

if ($filterUser !== '') {
    $whereClauses[] = "username = :usr";
    $params[':usr'] = $filterUser;
}

if ($dateFrom !== '') {
    $whereClauses[] = "created_at >= :dfrom";
    $params[':dfrom'] = $dateFrom . ' 00:00:00';
}

if ($dateTo !== '') {
    $whereClauses[] = "created_at <= :dto";
    $params[':dto'] = $dateTo . ' 23:59:59';
}

$whereSql = implode(' AND ', $whereClauses);

// ── Export to CSV Handler ──────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportSql = "SELECT * FROM activity_logs WHERE {$whereSql} ORDER BY log_id DESC LIMIT 5000";
    $exportRows = Database::rawQuery($exportSql, $params);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="AgriTrack_Activity_Logs_' . date('Y-m-d_His') . '.csv"');

    $out = fopen('php://output', 'w');
    // UTF-8 BOM for Excel
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Log ID', 'Timestamp', 'Action Type', 'Module', 'Record ID', 'Username', 'IP Address', 'Description']);

    foreach ($exportRows as $r) {
        fputcsv($out, [
            $r['log_id'],
            $r['created_at'],
            $r['action'],
            $r['module'],
            $r['record_id'] ?? '—',
            $r['username'],
            $r['ip_address'] ?? '—',
            $r['description']
        ]);
    }
    fclose($out);
    exit;
}

// ── Metric Summary Counters ─────────────────────────────────────────
$totalLogs   = (int)(Database::rawQuery("SELECT COUNT(*) as c FROM activity_logs")[0]['c'] ?? 0);
$totalLogins = (int)(Database::rawQuery("SELECT COUNT(*) as c FROM activity_logs WHERE action IN ('LOGIN', 'FAILED_LOGIN')")[0]['c'] ?? 0);
$totalCreates= (int)(Database::rawQuery("SELECT COUNT(*) as c FROM activity_logs WHERE action = 'CREATE'")[0]['c'] ?? 0);
$totalUpdates= (int)(Database::rawQuery("SELECT COUNT(*) as c FROM activity_logs WHERE action = 'UPDATE'")[0]['c'] ?? 0);
$totalDeletes= (int)(Database::rawQuery("SELECT COUNT(*) as c FROM activity_logs WHERE action IN ('DELETE', 'SOFT_DELETE')")[0]['c'] ?? 0);

// Filtered Count & Pagination
$countSql   = "SELECT COUNT(*) as total FROM activity_logs WHERE {$whereSql}";
$filteredTotal = (int)(Database::rawQuery($countSql, $params)[0]['total'] ?? 0);
$totalPages = max(1, (int)ceil($filteredTotal / $perPage));

// Main Records Query
$logsSql = "SELECT * FROM activity_logs WHERE {$whereSql} ORDER BY log_id DESC LIMIT {$perPage} OFFSET {$offset}";
$logsList = Database::rawQuery($logsSql, $params);

// Distinct lookup lists for filters
$distinctUsers = Database::rawQuery("SELECT DISTINCT username FROM activity_logs ORDER BY username ASC");
$distinctModules = ['AUTH', 'FARMER', 'FARM', 'CROP', 'PLANTING', 'HARVEST', 'USER', 'PROFILE'];
$distinctActions = ['CREATE', 'UPDATE', 'DELETE', 'SOFT_DELETE', 'RESTORE', 'LOGIN', 'LOGOUT', 'FAILED_LOGIN', 'SECURITY'];

require_once __DIR__ . '/includes/header.php';
?>

<!-- Header & Quick Action Buttons -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0 text-dark"><i class="bi bi-clock-history text-success me-2"></i>System Activity & Audit Logs</h4>
        <small class="text-muted">Comprehensive immutable tracking: who logged in, what was created, updated, or deleted, and exact timestamps.</small>
    </div>
    <div class="d-flex gap-2">
        <a href="logs.php?export=csv<?= $search !== '' ? '&q=' . urlencode($search) : '' ?><?= $filterAct !== '' ? '&action_type=' . urlencode($filterAct) : '' ?><?= $filterMod !== '' ? '&module=' . urlencode($filterMod) : '' ?><?= $filterUser !== '' ? '&user=' . urlencode($filterUser) : '' ?><?= $dateFrom !== '' ? '&from=' . urlencode($dateFrom) : '' ?><?= $dateTo !== '' ? '&to=' . urlencode($dateTo) : '' ?>" class="btn btn-outline-success">
            <i class="bi bi-file-earmark-excel-fill me-1"></i> Export to CSV
        </a>
        <?php if (is_admin()): ?>
            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#clearLogsModal">
                <i class="bi bi-trash3 me-1"></i> Clear Logs
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Summary Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <span class="stat-label">Total Logs</span>
                <div class="stat-icon stat-icon-green"><i class="bi bi-journal-text"></i></div>
            </div>
            <div class="stat-number"><?= format_number($totalLogs, 0) ?></div>
            <div class="text-muted small mt-1">Recorded audit events</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <span class="stat-label">Creations</span>
                <div class="stat-icon stat-icon-teal"><i class="bi bi-plus-circle-fill"></i></div>
            </div>
            <div class="stat-number"><?= format_number($totalCreates, 0) ?></div>
            <div class="text-muted small mt-1">New records added</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <span class="stat-label">Modifications</span>
                <div class="stat-icon stat-icon-blue"><i class="bi bi-pencil-square"></i></div>
            </div>
            <div class="stat-number"><?= format_number($totalUpdates, 0) ?></div>
            <div class="text-muted small mt-1">Updates made</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <span class="stat-label">Deletions</span>
                <div class="stat-icon stat-icon-rose"><i class="bi bi-trash3-fill"></i></div>
            </div>
            <div class="stat-number"><?= format_number($totalDeletes, 0) ?></div>
            <div class="text-muted small mt-1">Soft & Hard deletes</div>
        </div>
    </div>
    <div class="col-12 col-md-4 col-xl">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <span class="stat-label">Sign-ins</span>
                <div class="stat-icon stat-icon-purple"><i class="bi bi-box-arrow-in-right"></i></div>
            </div>
            <div class="stat-number"><?= format_number($totalLogins, 0) ?></div>
            <div class="text-muted small mt-1">User authentication sessions</div>
        </div>
    </div>
</div>

<!-- Filters Bar -->
<div class="card-panel mb-3 p-3">
    <form method="get" action="logs.php" class="row g-2 align-items-center">
        <div class="col-md-4 col-lg-3">
            <div class="input-group">
                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                <input type="text" name="q" class="form-control" placeholder="Search keyword, user, or IP..." value="<?= h($search) ?>">
            </div>
        </div>

        <div class="col-6 col-md-2">
            <select name="action_type" class="form-select" onchange="this.form.submit()">
                <option value="">All Actions</option>
                <?php foreach ($distinctActions as $act): ?>
                    <option value="<?= $act ?>" <?= $filterAct === $act ? 'selected' : '' ?>><?= $act ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-6 col-md-2">
            <select name="module" class="form-select" onchange="this.form.submit()">
                <option value="">All Modules</option>
                <?php foreach ($distinctModules as $m): ?>
                    <option value="<?= $m ?>" <?= $filterMod === $m ? 'selected' : '' ?>><?= $m ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-6 col-md-2">
            <select name="user" class="form-select" onchange="this.form.submit()">
                <option value="">All Users</option>
                <?php foreach ($distinctUsers as $u): ?>
                    <option value="<?= h($u['username']) ?>" <?= $filterUser === $u['username'] ? 'selected' : '' ?>>
                        <?= h($u['username']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-6 col-md-3 d-flex gap-1">
            <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel"></i> Filter</button>
            <?php if ($search !== '' || $filterAct !== '' || $filterMod !== '' || $filterUser !== '' || $dateFrom !== '' || $dateTo !== ''): ?>
                <a href="logs.php" class="btn btn-outline-secondary" title="Reset Filters"><i class="bi bi-arrow-counterclockwise"></i></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Activity Logs Data Table -->
<div class="card-panel mb-3">
    <div class="table-responsive">
        <table class="table table-custom align-middle">
            <thead>
                <tr>
                    <th style="width: 70px;">ID</th>
                    <th style="width: 170px;">When (Timestamp)</th>
                    <th style="width: 120px;">Action</th>
                    <th style="width: 100px;">Module</th>
                    <th style="width: 150px;">User</th>
                    <th>What Happened (Description)</th>
                    <th style="width: 90px;">Ref ID</th>
                    <th style="width: 110px;">IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logsList)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-journal-x fs-1 d-block mb-2 opacity-25"></i>
                            No activity logs found matching your selected criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logsList as $log): ?>
                        <?php
                            $act = $log['action'];
                            $actBadge = match($act) {
                                'CREATE'      => 'bg-success text-white',
                                'UPDATE'      => 'bg-primary text-white',
                                'DELETE'      => 'bg-danger text-white',
                                'SOFT_DELETE' => 'bg-warning text-dark',
                                'RESTORE'     => 'bg-info text-white',
                                'LOGIN'       => 'bg-success-subtle text-success border border-success-subtle',
                                'LOGOUT'      => 'bg-secondary text-white',
                                'FAILED_LOGIN'=> 'bg-danger-subtle text-danger border border-danger-subtle',
                                'SECURITY'    => 'bg-dark text-white',
                                default       => 'bg-secondary text-white'
                            };

                            $actIcon = match($act) {
                                'CREATE'      => 'bi-plus-circle-fill',
                                'UPDATE'      => 'bi-pencil-fill',
                                'DELETE'      => 'bi-trash-fill',
                                'SOFT_DELETE' => 'bi-trash3',
                                'RESTORE'     => 'bi-arrow-counterclockwise',
                                'LOGIN'       => 'bi-box-arrow-in-right',
                                'LOGOUT'      => 'bi-box-arrow-right',
                                'FAILED_LOGIN'=> 'bi-shield-x',
                                'SECURITY'    => 'bi-shield-lock-fill',
                                default       => 'bi-activity'
                            };

                            $uInitial = strtoupper(substr($log['username'] ?? 'U', 0, 1));
                        ?>
                        <tr>
                            <td class="fw-bold text-secondary">#<?= (int)$log['log_id'] ?></td>
                            <td>
                                <div class="fw-semibold text-dark" style="font-size: 0.86rem;"><?= date('M j, Y g:i A', strtotime($log['created_at'])) ?></div>
                                <small class="text-muted"><i class="bi bi-clock me-1"></i><?= time_ago($log['created_at']) ?></small>
                            </td>
                            <td>
                                <span class="badge <?= $actBadge ?> d-inline-flex align-items-center gap-1 px-2 py-1" style="font-size: 0.74rem;">
                                    <i class="bi <?= $actIcon ?>"></i> <?= h($act) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary border font-monospace" style="font-size: 0.74rem;">
                                    <?= h($log['module']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="sidebar-avatar" style="width: 28px; height: 28px; font-size: 0.75rem;">
                                        <?= h($uInitial) ?>
                                    </div>
                                    <strong class="text-dark small">@<?= h($log['username']) ?></strong>
                                </div>
                            </td>
                            <td>
                                <div class="text-dark" style="font-size: 0.88rem; line-height: 1.35;">
                                    <?= h($log['description']) ?>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($log['record_id'])): ?>
                                    <span class="badge bg-light text-dark border">#<?= (int)$log['record_id'] ?></span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small class="text-muted font-monospace"><?= h($log['ip_address'] ?: '—') ?></small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Footer -->
    <?php if ($totalPages > 1): ?>
        <div class="card-panel-footer d-flex align-items-center justify-content-between p-3 border-top">
            <span class="text-muted small">
                Showing <?= count($logsList) ?> of <?= $filteredTotal ?> logs (Page <?= $pageNo ?> of <?= $totalPages ?>)
            </span>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $pageNo <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?p=<?= $pageNo - 1 ?>&q=<?= urlencode($search) ?>&action_type=<?= urlencode($filterAct) ?>&module=<?= urlencode($filterMod) ?>&user=<?= urlencode($filterUser) ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i === $pageNo ? 'active' : '' ?>">
                            <a class="page-link" href="?p=<?= $i ?>&q=<?= urlencode($search) ?>&action_type=<?= urlencode($filterAct) ?>&module=<?= urlencode($filterMod) ?>&user=<?= urlencode($filterUser) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $pageNo >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?p=<?= $pageNo + 1 ?>&q=<?= urlencode($search) ?>&action_type=<?= urlencode($filterAct) ?>&module=<?= urlencode($filterMod) ?>&user=<?= urlencode($filterUser) ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- Clear Logs Confirmation Modal (Admin only) -->
<?php if (is_admin()): ?>
<div class="modal fade" id="clearLogsModal" tabindex="-1" aria-labelledby="clearLogsModalTitle" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" action="logs.php" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="clear_logs">

            <div class="modal-header">
                <h5 class="modal-title fw-bold text-danger" id="clearLogsModalTitle"><i class="bi bi-exclamation-triangle-fill me-2"></i>Clear All Activity Logs</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to permanently clear all activity and audit log entries?</p>
                <div class="alert alert-warning small mb-0">
                    <i class="bi bi-shield-exclamation me-1"></i>
                    <strong>Audit Notice:</strong> This action cannot be reversed. A security record noting this audit trail purge will be created automatically.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger"><i class="bi bi-trash3-fill me-1"></i> Yes, Purge Audit Logs</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
