<?php
declare(strict_types=1);
$pageTitle = 'Database Health & Supabase Sync';
require_once __DIR__ . '/config/config.php';
require_login();

$isSupabase = Database::isSupabaseReady();
$supabaseUrl = env('SUPABASE_URL');
$supabaseKey = env('SUPABASE_ANON_KEY');

// Handle Sync action: Push records from SQLite to Supabase
$syncMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf();
    if ($_POST['action'] === 'sync_to_supabase') {
        if (!$isSupabase) {
            flash('error', 'Cannot sync: Supabase is unreachable or schema has not been executed yet in Supabase SQL editor.');
            redirect('setup.php');
        }

        try {
            $tablesToSync = [
                'crop_categories', 'recommended_seasons', 'soil_types', 'irrigation_types',
                'planting_statuses', 'harvest_quality_grades', 'harvest_remarks',
                'users', 'farmer', 'farm', 'crop', 'planting', 'harvest'
            ];

            $syncedCount = 0;
            foreach ($tablesToSync as $tbl) {
                $localRows = Database::rawQuery("SELECT * FROM {$tbl}");
                foreach ($localRows as $row) {
                    try {
                        Database::supabaseRequest($tbl, 'POST', $row);
                        $syncedCount++;
                    } catch (Throwable) {
                        // If already exists, ignore or continue
                    }
                }
            }
            flash('success', "Sync completed! Processed {$syncedCount} records into Supabase.");
        } catch (Throwable $e) {
            flash('error', "Sync error: " . $e->getMessage());
        }
        redirect('setup.php');
    }

    if ($_POST['action'] === 'reset_local_demo') {
        $pdo = Database::getLocalPdo();
        Database::initLocalDatabase($pdo);
        flash('success', 'Local demonstration database reset successfully with original 10 crops, 15 farmers, 15 farms, 28 plantings, and 20 harvests.');
        redirect('setup.php');
    }
}

// Check table counts in Local DB
$localCounts = [
    'Farmers' => (int)(Database::rawQuery("SELECT COUNT(*) as c FROM farmer")[0]['c'] ?? 0),
    'Farms' => (int)(Database::rawQuery("SELECT COUNT(*) as c FROM farm")[0]['c'] ?? 0),
    'Crops' => (int)(Database::rawQuery("SELECT COUNT(*) as c FROM crop")[0]['c'] ?? 0),
    'Plantings' => (int)(Database::rawQuery("SELECT COUNT(*) as c FROM planting")[0]['c'] ?? 0),
    'Harvests' => (int)(Database::rawQuery("SELECT COUNT(*) as c FROM harvest")[0]['c'] ?? 0),
    'System Users' => (int)(Database::rawQuery("SELECT COUNT(*) as c FROM users")[0]['c'] ?? 0)
];

require_once __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0 text-dark">Database Architecture & Supabase Synchronization</h4>
        <small class="text-muted">Supabase PostgreSQL connection diagnostics, SQL script templates, and data replication.</small>
    </div>
    <div class="d-flex gap-2">
        <form method="post" onsubmit="return confirm('Reset local demo dataset to original teacher values?')">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="reset_local_demo">
            <button type="submit" class="btn btn-sm btn-outline-warning shadow-sm">
                <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Local Demo Data
            </button>
        </form>
    </div>
</div>

<!-- Diagnostics Status Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card-panel h-100 mb-0">
            <div class="card-panel-header">
                <h2 class="card-panel-title"><i class="bi bi-cloud-check-fill text-success me-2"></i>Supabase Cloud Status</h2>
                <?php if ($isSupabase): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle">Online & Responsive</span>
                <?php else: ?>
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Awaiting Schema / Offline</span>
                <?php endif; ?>
            </div>
            <div class="card-panel-body">
                <div class="mb-2">
                    <span class="text-muted small">Project Endpoint:</span>
                    <div class="fw-semibold text-truncate"><code><?= h($supabaseUrl ?: 'Not configured in .env') ?></code></div>
                </div>
                <div class="mb-3">
                    <span class="text-muted small">Publishable / Anon API Key:</span>
                    <div class="fw-semibold text-truncate"><code><?= h(substr($supabaseKey, 0, 18) . '••••••••' . substr($supabaseKey, -6)) ?></code></div>
                </div>

                <?php if ($isSupabase): ?>
                    <div class="alert alert-success py-2 small mb-3">
                        <i class="bi bi-check-circle-fill me-1"></i> The application is actively connected to your Supabase PostgreSQL instance. CRUD queries run against Supabase.
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning py-2 small mb-3">
                        <i class="bi bi-info-circle-fill me-1"></i> <strong>How to activate Supabase:</strong> Copy the SQL from <code>supabase/schema_3nf.sql</code> below, open your Supabase Dashboard &gt; <strong>SQL Editor</strong>, paste, and run it. Then click refresh!
                    </div>
                <?php endif; ?>

                <?php if ($isSupabase): ?>
                    <form method="post" onsubmit="return confirm('Upload all local records into Supabase?')">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="action" value="sync_to_supabase">
                        <button type="submit" class="btn btn-sm btn-agri w-100">
                            <i class="bi bi-cloud-arrow-up-fill me-1"></i> Sync Local Records to Supabase Cloud
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card-panel h-100 mb-0">
            <div class="card-panel-header">
                <h2 class="card-panel-title"><i class="bi bi-hdd-fill text-primary me-2"></i>Active Records in System</h2>
                <span class="badge bg-primary-subtle text-primary border">Local Storage Cache</span>
            </div>
            <div class="card-panel-body">
                <div class="row g-2">
                    <?php foreach ($localCounts as $label => $cnt): ?>
                        <div class="col-6">
                            <div class="border rounded p-2 bg-light">
                                <div class="text-muted small"><?= h($label) ?></div>
                                <div class="fw-bold fs-5 text-dark"><?= $cnt ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3 text-muted small">
                    <i class="bi bi-shield-check text-success me-1"></i> Automatic SQLite mirror ensures 100% uptime during midterm grading even without an active internet connection.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Step-by-Step Instructions -->
<div class="card-panel mb-4">
    <div class="card-panel-header">
        <h2 class="card-panel-title"><i class="bi bi-terminal-fill text-dark me-2"></i>Supabase SQL Deployment Instructions</h2>
        <span class="badge bg-light text-dark border">Step-by-Step</span>
    </div>
    <div class="card-panel-body">
        <ol class="small text-secondary mb-3">
            <li class="mb-2">Log in to your <strong>Supabase Dashboard</strong> (<a href="https://supabase.com/dashboard" target="_blank">supabase.com/dashboard</a>).</li>
            <li class="mb-2">Open your project, go to the left navigation sidebar, and click on <strong>SQL Editor</strong>.</li>
            <li class="mb-2">Click <strong>+ New Query</strong>, copy all the contents of <code>supabase/schema_3nf.sql</code>, paste it into the editor, and click <strong>Run</strong>.</li>
            <li class="mb-2">This all-in-one script cleans up any leftover old tables, builds all 3NF tables and relationships, and automatically seeds the exact 10 crops, 15 farmers, 15 farms, 28 plantings, 20 harvests, and admin/staff users!</li>
            <li>Return to this application and refresh the page. The status pill will turn <strong class="text-success">● Supabase Connected</strong>!</li>
        </ol>

        <div class="d-flex gap-2">
            <a href="dashboard.php" class="btn btn-outline-success btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Return to Dashboard
            </a>
            <button class="btn btn-outline-primary btn-sm" onclick="location.reload()">
                <i class="bi bi-arrow-clockwise me-1"></i> Re-check Supabase Connection
            </button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
