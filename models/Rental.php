<?php
/**
 * Rental Model
 * Handles rental lifecycle and overtime calculation
 */

require_once __DIR__ . '/../config/Database.php';

class Rental {
    private $db;
    private $table = 'rentals';
    private const OVERTIME_FEE_PER_HOUR = 200.00;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Create a new rental
     */
    public function create(array $data): ?int {
        $sql = "INSERT INTO {$this->table} 
                (user_id, car_id, rental_type, start_time, expected_end_time, duration_hours,
                 base_price, chauffeur_fee, discount_amount, total_price, promo_id, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $this->db->prepare($sql, [
            $data['user_id'],
            $data['car_id'],
            $data['rental_type'],
            $data['start_time'],
            $data['expected_end_time'],
            $data['duration_hours'],
            $data['base_price'],
            $data['chauffeur_fee'] ?? 0,
            $data['discount_amount'] ?? 0,
            $data['total_price'],
            $data['promo_id'] ?? null,
            $data['notes'] ?? null
        ]);
        
        return (int) $this->db->lastInsertId();
    }
    
    /**
     * Find rental by ID
     */
    public function findById(int $id): ?array {
        $sql = "SELECT r.*, 
                       c.make, c.model, c.plate_number, c.category,
                       u.email, u.full_name
                FROM {$this->table} r
                JOIN cars c ON r.car_id = c.id
                JOIN users u ON r.user_id = u.id
                WHERE r.id = ?";
        
        $stmt = $this->db->prepare($sql, [$id]);
        $result = $stmt->fetch();
        
        if ($result) {
            // Calculate current overtime if active
            $result = $this->calculateCurrentOvertime($result);
        }
        
        return $result ?: null;
    }
    
    /**
     * Calculate current overtime for an active rental
     * Adds 200 PHP per hour automatically
     */
    public function calculateCurrentOvertime(array $rental): array {
        if ($rental['status'] === 'active' && !$rental['actual_end_time']) {
            $expectedEnd = new DateTime($rental['expected_end_time']);
            $now = new DateTime();
            
            if ($now > $expectedEnd) {
                $diff = $now->diff($expectedEnd);
                $overtimeHours = $diff->h + ($diff->days * 24);
                
                // Add partial hours as full hours
                if ($diff->i > 0) {
                    $overtimeHours++;
                }
                
                $currentOvertime = $overtimeHours * self::OVERTIME_FEE_PER_HOUR;
                $rental['current_overtime'] = $currentOvertime;
                $rental['overtime_hours'] = $overtimeHours;
                $rental['current_total'] = $rental['base_price'] + $rental['chauffeur_fee'] + $currentOvertime - $rental['discount_amount'];
            } else {
                $rental['current_overtime'] = 0;
                $rental['overtime_hours'] = 0;
                $rental['current_total'] = $rental['total_price'];
            }
        }
        
        return $rental;
    }
    
    /**
     * Get rentals by user
     */
    public function getByUser(int $userId): array {
        $sql = "SELECT r.*, c.make, c.model, c.plate_number, c.category
                FROM {$this->table} r
                JOIN cars c ON r.car_id = c.id
                WHERE r.user_id = ?
                ORDER BY r.created_at DESC";
        
        $stmt = $this->db->prepare($sql, [$userId]);
        $rentals = $stmt->fetchAll();
        
        // Calculate overtime for each active rental
        return array_map([$this, 'calculateCurrentOvertime'], $rentals);
    }
    
    /**
     * Get all rentals (admin)
     */
    public function getAll(int $limit = 100, int $offset = 0): array {
        $sql = "SELECT r.*, 
                       c.make, c.model, c.plate_number,
                       u.email, u.full_name
                FROM {$this->table} r
                JOIN cars c ON r.car_id = c.id
                JOIN users u ON r.user_id = u.id
                ORDER BY r.created_at DESC
                LIMIT ? OFFSET ?";
        
        $stmt = $this->db->prepare($sql, [$limit, $offset]);
        $rentals = $stmt->fetchAll();
        
        return array_map([$this, 'calculateCurrentOvertime'], $rentals);
    }
    
    /**
     * Get active rentals
     */
    public function getActive(): array {
        $sql = "SELECT r.*, 
                       c.make, c.model, c.plate_number,
                       u.email, u.full_name
                FROM {$this->table} r
                JOIN cars c ON r.car_id = c.id
                JOIN users u ON r.user_id = u.id
                WHERE r.status = 'active'
                ORDER BY r.expected_end_time";
        
        $stmt = $this->db->prepare($sql, []);
        $rentals = $stmt->fetchAll();
        
        return array_map([$this, 'calculateCurrentOvertime'], $rentals);
    }
    
    /**
     * Confirm rental (admin)
     */
    public function confirm(int $id): bool {
        $sql = "UPDATE {$this->table} SET status = 'confirmed' WHERE id = ? AND status = 'pending'";
        $this->db->prepare($sql, [$id]);
        return true;
    }
    
    /**
     * Activate rental (start)
     */
    public function activate(int $id): bool {
        $sql = "UPDATE {$this->table} SET status = 'active', start_time = NOW() WHERE id = ? AND status = 'confirmed'";
        $this->db->prepare($sql, [$id]);
        return true;
    }
    
    /**
     * Release car key
     */
    public function releaseKey(int $id): bool {
        $sql = "UPDATE {$this->table} 
                SET key_released = TRUE, key_released_at = NOW(), status = 'active'
                WHERE id = ? AND status IN ('confirmed', 'pending')";
        $this->db->prepare($sql, [$id]);
        return true;
    }
    
    /**
     * Return car and finalize overtime
     */
    public function returnCar(int $id): ?array {
        $rental = $this->findById($id);
        
        if (!$rental || $rental['status'] !== 'active') {
            return null;
        }
        
        // Calculate final overtime
        $expectedEnd = new DateTime($rental['expected_end_time']);
        $now = new DateTime();
        $overtimeFee = 0;
        
        if ($now > $expectedEnd) {
            $diff = $now->diff($expectedEnd);
            $overtimeHours = $diff->h + ($diff->days * 24);
            if ($diff->i > 0) {
                $overtimeHours++;
            }
            $overtimeFee = $overtimeHours * self::OVERTIME_FEE_PER_HOUR;
        }
        
        $totalPrice = $rental['base_price'] + $rental['chauffeur_fee'] + $overtimeFee - $rental['discount_amount'];
        
        $sql = "UPDATE {$this->table} 
                SET actual_end_time = NOW(), 
                    key_returned = TRUE, 
                    key_returned_at = NOW(),
                    overtime_fee = ?,
                    total_price = ?,
                    status = 'completed'
                WHERE id = ?";
        
        $this->db->prepare($sql, [$overtimeFee, $totalPrice, $id]);
        
        return [
            'rental_id' => $id,
            'overtime_fee' => $overtimeFee,
            'total_price' => $totalPrice
        ];
    }
    
    /**
     * Cancel rental
     */
    public function cancel(int $id): bool {
        $sql = "UPDATE {$this->table} SET status = 'cancelled' WHERE id = ? AND status IN ('pending', 'confirmed')";
        $this->db->prepare($sql, [$id]);
        return true;
    }
    
    /**
     * Update rental status (admin)
     */
    public function updateStatus(int $id, string $status): bool {
        $validStatuses = ['pending', 'confirmed', 'active', 'completed', 'cancelled'];
        
        if (!in_array($status, $validStatuses)) {
            return false;
        }
        
        $sql = "UPDATE {$this->table} SET status = ? WHERE id = ?";
        $this->db->prepare($sql, [$status, $id]);
        return true;
    }
    
    /**
     * Admin set car returned
     */
    public function setReturned(int $id, bool $returned = true): bool {
        $updates = ['key_returned' => $returned ? 1 : 0];
        
        if ($returned) {
            $sql = "UPDATE {$this->table} 
                    SET key_returned = TRUE, key_returned_at = NOW() 
                    WHERE id = ?";
        } else {
            $sql = "UPDATE {$this->table} 
                    SET key_returned = FALSE, key_returned_at = NULL 
                    WHERE id = ?";
        }
        
        $this->db->prepare($sql, [$id]);
        return true;
    }
    
    /**
     * Get rental statistics (admin)
     */
    public function getStatistics(): array {
        $stats = [];
        
        // Total rentals
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        $stmt = $this->db->prepare($sql, []);
        $stats['total_rentals'] = (int) $stmt->fetch()['count'];
        
        // Active rentals
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE status = 'active'";
        $stmt = $this->db->prepare($sql, []);
        $stats['active_rentals'] = (int) $stmt->fetch()['count'];
        
        // Completed rentals
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE status = 'completed'";
        $stmt = $this->db->prepare($sql, []);
        $stats['completed_rentals'] = (int) $stmt->fetch()['count'];
        
        // Total revenue
        $sql = "SELECT COALESCE(SUM(total_price), 0) as total FROM {$this->table} WHERE status = 'completed'";
        $stmt = $this->db->prepare($sql, []);
        $stats['total_revenue'] = (float) $stmt->fetch()['total'];
        
        // Overtime collected
        $sql = "SELECT COALESCE(SUM(overtime_fee), 0) as total FROM {$this->table} WHERE status = 'completed'";
        $stmt = $this->db->prepare($sql, []);
        $stats['overtime_collected'] = (float) $stmt->fetch()['total'];
        
        // Overdue rentals (active and past expected end time)
        $sql = "SELECT COUNT(*) as count FROM {$this->table} 
                WHERE status = 'active' AND expected_end_time < NOW()";
        $stmt = $this->db->prepare($sql, []);
        $stats['overdue_rentals'] = (int) $stmt->fetch()['count'];
        
        return $stats;
    }
    
    /**
     * Get total count
     */
    public function getCount(): int {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        $stmt = $this->db->prepare($sql, []);
        return (int) $stmt->fetch()['count'];
    }
}
