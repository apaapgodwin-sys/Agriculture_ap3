<?php
declare(strict_types=1);
$pageTitle = 'My Profile & User Settings';
require_once __DIR__ . '/config/config.php';
require_login();

$sessionUser = current_user();
$userId = (int)($sessionUser['user_id'] ?? 0);

// Fetch fresh user record from database
$userQuery = Database::query('users', '*', ['user_id' => $userId]);
$userData = !empty($userQuery) ? $userQuery[0] : [
    'user_id' => $userId,
    'username' => $sessionUser['username'] ?? 'user',
    'email' => $sessionUser['email'] ?? '',
    'full_name' => $sessionUser['full_name'] ?? 'User',
    'role' => $sessionUser['role'] ?? 'staff',
    'status' => 'active',
    'created_at' => date('Y-m-d H:i:s')
];

// Handle Profile Updates and Password Changes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    // ── Update Profile Information ──────────────────────────────────
    if ($action === 'update_profile') {
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $email    = trim((string)($_POST['email'] ?? ''));
        $username = trim((string)($_POST['username'] ?? ''));

        if ($fullName === '' || $email === '' || $username === '') {
            flash('error', 'All profile fields (Full Name, Email, and Username) are required.');
            redirect('profile.php');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Please enter a valid email address.');
            redirect('profile.php');
        }

        // Check for duplicate username or email with other users
        $dup = Database::rawQuery(
            "SELECT user_id FROM users WHERE (username = :u OR email = :e) AND user_id != :id",
            [':u' => $username, ':e' => $email, ':id' => $userId]
        );
        if (!empty($dup)) {
            flash('error', 'That username or email address is already taken by another account.');
            redirect('profile.php');
        }

        $updateData = [
            'full_name' => $fullName,
            'email'     => $email,
            'username'  => $username,
            'updated_at'=> date('Y-m-d H:i:s')
        ];

        Database::update('users', 'user_id', $userId, $updateData);

        // Update active session
        $_SESSION['user']['full_name'] = $fullName;
        $_SESSION['user']['email']     = $email;
        $_SESSION['user']['username']  = $username;

        log_activity('UPDATE', 'PROFILE', "User updated profile details (Name: '{$fullName}', Email: '{$email}').", $userId);

        flash('success', 'Your personal profile details have been successfully updated.');
        redirect('profile.php');
    }

    // ── Change Password ─────────────────────────────────────────────
    if ($action === 'change_password') {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword     = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            flash('error', 'Please complete all password fields.');
            redirect('profile.php?tab=security');
        }

        // Verify current password
        if (empty($userData['password_hash']) || !password_verify($currentPassword, $userData['password_hash'])) {
            flash('error', 'The current password you entered is incorrect.');
            redirect('profile.php?tab=security');
        }

        if (strlen($newPassword) < 6) {
            flash('error', 'New password must be at least 6 characters long.');
            redirect('profile.php?tab=security');
        }

        if ($newPassword !== $confirmPassword) {
            flash('error', 'New password and confirmation password do not match.');
            redirect('profile.php?tab=security');
        }

        $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
        Database::update('users', 'user_id', $userId, [
            'password_hash' => $hashed,
            'updated_at'    => date('Y-m-d H:i:s')
        ]);

        log_activity('SECURITY', 'PROFILE', "User updated their account password.", $userId);

        flash('success', 'Password updated successfully! Please keep your new credentials safe.');
        redirect('profile.php?tab=security');
    }
}

// Fetch general system statistics for the user audit panel
$farmerCount   = (int)(Database::rawQuery("SELECT COUNT(*) as c FROM farmer")[0]['c'] ?? 0);
$farmCount     = (int)(Database::rawQuery("SELECT COUNT(*) as c FROM farm WHERE deleted_at IS NULL")[0]['c'] ?? 0);
$plantingCount = (int)(Database::rawQuery("SELECT COUNT(*) as c FROM planting")[0]['c'] ?? 0);
$harvestCount  = (int)(Database::rawQuery("SELECT COUNT(*) as c FROM harvest")[0]['c'] ?? 0);
$myRecentLogs  = Database::rawQuery("SELECT * FROM activity_logs WHERE username = :u ORDER BY log_id DESC LIMIT 5", [':u' => $userData['username']]);

