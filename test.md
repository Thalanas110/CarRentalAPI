# TDA Car Rental API - Test Cases

## Setup

### 1. Create Database
```sql
CREATE DATABASE tda_car_rental;
USE tda_car_rental;
```

### 2. Run Migrations (in order)
```bash
# Execute each SQL file in migrations folder
mysql -u root -p tda_car_rental < migrations/001_create_users_table.sql
mysql -u root -p tda_car_rental < migrations/002_create_cars_table.sql
mysql -u root -p tda_car_rental < migrations/003_create_rentals_table.sql
mysql -u root -p tda_car_rental < migrations/004_create_payments_table.sql
mysql -u root -p tda_car_rental < migrations/005_create_ratings_table.sql
mysql -u root -p tda_car_rental < migrations/006_create_promos_table.sql
mysql -u root -p tda_car_rental < migrations/007_create_event_logs_table.sql
mysql -u root -p tda_car_rental < migrations/008_seed_admin_and_cars.sql
```

---

## Authentication Tests

### Register New User
```bash
curl -X POST http://localhost/TDACarRental/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "Test123!@#",
    "full_name": "Test User",
    "phone": "+639123456789"
  }'
```

**Expected:** 201 Created with user_id and token

### Login (Returns Car List)
```bash
curl -X POST http://localhost/TDACarRental/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "Test123!@#"
  }'
```

**Expected:** 200 OK with user, token, and cars (available/locked)

### Admin Login
```bash
curl -X POST http://localhost/TDACarRental/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "amdimate43@gmail.com",
    "password": "Dimate101%!"
  }'
```

**Expected:** 200 OK with admin token

### Get Profile (Auth Required)
```bash
curl -X GET http://localhost/TDACarRental/api/auth/profile \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

---

## Cars Tests

### List All Cars
```bash
curl -X GET http://localhost/TDACarRental/api/cars
```

### Get Specific Car
```bash
curl -X GET http://localhost/TDACarRental/api/cars/1
```

### Get Unlocked Cars (Auth)
```bash
curl -X GET http://localhost/TDACarRental/api/cars/unlocked \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

### Get Locked Cars (Auth)
```bash
curl -X GET http://localhost/TDACarRental/api/cars/locked \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

### Get Cars by Category
```bash
curl -X GET http://localhost/TDACarRental/api/cars/category/economy
curl -X GET http://localhost/TDACarRental/api/cars/category/premium
```

---

## Rentals Tests

### Create Rental (Self-Drive)
```bash
curl -X POST http://localhost/TDACarRental/api/rentals \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{
    "car_id": 1,
    "rental_type": "self_drive",
    "start_time": "2024-12-17 10:00:00",
    "duration_hours": 5
  }'
```

### Create Rental (Chauffeured with Promo)
```bash
curl -X POST http://localhost/TDACarRental/api/rentals \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{
    "car_id": 2,
    "rental_type": "chauffeured",
    "start_time": "2024-12-17 14:00:00",
    "duration_hours": 3,
    "promo_code": "WELCOME10"
  }'
```

### Get My Rentals
```bash
curl -X GET http://localhost/TDACarRental/api/rentals/my-rentals \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

### Get Rental Details (Shows Overtime)
```bash
curl -X GET http://localhost/TDACarRental/api/rentals/1 \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

### Release Key (Admin Only)
```bash
curl -X PUT http://localhost/TDACarRental/api/rentals/1/release-key \
  -H "Authorization: Bearer ADMIN_TOKEN_HERE"
```

### Return Car (Admin Only - Calculates Overtime)
```bash
curl -X PUT http://localhost/TDACarRental/api/rentals/1/return \
  -H "Authorization: Bearer ADMIN_TOKEN_HERE"
```

---

## Payments Tests

### Create Payment
```bash
curl -X POST http://localhost/TDACarRental/api/payments \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{
    "rental_id": 1,
    "payment_type": "gcash",
    "amount": 750.00,
    "reference_number": "GC123456789"
  }'
```

### Confirm Payment (Admin Only)
```bash
curl -X PUT http://localhost/TDACarRental/api/payments/1/confirm \
  -H "Authorization: Bearer ADMIN_TOKEN_HERE"
```

### Get Payments for Rental
```bash
curl -X GET http://localhost/TDACarRental/api/payments/rental/1 \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

---

## Ratings Tests

### Submit Rating
```bash
curl -X POST http://localhost/TDACarRental/api/ratings \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{
    "rental_id": 1,
    "car_rating": 5,
    "service_rating": 4,
    "comment": "Great car and excellent service!"
  }'
```

### Get Car Ratings
```bash
curl -X GET http://localhost/TDACarRental/api/ratings/car/1
```

### Get My Ratings
```bash
curl -X GET http://localhost/TDACarRental/api/ratings/my-ratings \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

---

## Promos Tests

### List Active Promos
```bash
curl -X GET http://localhost/TDACarRental/api/promos
```

### Get Eligible Promos (Auth)
```bash
curl -X GET http://localhost/TDACarRental/api/promos/eligible \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

### Validate Promo Code
```bash
curl -X POST http://localhost/TDACarRental/api/promos/validate \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{
    "code": "WELCOME10",
    "rental_hours": 5,
    "car_category": "economy",
    "base_price": 750
  }'
```

---

## Admin Tests

### Dashboard Stats
```bash
curl -X GET http://localhost/TDACarRental/api/admin/dashboard \
  -H "Authorization: Bearer ADMIN_TOKEN_HERE"
```

