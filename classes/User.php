<?php
declare(strict_types=1);

/**
 * User Model Class
 * 
 * Manages system operator accounts, authentication credentials,
 * password hashing (BCRYPT), role-based access control, and status flags.
 */
class User extends BaseModel {
    protected string $table = 'users';
    protected string $primaryKey = 'user_id';

    /**
     * Authenticate user by username and plain password
     */
    public function authenticate(string $username, string $password): ?array {
        $user = $this->getByUsername($username);
        if (!$user) {
            return null;
        }

        if (($user['status'] ?? 'active') !== 'active') {
            return null;
        }

        if (password_verify($password, $user['password_hash'] ?? '')) {
            return $user;
        }

        return null;
    }

    /**
     * Find user by username
     */
    public function getByUsername(string $username): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE username = :uname LIMIT 1");
        $stmt->execute([':uname' => $username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Find user by email
     */
    public function getByEmail(string $email): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Get all users ordered by creation date
     */
    public function getAll(): array {
        return $this->rawQuery("SELECT user_id, username, email, full_name, role, status, created_at, updated_at FROM {$this->table} ORDER BY user_id ASC");
    }

    /**
     * Get single user by ID (excluding password hash)
     */
    public function getById(int $userId): ?array {
        return $this->find($userId);
    }

    /**
     * Create new user with hashed password
     */
    public function create(array $data): int {
        if (!empty($data['password'])) {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT);
            unset($data['password']);
        }
        $res = Database::insert($this->table, $data);
        return (int)($res['id'] ?? $res['user_id'] ?? 0);
    }

    /**
     * Update user details
     */
    public function update(int $userId, array $data): bool {
        if (!empty($data['password'])) {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT);
            unset($data['password']);
        }
        return Database::update($this->table, $this->primaryKey, $userId, $data);
    }

    /**
     * Change user password securely
     */
    public function updatePassword(int $userId, string $newPassword): bool {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        return Database::update($this->table, $this->primaryKey, $userId, ['password_hash' => $hash]);
    }

    /**
     * Delete user account
     */
    public function deleteUser(int $userId): bool {
        return $this->delete($userId);
    }
}