$activeTab = $_GET['tab'] ?? 'profile';

// Build initials for avatar
$nameParts = array_filter(explode(' ', $userData['full_name'] ?? 'User'));
$initials = '';
foreach ($nameParts as $part) { $initials .= strtoupper($part[0]); }
$initials = substr($initials, 0, 2) ?: 'U';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Header & Breadcrumbs -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-0 text-dark">User Account & Settings Interface</h4>
        <small class="text-muted">Manage your personal credentials, system authorization, and preferences.</small>
    </div>
    <div class="d-flex gap-2">
        <a href="app.php" class="btn btn-outline-success">
            <i class="bi bi-phone-fill me-1"></i> Open Field User App
        </a>
        <a href="dashboard.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: User Profile Card -->
    <div class="col-lg-4 col-xl-3">
        <div class="card-panel text-center p-4 h-100">
            <!-- Large Gradient Avatar -->
            <div class="profile-avatar-large mx-auto mb-3 shadow">
                <?= h($initials) ?>
            </div>

            <h5 class="fw-bold mb-1 text-dark"><?= h($userData['full_name']) ?></h5>
            <p class="text-muted small mb-2">@<?= h($userData['username']) ?></p>

            <div class="mb-3">
                <span class="user-badge-role <?= ($userData['role'] ?? '') === 'admin' ? 'bg-danger text-white' : 'bg-success text-white' ?> px-3 py-1">
                    <i class="bi bi-shield-check me-1"></i> <?= h(strtoupper($userData['role'] ?? 'staff')) ?>
                </span>
                <span class="badge bg-success-subtle text-success border ms-1">
                    <i class="bi bi-check-circle-fill me-1"></i> <?= h(ucfirst($userData['status'] ?? 'active')) ?>
                </span>
            </div>

            <hr class="my-3">

            <div class="text-start small">
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">User ID:</span>
                    <span class="fw-semibold">#<?= (int)$userData['user_id'] ?></span>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Email:</span>
                    <span class="fw-semibold text-truncate ms-2" style="max-width: 160px;" title="<?= h($userData['email']) ?>">
                        <?= h($userData['email']) ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Member Since:</span>
                    <span class="fw-semibold"><?= format_date($userData['created_at'] ?? null) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Storage Engine:</span>
                    <span class="fw-semibold <?= Database::isSupabaseReady() ? 'text-success' : 'text-primary' ?>">
                        <?= Database::isSupabaseReady() ? 'Cloud Supabase' : 'Local SQLite' ?>
                    </span>
                </div>
            </div>

            <div class="mt-4 pt-2">
                <a href="logout.php" class="btn btn-outline-danger w-100 btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i> Sign Out of Account
                </a>
            </div>
        </div>
    </div>

    <!-- Right Column: Tabs for Settings & Information -->
    <div class="col-lg-8 col-xl-9">
        <div class="card-panel p-0 overflow-hidden">
            <!-- Navigation Tabs -->
            <div class="card-panel-header p-0 border-bottom">
                <ul class="nav nav-tabs border-0 px-3 pt-2" id="profileTabList" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?= $activeTab === 'profile' ? 'active' : '' ?>" href="profile.php?tab=profile">
                            <i class="bi bi-person-fill me-1"></i> Personal Profile
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?= $activeTab === 'security' ? 'active' : '' ?>" href="profile.php?tab=security">
                            <i class="bi bi-key-fill me-1"></i> Security & Password
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?= $activeTab === 'permissions' ? 'active' : '' ?>" href="profile.php?tab=permissions">
                            <i class="bi bi-shield-lock-fill me-1"></i> Role & Permissions
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?= $activeTab === 'audit' ? 'active' : '' ?>" href="profile.php?tab=audit">
                            <i class="bi bi-activity me-1"></i> System Activity
                        </a>
                    </li>
                </ul>
            </div>

            <div class="card-panel-body p-4">
                <!-- Tab 1: Personal Profile Details -->
                <?php if ($activeTab === 'profile'): ?>
                    <div class="tab-pane-content">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-person-lines-fill text-success me-2"></i>Edit Personal Information</h5>
                            <small class="text-muted">Changes will update your system display and credentials immediately.</small>
                        </div>

                        <form method="post" action="profile.php">
                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                            <input type="hidden" name="action" value="update_profile">

                            <div class="row g-3 mb-3">
                                <div class="col-md-12">
                                    <label class="form-label small fw-semibold">Full Legal Name <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                                        <input type="text" name="full_name" class="form-control" value="<?= h($userData['full_name']) ?>" required>
                                    </div>
                                    <div class="form-text small">Your name as displayed in official reports and system audit trails.</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Username <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-at"></i></span>
                                        <input type="text" name="username" class="form-control" value="<?= h($userData['username']) ?>" required>
                                    </div>
                                    <div class="form-text small">Used for logging into AgriTrack.</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Email Address <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                        <input type="email" name="email" class="form-control" value="<?= h($userData['email']) ?>" required>
                                    </div>
                                    <div class="form-text small">Primary contact for notifications and system alerts.</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">System Role</label>
                                    <input type="text" class="form-control bg-light" value="<?= h(strtoupper($userData['role'])) ?>" readonly>
                                    <div class="form-text small">Assigned by System Administrator.</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Account Status</label>
                                    <input type="text" class="form-control bg-light" value="<?= h(ucfirst($userData['status'])) ?>" readonly>
                                    <div class="form-text small">Verified active user.</div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                                <button type="reset" class="btn btn-outline-secondary">Reset Changes</button>
                                <button type="submit" class="btn btn-agri px-4">
                                    <i class="bi bi-check2-circle me-1"></i> Save Profile Details
                                </button>
                            </div>
                        </form>
                    </div>

                <!-- Tab 2: Security & Password -->
                <?php elseif ($activeTab === 'security'): ?>
                    <div class="tab-pane-content">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-shield-lock-fill text-warning me-2"></i>Change Account Password</h5>
                            <small class="text-muted">Passwords are secured using one-way cryptographic BCRYPT hashing.</small>
                        </div>

                        <form method="post" action="profile.php">
                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                            <input type="hidden" name="action" value="change_password">

                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Current Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" name="current_password" id="curPwd" class="form-control" placeholder="Enter current password" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePwdVisibility('curPwd')">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">New Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-key"></i></span>
                                        <input type="password" name="new_password" id="newPwd" class="form-control" placeholder="Minimum 6 characters" required minlength="6" oninput="checkPwdStrength(this.value)">
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePwdVisibility('newPwd')">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <div class="progress mt-2" style="height: 4px;">
                                        <div id="pwdStrengthBar" class="progress-bar bg-danger" role="progressbar" style="width: 0%"></div>
                                    </div>
                                    <small id="pwdStrengthText" class="text-muted" style="font-size: 0.72rem;">Enter new password</small>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Confirm New Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-check-all"></i></span>
                                        <input type="password" name="confirm_password" id="cfmPwd" class="form-control" placeholder="Re-type new password" required minlength="6">
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePwdVisibility('cfmPwd')">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <div class="form-text small">Both new passwords must match exactly.</div>
                                </div>
                            </div>

                            <div class="alert alert-info py-2 small mb-3">
                                <i class="bi bi-info-circle-fill me-1"></i>
                                <strong>Security Tip:</strong> Choose a strong password containing a combination of letters, numbers, and symbols.
                            </div>

                            <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                                <button type="reset" class="btn btn-outline-secondary">Clear</button>
                                <button type="submit" class="btn btn-agri px-4">
                                    <i class="bi bi-lock-fill me-1"></i> Update Password
                                </button>
                            </div>
                        </form>
                    </div>

                <!-- Tab 3: Role & Permissions Matrix -->
                <?php elseif ($activeTab === 'permissions'): ?>
                    <div class="tab-pane-content">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-shield-check text-primary me-2"></i>Role Authorization Matrix</h5>
                            <span class="user-badge-role <?= is_admin() ? 'bg-danger text-white' : 'bg-success text-white' ?>">
                                Current: <?= h(strtoupper($userData['role'])) ?>
                            </span>
                        </div>

                        <p class="text-muted small">AgriTrack implements strict Role-Based Access Control (RBAC) across all system modules.</p>

                        <div class="table-responsive">
                            <table class="table table-custom align-middle">
                                <thead>
                                    <tr>
                                        <th>Module / Operation</th>
                                        <th>Administrator</th>
                                        <th>Staff (Field Officer)</th>
                                        <th>Your Authorization</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>Farmers Registry (View & Search)</strong></td>
                                        <td><i class="bi bi-check-circle-fill text-success"></i> Full</td>
                                        <td><i class="bi bi-check-circle-fill text-success"></i> Full</td>
                                        <td><span class="badge bg-success">Authorized</span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Farms & Land Parcels (Create / Edit)</strong></td>
                                        <td><i class="bi bi-check-circle-fill text-success"></i> Full</td>
                                        <td><i class="bi bi-check-circle-fill text-success"></i> Full</td>
                                        <td><span class="badge bg-success">Authorized</span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Soft Deletes & Trash Recovery</strong></td>
                                        <td><i class="bi bi-check-circle-fill text-success"></i> Restore & Purge</td>
                                        <td><i class="bi bi-x-circle-fill text-danger"></i> Restricted</td>
                                        <td>
                                            <span class="badge <?= is_admin() ? 'bg-success' : 'bg-secondary' ?>">
                                                <?= is_admin() ? 'Authorized' : 'Admin Only' ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Plantings & Harvest Yields</strong></td>
                                        <td><i class="bi bi-check-circle-fill text-success"></i> Full Access</td>
                                        <td><i class="bi bi-check-circle-fill text-success"></i> Full Access</td>
                                        <td><span class="badge bg-success">Authorized</span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Reporting & CSV Export</strong></td>
                                        <td><i class="bi bi-check-circle-fill text-success"></i> Export & Print</td>
                                        <td><i class="bi bi-check-circle-fill text-success"></i> Export & Print</td>
                                        <td><span class="badge bg-success">Authorized</span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>User Management (Create & Edit Accounts)</strong></td>
                                        <td><i class="bi bi-check-circle-fill text-success"></i> Full Admin</td>
                                        <td><i class="bi bi-x-circle-fill text-danger"></i> No Access</td>
                                        <td>
                                            <span class="badge <?= is_admin() ? 'bg-success' : 'bg-secondary' ?>">
                                                <?= is_admin() ? 'Authorized' : 'Admin Only' ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Database Setup & Cloud Sync</strong></td>
                                        <td><i class="bi bi-check-circle-fill text-success"></i> Full Diagnostic</td>
                                        <td><i class="bi bi-info-circle-fill text-info"></i> View Diagnostic</td>
                                        <td><span class="badge bg-success">Authorized</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <!-- Tab 4: System Activity & Audit -->
                <?php elseif ($activeTab === 'audit'): ?>
                    <div class="tab-pane-content">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-activity text-info me-2"></i>System Overview & Activity</h5>
                            <small class="text-muted">Live audit records managed in the current database instance.</small>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-sm-6 col-md-3">
                                <div class="card-panel p-3 text-center border">
                                    <div class="display-6 fw-bold text-success"><?= $farmerCount ?></div>
                                    <div class="text-muted small">Registered Farmers</div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <div class="card-panel p-3 text-center border">
                                    <div class="display-6 fw-bold text-primary"><?= $farmCount ?></div>
                                    <div class="text-muted small">Active Farms</div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <div class="card-panel p-3 text-center border">
                                    <div class="display-6 fw-bold text-warning"><?= $plantingCount ?></div>
                                    <div class="text-muted small">Planting Cycles</div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <div class="card-panel p-3 text-center border">
                                    <div class="display-6 fw-bold text-info"><?= $harvestCount ?></div>
                                    <div class="text-muted small">Harvest Records</div>
                                </div>
                            </div>
                        </div>

                        <div class="card-panel p-3 border mb-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-clock-history me-1 text-success"></i>My Recent Actions</h6>
                                <a href="logs.php?user=<?= urlencode($userData['username']) ?>" class="btn btn-sm btn-outline-success">View All My Logs &rarr;</a>
                            </div>
                            <?php if (empty($myRecentLogs)): ?>
                                <p class="text-muted small mb-0">No actions recorded for your account yet.</p>
                            <?php else: ?>
                                <ul class="list-group list-group-flush small">
                                    <?php foreach ($myRecentLogs as $ml): ?>
                                        <li class="list-group-item bg-transparent px-0 py-2 d-flex justify-content-between align-items-start">
                                            <div>
                                                <span class="badge bg-secondary-subtle text-secondary me-1"><?= h($ml['action']) ?></span>
                                                <span class="text-dark"><?= h($ml['description']) ?></span>
                                            </div>
                                            <span class="text-muted text-nowrap ms-2" style="font-size: 0.75rem;"><?= time_ago($ml['created_at']) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>

                        <div class="card-panel p-3 border">
                            <h6 class="fw-bold mb-2 text-dark"><i class="bi bi-shield-lock me-1"></i>Active Session Information</h6>
                            <ul class="list-unstyled mb-0 small">
                                <li class="py-1"><strong>Authenticated As:</strong> <?= h($userData['full_name']) ?> (<code><?= h($userData['username']) ?></code>)</li>
                                <li class="py-1"><strong>Session Initiated:</strong> <?= date('F j, Y, g:i A', $sessionUser['logged_in_at'] ?? time()) ?></li>
                                <li class="py-1"><strong>IP / Host Client:</strong> <?= h($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1') ?></li>
                                <li class="py-1"><strong>PHP Runtime:</strong> PHP <?= PHP_VERSION ?> on Apache (XAMPP)</li>
                                <li class="py-1"><strong>Active Database Backend:</strong> <?= Database::isSupabaseReady() ? 'Supabase PostgreSQL Cloud (Direct REST API)' : 'Embedded Normalized SQLite Fallback' ?></li>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function togglePwdVisibility(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    input.type = input.type === 'password' ? 'text' : 'password';
}

function checkPwdStrength(pwd) {
    const bar = document.getElementById('pwdStrengthBar');
    const text = document.getElementById('pwdStrengthText');
    if (!bar || !text) return;

    let score = 0;
    if (pwd.length >= 6) score += 25;
    if (pwd.length >= 10) score += 25;
    if (/[A-Z]/.test(pwd) && /[a-z]/.test(pwd)) score += 25;
    if (/[0-9]/.test(pwd) || /[^A-Za-z0-9]/.test(pwd)) score += 25;

    bar.style.width = score + '%';
    if (score <= 25) {
        bar.className = 'progress-bar bg-danger';
        text.textContent = 'Weak password';
        text.className = 'text-danger small';
    } else if (score <= 75) {
        bar.className = 'progress-bar bg-warning';
        text.textContent = 'Medium strength';
        text.className = 'text-warning small';
    } else {
        bar.className = 'progress-bar bg-success';
        text.textContent = 'Strong password';
        text.className = 'text-success small';
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
