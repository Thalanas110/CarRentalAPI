<?php
/**
 * Rentals Controller
 * Handles rental creation, key release, and return
 */

require_once __DIR__ . '/../models/Rental.php';
require_once __DIR__ . '/../models/Car.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Promo.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../config/Database.php';

class RentalsController {
    private $rental;
    private $car;
    private $user;
    private $promo;
    private $logger;
    private $db;
    
    private const POINTS_PER_RENTAL = 10; // Points earned per completed rental
    
    public function __construct() {
        $this->rental = new Rental();
        $this->car = new Car();
        $this->user = new User();
        $this->promo = new Promo();
        $this->logger = Logger::getInstance();
        $this->db = Database::getInstance();
    }
    
    /**
     * POST /api/rentals
     * Create a new rental
     */
    public function create(): void {
        $authUser = AuthMiddleware::requireAuth();
        $data = json_decode(file_get_contents('php://input'), true);
        
        $validator = new Validator($data ?? []);
        $validator
            ->required('car_id', 'Car')
            ->integer('car_id', 'Car ID')
            ->required('rental_type', 'Rental Type')
            ->in('rental_type', ['self_drive', 'chauffeured'], 'Rental Type')
            ->required('start_time', 'Start Time')
            ->required('duration_hours', 'Duration')
            ->integer('duration_hours', 'Duration')
            ->min('duration_hours', 1, 'Duration');
        
        if (!$validator->isValid()) {
            Response::validationError($validator->getErrors());
        }
        
        // Get car details
        $car = $this->car->findById($data['car_id']);
        
        if (!$car) {
            Response::notFound('Car not found');
        }
        
        if (!$car['is_available'] || $car['is_rented']) {
            Response::error('Car is not available for rental', 409);
        }
        
        // Check if user has enough points for this car
        $userPoints = $this->user->getPoints($authUser['user_id']);
        
        if ($userPoints < $car['required_points']) {
            Response::error("You need {$car['required_points']} points to rent this car. You have $userPoints points.", 403);
        }
        
        // Calculate pricing
        $durationHours = (int) $data['duration_hours'];
        $basePrice = $car['price_per_hour'] * $durationHours;
        $chauffeurFee = $data['rental_type'] === 'chauffeured' ? ($car['chauffeur_fee'] * $durationHours) : 0;
        
        // Apply promo if provided
        $discountAmount = 0;
        $promoId = null;
        
        if (!empty($data['promo_code'])) {
            $promoValidation = $this->promo->validate(
                $data['promo_code'],
                $userPoints,
                $durationHours,
                $car['category']
            );
            
            if ($promoValidation['valid']) {
                $promo = $promoValidation['promo'];
                $discountAmount = $this->promo->calculateDiscount($promo, $basePrice + $chauffeurFee);
                $promoId = $promo['id'];
                
                // Increment promo usage
                $this->promo->incrementUsage($promoId);
            } else {
                Response::error($promoValidation['error'], 400);
            }
        }
        
        $totalPrice = $basePrice + $chauffeurFee - $discountAmount;
        
        // Calculate times
        $startTime = new DateTime($data['start_time']);
        $endTime = clone $startTime;
        $endTime->add(new DateInterval("PT{$durationHours}H"));
        
        // Start transaction
        $this->db->beginTransaction();
        
        try {
            // Create rental
            $rentalId = $this->rental->create([
                'user_id' => $authUser['user_id'],
                'car_id' => $data['car_id'],
                'rental_type' => $data['rental_type'],
                'start_time' => $startTime->format('Y-m-d H:i:s'),
                'expected_end_time' => $endTime->format('Y-m-d H:i:s'),
                'duration_hours' => $durationHours,
                'base_price' => $basePrice,
                'chauffeur_fee' => $chauffeurFee,
                'discount_amount' => $discountAmount,
                'total_price' => $totalPrice,
                'promo_id' => $promoId,
                'notes' => $data['notes'] ?? null
            ]);
            
            // Mark car as rented
            $this->car->setRented($data['car_id'], true);
            
            $this->db->commit();
            
            $this->logger->logTransaction('create', $rentalId, $authUser['user_id'], [
                'car_id' => $data['car_id'],
                'total_price' => $totalPrice
            ]);
            
            Response::created([
                'rental_id' => $rentalId,
                'car' => [
                    'id' => $car['id'],
                    'make' => $car['make'],
                    'model' => $car['model'],
                    'plate_number' => $car['plate_number']
                ],
                'rental_type' => $data['rental_type'],
                'start_time' => $startTime->format('Y-m-d H:i:s'),
                'expected_end_time' => $endTime->format('Y-m-d H:i:s'),
                'duration_hours' => $durationHours,
                'pricing' => [
                    'base_price' => $basePrice,
                    'chauffeur_fee' => $chauffeurFee,
                    'discount' => $discountAmount,
                    'total' => $totalPrice
                ],
                'status' => 'pending',
                'message' => 'Rental created. Please proceed to payment.'
            ], 'Rental created successfully');
            
        } catch (Exception $e) {
            $this->db->rollback();
            $this->logger->logError('Rental creation failed', $e, $authUser['user_id']);
            Response::serverError('Failed to create rental');
        }
    }
    
