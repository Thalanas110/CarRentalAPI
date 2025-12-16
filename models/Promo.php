<?php
/**
 * Promo Model
 * Handles promotional codes and discounts
 */

require_once __DIR__ . '/../config/Database.php';

class Promo {
    private $db;
    private $table = 'promos';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Create a new promo (admin)
     */
    public function create(array $data): ?int {
        $sql = "INSERT INTO {$this->table} 
                (code, name, description, discount_type, discount_value, max_discount, 
                 min_rental_hours, min_points_required, valid_from, valid_until, applicable_categories) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $this->db->prepare($sql, [
            strtoupper($data['code']),
            $data['name'],
            $data['description'] ?? null,
            $data['discount_type'] ?? 'percentage',
            $data['discount_value'],
            $data['max_discount'] ?? null,
            $data['min_rental_hours'] ?? 1,
            $data['min_points_required'] ?? 0,
            $data['valid_from'],
            $data['valid_until'],
            json_encode($data['applicable_categories'] ?? ['economy', 'standard', 'luxury', 'premium'])
        ]);
        
        return (int) $this->db->lastInsertId();
    }
    
    /**
     * Find promo by code
     */
    public function findByCode(string $code): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE code = ? AND is_active = TRUE";
        $stmt = $this->db->prepare($sql, [strtoupper($code)]);
        $result = $stmt->fetch();
        
        if ($result && isset($result['applicable_categories'])) {
            $result['applicable_categories'] = json_decode($result['applicable_categories'], true);
        }
        
        return $result ?: null;
    }
    
    /**
     * Find promo by ID
     */
    public function findById(int $id): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($sql, [$id]);
        $result = $stmt->fetch();
        
        if ($result && isset($result['applicable_categories'])) {
            $result['applicable_categories'] = json_decode($result['applicable_categories'], true);
        }
        
        return $result ?: null;
    }
    
    /**
     * Validate promo for use
     */
    public function validate(string $code, int $userPoints, int $rentalHours, string $carCategory): array {
        $promo = $this->findByCode($code);
        
        if (!$promo) {
            return ['valid' => false, 'error' => 'Invalid promo code'];
        }
        
        $now = new DateTime();
        $validFrom = new DateTime($promo['valid_from']);
        $validUntil = new DateTime($promo['valid_until']);
        
        if ($now < $validFrom) {
            return ['valid' => false, 'error' => 'Promo code is not yet valid'];
        }
        
        if ($now > $validUntil) {
            return ['valid' => false, 'error' => 'Promo code has expired'];
        }
        
        if ($promo['usage_limit'] !== null && $promo['usage_count'] >= $promo['usage_limit']) {
            return ['valid' => false, 'error' => 'Promo code usage limit reached'];
        }
        
        if ($userPoints < $promo['min_points_required']) {
            return ['valid' => false, 'error' => "You need {$promo['min_points_required']} points to use this promo"];
        }
        
        if ($rentalHours < $promo['min_rental_hours']) {
            return ['valid' => false, 'error' => "Minimum rental of {$promo['min_rental_hours']} hours required"];
        }
        
        $applicableCategories = $promo['applicable_categories'] ?? [];
        if (!empty($applicableCategories) && !in_array($carCategory, $applicableCategories)) {
            return ['valid' => false, 'error' => 'Promo code not applicable for this car category'];
        }
        
        return ['valid' => true, 'promo' => $promo];
    }
    
    /**
     * Calculate discount
     */
    public function calculateDiscount(array $promo, float $basePrice): float {
        if ($promo['discount_type'] === 'percentage') {
            $discount = $basePrice * ($promo['discount_value'] / 100);
            
            // Apply max discount cap
            if ($promo['max_discount'] !== null && $discount > $promo['max_discount']) {
                $discount = $promo['max_discount'];
            }
        } else {
            // Fixed discount
            $discount = $promo['discount_value'];
        }
        
        // Ensure discount doesn't exceed base price
        return min($discount, $basePrice);
    }
    
    /**
     * Increment usage count
     */
    public function incrementUsage(int $promoId): bool {
        $sql = "UPDATE {$this->table} SET usage_count = usage_count + 1 WHERE id = ?";
        $this->db->prepare($sql, [$promoId]);
        return true;
    }
    
    /**
     * Get active promos
     */
    public function getActive(): array {
        $sql = "SELECT * FROM {$this->table} 
                WHERE is_active = TRUE 
                AND valid_from <= NOW() 
                AND valid_until >= NOW()
                ORDER BY min_points_required, discount_value DESC";
        
        $stmt = $this->db->prepare($sql, []);
        $promos = $stmt->fetchAll();
        
        return array_map(function($promo) {
            $promo['applicable_categories'] = json_decode($promo['applicable_categories'], true);
            return $promo;
        }, $promos);
    }
    
    /**
     * Get promos eligible for user
     */
    public function getEligibleForUser(int $userPoints): array {
        $sql = "SELECT * FROM {$this->table} 
                WHERE is_active = TRUE 
                AND valid_from <= NOW() 
                AND valid_until >= NOW()
                AND min_points_required <= ?
                AND (usage_limit IS NULL OR usage_count < usage_limit)
                ORDER BY discount_value DESC";
        
        $stmt = $this->db->prepare($sql, [$userPoints]);
        $promos = $stmt->fetchAll();
        
        return array_map(function($promo) {
            $promo['applicable_categories'] = json_decode($promo['applicable_categories'], true);
            return $promo;
        }, $promos);
    }
    
    /**
     * Get all promos (admin)
     */
    public function getAll(): array {
        $sql = "SELECT * FROM {$this->table} ORDER BY valid_until DESC";
        $stmt = $this->db->prepare($sql, []);
        $promos = $stmt->fetchAll();
        
        return array_map(function($promo) {
            $promo['applicable_categories'] = json_decode($promo['applicable_categories'], true);
            return $promo;
        }, $promos);
    }
    
    /**
     * Update promo (admin)
     */
    public function update(int $id, array $data): bool {
        $fields = [];
        $values = [];
        
        $allowedFields = [
            'name', 'description', 'discount_type', 'discount_value', 
            'max_discount', 'min_rental_hours', 'min_points_required',
            'valid_from', 'valid_until', 'usage_limit', 'is_active'
        ];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }
        
        if (isset($data['applicable_categories'])) {
            $fields[] = "applicable_categories = ?";
            $values[] = json_encode($data['applicable_categories']);
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
     * Deactivate promo
     */
    public function deactivate(int $id): bool {
        $sql = "UPDATE {$this->table} SET is_active = FALSE WHERE id = ?";
        $this->db->prepare($sql, [$id]);
        return true;
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
