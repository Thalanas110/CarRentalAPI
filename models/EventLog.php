<?php
/**
 * EventLog Model
 * Handles event log queries for admin
 */

require_once __DIR__ . '/../config/Database.php';

class EventLog {
    private $db;
    private $table = 'event_logs';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Get all logs with pagination
     */
    public function getAll(int $limit = 100, int $offset = 0, ?string $category = null): array {
        $sql = "SELECT el.*, u.email as user_email, u.full_name
                FROM {$this->table} el
                LEFT JOIN users u ON el.user_id = u.id";
        
        $params = [];
        
        if ($category) {
            $sql .= " WHERE el.event_category = ?";
            $params[] = $category;
        }
        
        $sql .= " ORDER BY el.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get logs by user
     */
    public function getByUser(int $userId, int $limit = 50): array {
        $sql = "SELECT * FROM {$this->table} 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?";
        
        $stmt = $this->db->prepare($sql, [$userId, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get logs by category
     */
    public function getByCategory(string $category, int $limit = 100): array {
        $sql = "SELECT el.*, u.email as user_email
                FROM {$this->table} el
                LEFT JOIN users u ON el.user_id = u.id
                WHERE el.event_category = ?
                ORDER BY el.created_at DESC
                LIMIT ?";
        
        $stmt = $this->db->prepare($sql, [$category, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get logs by type
     */
    public function getByType(string $type, int $limit = 100): array {
        $sql = "SELECT el.*, u.email as user_email
                FROM {$this->table} el
                LEFT JOIN users u ON el.user_id = u.id
                WHERE el.event_type = ?
                ORDER BY el.created_at DESC
                LIMIT ?";
        
        $stmt = $this->db->prepare($sql, [$type, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get logs within date range
     */
    public function getByDateRange(string $startDate, string $endDate, int $limit = 500): array {
        $sql = "SELECT el.*, u.email as user_email
                FROM {$this->table} el
                LEFT JOIN users u ON el.user_id = u.id
                WHERE el.created_at BETWEEN ? AND ?
                ORDER BY el.created_at DESC
                LIMIT ?";
        
        $stmt = $this->db->prepare($sql, [$startDate, $endDate, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Search logs
     */
    public function search(string $query, int $limit = 100): array {
        $searchTerm = "%$query%";
        
        $sql = "SELECT el.*, u.email as user_email
                FROM {$this->table} el
                LEFT JOIN users u ON el.user_id = u.id
                WHERE el.event_description LIKE ?
                   OR el.event_type LIKE ?
                   OR u.email LIKE ?
                ORDER BY el.created_at DESC
                LIMIT ?";
        
        $stmt = $this->db->prepare($sql, [$searchTerm, $searchTerm, $searchTerm, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get auth logs (login/logout/register)
     */
    public function getAuthLogs(int $limit = 100): array {
        return $this->getByCategory('auth', $limit);
    }
    
    /**
     * Get transaction logs
     */
    public function getTransactionLogs(int $limit = 100): array {
        return $this->getByCategory('rental', $limit);
    }
    
    /**
     * Get payment logs
     */
    public function getPaymentLogs(int $limit = 100): array {
        return $this->getByCategory('payment', $limit);
    }
    
    /**
     * Get admin action logs
     */
    public function getAdminLogs(int $limit = 100): array {
        return $this->getByCategory('admin', $limit);
    }
    
    /**
     * Get error logs
     */
    public function getErrorLogs(int $limit = 100): array {
        return $this->getByCategory('error', $limit);
    }
    
    /**
     * Get statistics
     */
    public function getStatistics(): array {
        $stats = [];
        
        // Total logs
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        $stmt = $this->db->prepare($sql, []);
        $stats['total_logs'] = (int) $stmt->fetch()['count'];
        
        // By category
        $sql = "SELECT event_category, COUNT(*) as count FROM {$this->table} GROUP BY event_category";
        $stmt = $this->db->prepare($sql, []);
        $stats['by_category'] = $stmt->fetchAll();
        
        // Today's logs
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE DATE(created_at) = CURDATE()";
        $stmt = $this->db->prepare($sql, []);
        $stats['today_logs'] = (int) $stmt->fetch()['count'];
        
        // Error count today
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE event_category = 'error' AND DATE(created_at) = CURDATE()";
        $stmt = $this->db->prepare($sql, []);
        $stats['today_errors'] = (int) $stmt->fetch()['count'];
        
        // Top IPs
        $sql = "SELECT ip_address, COUNT(*) as count FROM {$this->table} 
                WHERE ip_address IS NOT NULL 
                GROUP BY ip_address 
                ORDER BY count DESC 
                LIMIT 10";
        $stmt = $this->db->prepare($sql, []);
        $stats['top_ips'] = $stmt->fetchAll();
        
        return $stats;
    }
    
    /**
     * Clean old logs (admin)
     */
    public function cleanOldLogs(int $daysToKeep = 90): int {
        $sql = "DELETE FROM {$this->table} WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $this->db->prepare($sql, [$daysToKeep]);
        return $stmt->rowCount();
    }
    
    /**
     * Get count
     */
    public function getCount(?string $category = null): int {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        $params = [];
        
        if ($category) {
            $sql .= " WHERE event_category = ?";
            $params[] = $category;
        }
        
        $stmt = $this->db->prepare($sql, $params);
        return (int) $stmt->fetch()['count'];
    }
}
