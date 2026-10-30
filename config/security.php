<?php
class Security {
    
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }
        
        if (is_string($data)) {
            $data = trim($data);
            $data = stripslashes($data);
            $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return $data;
        }
        
        return $data;
    }
    
    public static function validateEmail($email) {
        if (empty($email)) {
            return false;
        }
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    public static function generateToken($length = 32) {
        try {
            return bin2hex(random_bytes($length));
        } catch (Exception $e) {
            return bin2hex(openssl_random_pseudo_bytes($length));
        }
    }
}

function sanitize($data) {
    return Security::sanitizeInput($data);
}

function validateEmail($email) {
    return Security::validateEmail($email);
}

function generateToken($length = 32) {
    return Security::generateToken($length);
}
?>