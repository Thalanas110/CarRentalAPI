-- Migration: Create cars table
-- Cars with categories and points requirements for premium vehicles

CREATE TABLE IF NOT EXISTS cars (
    id INT AUTO_INCREMENT PRIMARY KEY,
    make VARCHAR(100) NOT NULL,
    model VARCHAR(100) NOT NULL,
    year INT NOT NULL,
    plate_number VARCHAR(20) UNIQUE NOT NULL,
    category ENUM('economy', 'standard', 'luxury', 'premium') DEFAULT 'standard',
    price_per_hour DECIMAL(10,2) NOT NULL,
    chauffeur_fee DECIMAL(10,2) DEFAULT 100.00,
    is_available BOOLEAN DEFAULT TRUE,
    is_rented BOOLEAN DEFAULT FALSE,
    required_points INT DEFAULT 0,
    description TEXT,
    image_url VARCHAR(500),
    seats INT DEFAULT 5,
    transmission ENUM('automatic', 'manual') DEFAULT 'automatic',
    fuel_type ENUM('gasoline', 'diesel', 'electric', 'hybrid') DEFAULT 'gasoline',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_category (category),
    INDEX idx_available (is_available),
    INDEX idx_rented (is_rented),
    INDEX idx_points (required_points)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
