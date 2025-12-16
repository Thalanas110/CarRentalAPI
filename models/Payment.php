<?php
/**
 * Payment Model
 * Handles payment records with multiple payment types
 */

require_once __DIR__ . '/../config/Database.php';

class Payment {
    private $db;
    private $table = 'payments';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Create a new payment
     */
    public function create(array $data): ?int {
        $sql = "INSERT INTO {$this->table} 
                (rental_id, payment_type, amount, reference_number, notes) 
                VALUES (?, ?, ?, ?, ?)";
        
        $this->db->prepare($sql, [
            $data['rental_id'],
            $data['payment_type'],
            $data['amount'],
            $data['reference_number'] ?? null,
            $data['notes'] ?? null
        ]);
        
        return (int) $this->db->lastInsertId();
    }
    
    /**
     * Find payment by ID
     */
    public function findById(int $id): ?array {
        $sql = "SELECT p.*, r.user_id, r.car_id, r.status as rental_status
                FROM {$this->table} p
                JOIN rentals r ON p.rental_id = r.id
                WHERE p.id = ?";
        
        $stmt = $this->db->prepare($sql, [$id]);
        $result = $stmt->fetch();
        
        return $result ?: null;
    }
    
    /**
     * Get payments for a rental
     */
    public function getByRental(int $rentalId): array {
        $sql = "SELECT * FROM {$this->table} WHERE rental_id = ? ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql, [$rentalId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Confirm payment as received (admin)
     */
    public function confirmReceived(int $id, int $receivedBy): bool {
        $sql = "UPDATE {$this->table} 
                SET is_received = TRUE, received_at = NOW(), received_by = ?
                WHERE id = ?";
        
        $this->db->prepare($sql, [$receivedBy, $id]);
        return true;
    }
    
    /**
     * Get all payments (admin)
     */
    public function getAll(int $limit = 100, int $offset = 0): array {
        $sql = "SELECT p.*, 
                       r.user_id, r.car_id,
                       u.email, u.full_name,
                       c.make, c.model, c.plate_number,
                       admin.email as received_by_email
                FROM {$this->table} p
                JOIN rentals r ON p.rental_id = r.id
                JOIN users u ON r.user_id = u.id
                JOIN cars c ON r.car_id = c.id
                LEFT JOIN users admin ON p.received_by = admin.id
                ORDER BY p.created_at DESC
                LIMIT ? OFFSET ?";
        
        $stmt = $this->db->prepare($sql, [$limit, $offset]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get pending payments (not received)
     */
    public function getPending(): array {
        $sql = "SELECT p.*, 
                       r.user_id, u.email, u.full_name,
                       c.make, c.model, c.plate_number
                FROM {$this->table} p
                JOIN rentals r ON p.rental_id = r.id
                JOIN users u ON r.user_id = u.id
                JOIN cars c ON r.car_id = c.id
                WHERE p.is_received = FALSE
                ORDER BY p.created_at";
        
        $stmt = $this->db->prepare($sql, []);
        return $stmt->fetchAll();
    }
    
    /**
     * Get total received for a rental
     */
    public function getTotalReceived(int $rentalId): float {
        $sql = "SELECT COALESCE(SUM(amount), 0) as total 
                FROM {$this->table} 
                WHERE rental_id = ? AND is_received = TRUE";
        
        $stmt = $this->db->prepare($sql, [$rentalId]);
        $result = $stmt->fetch();
        
        return (float) $result['total'];
    }
    
    /**
     * Check if rental is fully paid
     */
    public function isFullyPaid(int $rentalId, float $totalDue): bool {
        return $this->getTotalReceived($rentalId) >= $totalDue;
    }
    
    /**
     * Update payment
     */
    public function update(int $id, array $data): bool {
        $fields = [];
        $values = [];
        
        $allowedFields = ['payment_type', 'amount', 'reference_number', 'notes', 'is_received'];
        
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
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $this->db->prepare($sql, $values);
        return true;
    }
    
    /**
     * Get payment statistics
     */
    public function getStatistics(): array {
        $stats = [];
        
        // Total received
        $sql = "SELECT COALESCE(SUM(amount), 0) as total FROM {$this->table} WHERE is_received = TRUE";
        $stmt = $this->db->prepare($sql, []);
        $stats['total_received'] = (float) $stmt->fetch()['total'];
        
        // Total pending
        $sql = "SELECT COALESCE(SUM(amount), 0) as total FROM {$this->table} WHERE is_received = FALSE";
        $stmt = $this->db->prepare($sql, []);
        $stats['total_pending'] = (float) $stmt->fetch()['total'];
        
        // By payment type
        $sql = "SELECT payment_type, COUNT(*) as count, SUM(amount) as total 
                FROM {$this->table} 
                WHERE is_received = TRUE
                GROUP BY payment_type";
        $stmt = $this->db->prepare($sql, []);
        $stats['by_type'] = $stmt->fetchAll();
        
        return $stats;
    }
    
    /**
     * Get payment count
     */
    public function getCount(): int {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        $stmt = $this->db->prepare($sql, []);
        return (int) $stmt->fetch()['count'];
    }
}
