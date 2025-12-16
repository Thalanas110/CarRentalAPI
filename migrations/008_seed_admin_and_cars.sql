-- Migration: Seed admin user and sample cars
-- Admin: amdimate43@gmail.com / Dimate101%!
-- Password hash generated with password_hash('Dimate101%!', PASSWORD_BCRYPT)

-- Insert Admin User
INSERT INTO users (email, password, full_name, phone, points, role) VALUES
('amdimate43@gmail.com', '$2y$10$8K1p/HazV.P0O3YB8UPAgeXHVB6WZk7WKwXdMFpT0G.6K.fGy0Whi', 'Admin Dimate', '+639123456789', 9999, 'admin');

-- Insert Sample Cars
INSERT INTO cars (make, model, year, plate_number, category, price_per_hour, chauffeur_fee, required_points, description, seats, transmission, fuel_type) VALUES
-- Economy Cars (No points required)
('Toyota', 'Vios', 2023, 'ABC-1234', 'economy', 150.00, 100.00, 0, 'Fuel-efficient sedan perfect for city driving', 5, 'automatic', 'gasoline'),
('Honda', 'City', 2023, 'DEF-5678', 'economy', 160.00, 100.00, 0, 'Comfortable compact sedan with great mileage', 5, 'automatic', 'gasoline'),
('Suzuki', 'Swift', 2022, 'GHI-9012', 'economy', 140.00, 100.00, 0, 'Sporty hatchback for everyday use', 5, 'manual', 'gasoline'),

-- Standard Cars (50 points required)
('Toyota', 'Camry', 2023, 'JKL-3456', 'standard', 250.00, 150.00, 50, 'Premium midsize sedan with advanced features', 5, 'automatic', 'gasoline'),
('Honda', 'Accord', 2023, 'MNO-7890', 'standard', 260.00, 150.00, 50, 'Executive sedan with superior comfort', 5, 'automatic', 'gasoline'),
('Mazda', 'CX-5', 2023, 'PQR-1357', 'standard', 280.00, 150.00, 50, 'Versatile SUV for family adventures', 5, 'automatic', 'gasoline'),

-- Luxury Cars (200 points required)
('BMW', '3 Series', 2024, 'STU-2468', 'luxury', 500.00, 250.00, 200, 'German engineering at its finest', 5, 'automatic', 'gasoline'),
('Mercedes-Benz', 'C-Class', 2024, 'VWX-1593', 'luxury', 520.00, 250.00, 200, 'Luxury and performance combined', 5, 'automatic', 'gasoline'),
('Audi', 'A4', 2024, 'YZA-7531', 'luxury', 480.00, 250.00, 200, 'Sophisticated design with quattro AWD', 5, 'automatic', 'gasoline'),

-- Premium Cars (500 points required - High-end vehicles)
('Porsche', '911 Carrera', 2024, 'BCD-9876', 'premium', 1500.00, 500.00, 500, 'Iconic sports car for the ultimate driving experience', 2, 'automatic', 'gasoline'),
('Lamborghini', 'Huracan', 2024, 'EFG-5432', 'premium', 3000.00, 800.00, 500, 'Supercar performance and head-turning style', 2, 'automatic', 'gasoline'),
('Ferrari', 'Roma', 2024, 'HIJ-1098', 'premium', 3500.00, 800.00, 500, 'Italian excellence in automotive art', 2, 'automatic', 'gasoline'),
('Tesla', 'Model S Plaid', 2024, 'KLM-7654', 'premium', 1200.00, 400.00, 500, 'Electric performance with zero emissions', 5, 'automatic', 'electric');

-- Insert Sample Promos
INSERT INTO promos (code, name, description, discount_type, discount_value, max_discount, min_rental_hours, min_points_required, valid_from, valid_until, applicable_categories) VALUES
('WELCOME10', 'Welcome Discount', '10% off for new customers', 'percentage', 10.00, 500.00, 1, 0, '2024-01-01 00:00:00', '2025-12-31 23:59:59', '["economy", "standard", "luxury", "premium"]'),
('LOYAL20', 'Loyalty Reward', '20% off for loyal customers with 100+ points', 'percentage', 20.00, 1000.00, 3, 100, '2024-01-01 00:00:00', '2025-12-31 23:59:59', '["economy", "standard", "luxury"]'),
('WEEKEND500', 'Weekend Special', 'P500 off weekend rentals', 'fixed', 500.00, NULL, 24, 0, '2024-01-01 00:00:00', '2025-12-31 23:59:59', '["economy", "standard"]'),
('VIP50', 'VIP Exclusive', '50% off for VIP members', 'percentage', 50.00, 5000.00, 1, 500, '2024-01-01 00:00:00', '2025-12-31 23:59:59', '["luxury", "premium"]');
