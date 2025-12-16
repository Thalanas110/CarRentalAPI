-- Migration: Create payments table
-- Payment records with multiple payment types

CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rental_id INT NOT NULL,
    payment_type ENUM('cash', 'credit_card', 'debit_card', 'gcash', 'maya', 'bank_transfer') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    reference_number VARCHAR(100),
    is_received BOOLEAN DEFAULT FALSE,
    received_at DATETIME NULL,
    received_by INT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_rental (rental_id),
    INDEX idx_received (is_received),
    INDEX idx_type (payment_type),
    
    FOREIGN KEY (rental_id) REFERENCES rentals(id) ON DELETE RESTRICT,
    FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
