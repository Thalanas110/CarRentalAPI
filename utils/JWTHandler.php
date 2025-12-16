<?php
/**
 * JWT Handler
 * Generates and validates JWT tokens for authentication
 */

class JWTHandler {
    private static $secretKey = 'TDA_CarRental_Secret_Key_2024!@#$%^&*()_CHANGE_THIS_IN_PRODUCTION';
    private static $algorithm = 'HS256';
    private static $expirationHours = 24;
    
    /**
     * Generate JWT token
     */
    public static function generateToken(array $payload): string {
        $header = [
            'typ' => 'JWT',
            'alg' => self::$algorithm
        ];
        
        // Add standard claims
        $payload['iat'] = time(); // Issued at
        $payload['exp'] = time() + (self::$expirationHours * 3600); // Expiration
        $payload['iss'] = 'TDA_CarRental_API'; // Issuer
        
        $headerEncoded = self::base64UrlEncode(json_encode($header));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload));
        
        $signature = self::generateSignature($headerEncoded, $payloadEncoded);
        
        return "$headerEncoded.$payloadEncoded.$signature";
    }
    
    /**
     * Validate JWT token and return payload
     */
    public static function validateToken(string $token): ?array {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return null;
        }
        
        list($headerEncoded, $payloadEncoded, $signature) = $parts;
        
        // Verify signature
        $expectedSignature = self::generateSignature($headerEncoded, $payloadEncoded);
        
        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }
        
        // Decode payload
        $payload = json_decode(self::base64UrlDecode($payloadEncoded), true);
        
        if (!$payload) {
            return null;
        }
        
        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }
        
        return $payload;
    }
    
    /**
     * Get token from Authorization header
     */
    public static function getTokenFromHeader(): ?string {
        $headers = getallheaders();
        
        // Check various header formats
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        
        if (!$authHeader) {
            // Try to get from Apache
            if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
                $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            }
        }
        
        if (!$authHeader) {
            return null;
        }
        
        // Extract Bearer token
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return trim($matches[1]);
        }
        
        return null;
    }
    
    /**
     * Decode and get user from token
     */
    public static function getUserFromToken(): ?array {
        $token = self::getTokenFromHeader();
        
        if (!$token) {
            return null;
        }
        
        return self::validateToken($token);
    }
    
    /**
     * Check if current user is admin
     */
    public static function isAdmin(): bool {
        $user = self::getUserFromToken();
        return $user && isset($user['role']) && $user['role'] === 'admin';
    }
    
    /**
     * Get current user ID
     */
    public static function getUserId(): ?int {
        $user = self::getUserFromToken();
        return $user['user_id'] ?? null;
    }
    
    /**
     * Generate signature
     */
    private static function generateSignature(string $headerEncoded, string $payloadEncoded): string {
        $data = "$headerEncoded.$payloadEncoded";
        $signature = hash_hmac('sha256', $data, self::$secretKey, true);
        return self::base64UrlEncode($signature);
    }
    
    /**
     * Base64 URL encode
     */
    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Base64 URL decode
     */
    private static function base64UrlDecode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/'));
    }
    
    /**
     * Refresh token (generate new token with extended expiration)
     */
    public static function refreshToken(): ?string {
        $user = self::getUserFromToken();
        
        if (!$user) {
            return null;
        }
        
        // Remove old claims
        unset($user['iat'], $user['exp'], $user['iss']);
        
        return self::generateToken($user);
    }
}
