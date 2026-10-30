<?php

class SecurityHeaders {
    
    public static function enforceHttps() {
        if (in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1'])) {
            return;
        }
        
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return;
        }
        
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return;
        }
        
        if (!headers_sent()) {
            $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            header('Location: ' . $redirect, true, 301);
            exit();
        }
    }
    
    public static function setHeaders() {
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com https://cdn.datatables.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data:; font-src 'self' data:");
        header_remove('X-Powered-By');
    }
    
    public static function apply() {
        if (!headers_sent()) {
            self::enforceHttps();
            self::setHeaders();
        }
    }
}
?>