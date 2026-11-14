<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_login();

$currentUser = current_user();
$activePage = basename($_SERVER['PHP_SELF'], '.php');
$isSupabase = Database::isSupabaseReady();
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle ?? 'AgriTrack') ?> | Agriculture Information System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom Styles -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- PWA Web App Manifest & Mobile Capability -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#15803d">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
    <!-- Prevent dark-mode flash: apply saved theme before first paint -->
    <script>
        (function(){
            var t = localStorage.getItem('agritrack_theme') || 'light';
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
</head>
<body>
<div class="app-wrapper">
    <!-- Sidebar -->
    <aside class="app-sidebar">
        <a href="dashboard.php" class="sidebar-brand">
            <div class="brand-icon-box">
                <i class="bi bi-flower1"></i>
            </div>
            <div>
                <div style="line-height: 1.1;">AgriTrack</div>
                <small style="font-size: 0.68rem; color: #94a3b8; font-weight: normal; letter-spacing: 0.05em;">AGRICULTURE IS</small>
            </div>
        </a>

        <div class="sidebar-menu">
            <div class="sidebar-heading">Core Modules</div>
            <a href="dashboard.php" class="nav-item-link <?= $activePage === 'dashboard' ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2-fill"></i>
                <span>Dashboard</span>
            </a>
            <a href="farmers.php" class="nav-item-link <?= $activePage === 'farmers' ? 'active' : '' ?>">
                <i class="bi bi-person-badge-fill"></i>
                <span>Farmers</span>
            </a>
            <a href="farms.php" class="nav-item-link <?= $activePage === 'farms' ? 'active' : '' ?>">
                <i class="bi bi-geo-alt-fill"></i>
                <span>Farms</span>
            </a>
            <a href="crops.php" class="nav-item-link <?= $activePage === 'crops' ? 'active' : '' ?>">
                <i class="bi bi-tree-fill"></i>
                <span>Crops</span>
            </a>
            <a href="plantings.php" class="nav-item-link <?= $activePage === 'plantings' ? 'active' : '' ?>">
                <i class="bi bi-calendar2-range-fill"></i>
                <span>Planting Records</span>
            </a>
            <a href="harvests.php" class="nav-item-link <?= $activePage === 'harvests' ? 'active' : '' ?>">
                <i class="bi bi-basket3-fill"></i>
                <span>Harvest Records</span>
            </a>

            <div class="sidebar-heading mt-2">Field & User App</div>
            <a href="app.php" class="nav-item-link <?= $activePage === 'app' ? 'active' : '' ?>">
                <i class="bi bi-phone-fill text-success"></i>
                <span>Field User App</span>
                <span class="badge bg-success ms-auto" style="font-size: 0.65rem;">NEW</span>
            </a>
            <a href="profile.php" class="nav-item-link <?= $activePage === 'profile' ? 'active' : '' ?>">
                <i class="bi bi-person-gear"></i>
                <span>User Account & UI</span>
            </a>

            <div class="sidebar-heading mt-2">Analytics & Management</div>
            <a href="reports.php" class="nav-item-link <?= $activePage === 'reports' ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-bar-graph-fill"></i>
                <span>Reports & Valuation</span>
            </a>
            <a href="logs.php" class="nav-item-link <?= $activePage === 'logs' ? 'active' : '' ?>">
                <i class="bi bi-clock-history"></i>
                <span>Activity & Audit Logs</span>
            </a>

            <?php if (is_admin()): ?>
            <a href="users.php" class="nav-item-link <?= $activePage === 'users' ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i>
                <span>User Management</span>
            </a>
            <?php endif; ?>

            <a href="setup.php" class="nav-item-link <?= $activePage === 'setup' ? 'active' : '' ?>">
                <i class="bi bi-database-check"></i>
                <span>Database & Sync</span>
            </a>
        </div>

        <div class="sidebar-footer">
            <?php
                $fullName   = $currentUser['full_name'] ?? 'User';
                $email      = $currentUser['email'] ?? '';
                $role       = strtoupper($currentUser['role'] ?? 'staff');
                // Build initials: up to 2 letters from name parts
                $nameParts  = array_filter(explode(' ', $fullName));
                $initials   = '';
                foreach ($nameParts as $part) { $initials .= strtoupper($part[0]); }
                $initials   = substr($initials, 0, 2) ?: 'U';
            ?>
            <!-- Clickable Profile Row -->
            <a href="profile.php" class="d-flex align-items-center gap-2 mb-2 text-decoration-none user-footer-link p-1 rounded" title="View & Edit My Profile">
                <!-- Avatar circle with gradient -->
                <div class="sidebar-avatar" title="<?= h($fullName) ?>">
                    <?= h($initials) ?>
                </div>
                <div class="text-truncate flex-grow-1" style="min-width:0;">
                    <div class="fw-semibold text-white user-name-link" style="font-size:0.88rem; line-height:1.2; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= h($fullName) ?></div>
                    <div style="font-size: 0.69rem; color: #94a3b8; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= h($email) ?></div>
                </div>
                <span class="user-badge-role <?= is_admin() ? 'bg-danger text-white' : 'bg-success text-white' ?>" style="flex-shrink:0;">
                    <?= h($role) ?>
                </span>
            </a>
            <a href="logout.php" class="btn btn-sm btn-outline-light w-100">
                <i class="bi bi-box-arrow-right me-1"></i> Sign Out
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="app-main">
        <!-- Top Bar -->
        <header class="app-topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-outline-secondary btn-sm d-lg-none" id="sidebarToggleBtn" type="button">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <div>
                    <h1 class="page-title"><?= h($pageTitle ?? 'Agriculture Information System') ?></h1>
                    <div class="text-muted small d-none d-sm-block">Cebu Technological University · AP3 Midterm Project</div>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <a href="app.php" class="btn btn-sm btn-outline-success d-none d-md-inline-flex align-items-center gap-1 <?= $activePage === 'app' ? 'active' : '' ?>" title="Open Field User App">
                    <i class="bi bi-phone-fill"></i> User App
                </a>
                <a href="profile.php" class="btn btn-sm btn-outline-secondary d-none d-sm-inline-flex align-items-center gap-1 <?= $activePage === 'profile' ? 'active' : '' ?>" title="User Profile & Settings">
                    <i class="bi bi-person-circle"></i> Profile
                </a>
                <?php if ($isSupabase): ?>
                    <span class="status-indicator-pill status-online" title="Direct connection to Supabase PostgreSQL active">
                        <span class="pulse-dot"></span> Supabase Connected
                    </span>
                <?php else: ?>
                    <span class="status-indicator-pill status-offline" title="Operating with local SQLite storage. Run schema_3nf.sql on Supabase to enable cloud sync.">
                        <span class="pulse-dot"></span> Local Storage Active
                    </span>
                <?php endif; ?>
                <!-- Dark / Light Mode Toggle -->
                <button class="theme-toggle-btn" id="themeToggleBtn" title="Toggle dark/light mode" onclick="toggleTheme()">
                    <i class="bi bi-moon-fill" id="themeIcon"></i>
                </button>
            </div>
        </header>

        <!-- Flash messages -->
        <div class="page-container pb-0">
            <?php foreach (get_flashes() as $f): ?>
                <div class="alert alert-<?= $f['type'] === 'error' ? 'danger' : ($f['type'] === 'warning' ? 'warning' : 'success') ?> alert-dismissible fade show shadow-sm" role="alert">
                    <i class="bi bi-<?= $f['type'] === 'error' ? 'exclamation-triangle-fill' : ($f['type'] === 'warning' ? 'exclamation-circle-fill' : 'check-circle-fill') ?> me-2"></i>
                    <?= h($f['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Page Body Content Begins in page views -->
        <div class="page-container">
