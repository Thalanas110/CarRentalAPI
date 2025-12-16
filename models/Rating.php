<?php
/**
 * Rating Model
 * Handles user ratings for cars and service
 */

require_once __DIR__ . '/../config/Database.php';

class Rating {
    private $db;
    private $table = 'ratings';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Create a new rating
     */
    public function create(array $data): ?int {
        // Check if rating already exists for this rental
        if ($this->existsForRental($data['rental_id'])) {
            return null;
        }
        
        $sql = "INSERT INTO {$this->table} 
                (user_id, rental_id, car_id, car_rating, service_rating, comment) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $this->db->prepare($sql, [
            $data['user_id'],
            $data['rental_id'],
            $data['car_id'],
            $data['car_rating'],
            $data['service_rating'],
            $data['comment'] ?? null
        ]);
        
        return (int) $this->db->lastInsertId();
    }
    
    /**
     * Check if rating exists for rental
     */
    public function existsForRental(int $rentalId): bool {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE rental_id = ?";
        $stmt = $this->db->prepare($sql, [$rentalId]);
        return $stmt->fetch()['count'] > 0;
    }
    
    /**
     * Find rating by ID
     */
    public function findById(int $id): ?array {
        $sql = "SELECT r.*, u.full_name, u.email,
                       c.make, c.model
                FROM {$this->table} r
                JOIN users u ON r.user_id = u.id
                JOIN cars c ON r.car_id = c.id
                WHERE r.id = ?";
        
        $stmt = $this->db->prepare($sql, [$id]);
        $result = $stmt->fetch();
        
        return $result ?: null;
    }
    
    /**
     * Get ratings for a car
     */
    public function getBycar(int $carId): array {
        $sql = "SELECT r.*, u.full_name
                FROM {$this->table} r
                JOIN users u ON r.user_id = u.id
                WHERE r.car_id = ? AND r.is_approved = TRUE
                ORDER BY r.created_at DESC";
        
        $stmt = $this->db->prepare($sql, [$carId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get car average rating
     */
    public function getCarAverageRating(int $carId): array {
        $sql = "SELECT 
                    COALESCE(AVG(car_rating), 0) as avg_car_rating,
                    COALESCE(AVG(service_rating), 0) as avg_service_rating,
                    COUNT(*) as total_ratings
                FROM {$this->table} 
                WHERE car_id = ? AND is_approved = TRUE";
        
        $stmt = $this->db->prepare($sql, [$carId]);
        return $stmt->fetch();
    }
    
    /**
     * Get ratings by user
     */
    public function getByUser(int $userId): array {
        $sql = "SELECT r.*, c.make, c.model, c.plate_number
                FROM {$this->table} r
                JOIN cars c ON r.car_id = c.id
                WHERE r.user_id = ?
                ORDER BY r.created_at DESC";
        
        $stmt = $this->db->prepare($sql, [$userId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get all ratings (admin)
     */
    public function getAll(int $limit = 100, int $offset = 0): array {
        $sql = "SELECT r.*, 
                       u.full_name, u.email,
                       c.make, c.model, c.plate_number
                FROM {$this->table} r
                JOIN users u ON r.user_id = u.id
                JOIN cars c ON r.car_id = c.id
                ORDER BY r.created_at DESC
                LIMIT ? OFFSET ?";
        
        $stmt = $this->db->prepare($sql, [$limit, $offset]);
        return $stmt->fetchAll();
    }
    
    /**
     * Approve/disapprove rating (admin)
     */
    public function setApproved(int $id, bool $approved): bool {
        $sql = "UPDATE {$this->table} SET is_approved = ? WHERE id = ?";
        $this->db->prepare($sql, [$approved ? 1 : 0, $id]);
        return true;
    }
    
    /**
     * Update rating
     */
    public function update(int $id, int $userId, array $data): bool {
        $fields = [];
        $values = [];
        
        $allowedFields = ['car_rating', 'service_rating', 'comment'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $values[] = $id;
        $values[] = $userId;
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = ? AND user_id = ?";
        
        $this->db->prepare($sql, $values);
        return true;
    }
    
    /**
     * Delete rating
     */
    public function delete(int $id, int $userId): bool {
        $sql = "DELETE FROM {$this->table} WHERE id = ? AND user_id = ?";
        $this->db->prepare($sql, [$id, $userId]);
        return true;
    }
    
    /**
     * Get overall statistics
     */
    public function getStatistics(): array {
        $stats = [];
        
        // Overall averages
        $sql = "SELECT 
                    COALESCE(AVG(car_rating), 0) as avg_car_rating,
                    COALESCE(AVG(service_rating), 0) as avg_service_rating,
                    COUNT(*) as total_ratings
                FROM {$this->table} WHERE is_approved = TRUE";
        $stmt = $this->db->prepare($sql, []);
        $stats = $stmt->fetch();
        
        // Rating distribution
        $sql = "SELECT car_rating, COUNT(*) as count FROM {$this->table} WHERE is_approved = TRUE GROUP BY car_rating";
        $stmt = $this->db->prepare($sql, []);
        $stats['car_rating_distribution'] = $stmt->fetchAll();
        
        $sql = "SELECT service_rating, COUNT(*) as count FROM {$this->table} WHERE is_approved = TRUE GROUP BY service_rating";
        $stmt = $this->db->prepare($sql, []);
        $stats['service_rating_distribution'] = $stmt->fetchAll();
        
        return $stats;
    }
    
    /**
     * Get count
     */
    public function getCount(): int {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        $stmt = $this->db->prepare($sql, []);
        return (int) $stmt->fetch()['count'];
    }
}
