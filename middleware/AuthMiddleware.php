<?php
/**
 * Authentication Middleware
 * Handles JWT verification and role-based access control
 */

require_once __DIR__ . '/../utils/JWTHandler.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Logger.php';

class AuthMiddleware {
    
    /**
     * Require authentication
     * Returns user data if authenticated, sends error response if not
     */
    public static function requireAuth(): array {
        $user = JWTHandler::getUserFromToken();
        
        if (!$user) {
            Logger::getInstance()->logAuth('access_denied', 'unknown', false);
            Response::unauthorized('Authentication required. Please login.');
        }
        
        return $user;
    }
    
    /**
     * Require admin role
     * Returns user data if admin, sends error response if not
     */
    public static function requireAdmin(): array {
        $user = self::requireAuth();
        
        if (!isset($user['role']) || $user['role'] !== 'admin') {
            Logger::getInstance()->logAuth('admin_access_denied', $user['email'] ?? 'unknown', false, $user['user_id'] ?? null);
            Response::forbidden('Admin access required');
        }
        
        return $user;
    }
    
    /**
     * Optional authentication
     * Returns user data if authenticated, null if not
     */
    public static function optionalAuth(): ?array {
        return JWTHandler::getUserFromToken();
    }
    
    /**
     * Check if current user is the owner of a resource
     */
    public static function requireOwnerOrAdmin(int $resourceOwnerId): array {
        $user = self::requireAuth();
        
        $isOwner = isset($user['user_id']) && $user['user_id'] == $resourceOwnerId;
        $isAdmin = isset($user['role']) && $user['role'] === 'admin';
        
        if (!$isOwner && !$isAdmin) {
            Logger::getInstance()->logAuth('ownership_denied', $user['email'] ?? 'unknown', false, $user['user_id'] ?? null);
            Response::forbidden('You do not have permission to access this resource');
        }
        
        return $user;
    }
    
    /**
     * Verify token and log the attempt
     */
    public static function verify(): bool {
        $user = JWTHandler::getUserFromToken();
        
        if ($user) {
            Logger::getInstance()->logAuth('token_verified', $user['email'] ?? 'unknown', true, $user['user_id'] ?? null);
            return true;
        }
        
        return false;
    }
}
