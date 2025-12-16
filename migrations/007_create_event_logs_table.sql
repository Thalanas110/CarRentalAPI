-- Migration: Create event_logs table
-- Comprehensive logging for all events

CREATE TABLE IF NOT EXISTS event_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    event_type VARCHAR(100) NOT NULL,
    event_category ENUM('auth', 'rental', 'payment', 'admin', 'system', 'error') NOT NULL,
    event_description TEXT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    request_method VARCHAR(10),
    request_uri VARCHAR(500),
    request_data JSON,
    response_code INT,
    response_data JSON,
    execution_time_ms INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user (user_id),
    INDEX idx_type (event_type),
    INDEX idx_category (event_category),
    INDEX idx_created (created_at),
    INDEX idx_ip (ip_address),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
