<?php
declare(strict_types=1);

/**
 * Authentication & Access Control Helpers
 */

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool {
    return !empty($_SESSION['user']);
}

function is_admin(): bool {
    return is_logged_in() && (($_SESSION['user']['role'] ?? '') === 'admin');
}

function require_login(): void {
    if (!is_logged_in()) {
        flash('warning', 'Please sign in to access the Agriculture Information System.');
        redirect('login.php');
    }
}

function require_admin(): void {
    require_login();
    if (!is_admin()) {
        flash('error', 'Access denied. Administrator privileges are required for this action.');
        redirect('dashboard.php');
    }
}

function attempt_login(string $identifier, string $password): bool {
    $identifier = trim($identifier);
    if ($identifier === '' || $password === '') {
        return false;
    }

    // Check users table in DB (Supabase or Local)
    $users = Database::query('users', '*', [
        'username' => $identifier
    ]);

    if (empty($users)) {
        // Also check by email
        $users = Database::query('users', '*', [
            'email' => $identifier
        ]);
    }

    if (!empty($users)) {
        $user = $users[0];
        if (password_verify($password, $user['password_hash'])) {
            if (($user['status'] ?? 'active') !== 'active') {
                flash('error', 'Your account has been deactivated. Please contact an administrator.');
                return false;
            }

            if (session_status() === PHP_SESSION_ACTIVE) {
                @session_regenerate_id(true);
            }
            $_SESSION['user'] = [
                'user_id' => (int)$user['user_id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'role' => $user['role'],
                'logged_in_at' => time()
            ];
            return true;
        }
    }

    return false;
}

function logout(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}
