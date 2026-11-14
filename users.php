<?php
declare(strict_types=1);
$pageTitle = 'User Management';
require_once __DIR__ . '/config/config.php';
require_admin();

// Handle POST actions: Save (Create/Update), Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save') {
        $id = (int)($_POST['user_id'] ?? 0);
        $username = trim((string)($_POST['username'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $role = trim((string)($_POST['role'] ?? 'staff'));
        $status = trim((string)($_POST['status'] ?? 'active'));
        $password = (string)($_POST['password'] ?? '');

        // Validation
        if ($username === '' || $email === '' || $fullName === '') {
            flash('error', 'Username, email, and full name are required.');
            redirect('users.php');
        }
        if (!in_array($role, ['admin', 'staff'], true)) {
            $role = 'staff';
        }
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        // Check duplicates for username/email
        $dupCheck = Database::rawQuery("SELECT user_id FROM users WHERE (username = :u OR email = :e) AND user_id != :id", [
            ':u' => $username,
            ':e' => $email,
            ':id' => $id
        ]);
        if (!empty($dupCheck)) {
            flash('error', 'That username or email address is already in use.');
            redirect('users.php');
        }

        $data = [
            'username' => $username,
            'email' => $email,
            'full_name' => $fullName,
            'role' => $role,
            'status' => $status
        ];

        if ($id > 0) {
            if ($password !== '') {
                if (strlen($password) < 6) {
                    flash('error', 'Password must be at least 6 characters long.');
                    redirect('users.php');
                }
                $data['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
            }
            Database::update('users', 'user_id', $id, $data);
            log_activity('UPDATE', 'USER', "Updated user account '{$username}' (#{$id}, Role: " . strtoupper($role) . ", Status: " . ucfirst($status) . ").", $id);
            flash('success', "User '{$username}' updated successfully.");
        } else {
            if (strlen($password) < 6) {
                flash('error', 'Password is required and must be at least 6 characters long.');
                redirect('users.php');
            }
            $data['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
            $res = Database::insert('users', $data);
            $newId = (int)($res['id'] ?? $res['user_id'] ?? 0);
            log_activity('CREATE', 'USER', "Created new user account '{$username}' ({$fullName}, Role: " . strtoupper($role) . ").", $newId);
            flash('success', "User '{$username}' created successfully.");
        }
        redirect('users.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['user_id'] ?? 0);
        $currentUserId = (int)(current_user()['user_id'] ?? 0);

        if ($id <= 0) {
            flash('error', 'Invalid user.');
            redirect('users.php');
        }
        if ($id === $currentUserId) {
            flash('error', 'You cannot delete your own active administrator account.');
            redirect('users.php');
        }

        $uRow = Database::query('users', 'username, full_name', ['user_id' => $id])[0] ?? [];
        $uName = $uRow['username'] ?? "User #{$id}";

        Database::delete('users', 'user_id', $id);
        log_activity('DELETE', 'USER', "Deleted user account '{$uName}' (#{$id}).", $id);
        flash('success', 'User account deleted successfully.');
        redirect('users.php');
    }
}

// Fetch all users
$usersList = Database::rawQuery("SELECT * FROM users ORDER BY user_id ASC");

require_once __DIR__ . '/includes/header.php';
?>

<!-- Header & Actions -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0 text-dark">System User Management</h4>
        <small class="text-muted">Administrator and field staff credentials, role-based authorization, and account statuses.</small>
    </div>
    <button class="btn btn-agri shadow-sm" data-bs-toggle="modal" data-bs-target="#userFormModal" onclick="prepareAddModal()">
        <i class="bi bi-person-plus-fill me-1"></i> Create User Account
    </button>
</div>

<!-- Data Table -->
<div class="card-panel">
    <div class="table-responsive">
        <table class="table table-custom align-middle">
            <thead>
                <tr>
                    <th style="width: 60px;">ID</th>
                    <th>Full Name</th>
                    <th>Username</th>
                    <th>Email Address</th>
                    <th>System Role</th>
                    <th>Status</th>
                    <th>Registered At</th>
                    <th class="text-end" style="width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usersList as $u): ?>
                    <?php
                        $uInit = strtoupper(substr($u['full_name'] ?: $u['username'], 0, 1));
                    ?>
                    <tr>
                        <td class="fw-bold text-secondary">#<?= (int)$u['user_id'] ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="sidebar-avatar" style="width: 32px; height: 32px; font-size: 0.78rem;">
                                    <?= h($uInit) ?>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark"><?= h($u['full_name']) ?></div>
                                    <small class="text-muted d-block">@<?= h($u['username']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td><code><?= h($u['username']) ?></code></td>
                        <td><?= h($u['email']) ?></td>
                        <td>
                            <span class="user-badge-role <?= $u['role'] === 'admin' ? 'bg-danger text-white' : 'bg-success text-white' ?>">
                                <?= h(strtoupper($u['role'])) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?= $u['status'] === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary' ?>">
                                <?= h(ucfirst($u['status'])) ?>
                            </span>
                        </td>
                        <td><?= format_date($u['created_at']) ?></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="openEditModal(<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>)" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php if ((int)$u['user_id'] !== (int)(current_user()['user_id'] ?? 0)): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?= $u['user_id'] ?>, '<?= h($u['username']) ?>')" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add / Edit Modal -->
<div class="modal fade" id="userFormModal" tabindex="-1" aria-labelledby="userModalTitle" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" action="users.php" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="user_id" id="modal_user_id" value="0">

            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="userModalTitle">Create User Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="modal_full_name" class="form-label small fw-semibold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" id="modal_full_name" class="form-control" placeholder="e.g. Maria Santos" required>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label for="modal_username" class="form-label small fw-semibold">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" id="modal_username" class="form-control" placeholder="e.g. msantos" required>
                    </div>
                    <div class="col-6">
                        <label for="modal_email" class="form-label small fw-semibold">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="modal_email" class="form-control" placeholder="e.g. msantos@example.com" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="modal_password" class="form-label small fw-semibold">Password <span id="pwdRequiredStar" class="text-danger">*</span></label>
                    <input type="password" name="password" id="modal_password" class="form-control" placeholder="••••••••">
                    <div class="form-text small" id="pwdHelpText">Minimum 6 characters.</div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label for="modal_role" class="form-label small fw-semibold">User Role <span class="text-danger">*</span></label>
                        <select name="role" id="modal_role" class="form-select" required>
                            <option value="staff">Staff (Field Officer)</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label for="modal_status" class="form-label small fw-semibold">Account Status <span class="text-danger">*</span></label>
                        <select name="status" id="modal_status" class="form-select" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-agri"><i class="bi bi-save me-1"></i> Save User</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Form (Hidden) -->
<form id="deleteForm" method="post" action="users.php" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="user_id" id="delete_user_id" value="0">
</form>

<script>
function prepareAddModal() {
    document.getElementById('userModalTitle').textContent = 'Create User Account';
    document.getElementById('modal_user_id').value = '0';
    document.getElementById('modal_full_name').value = '';
    document.getElementById('modal_username').value = '';
    document.getElementById('modal_email').value = '';
    document.getElementById('modal_password').value = '';
    document.getElementById('modal_password').required = true;
    document.getElementById('pwdRequiredStar').style.display = 'inline';
    document.getElementById('pwdHelpText').textContent = 'Required for new accounts. Minimum 6 characters.';
    document.getElementById('modal_role').value = 'staff';
    document.getElementById('modal_status').value = 'active';
}

function openEditModal(u) {
    document.getElementById('userModalTitle').textContent = 'Edit User: ' + u.username;
    document.getElementById('modal_user_id').value = u.user_id;
    document.getElementById('modal_full_name').value = u.full_name || '';
    document.getElementById('modal_username').value = u.username || '';
    document.getElementById('modal_email').value = u.email || '';
    document.getElementById('modal_password').value = '';
    document.getElementById('modal_password').required = false;
    document.getElementById('pwdRequiredStar').style.display = 'none';
    document.getElementById('pwdHelpText').textContent = 'Leave blank to keep existing password.';
    document.getElementById('modal_role').value = u.role || 'staff';
    document.getElementById('modal_status').value = u.status || 'active';
    new bootstrap.Modal(document.getElementById('userFormModal')).show();
}

function confirmDelete(id, username) {
    if (confirm(`Are you sure you want to permanently delete user account '${username}'?`)) {
        document.getElementById('delete_user_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