### View All Transactions
```bash
curl -X GET http://localhost/TDACarRental/api/admin/transactions \
  -H "Authorization: Bearer ADMIN_TOKEN_HERE"
```

### View All Users
```bash
curl -X GET http://localhost/TDACarRental/api/admin/users \
  -H "Authorization: Bearer ADMIN_TOKEN_HERE"
```

### View Event Logs
```bash
curl -X GET http://localhost/TDACarRental/api/admin/logs \
  -H "Authorization: Bearer ADMIN_TOKEN_HERE"

# Filter by category
curl -X GET "http://localhost/TDACarRental/api/admin/logs?category=auth" \
  -H "Authorization: Bearer ADMIN_TOKEN_HERE"
```

### Update Car Status
```bash
curl -X PUT http://localhost/TDACarRental/api/admin/cars/1 \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ADMIN_TOKEN_HERE" \
  -d '{
    "is_available": false
  }'
```

### Update Rental Status
```bash
curl -X PUT http://localhost/TDACarRental/api/admin/rentals/1/status \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ADMIN_TOKEN_HERE" \
  -d '{
    "status": "completed",
    "key_returned": true
  }'
```

### Create New Car
```bash
curl -X POST http://localhost/TDACarRental/api/admin/cars \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ADMIN_TOKEN_HERE" \
  -d '{
    "make": "Nissan",
    "model": "GT-R",
    "year": 2024,
    "plate_number": "NEW-1234",
    "category": "premium",
    "price_per_hour": 2000.00,
    "required_points": 500
  }'
```

### Create New Promo
```bash
curl -X POST http://localhost/TDACarRental/api/admin/promos \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ADMIN_TOKEN_HERE" \
  -d '{
    "code": "HOLIDAY30",
    "name": "Holiday Special",
    "discount_type": "percentage",
    "discount_value": 30,
    "valid_from": "2024-12-20 00:00:00",
    "valid_until": "2025-01-05 23:59:59"
  }'
```

---

## SQL Injection Attack Tests

All these attacks should FAIL because we use prepared statements.

### Login SQLi - Classic OR Attack
```bash
curl -X POST http://localhost/TDACarRental/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@test.com\" OR 1=1 --",
    "password": "anything"
  }'
```
**Expected:** 401 Unauthorized (attack blocked)

### Login SQLi - Union Attack
```bash
curl -X POST http://localhost/TDACarRental/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@test.com\" UNION SELECT * FROM users --",
    "password": "test"
  }'
```
**Expected:** 401 Unauthorized

### Login SQLi - Comment Attack
```bash
curl -X POST http://localhost/TDACarRental/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "amdimate43@gmail.com\"--",
    "password": "x"
  }'
```
**Expected:** 401 Unauthorized

### Registration SQLi
```bash
curl -X POST http://localhost/TDACarRental/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@test.com\"; DROP TABLE users; --",
    "password": "Test123!",
    "full_name": "Hacker"
  }'
```
**Expected:** 422 Validation Error (invalid email format)

### Car ID SQLi
```bash
curl -X GET "http://localhost/TDACarRental/api/cars/1;DROP TABLE cars"
```
**Expected:** 404 Not Found (integer parsing)

### Search Parameter SQLi
```bash
curl -X GET "http://localhost/TDACarRental/api/cars/category/economy' OR '1'='1"
```
**Expected:** 400 Invalid Category

### Payment SQLi
```bash
curl -X POST http://localhost/TDACarRental/api/payments \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "rental_id": "1; DELETE FROM payments;--",
    "payment_type": "cash",
    "amount": 500
  }'
```
**Expected:** 422 Validation Error

### Admin Log Filter SQLi
```bash
curl -X GET "http://localhost/TDACarRental/api/admin/logs?category=auth' OR '1'='1" \
  -H "Authorization: Bearer ADMIN_TOKEN"
```
**Expected:** Empty results (invalid category)

---

## Overtime Calculation Test

### Simulate Overdue Rental
1. Create rental with past expected_end_time:
```sql
-- After creating a rental, update it to simulate overtime
UPDATE rentals 
SET start_time = DATE_SUB(NOW(), INTERVAL 10 HOUR),
    expected_end_time = DATE_SUB(NOW(), INTERVAL 5 HOUR),
    status = 'active',
    key_released = TRUE
WHERE id = 1;
```

2. Check rental (should show overtime):
```bash
curl -X GET http://localhost/TDACarRental/api/rentals/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```
**Expected:** Response includes `current_overtime` (5 hours * 200 = 1000) and `overtime_hours: 5`

3. Return car and verify overtime fee:
```bash
curl -X PUT http://localhost/TDACarRental/api/rentals/1/return \
  -H "Authorization: Bearer ADMIN_TOKEN"
```
**Expected:** `overtime_fee: 1000` in response

---

## Points System Test

1. Complete a rental (admin returns car)
2. Check user profile - should have +10 points
3. Try to rent a car requiring points

```bash
# After rental completion
curl -X GET http://localhost/TDACarRental/api/auth/profile \
  -H "Authorization: Bearer YOUR_TOKEN"
```
**Expected:** `points: 10`

---

## Notes

- Replace `YOUR_TOKEN_HERE` with actual JWT token from login
- Replace `ADMIN_TOKEN_HERE` with admin JWT token
- All timestamps are in `Y-m-d H:i:s` format
- Overtime fee is 200 PHP per hour
- Points earned: 10 per completed rental
