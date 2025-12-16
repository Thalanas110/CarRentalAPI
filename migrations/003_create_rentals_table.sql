-- Migration: Create rentals table
-- Rentals with chauffeur option and overtime tracking

CREATE TABLE IF NOT EXISTS rentals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    car_id INT NOT NULL,
    rental_type ENUM('self_drive', 'chauffeured') NOT NULL,
    start_time DATETIME NOT NULL,
    expected_end_time DATETIME NOT NULL,
    actual_end_time DATETIME NULL,
    duration_hours INT NOT NULL,
    base_price DECIMAL(10,2) NOT NULL,
    chauffeur_fee DECIMAL(10,2) DEFAULT 0,
    overtime_fee DECIMAL(10,2) DEFAULT 0,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    total_price DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'confirmed', 'active', 'completed', 'cancelled') DEFAULT 'pending',
    key_released BOOLEAN DEFAULT FALSE,
    key_released_at DATETIME NULL,
    key_returned BOOLEAN DEFAULT FALSE,
    key_returned_at DATETIME NULL,
    promo_id INT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_user (user_id),
    INDEX idx_car (car_id),
    INDEX idx_status (status),
    INDEX idx_dates (start_time, expected_end_time),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (car_id) REFERENCES cars(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
