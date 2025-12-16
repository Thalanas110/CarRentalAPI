<?php
/**
 * Input Validator
 * Validates and sanitizes user input to prevent injection attacks
 */

class Validator {
    private $errors = [];
    private $data = [];
    
    /**
     * Create validator with input data
     */
    public function __construct(array $data) {
        $this->data = $data;
    }
    
    /**
     * Check if field is required
     */
    public function required(string $field, string $label = null): self {
        $label = $label ?? $field;
        
        if (!isset($this->data[$field]) || trim($this->data[$field]) === '') {
            $this->errors[$field] = "$label is required";
        }
        
        return $this;
    }
    
    /**
     * Validate email format
     */
    public function email(string $field, string $label = null): self {
        $label = $label ?? $field;
        
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (!filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
                $this->errors[$field] = "$label must be a valid email address";
            }
        }
        
        return $this;
    }
    
    /**
     * Validate minimum length
     */
    public function minLength(string $field, int $min, string $label = null): self {
        $label = $label ?? $field;
        
        if (isset($this->data[$field]) && strlen($this->data[$field]) < $min) {
            $this->errors[$field] = "$label must be at least $min characters";
        }
        
        return $this;
    }
    
    /**
     * Validate maximum length
     */
    public function maxLength(string $field, int $max, string $label = null): self {
        $label = $label ?? $field;
        
        if (isset($this->data[$field]) && strlen($this->data[$field]) > $max) {
            $this->errors[$field] = "$label must not exceed $max characters";
        }
        
        return $this;
    }
    
    /**
     * Validate integer
     */
    public function integer(string $field, string $label = null): self {
        $label = $label ?? $field;
        
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (!filter_var($this->data[$field], FILTER_VALIDATE_INT)) {
                $this->errors[$field] = "$label must be an integer";
            }
        }
        
        return $this;
    }
    
    /**
     * Validate numeric (int or float)
     */
    public function numeric(string $field, string $label = null): self {
        $label = $label ?? $field;
        
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (!is_numeric($this->data[$field])) {
                $this->errors[$field] = "$label must be a number";
            }
        }
        
        return $this;
    }
    
    /**
     * Validate minimum value
     */
    public function min(string $field, float $min, string $label = null): self {
        $label = $label ?? $field;
        
        if (isset($this->data[$field]) && is_numeric($this->data[$field])) {
            if ((float)$this->data[$field] < $min) {
                $this->errors[$field] = "$label must be at least $min";
            }
        }
        
        return $this;
    }
    
    /**
     * Validate maximum value
     */
    public function max(string $field, float $max, string $label = null): self {
        $label = $label ?? $field;
        
        if (isset($this->data[$field]) && is_numeric($this->data[$field])) {
            if ((float)$this->data[$field] > $max) {
                $this->errors[$field] = "$label must not exceed $max";
            }
        }
        
        return $this;
    }
    
    /**
     * Validate value is in a set of allowed values
     */
    public function in(string $field, array $allowed, string $label = null): self {
        $label = $label ?? $field;
        
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (!in_array($this->data[$field], $allowed)) {
                $this->errors[$field] = "$label must be one of: " . implode(', ', $allowed);
            }
        }
        
        return $this;
    }
    
    /**
     * Validate date format
     */
    public function date(string $field, string $format = 'Y-m-d', string $label = null): self {
        $label = $label ?? $field;
        
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $d = DateTime::createFromFormat($format, $this->data[$field]);
            if (!$d || $d->format($format) !== $this->data[$field]) {
                $this->errors[$field] = "$label must be a valid date in format $format";
            }
        }
        
        return $this;
    }
    
    /**
     * Validate datetime format
     */
    public function datetime(string $field, string $format = 'Y-m-d H:i:s', string $label = null): self {
        $label = $label ?? $field;
        
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $d = DateTime::createFromFormat($format, $this->data[$field]);
            if (!$d || $d->format($format) !== $this->data[$field]) {
                $this->errors[$field] = "$label must be a valid datetime in format $format";
            }
        }
        
        return $this;
    }
    
    /**
     * Validate password strength
     */
    public function password(string $field, string $label = null): self {
        $label = $label ?? $field;
        
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $password = $this->data[$field];
            
            if (strlen($password) < 8) {
                $this->errors[$field] = "$label must be at least 8 characters";
            } elseif (!preg_match('/[A-Z]/', $password)) {
                $this->errors[$field] = "$label must contain at least one uppercase letter";
            } elseif (!preg_match('/[a-z]/', $password)) {
                $this->errors[$field] = "$label must contain at least one lowercase letter";
            } elseif (!preg_match('/[0-9]/', $password)) {
                $this->errors[$field] = "$label must contain at least one number";
            } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
                $this->errors[$field] = "$label must contain at least one special character";
            }
        }
        
        return $this;
    }
    
    /**
     * Validate phone number
     */
    public function phone(string $field, string $label = null): self {
        $label = $label ?? $field;
        
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $phone = preg_replace('/[^0-9+]/', '', $this->data[$field]);
            if (strlen($phone) < 10 || strlen($phone) > 15) {
                $this->errors[$field] = "$label must be a valid phone number";
            }
        }
        
        return $this;
    }
    
    /**
     * Check if validation passed
     */
    public function isValid(): bool {
        return empty($this->errors);
    }
    
    /**
     * Get validation errors
     */
    public function getErrors(): array {
        return $this->errors;
    }
    
    /**
     * Get single sanitized value
     */
    public function getValue(string $field, $default = null) {
        return isset($this->data[$field]) ? self::sanitize($this->data[$field]) : $default;
    }
    
    /**
     * Get all sanitized data
     */
    public function getSanitizedData(): array {
        $sanitized = [];
        foreach ($this->data as $key => $value) {
            $sanitized[$key] = self::sanitize($value);
        }
        return $sanitized;
    }
    
    /**
     * Sanitize a string value
     * @param mixed $value
     * @return string|array
     */
    public static function sanitize($value) {
        if (is_array($value)) {
            return array_map([self::class, 'sanitize'], $value);
        }
        
        // Handle null values
        if ($value === null) {
            return '';
        }
        
        // Remove any null bytes
        $value = str_replace(chr(0), '', (string)$value);
        
        // Trim whitespace
        $value = trim($value);
        
        // Convert special characters to HTML entities
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        
        return $value;
    }
    
    /**
     * Sanitize for SQL (additional layer, but use prepared statements!)
     */
    public static function sanitizeForSql($value): string {
        // Note: Always use prepared statements! This is just an additional safety layer.
        $value = self::sanitize($value);
        
        // Remove potential SQL injection patterns
        $sqlPatterns = [
            '/(\%27)|(\')|(\-\-)|(\%23)|(#)/i',
            '/(\%22)|(\")/i',
            '/(\%27)|(\')|(--)|(\%23)|(#)/i',
        ];
        
        foreach ($sqlPatterns as $pattern) {
            $value = preg_replace($pattern, '', $value);
        }
        
        return $value;
    }
}
