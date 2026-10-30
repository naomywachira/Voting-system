<?php
class RateLimiter {
    private $pdo;
    private $max_attempts;
    private $decay_minutes;
    private $table_prefix = 'rate_limits_';
    
    public function __construct($pdo, $max_attempts = 10, $decay_minutes = 1) {
        $this->pdo = $pdo;
        $this->max_attempts = $max_attempts;
        $this->decay_minutes = $decay_minutes;
    }
    
    public function attempt($key, $action = 'vote') {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $table = $this->table_prefix . $action;
        
        $this->createTableIfNotExists($table);
        $this->cleanOldRecords($table);
        
        $attempts = $this->getAttempts($table, $ip, $key);
        
        if ($attempts >= $this->max_attempts) {
            return false;
        }
        
        $this->logAttempt($table, $ip, $key);
        return true;
    }
    
    private function createTableIfNotExists($table) {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS $table (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    ip_address VARCHAR(45) NOT NULL,
                    action_key VARCHAR(255) NOT NULL,
                    user_agent TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_ip_action (ip_address, action_key),
                    INDEX idx_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (Exception $e) {
            error_log("Failed to create rate limit table: " . $e->getMessage());
        }
    }
    
    private function getAttempts($table, $ip, $key) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as attempts 
                FROM $table 
                WHERE ip_address = ? 
                AND action_key = ? 
                AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
            ");
            $stmt->execute([$ip, $key, $this->decay_minutes]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($result['attempts'] ?? 0);
        } catch (Exception $e) {
            error_log("Failed to get attempts: " . $e->getMessage());
            return 0;
        }
    }
    
    private function logAttempt($table, $ip, $key) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO $table (ip_address, action_key, user_agent) 
                VALUES (?, ?, ?)
            ");
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $stmt->execute([$ip, $key, $user_agent]);
        } catch (Exception $e) {
            error_log("Failed to log rate limit attempt: " . $e->getMessage());
        }
    }
    
    private function cleanOldRecords($table) {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM $table 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)
            ");
            $stmt->execute();
        } catch (Exception $e) {
            error_log("Failed to clean rate limit records: " . $e->getMessage());
        }
    }
}
?>