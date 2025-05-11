<?php

class Security {
    private static $rateLimits = [];
    private static $lastCleanup = 0;

    /**
     * Sanitize input data
     * @param mixed $data The data to sanitize
     * @return mixed The sanitized data
     */
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validate and sanitize email
     * @param string $email The email to validate
     * @return string|false The sanitized email or false if invalid
     */
    public static function validateEmail($email) {
        $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : false;
    }

    /**
     * Rate limiting implementation
     * @param string $key The key to rate limit (e.g., IP address or user ID)
     * @param int $maxRequests Maximum number of requests allowed
     * @param int $timeWindow Time window in seconds
     * @return bool True if request is allowed, false if rate limited
     */
    public static function checkRateLimit($key, $maxRequests = 60, $timeWindow = 60) {
        $currentTime = time();
        
        // Cleanup old entries every minute
        if ($currentTime - self::$lastCleanup > 60) {
            self::cleanupRateLimits();
            self::$lastCleanup = $currentTime;
        }
        
        // Initialize or get rate limit data
        if (!isset(self::$rateLimits[$key])) {
            self::$rateLimits[$key] = [
                'count' => 1,
                'window_start' => $currentTime
            ];
            return true;
        }
        
        $data = &self::$rateLimits[$key];
        
        // Reset if window has passed
        if ($currentTime - $data['window_start'] >= $timeWindow) {
            $data['count'] = 1;
            $data['window_start'] = $currentTime;
            return true;
        }
        
        // Check if limit exceeded
        if ($data['count'] >= $maxRequests) {
            return false;
        }
        
        $data['count']++;
        return true;
    }

    /**
     * Cleanup expired rate limit entries
     */
    private static function cleanupRateLimits() {
        $currentTime = time();
        foreach (self::$rateLimits as $key => $data) {
            if ($currentTime - $data['window_start'] >= 60) {
                unset(self::$rateLimits[$key]);
            }
        }
    }

    /**
     * Validate and sanitize file upload
     * @param array $file The $_FILES array element
     * @param array $allowedTypes Allowed MIME types
     * @param int $maxSize Maximum file size in bytes
     * @return array|false Array with sanitized file info or false if invalid
     */
    public static function validateFileUpload($file, $allowedTypes = [], $maxSize = 5242880) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return false;
        }

        // Check file size
        if ($file['size'] > $maxSize) {
            return false;
        }

        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!empty($allowedTypes) && !in_array($mimeType, $allowedTypes)) {
            return false;
        }

        // Sanitize filename
        $filename = preg_replace("/[^a-zA-Z0-9.-]/", "_", $file['name']);
        
        return [
            'name' => $filename,
            'type' => $mimeType,
            'size' => $file['size'],
            'tmp_name' => $file['tmp_name']
        ];
    }

    /**
     * Generate CSRF token
     * @return string The generated token
     */
    public static function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Validate CSRF token
     * @param string $token The token to validate
     * @return bool True if token is valid
     */
    public static function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Set secure headers
     */
    public static function setSecureHeaders() {
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' \'unsafe-eval\'; style-src \'self\' \'unsafe-inline\';');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
} 