<?php
/**
 * Cars Controller
 * Handles car listing and details
 */

require_once __DIR__ . '/../models/Car.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Rating.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class CarsController {
    private $car;
    private $user;
    private $rating;
    private $logger;
    
    public function __construct() {
        $this->car = new Car();
        $this->user = new User();
        $this->rating = new Rating();
        $this->logger = Logger::getInstance();
    }
    
    /**
     * GET /api/cars
     * List all available cars (for authenticated users, filter by points)
     */
    public function index(): void {
        $authUser = AuthMiddleware::optionalAuth();
        
        if ($authUser) {
            // Authenticated user - filter by points
            $userPoints = $this->user->getPoints($authUser['user_id']);
            $availableCars = $this->car->getAvailableForUser($userPoints);
            $lockedCars = $this->car->getLockedCars($userPoints);
            
            $this->logger->logApiRequest('GET', '/api/cars', $authUser['user_id']);
            
            Response::success([
                'available' => $availableCars,
                'locked' => $lockedCars,
                'user_points' => $userPoints
            ]);
        } else {
            // Guest - show all available cars
            $cars = $this->car->getAvailable();
            
            $this->logger->logApiRequest('GET', '/api/cars', null);
            
            Response::success([
                'cars' => $cars,
                'message' => 'Login to see cars available for your points level'
            ]);
        }
    }
    
    /**
     * GET /api/cars/{id}
     * Get car details with ratings
     */
    public function show(int $id): void {
        $authUser = AuthMiddleware::optionalAuth();
        
        $car = $this->car->getWithRating($id);
        
        if (!$car) {
            Response::notFound('Car not found');
        }
        
        // Get car ratings
        $ratings = $this->rating->getByCar($id);
        
        // Check if user can rent this car
        $canRent = false;
        $pointsNeeded = 0;
        
        if ($authUser) {
            $userPoints = $this->user->getPoints($authUser['user_id']);
            $canRent = $this->car->canUserRent($id, $userPoints);
            
            if (!$canRent) {
                $pointsNeeded = $car['required_points'] - $userPoints;
            }
            
            $this->logger->logApiRequest('GET', "/api/cars/$id", $authUser['user_id']);
        }
        
        Response::success([
            'car' => $car,
            'ratings' => $ratings,
            'can_rent' => $canRent,
            'points_needed' => $pointsNeeded
        ]);
    }
    
    /**
     * GET /api/cars/available
     * Get only available cars (not rented)
     */
    public function available(): void {
        $authUser = AuthMiddleware::optionalAuth();
        
        $cars = $this->car->getAvailable();
        
        $this->logger->logApiRequest('GET', '/api/cars/available', $authUser['user_id'] ?? null);
        
        Response::success($cars);
    }
    
    /**
     * GET /api/cars/category/{category}
     * Get cars by category
     */
    public function byCategory(string $category): void {
        $validCategories = ['economy', 'standard', 'luxury', 'premium'];
        
        if (!in_array($category, $validCategories)) {
            Response::error('Invalid category. Must be one of: ' . implode(', ', $validCategories));
        }
        
        $cars = $this->car->getByCategory($category);
        
        Response::success([
            'category' => $category,
            'cars' => $cars
        ]);
    }
    
    /**
     * GET /api/cars/unlocked
     * Get cars unlocked for user based on points
     */
    public function unlocked(): void {
        $authUser = AuthMiddleware::requireAuth();
        
        $userPoints = $this->user->getPoints($authUser['user_id']);
        $cars = $this->car->getAvailableForUser($userPoints);
        
        $this->logger->logApiRequest('GET', '/api/cars/unlocked', $authUser['user_id']);
        
        Response::success([
            'user_points' => $userPoints,
            'cars' => $cars
        ]);
    }
    
    /**
     * GET /api/cars/locked
     * Get cars locked for user (need more points)
     */
    public function locked(): void {
        $authUser = AuthMiddleware::requireAuth();
        
        $userPoints = $this->user->getPoints($authUser['user_id']);
        $cars = $this->car->getLockedCars($userPoints);
        
        // Add points needed for each car
        foreach ($cars as &$car) {
            $car['points_needed'] = $car['required_points'] - $userPoints;
        }
        
        $this->logger->logApiRequest('GET', '/api/cars/locked', $authUser['user_id']);
        
        Response::success([
            'user_points' => $userPoints,
            'cars' => $cars
        ]);
    }
}
