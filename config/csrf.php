<?php


class CSRFToken {
    /**
     * Generate a CSRF token
     * @return string CSRF token
     */
    public static function generate() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify CSRF token
     * @param string $token Token to verify
     * @return bool True if valid
     */
    public static function verify($token) {
        if (!isset($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Regenerate CSRF token
     * @return string New token
     */
    public static function regenerate() {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Get CSRF token
     * @return string CSRF token
     */
    public static function getToken() {
        return self::generate();
    }
}

/**
 * Generate CSRF hidden field for forms
 * @return string HTML hidden input
 */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . CSRFToken::generate() . '">';
}

/**
 * Generate CSRF meta tag for AJAX
 * @return string HTML meta tag
 */
function csrf_meta() {
    return '<meta name="csrf-token" content="' . CSRFToken::generate() . '">';
}

/**
 * Verify CSRF token from POST or GET
 * @param string $method Request method (POST, GET)
 * @return bool True if valid
 */
function verify_csrf($method = 'POST') {
    if ($_SERVER['REQUEST_METHOD'] === $method) {
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? null;
        if (!$token || !CSRFToken::verify($token)) {
            error_log("CSRF token validation failed - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            return false;
        }
        return true;
    }
    return true;
}

/**
 * Verify CSRF and die if invalid
 * @param string $method Request method (POST, GET)
 */
function require_csrf($method = 'POST') {
    if (!verify_csrf($method)) {
        header('HTTP/1.1 403 Forbidden');
        die('<h2>CSRF Token Validation Failed</h2>
             <p>Security token validation failed. Please refresh the page and try again.</p>
             <a href="javascript:history.back()">Go Back</a>');
    }
}

/**
 * Get CSRF token for AJAX requests
 * @return array CSRF token data
 */
function csrf_token_data() {
    return [
        'token' => CSRFToken::generate(),
        'header' => 'X-CSRF-TOKEN'
    ];
}
?>