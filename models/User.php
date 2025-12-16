<?php
/**
 * User Model
 * Handles user CRUD operations with prepared statements
 */

require_once __DIR__ . '/../config/Database.php';

class User {
    private $db;
    private $table = 'users';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Create a new user
     */
    public function create(array $data): ?int {
        $sql = "INSERT INTO {$this->table} (email, password, full_name, phone, role) 
                VALUES (?, ?, ?, ?, ?)";
        
        $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
        
        $this->db->prepare($sql, [
            $data['email'],
            $hashedPassword,
            $data['full_name'],
            $data['phone'] ?? null,
            $data['role'] ?? 'user'
        ]);
        
        return (int) $this->db->lastInsertId();
    }
    
    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE email = ? AND is_active = TRUE";
        $stmt = $this->db->prepare($sql, [$email]);
        $result = $stmt->fetch();
        
        return $result ?: null;
    }
    
    /**
     * Find user by ID
     */
    public function findById(int $id): ?array {
        $sql = "SELECT id, email, full_name, phone, points, role, created_at, updated_at 
                FROM {$this->table} WHERE id = ? AND is_active = TRUE";
        $stmt = $this->db->prepare($sql, [$id]);
        $result = $stmt->fetch();
        
        return $result ?: null;
    }
    
    /**
     * Verify password
     */
    public function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }
    
    /**
     * Update user
     */
    public function update(int $id, array $data): bool {
        $fields = [];
        $values = [];
        
        $allowedFields = ['full_name', 'phone', 'email'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }
        
        if (isset($data['password'])) {
            $fields[] = "password = ?";
            $values[] = password_hash($data['password'], PASSWORD_BCRYPT);
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $values[] = $id;
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $this->db->prepare($sql, $values);
        return true;
    }
    
    /**
     * Add points to user
     */
    public function addPoints(int $userId, int $points): bool {
        $sql = "UPDATE {$this->table} SET points = points + ? WHERE id = ?";
        $this->db->prepare($sql, [$points, $userId]);
        return true;
    }
    
    /**
     * Get user points
     */
    public function getPoints(int $userId): int {
        $sql = "SELECT points FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($sql, [$userId]);
        $result = $stmt->fetch();
        
        return $result ? (int) $result['points'] : 0;
    }
    
    /**
     * Check if email exists
     */
    public function emailExists(string $email): bool {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE email = ?";
        $stmt = $this->db->prepare($sql, [$email]);
        $result = $stmt->fetch();
        
        return $result['count'] > 0;
    }
    
    /**
     * Get all users (admin only)
     */
    public function getAll(int $limit = 100, int $offset = 0): array {
        $sql = "SELECT id, email, full_name, phone, points, role, is_active, created_at 
                FROM {$this->table} 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";
        
        $stmt = $this->db->prepare($sql, [$limit, $offset]);
        return $stmt->fetchAll();
    }
    
    /**
     * Deactivate user (soft delete)
     */
    public function deactivate(int $id): bool {
        $sql = "UPDATE {$this->table} SET is_active = FALSE WHERE id = ?";
        $this->db->prepare($sql, [$id]);
        return true;
    }
    
    /**
     * Get total user count
     */
    public function getCount(): int {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        $stmt = $this->db->prepare($sql, []);
        $result = $stmt->fetch();
        
        return (int) $result['count'];
    }
}