    /**
     * GET /api/rentals/{id}
     * Get rental details with current overtime
     */
    public function show(int $id): void {
        $authUser = AuthMiddleware::requireAuth();
        
        $rental = $this->rental->findById($id);
        
        if (!$rental) {
            Response::notFound('Rental not found');
        }
        
        // Check ownership or admin
        if ($rental['user_id'] != $authUser['user_id'] && $authUser['role'] !== 'admin') {
            Response::forbidden('You can only view your own rentals');
        }
        
        $this->logger->logApiRequest('GET', "/api/rentals/$id", $authUser['user_id']);
        
        Response::success($rental);
    }
    
    /**
     * GET /api/rentals/my-rentals
     * Get user's rental history
     */
    public function myRentals(): void {
        $authUser = AuthMiddleware::requireAuth();
        
        $rentals = $this->rental->getByUser($authUser['user_id']);
        
        $this->logger->logApiRequest('GET', '/api/rentals/my-rentals', $authUser['user_id']);
        
        Response::success($rentals);
    }
    
    /**
     * PUT /api/rentals/{id}/release-key
     * Release car key to renter (admin only)
     */
    public function releaseKey(int $id): void {
        $authUser = AuthMiddleware::requireAdmin();
        
        $rental = $this->rental->findById($id);
        
        if (!$rental) {
            Response::notFound('Rental not found');
        }
        
        if ($rental['key_released']) {
            Response::error('Key already released', 409);
        }
        
        if (!in_array($rental['status'], ['pending', 'confirmed'])) {
            Response::error('Cannot release key for this rental status', 400);
        }
        
        $this->rental->releaseKey($id);
        
        $this->logger->logAdmin('release_key', $authUser['user_id'], [
            'rental_id' => $id,
            'car_id' => $rental['car_id']
        ]);
        
        Response::success([
            'rental_id' => $id,
            'key_released' => true,
            'status' => 'active'
        ], 'Car key released to renter');
    }
    
    /**
     * PUT /api/rentals/{id}/return
     * Return car and calculate overtime
     */
    public function returnCar(int $id): void {
        $authUser = AuthMiddleware::requireAdmin();
        
        $rental = $this->rental->findById($id);
        
        if (!$rental) {
            Response::notFound('Rental not found');
        }
        
        if ($rental['status'] !== 'active') {
            Response::error('Only active rentals can be returned', 400);
        }
        
        // Process return (calculates overtime automatically)
        $result = $this->rental->returnCar($id);
        
        if (!$result) {
            Response::error('Failed to process return', 500);
        }
        
        // Mark car as available
        $this->car->setRented($rental['car_id'], false);
        
        // Award points to user
        $this->user->addPoints($rental['user_id'], self::POINTS_PER_RENTAL);
        
        $this->logger->logAdmin('return_car', $authUser['user_id'], [
            'rental_id' => $id,
            'overtime_fee' => $result['overtime_fee'],
            'total_price' => $result['total_price']
        ]);
        
        $this->logger->logTransaction('return', $id, $rental['user_id'], [
            'overtime_fee' => $result['overtime_fee'],
            'final_total' => $result['total_price']
        ]);
        
        Response::success([
            'rental_id' => $id,
            'overtime_fee' => $result['overtime_fee'],
            'total_price' => $result['total_price'],
            'status' => 'completed',
            'points_earned' => self::POINTS_PER_RENTAL
        ], 'Car returned successfully');
    }
    
    /**
     * PUT /api/rentals/{id}/cancel
     * Cancel a rental
     */
    public function cancel(int $id): void {
        $authUser = AuthMiddleware::requireAuth();
        
        $rental = $this->rental->findById($id);
        
        if (!$rental) {
            Response::notFound('Rental not found');
        }
        
        // User can cancel own pending/confirmed rentals, admin can cancel any
        if ($rental['user_id'] != $authUser['user_id'] && $authUser['role'] !== 'admin') {
            Response::forbidden('You can only cancel your own rentals');
        }
        
        if (!in_array($rental['status'], ['pending', 'confirmed'])) {
            Response::error('Only pending or confirmed rentals can be cancelled', 400);
        }
        
        $this->rental->cancel($id);
        
        // Mark car as available again
        $this->car->setRented($rental['car_id'], false);
        
        $this->logger->logTransaction('cancel', $id, $authUser['user_id']);
        
        Response::success([
            'rental_id' => $id,
            'status' => 'cancelled'
        ], 'Rental cancelled');
    }
}
