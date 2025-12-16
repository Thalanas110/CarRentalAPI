<?php
/**
 * Auth Controller
 * Handles registration, login, logout, and profile
 */

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Car.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/JWTHandler.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class AuthController {
    private $user;
    private $car;
    private $logger;
    
    public function __construct() {
        $this->user = new User();
        $this->car = new Car();
        $this->logger = Logger::getInstance();
    }
    
    /**
     * POST /api/auth/register
     * Register a new user
     */
    public function register(): void {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $validator = new Validator($data ?? []);
        $validator
            ->required('email', 'Email')
            ->email('email', 'Email')
            ->required('password', 'Password')
            ->password('password', 'Password')
            ->required('full_name', 'Full Name')
            ->minLength('full_name', 2, 'Full Name')
            ->maxLength('full_name', 255, 'Full Name');
        
        if (isset($data['phone'])) {
            $validator->phone('phone', 'Phone');
        }
        
        if (!$validator->isValid()) {
            $this->logger->logAuth('register', $data['email'] ?? 'unknown', false);
            Response::validationError($validator->getErrors());
        }
        
        // Check if email exists
        if ($this->user->emailExists($data['email'])) {
            $this->logger->logAuth('register', $data['email'], false);
            Response::error('Email already registered', 409);
        }
        
        // Create user
        try {
            $userId = $this->user->create([
                'email' => $data['email'],
                'password' => $data['password'],
                'full_name' => $data['full_name'],
                'phone' => $data['phone'] ?? null
            ]);
            
            $this->logger->logAuth('register', $data['email'], true, $userId);
            
            // Generate token
            $token = JWTHandler::generateToken([
                'user_id' => $userId,
                'email' => $data['email'],
                'role' => 'user'
            ]);
            
            Response::created([
                'user_id' => $userId,
                'email' => $data['email'],
                'token' => $token
            ], 'Registration successful');
            
        } catch (Exception $e) {
            $this->logger->logError('Registration failed', $e);
            Response::serverError('Registration failed');
        }
    }
    
    /**
     * POST /api/auth/login
     * Login and return token + available cars
     */
    public function login(): void {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $validator = new Validator($data ?? []);
        $validator
            ->required('email', 'Email')
            ->email('email', 'Email')
            ->required('password', 'Password');
        
        if (!$validator->isValid()) {
            $this->logger->logAuth('login', $data['email'] ?? 'unknown', false);
            Response::validationError($validator->getErrors());
        }
        
        // Find user
        $user = $this->user->findByEmail($data['email']);
        
        if (!$user) {
            $this->logger->logAuth('login', $data['email'], false);
            Response::error('Invalid email or password', 401);
        }
        
        // Verify password
        if (!$this->user->verifyPassword($data['password'], $user['password'])) {
            $this->logger->logAuth('login', $data['email'], false);
            Response::error('Invalid email or password', 401);
        }
        
        $this->logger->logAuth('login', $data['email'], true, $user['id']);
        
        // Generate token
        $token = JWTHandler::generateToken([
            'user_id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'points' => $user['points']
        ]);
        
        // Get available cars based on user points
        $availableCars = $this->car->getAvailableForUser($user['points']);
        $lockedCars = $this->car->getLockedCars($user['points']);
        
        Response::success([
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'points' => $user['points'],
                'role' => $user['role']
            ],
            'token' => $token,
            'cars' => [
                'available' => $availableCars,
                'locked' => $lockedCars
            ]
        ], 'Login successful');
    }
    
    /**
     * POST /api/auth/logout
     * Logout (invalidate token on client side)
     */
    public function logout(): void {
        $user = AuthMiddleware::optionalAuth();
        
        if ($user) {
            $this->logger->logAuth('logout', $user['email'], true, $user['user_id']);
        }
        
        Response::success(null, 'Logged out successfully');
    }
    
    /**
     * GET /api/auth/profile
     * Get current user profile
     */
    public function profile(): void {
        $authUser = AuthMiddleware::requireAuth();
        
        $user = $this->user->findById($authUser['user_id']);
        
        if (!$user) {
            Response::notFound('User not found');
        }
        
        $this->logger->logApiRequest('GET', '/api/auth/profile', $authUser['user_id']);
        
        Response::success([
            'id' => $user['id'],
            'email' => $user['email'],
            'full_name' => $user['full_name'],
            'phone' => $user['phone'],
            'points' => $user['points'],
            'role' => $user['role'],
            'created_at' => $user['created_at']
        ]);
    }
    
    /**
     * PUT /api/auth/profile
     * Update current user profile
     */
    public function updateProfile(): void {
        $authUser = AuthMiddleware::requireAuth();
        $data = json_decode(file_get_contents('php://input'), true);
        
        $validator = new Validator($data ?? []);
        
        if (isset($data['full_name'])) {
            $validator->minLength('full_name', 2, 'Full Name')
                      ->maxLength('full_name', 255, 'Full Name');
        }
        
        if (isset($data['phone'])) {
            $validator->phone('phone', 'Phone');
        }
        
        if (isset($data['password'])) {
            $validator->password('password', 'Password');
        }
        
        if (!$validator->isValid()) {
            Response::validationError($validator->getErrors());
        }
        
        try {
            $this->user->update($authUser['user_id'], $data);
            
            $this->logger->log('PROFILE_UPDATE', 'auth', 'Profile updated', $authUser['user_id']);
            
            $updatedUser = $this->user->findById($authUser['user_id']);
            
            Response::success([
                'id' => $updatedUser['id'],
                'email' => $updatedUser['email'],
                'full_name' => $updatedUser['full_name'],
                'phone' => $updatedUser['phone'],
                'points' => $updatedUser['points']
            ], 'Profile updated successfully');
            
        } catch (Exception $e) {
            $this->logger->logError('Profile update failed', $e, $authUser['user_id']);
            Response::serverError('Failed to update profile');
        }
    }
    
    /**
     * POST /api/auth/refresh
     * Refresh JWT token
     */
    public function refreshToken(): void {
        $newToken = JWTHandler::refreshToken();
        
        if (!$newToken) {
            Response::unauthorized('Invalid or expired token');
        }
        
        Response::success(['token' => $newToken], 'Token refreshed');
    }
}
