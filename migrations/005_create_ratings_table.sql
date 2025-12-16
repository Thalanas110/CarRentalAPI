-- Migration: Create ratings table
-- User ratings for cars and service

CREATE TABLE IF NOT EXISTS ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    rental_id INT NOT NULL,
    car_id INT NOT NULL,
    car_rating TINYINT NOT NULL CHECK (car_rating >= 1 AND car_rating <= 5),
    service_rating TINYINT NOT NULL CHECK (service_rating >= 1 AND service_rating <= 5),
    comment TEXT,
    is_approved BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_user (user_id),
    INDEX idx_rental (rental_id),
    INDEX idx_car (car_id),
    INDEX idx_car_rating (car_rating),
    INDEX idx_service_rating (service_rating),
    
    UNIQUE KEY unique_rental_rating (rental_id),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (rental_id) REFERENCES rentals(id) ON DELETE CASCADE,
    FOREIGN KEY (car_id) REFERENCES cars(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
