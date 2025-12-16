<?php
/**
 * Car Model
 * Handles car CRUD and availability management
 */

require_once __DIR__ . '/../config/Database.php';

class Car {
    private $db;
    private $table = 'cars';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Get all available cars
     */
    public function getAvailable(): array {
        $sql = "SELECT * FROM {$this->table} 
                WHERE is_available = TRUE AND is_rented = FALSE 
                ORDER BY category, price_per_hour";
        
        $stmt = $this->db->prepare($sql, []);
        return $stmt->fetchAll();
    }
    
    /**
     * Get cars available for user based on their points
     */
    public function getAvailableForUser(int $userPoints): array {
        $sql = "SELECT * FROM {$this->table} 
                WHERE is_available = TRUE AND is_rented = FALSE AND required_points <= ?
                ORDER BY category, price_per_hour";
        
        $stmt = $this->db->prepare($sql, [$userPoints]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get locked cars (require more points)
     */
    public function getLockedCars(int $userPoints): array {
        $sql = "SELECT * FROM {$this->table} 
                WHERE required_points > ?
                ORDER BY required_points, price_per_hour";
        
        $stmt = $this->db->prepare($sql, [$userPoints]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get all cars (admin)
     */
    public function getAll(): array {
        $sql = "SELECT * FROM {$this->table} ORDER BY category, make, model";
        $stmt = $this->db->prepare($sql, []);
        return $stmt->fetchAll();
    }
    
    /**
     * Find car by ID
     */
    public function findById(int $id): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($sql, [$id]);
        $result = $stmt->fetch();
        
        return $result ?: null;
    }
    
    /**
     * Find car by plate number
     */
    public function findByPlate(string $plateNumber): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE plate_number = ?";
        $stmt = $this->db->prepare($sql, [$plateNumber]);
        $result = $stmt->fetch();
        
        return $result ?: null;
    }
    
    /**
     * Create new car (admin)
     */
    public function create(array $data): ?int {
        $sql = "INSERT INTO {$this->table} 
                (make, model, year, plate_number, category, price_per_hour, chauffeur_fee, 
                 required_points, description, seats, transmission, fuel_type, image_url) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $this->db->prepare($sql, [
            $data['make'],
            $data['model'],
            $data['year'],
            $data['plate_number'],
            $data['category'] ?? 'standard',
            $data['price_per_hour'],
            $data['chauffeur_fee'] ?? 100.00,
            $data['required_points'] ?? 0,
            $data['description'] ?? null,
            $data['seats'] ?? 5,
            $data['transmission'] ?? 'automatic',
            $data['fuel_type'] ?? 'gasoline',
            $data['image_url'] ?? null
        ]);
        
        return (int) $this->db->lastInsertId();
    }
    
    /**
     * Update car (admin)
     */
    public function update(int $id, array $data): bool {
        $fields = [];
        $values = [];
        
        $allowedFields = [
            'make', 'model', 'year', 'plate_number', 'category', 
            'price_per_hour', 'chauffeur_fee', 'required_points', 
            'description', 'seats', 'transmission', 'fuel_type', 
            'image_url', 'is_available'
        ];
        
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
     * Set car as rented
     */
    public function setRented(int $id, bool $rented = true): bool {
        $sql = "UPDATE {$this->table} SET is_rented = ? WHERE id = ?";
        $this->db->prepare($sql, [$rented ? 1 : 0, $id]);
        return true;
    }
    
    /**
     * Set car availability
     */
    public function setAvailable(int $id, bool $available = true): bool {
        $sql = "UPDATE {$this->table} SET is_available = ? WHERE id = ?";
        $this->db->prepare($sql, [$available ? 1 : 0, $id]);
        return true;
    }
    
    /**
     * Get cars by category
     */
    public function getByCategory(string $category): array {
        $sql = "SELECT * FROM {$this->table} 
                WHERE category = ? AND is_available = TRUE 
                ORDER BY price_per_hour";
        
        $stmt = $this->db->prepare($sql, [$category]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get car with average rating
     */
    public function getWithRating(int $id): ?array {
        $sql = "SELECT c.*, 
                       COALESCE(AVG(r.car_rating), 0) as avg_car_rating,
                       COUNT(r.id) as total_ratings
                FROM {$this->table} c
                LEFT JOIN ratings r ON c.id = r.car_id
                WHERE c.id = ?
                GROUP BY c.id";
        
        $stmt = $this->db->prepare($sql, [$id]);
        $result = $stmt->fetch();
        
        return $result ?: null;
    }
    
    /**
     * Check if user has enough points for car
     */
    public function canUserRent(int $carId, int $userPoints): bool {
        $car = $this->findById($carId);
        
        if (!$car) {
            return false;
        }
        
        return $userPoints >= $car['required_points'];
    }
    
    /**
     * Delete car (admin)
     */
    public function delete(int $id): bool {
        // Check if car has active rentals
        $sql = "SELECT COUNT(*) as count FROM rentals 
                WHERE car_id = ? AND status IN ('pending', 'confirmed', 'active')";
        $stmt = $this->db->prepare($sql, [$id]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            return false; // Cannot delete car with active rentals
        }
        
        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        $this->db->prepare($sql, [$id]);
        return true;
    }
    
    /**
     * Get total car count
     */
    public function getCount(): int {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        $stmt = $this->db->prepare($sql, []);
        $result = $stmt->fetch();
        
        return (int) $result['count'];
    }
    
    /**
     * Get available car count
     */
    public function getAvailableCount(): int {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} 
                WHERE is_available = TRUE AND is_rented = FALSE";
        $stmt = $this->db->prepare($sql, []);
        $result = $stmt->fetch();
        
        return (int) $result['count'];
    }
}
