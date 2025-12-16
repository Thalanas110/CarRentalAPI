<?php
/**
 * Promos Controller
 * Handles promotional codes
 */

require_once __DIR__ . '/../models/Promo.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class PromosController {
    private $promo;
    private $user;
    private $logger;
    
    public function __construct() {
        $this->promo = new Promo();
        $this->user = new User();
        $this->logger = Logger::getInstance();
    }
    
    /**
     * GET /api/promos
     */
    public function index(): void {
        Response::success($this->promo->getActive());
    }
    
    /**
     * GET /api/promos/eligible
     */
    public function eligible(): void {
        $authUser = AuthMiddleware::requireAuth();
        $userPoints = $this->user->getPoints($authUser['user_id']);
        
        Response::success([
            'user_points' => $userPoints,
            'promos' => $this->promo->getEligibleForUser($userPoints)
        ]);
    }
    
    /**
     * POST /api/promos/validate
     */
    public function validate(): void {
        $authUser = AuthMiddleware::requireAuth();
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['code'])) {
            Response::error('Promo code is required');
        }
        
        $userPoints = $this->user->getPoints($authUser['user_id']);
        $result = $this->promo->validate(
            $data['code'], 
            $userPoints, 
            $data['rental_hours'] ?? 1, 
            $data['car_category'] ?? 'standard'
        );
        
        if ($result['valid']) {
            $promo = $result['promo'];
            $discount = $this->promo->calculateDiscount($promo, $data['base_price'] ?? 1000);
            
            Response::success([
                'valid' => true,
                'promo' => $promo,
                'estimated_discount' => $discount
            ]);
        } else {
            Response::error($result['error'], 400);
        }
    }
    
    /**
     * GET /api/promos/{code}
     */
    public function show(string $code): void {
        $promo = $this->promo->findByCode($code);
        if (!$promo) Response::notFound('Promo not found');
        Response::success($promo);
    }
}
