<?php
/**
 * Admin Controller
 * Handles admin dashboard and management (backend only)
 */

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Car.php';
require_once __DIR__ . '/../models/Rental.php';
require_once __DIR__ . '/../models/Payment.php';
require_once __DIR__ . '/../models/Rating.php';
require_once __DIR__ . '/../models/Promo.php';
require_once __DIR__ . '/../models/EventLog.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class AdminController {
    private $user;
    private $car;
    private $rental;
    private $payment;
    private $rating;
    private $promo;
    private $eventLog;
    private $logger;
    
    public function __construct() {
        $this->user = new User();
        $this->car = new Car();
        $this->rental = new Rental();
        $this->payment = new Payment();
        $this->rating = new Rating();
        $this->promo = new Promo();
        $this->eventLog = new EventLog();
        $this->logger = Logger::getInstance();
    }
    
    /**
     * GET /api/admin/dashboard
     */
    public function dashboard(): void {
        $admin = AuthMiddleware::requireAdmin();
        
        $stats = [
            'users' => ['total' => $this->user->getCount()],
            'cars' => [
                'total' => $this->car->getCount(),
                'available' => $this->car->getAvailableCount()
            ],
            'rentals' => $this->rental->getStatistics(),
            'payments' => $this->payment->getStatistics(),
            'ratings' => $this->rating->getStatistics(),
            'logs' => $this->eventLog->getStatistics()
        ];
        
        $this->logger->logAdmin('view_dashboard', $admin['user_id']);
        Response::success($stats);
    }
    
    /**
     * GET /api/admin/transactions
     */
    public function transactions(): void {
        $admin = AuthMiddleware::requireAdmin();
        $page = $_GET['page'] ?? 1;
        $limit = $_GET['limit'] ?? 50;
        $offset = ($page - 1) * $limit;
        
        $rentals = $this->rental->getAll($limit, $offset);
        $total = $this->rental->getCount();
        
        $this->logger->logAdmin('view_transactions', $admin['user_id']);
        Response::paginated($rentals, $total, $page, $limit);
    }
    
    /**
     * GET /api/admin/rentals/active
     */
    public function activeRentals(): void {
        $admin = AuthMiddleware::requireAdmin();
        Response::success($this->rental->getActive());
    }
    
    /**
     * PUT /api/admin/cars/{id}
     */
    public function updateCar(int $id): void {
        $admin = AuthMiddleware::requireAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        
        $car = $this->car->findById($id);
        if (!$car) Response::notFound('Car not found');
        
        $this->car->update($id, $data);
        $this->logger->logAdmin('update_car', $admin['user_id'], ['car_id' => $id]);
        Response::success($this->car->findById($id), 'Car updated');
    }
    
    /**
     * PUT /api/admin/rentals/{id}/status
     */
    public function updateRentalStatus(int $id): void {
        $admin = AuthMiddleware::requireAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['status'])) Response::error('Status is required');
        
        $rental = $this->rental->findById($id);
        if (!$rental) Response::notFound('Rental not found');
        
        $this->rental->updateStatus($id, $data['status']);
        
        // If marking as returned
        if (isset($data['key_returned'])) {
            $this->rental->setReturned($id, $data['key_returned']);
            if ($data['key_returned']) {
                $this->car->setRented($rental['car_id'], false);
            }
        }
        
        $this->logger->logAdmin('update_rental_status', $admin['user_id'], [
            'rental_id' => $id, 'status' => $data['status']
        ]);
        Response::success(null, 'Rental status updated');
    }
    
    /**
     * PUT /api/admin/payments/{id}
     */
    public function updatePayment(int $id): void {
        $admin = AuthMiddleware::requireAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        
        $payment = $this->payment->findById($id);
        if (!$payment) Response::notFound('Payment not found');
        
        if (isset($data['is_received']) && $data['is_received']) {
            $this->payment->confirmReceived($id, $admin['user_id']);
        } else {
            $this->payment->update($id, $data);
        }
        
        $this->logger->logAdmin('update_payment', $admin['user_id'], ['payment_id' => $id]);
        Response::success(null, 'Payment updated');
    }
    
    /**
     * GET /api/admin/logs
     */
    public function logs(): void {
        $admin = AuthMiddleware::requireAdmin();
        $category = $_GET['category'] ?? null;
        $limit = $_GET['limit'] ?? 100;
        
        $logs = $this->eventLog->getAll($limit, 0, $category);
        Response::success($logs);
    }
    
    /**
     * GET /api/admin/users
     */
    public function users(): void {
        $admin = AuthMiddleware::requireAdmin();
        $users = $this->user->getAll();
        Response::success($users);
    }
    
    /**
     * POST /api/admin/promos
     */
    public function createPromo(): void {
        $admin = AuthMiddleware::requireAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        
        $validator = new Validator($data ?? []);
        $validator
            ->required('code', 'Code')
            ->required('name', 'Name')
            ->required('discount_value', 'Discount Value')
            ->required('valid_from', 'Valid From')
            ->required('valid_until', 'Valid Until');
        
        if (!$validator->isValid()) {
            Response::validationError($validator->getErrors());
        }
        
        $promoId = $this->promo->create($data);
        $this->logger->logAdmin('create_promo', $admin['user_id'], ['promo_id' => $promoId]);
        Response::created(['promo_id' => $promoId], 'Promo created');
    }
    
    /**
     * POST /api/admin/cars
     */
    public function createCar(): void {
        $admin = AuthMiddleware::requireAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        
        $validator = new Validator($data ?? []);
        $validator
            ->required('make', 'Make')
            ->required('model', 'Model')
            ->required('year', 'Year')
            ->required('plate_number', 'Plate Number')
            ->required('price_per_hour', 'Price Per Hour');
        
        if (!$validator->isValid()) {
            Response::validationError($validator->getErrors());
        }
        
        $carId = $this->car->create($data);
        $this->logger->logAdmin('create_car', $admin['user_id'], ['car_id' => $carId]);
        Response::created(['car_id' => $carId], 'Car added');
    }
    
    /**
     * GET /api/admin/payments/pending
     */
    public function pendingPayments(): void {
        AuthMiddleware::requireAdmin();
        Response::success($this->payment->getPending());
    }
    
    /**
     * GET /api/admin/cars
     */
    public function allCars(): void {
        AuthMiddleware::requireAdmin();
        Response::success($this->car->getAll());
    }
}
