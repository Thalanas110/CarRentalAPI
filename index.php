<?php
/**
 * TDA Car Rental API - Main Router
 * All API requests are routed through this file
 */

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Start session for timing
$startTime = microtime(true);

// Load utilities
require_once __DIR__ . '/utils/Response.php';
require_once __DIR__ . '/utils/Logger.php';

// Parse request
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Remove query string and base path
$basePath = '/TDACarRental';
$uri = parse_url($requestUri, PHP_URL_PATH);
$uri = str_replace($basePath, '', $uri);
$uri = trim($uri, '/');

// Split URI into parts
$parts = explode('/', $uri);

// Route mapping
try {
    // API routes must start with 'api'
    if ($parts[0] !== 'api') {
        Response::success([
            'name' => 'TDA Car Rental API',
            'version' => '1.0.0',
            'endpoints' => [
                'auth' => '/api/auth/*',
                'cars' => '/api/cars/*',
                'rentals' => '/api/rentals/*',
                'payments' => '/api/payments/*',
                'ratings' => '/api/ratings/*',
                'promos' => '/api/promos/*',
                'admin' => '/api/admin/*'
            ]
        ], 'Welcome to TDA Car Rental API');
    }
    
    // Get resource and action
    $resource = $parts[1] ?? '';
    $action = $parts[2] ?? '';
    $id = $parts[3] ?? null;
    
    // If action is numeric, treat as ID
    if (is_numeric($action)) {
        $id = $action;
        $action = '';
    }
    
    // Route to appropriate controller
    switch ($resource) {
        case 'auth':
            require_once __DIR__ . '/api/AuthController.php';
            $controller = new AuthController();
            
            switch ($action) {
                case 'register':
                    if ($requestMethod === 'POST') $controller->register();
                    break;
                case 'login':
                    if ($requestMethod === 'POST') $controller->login();
                    break;
                case 'logout':
                    if ($requestMethod === 'POST') $controller->logout();
                    break;
                case 'profile':
                    if ($requestMethod === 'GET') $controller->profile();
                    if ($requestMethod === 'PUT') $controller->updateProfile();
                    break;
                case 'refresh':
                    if ($requestMethod === 'POST') $controller->refreshToken();
                    break;
                default:
                    Response::notFound('Auth endpoint not found');
            }
            break;
            
        case 'cars':
            require_once __DIR__ . '/api/CarsController.php';
            $controller = new CarsController();
            
            if ($action === 'available') {
                $controller->available();
            } elseif ($action === 'unlocked') {
                $controller->unlocked();
            } elseif ($action === 'locked') {
                $controller->locked();
            } elseif ($action === 'category' && $id) {
                $controller->byCategory($id);
            } elseif ($id && $requestMethod === 'GET') {
                $controller->show((int)$id);
            } elseif (!$action && $requestMethod === 'GET') {
                $controller->index();
            } else {
                Response::notFound('Cars endpoint not found');
            }
            break;
            
        case 'rentals':
            require_once __DIR__ . '/api/RentalsController.php';
            $controller = new RentalsController();
            
            if ($action === 'my-rentals') {
                $controller->myRentals();
            } elseif ($id && $action === 'release-key' && $requestMethod === 'PUT') {
                $controller->releaseKey((int)$parts[2]);
            } elseif ($id && $action === 'return' && $requestMethod === 'PUT') {
                $controller->returnCar((int)$parts[2]);
            } elseif ($id && $action === 'cancel' && $requestMethod === 'PUT') {
                $controller->cancel((int)$parts[2]);
            } elseif (is_numeric($parts[2] ?? '') && ($parts[3] ?? '') === 'release-key') {
                $controller->releaseKey((int)$parts[2]);
            } elseif (is_numeric($parts[2] ?? '') && ($parts[3] ?? '') === 'return') {
                $controller->returnCar((int)$parts[2]);
            } elseif (is_numeric($parts[2] ?? '') && ($parts[3] ?? '') === 'cancel') {
                $controller->cancel((int)$parts[2]);
            } elseif ($id && $requestMethod === 'GET') {
                $controller->show((int)$id);
            } elseif (!$action && $requestMethod === 'POST') {
                $controller->create();
            } else {
                Response::notFound('Rentals endpoint not found');
            }
            break;
            
        case 'payments':
            require_once __DIR__ . '/api/PaymentsController.php';
            $controller = new PaymentsController();
            
            if ($action === 'my-payments') {
                $controller->myPayments();
            } elseif ($action === 'rental' && $id) {
                $controller->getByRental((int)$id);
            } elseif (is_numeric($parts[2] ?? '') && ($parts[3] ?? '') === 'confirm') {
                $controller->confirm((int)$parts[2]);
            } elseif (!$action && $requestMethod === 'POST') {
                $controller->create();
            } else {
                Response::notFound('Payments endpoint not found');
            }
            break;
            
        case 'ratings':
            require_once __DIR__ . '/api/RatingsController.php';
            $controller = new RatingsController();
            
            if ($action === 'my-ratings') {
                $controller->myRatings();
            } elseif ($action === 'car' && $id) {
                $controller->getByCar((int)$id);
            } elseif ($id && $requestMethod === 'PUT') {
                $controller->update((int)$id);
            } elseif ($id && $requestMethod === 'DELETE') {
                $controller->delete((int)$id);
            } elseif (!$action && $requestMethod === 'POST') {
                $controller->create();
            } else {
                Response::notFound('Ratings endpoint not found');
            }
            break;
            
        case 'promos':
            require_once __DIR__ . '/api/PromosController.php';
            $controller = new PromosController();
            
            if ($action === 'eligible') {
                $controller->eligible();
            } elseif ($action === 'validate' && $requestMethod === 'POST') {
                $controller->validate();
            } elseif ($action && $requestMethod === 'GET') {
                $controller->show($action);
            } elseif (!$action && $requestMethod === 'GET') {
                $controller->index();
            } else {
                Response::notFound('Promos endpoint not found');
            }
            break;
            
        case 'admin':
            require_once __DIR__ . '/api/AdminController.php';
            $controller = new AdminController();
            
            switch ($action) {
                case 'dashboard':
                    $controller->dashboard();
                    break;
                case 'transactions':
                    $controller->transactions();
                    break;
                case 'users':
                    $controller->users();
                    break;
                case 'logs':
                    $controller->logs();
                    break;
                case 'cars':
                    if ($id && $requestMethod === 'PUT') {
                        $controller->updateCar((int)$id);
                    } elseif ($requestMethod === 'POST') {
                        $controller->createCar();
                    } else {
                        $controller->allCars();
                    }
                    break;
                case 'rentals':
                    if ($id && ($parts[4] ?? '') === 'status') {
                        $controller->updateRentalStatus((int)$id);
                    } elseif ($action === 'active') {
                        $controller->activeRentals();
                    }
                    break;
                case 'payments':
                    if ($id && $requestMethod === 'PUT') {
                        $controller->updatePayment((int)$id);
                    } elseif ($action === 'pending') {
                        $controller->pendingPayments();
                    }
                    break;
                case 'promos':
                    if ($requestMethod === 'POST') {
                        $controller->createPromo();
                    }
                    break;
                default:
                    Response::notFound('Admin endpoint not found');
            }
            break;
            
        default:
            Response::notFound('Resource not found');
    }
    
} catch (PDOException $e) {
    Logger::getInstance()->logError('Database error', $e);
    Response::serverError('Database error occurred');
} catch (Exception $e) {
    Logger::getInstance()->logError('Server error', $e);
    Response::serverError('An error occurred');
}

// Log execution time
$executionTime = round((microtime(true) - $startTime) * 1000);
