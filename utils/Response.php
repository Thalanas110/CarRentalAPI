<?php
/**
 * Response Helper
 * Standardized JSON responses for the API
 */

class Response {
    
    /**
     * Send success response
     */
    public static function success($data = null, string $message = 'Success', int $code = 200): void {
        self::send([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $code);
    }
    
    /**
     * Send error response
     */
    public static function error(string $message = 'An error occurred', int $code = 400, $errors = null): void {
        $response = [
            'success' => false,
            'message' => $message
        ];
        
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        
        self::send($response, $code);
    }
    
    /**
     * Send unauthorized response
     */
    public static function unauthorized(string $message = 'Unauthorized access'): void {
        self::send([
            'success' => false,
            'message' => $message
        ], 401);
    }
    
    /**
     * Send forbidden response
     */
    public static function forbidden(string $message = 'Access forbidden'): void {
        self::send([
            'success' => false,
            'message' => $message
        ], 403);
    }
    
    /**
     * Send not found response
     */
    public static function notFound(string $message = 'Resource not found'): void {
        self::send([
            'success' => false,
            'message' => $message
        ], 404);
    }
    
    /**
     * Send validation error response
     */
    public static function validationError(array $errors, string $message = 'Validation failed'): void {
        self::send([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ], 422);
    }
    
    /**
     * Send server error response
     */
    public static function serverError(string $message = 'Internal server error'): void {
        self::send([
            'success' => false,
            'message' => $message
        ], 500);
    }
    
    /**
     * Send created response
     */
    public static function created($data = null, string $message = 'Resource created successfully'): void {
        self::send([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], 201);
    }
    
    /**
     * Send no content response
     */
    public static function noContent(): void {
        http_response_code(204);
        exit;
    }
    
    /**
     * Send paginated response
     */
    public static function paginated(array $data, int $total, int $page, int $perPage): void {
        $totalPages = ceil($total / $perPage);
        
        self::send([
            'success' => true,
            'message' => 'Success',
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'has_more' => $page < $totalPages
            ]
        ], 200);
    }
    
    /**
     * Send JSON response
     */
    private static function send(array $data, int $code): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}
