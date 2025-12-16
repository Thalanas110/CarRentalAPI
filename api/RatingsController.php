<?php
/**
 * Ratings Controller
 * Handles user ratings for cars and service
 */

require_once __DIR__ . '/../models/Rating.php';
require_once __DIR__ . '/../models/Rental.php';
require_once __DIR__ . '/../models/Car.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class RatingsController {
    private $rating;
    private $rental;
    private $car;
    private $logger;
    
    public function __construct() {
        $this->rating = new Rating();
        $this->rental = new Rental();
        $this->car = new Car();
        $this->logger = Logger::getInstance();
    }
    
    /**
     * POST /api/ratings
     * Submit a rating for a completed rental
     */
    public function create(): void {
        $authUser = AuthMiddleware::requireAuth();
        $data = json_decode(file_get_contents('php://input'), true);
        
        $validator = new Validator($data ?? []);
        $validator
            ->required('rental_id', 'Rental')
            ->integer('rental_id', 'Rental ID')
            ->required('car_rating', 'Car Rating')
            ->integer('car_rating', 'Car Rating')
            ->min('car_rating', 1, 'Car Rating')
            ->max('car_rating', 5, 'Car Rating')
            ->required('service_rating', 'Service Rating')
            ->integer('service_rating', 'Service Rating')
            ->min('service_rating', 1, 'Service Rating')
            ->max('service_rating', 5, 'Service Rating');
        
        if (isset($data['comment'])) {
            $validator->maxLength('comment', 1000, 'Comment');
        }
        
        if (!$validator->isValid()) {
            Response::validationError($validator->getErrors());
        }
        
        // Verify rental
        $rental = $this->rental->findById($data['rental_id']);
        
        if (!$rental) {
            Response::notFound('Rental not found');
        }
        
        if ($rental['user_id'] != $authUser['user_id']) {
            Response::forbidden('You can only rate your own rentals');
        }
        
        if ($rental['status'] !== 'completed') {
            Response::error('You can only rate completed rentals', 400);
        }
        
        // Check if already rated
        if ($this->rating->existsForRental($data['rental_id'])) {
            Response::error('You have already rated this rental', 409);
        }
        
        try {
            $ratingId = $this->rating->create([
                'user_id' => $authUser['user_id'],
                'rental_id' => $data['rental_id'],
                'car_id' => $rental['car_id'],
                'car_rating' => $data['car_rating'],
                'service_rating' => $data['service_rating'],
                'comment' => $data['comment'] ?? null
            ]);
            
            $this->logger->log('RATING_CREATE', 'rental', 
                "Rating submitted for rental #{$data['rental_id']}", 
                $authUser['user_id']);
            
            Response::created([
                'rating_id' => $ratingId,
                'car_rating' => $data['car_rating'],
                'service_rating' => $data['service_rating']
            ], 'Rating submitted successfully');
            
        } catch (Exception $e) {
            $this->logger->logError('Rating creation failed', $e, $authUser['user_id']);
            Response::serverError('Failed to submit rating');
        }
    }
    
    /**
     * GET /api/ratings/car/{car_id}
     * Get ratings for a specific car
     */
    public function getByCar(int $carId): void {
        $car = $this->car->findById($carId);
        
        if (!$car) {
            Response::notFound('Car not found');
        }
        
        $ratings = $this->rating->getByCar($carId);
        $averages = $this->rating->getCarAverageRating($carId);
        
        Response::success([
            'car' => [
                'id' => $car['id'],
                'make' => $car['make'],
                'model' => $car['model']
            ],
            'averages' => $averages,
            'ratings' => $ratings
        ]);
    }
    
    /**
     * GET /api/ratings/my-ratings
     * Get ratings submitted by current user
     */
    public function myRatings(): void {
        $authUser = AuthMiddleware::requireAuth();
        
        $ratings = $this->rating->getByUser($authUser['user_id']);
        
        $this->logger->logApiRequest('GET', '/api/ratings/my-ratings', $authUser['user_id']);
        
        Response::success($ratings);
    }
    
    /**
     * PUT /api/ratings/{id}
     * Update a rating
     */
    public function update(int $id): void {
        $authUser = AuthMiddleware::requireAuth();
        $data = json_decode(file_get_contents('php://input'), true);
        
        $rating = $this->rating->findById($id);
        
        if (!$rating) {
            Response::notFound('Rating not found');
        }
        
        if ($rating['user_id'] != $authUser['user_id']) {
            Response::forbidden('You can only edit your own ratings');
        }
        
        $validator = new Validator($data ?? []);
        
        if (isset($data['car_rating'])) {
            $validator->integer('car_rating', 'Car Rating')
                      ->min('car_rating', 1, 'Car Rating')
                      ->max('car_rating', 5, 'Car Rating');
        }
        
        if (isset($data['service_rating'])) {
            $validator->integer('service_rating', 'Service Rating')
                      ->min('service_rating', 1, 'Service Rating')
                      ->max('service_rating', 5, 'Service Rating');
        }
        
        if (isset($data['comment'])) {
            $validator->maxLength('comment', 1000, 'Comment');
        }
        
        if (!$validator->isValid()) {
            Response::validationError($validator->getErrors());
        }
        
        $this->rating->update($id, $authUser['user_id'], $data);
        
        $this->logger->log('RATING_UPDATE', 'rental', 
            "Rating #$id updated", $authUser['user_id']);
        
        Response::success(null, 'Rating updated successfully');
    }
    
    /**
     * DELETE /api/ratings/{id}
     * Delete a rating
     */
    public function delete(int $id): void {
        $authUser = AuthMiddleware::requireAuth();
        
        $rating = $this->rating->findById($id);
        
        if (!$rating) {
            Response::notFound('Rating not found');
        }
        
        if ($rating['user_id'] != $authUser['user_id'] && $authUser['role'] !== 'admin') {
            Response::forbidden('You can only delete your own ratings');
        }
        
        $this->rating->delete($id, $rating['user_id']);
        
        $this->logger->log('RATING_DELETE', 'rental', 
            "Rating #$id deleted", $authUser['user_id']);
        
        Response::success(null, 'Rating deleted successfully');
    }
}
