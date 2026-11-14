<?php
declare(strict_types=1);

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    $sessDir = __DIR__ . '/../data/sessions';
    if (!is_dir($sessDir)) {
        @mkdir($sessDir, 0777, true);
    }
    if (is_dir($sessDir)) {
        @session_save_path($sessDir);
    }
    @session_start();
}

// Function to load .env file
function loadEnvFile(string $filePath): void {
    if (!file_exists($filePath)) {
        return;
    }
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);
        if (strlen($val) >= 2 && (($val[0] === '"' && str_ends_with($val, '"')) || ($val[0] === "'" && str_ends_with($val, "'")))) {
            $val = substr($val, 1, -1);
        }
        $_ENV[$key] = $val;
        putenv("{$key}={$val}");
    }
}

// Load .env
loadEnvFile(__DIR__ . '/../.env');

// Helper to get environment variable
function env(string $key, string $default = ''): string {
    return (string)($_ENV[$key] ?? getenv($key) ?: $default);
}

// Security Helper: HTML escaping
function h(mixed $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// CSRF Token Management
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void {
    $submitted = (string)($_POST['csrf_token'] ?? '');
    if ($submitted === '' || !hash_equals(csrf_token(), $submitted)) {
        flash('error', 'Invalid security token or session expired. Please try again.');
        redirect($_SERVER['REQUEST_URI'] ?? 'index.php');
    }
}

// Redirection helper
function redirect(string $url): never {
    header("Location: {$url}");
    exit;
}

// Flash messages
function flash(string $type, string $message): void {
    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array {
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

// Formatting helpers
function format_currency(float|int|string|null $amount): string {
    return '₱' . number_format((float)($amount ?? 0), 2);
}

function format_number(float|int|string|null $num, int $decimals = 2): string {
    return number_format((float)($num ?? 0), $decimals);
}

function format_date(?string $date): string {
    if (!$date || $date === '0000-00-00') {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date('M j, Y', $ts) : $date;
}

// Include database & auth handlers
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/auth.php';

/**
 * System Activity / Audit Logger
 * Records what was created, updated, deleted, who logged in, and when.
 */
function log_activity(string $action, string $module, string $description, ?int $recordId = null, ?array $details = null, ?array $userContext = null): bool {
    try {
        $user = $userContext ?? (function_exists('current_user') ? current_user() : null);
        $userId = $user['user_id'] ?? null;
        $username = $user['username'] ?? 'system';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $data = [
            'user_id'     => $userId ? (int)$userId : null,
            'username'    => (string)$username,
            'action'      => strtoupper(trim($action)),
            'module'      => strtoupper(trim($module)),
            'record_id'   => $recordId ? (int)$recordId : null,
            'description' => trim($description),
            'details'     => $details !== null ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
            'ip_address'  => substr((string)$ip, 0, 45),
            'created_at'  => date('Y-m-d H:i:s')
        ];

        Database::insert('activity_logs', $data);
        return true;
    } catch (Throwable $e) {
        error_log("Failed to write activity log: " . $e->getMessage());
        return false;
    }
}

/**
 * Human-readable relative time helper
 */
function time_ago(?string $datetime): string {
    if (!$datetime) return '—';
    $time = strtotime($datetime);
    if (!$time) return '—';
    $diff = time() - $time;
    if ($diff < 5) return 'just now';
    if ($diff < 60) return $diff . 's ago';
    if ($diff < 3600) return (int)floor($diff / 60) . 'm ago';
    if ($diff < 86400) return (int)floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return (int)floor($diff / 86400) . 'd ago';
    return date('M j, Y g:i A', $time);
}
