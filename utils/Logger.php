<?php
/**
 * Logger Utility
 * Logs every event to database and file
 */

require_once __DIR__ . '/../config/Database.php';

class Logger {
    private static $instance = null;
    private $db;
    private $logFile;
    
    private function __construct() {
        $this->db = Database::getInstance();
        $this->logFile = __DIR__ . '/../logs/app.log';
        
        // Ensure logs directory exists
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    public static function getInstance(): Logger {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Log an event to both database and file
     */
    public function log(
        string $eventType,
        string $category,
        string $description,
        ?int $userId = null,
        ?array $requestData = null,
        ?array $responseData = null,
        ?int $responseCode = null,
        ?int $executionTimeMs = null
    ): bool {
        try {
            // Log to database
            $sql = "INSERT INTO event_logs 
                    (user_id, event_type, event_category, event_description, ip_address, user_agent, 
                     request_method, request_uri, request_data, response_code, response_data, execution_time_ms) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $this->db->prepare($sql, [
                $userId,
                $eventType,
                $category,
                $description,
                $this->getClientIp(),
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $_SERVER['REQUEST_METHOD'] ?? null,
                $_SERVER['REQUEST_URI'] ?? null,
                $requestData ? json_encode($requestData) : null,
                $responseCode,
                $responseData ? json_encode($responseData) : null,
                $executionTimeMs
            ]);
            
            // Log to file
            $this->logToFile($eventType, $category, $description, $userId);
            
            return true;
        } catch (Exception $e) {
            // If database logging fails, at least log to file
            $this->logToFile('LOG_ERROR', 'error', $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log authentication events
     */
    public function logAuth(string $action, string $email, bool $success, ?int $userId = null): void {
        $description = $success 
            ? "Auth: $action successful for $email"
            : "Auth: $action failed for $email";
        
        $this->log(
            "AUTH_" . strtoupper($action),
            'auth',
            $description,
            $userId
        );
    }
    
    /**
     * Log transaction events
     */
    public function logTransaction(string $action, int $rentalId, ?int $userId = null, ?array $details = null): void {
        $description = "Transaction: $action for rental #$rentalId";
        if ($details) {
            $description .= " - " . json_encode($details);
        }
        
        $this->log(
            "TRANSACTION_" . strtoupper($action),
            'rental',
            $description,
            $userId,
            $details
        );
    }
    
    /**
     * Log payment events
     */
    public function logPayment(string $action, int $paymentId, float $amount, ?int $userId = null): void {
        $description = "Payment: $action - ID #$paymentId, Amount: PHP $amount";
        
        $this->log(
            "PAYMENT_" . strtoupper($action),
            'payment',
            $description,
            $userId,
            ['payment_id' => $paymentId, 'amount' => $amount]
        );
    }
    
    /**
     * Log admin actions
     */
    public function logAdmin(string $action, int $adminId, ?array $details = null): void {
        $description = "Admin Action: $action by admin #$adminId";
        
        $this->log(
            "ADMIN_" . strtoupper($action),
            'admin',
            $description,
            $adminId,
            $details
        );
    }
    
    /**
     * Log errors
     */
    public function logError(string $message, ?Exception $exception = null, ?int $userId = null): void {
        $description = "Error: $message";
        if ($exception) {
            $description .= " | Exception: " . $exception->getMessage();
            $description .= " | File: " . $exception->getFile() . ":" . $exception->getLine();
        }
        
        $this->log(
            "ERROR",
            'error',
            $description,
            $userId
        );
    }
    
    /**
     * Log system events
     */
    public function logSystem(string $event, string $description): void {
        $this->log(
            "SYSTEM_" . strtoupper($event),
            'system',
            $description
        );
    }
    
    /**
     * Log API requests
     */
    public function logApiRequest(string $method, string $endpoint, ?int $userId = null, ?array $requestData = null): void {
        $description = "API Request: $method $endpoint";
        
        $this->log(
            "API_REQUEST",
            'system',
            $description,
            $userId,
            $requestData
        );
    }
    
    /**
     * Write to log file
     */
    private function logToFile(string $type, string $category, string $message, ?int $userId = null): void {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $this->getClientIp();
        $userStr = $userId ? "User:$userId" : "Guest";
        
        $logLine = "[$timestamp] [$type] [$category] [$ip] [$userStr] $message" . PHP_EOL;
        
        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Get client IP address
     */
    private function getClientIp(): string {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs (for proxies)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Get recent logs (for admin)
     */
    public function getRecentLogs(int $limit = 100, ?string $category = null): array {
        $sql = "SELECT el.*, u.email as user_email 
                FROM event_logs el 
                LEFT JOIN users u ON el.user_id = u.id";
        
        $params = [];
        
        if ($category) {
            $sql .= " WHERE el.event_category = ?";
            $params[] = $category;
        }
        
        $sql .= " ORDER BY el.created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->db->prepare($sql, $params);
        return $stmt->fetchAll();
    }
}
