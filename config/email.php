<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailNotifier {
    private $mail;
    private $pdo;
    private $config;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->loadConfig();
        $this->initMailer();
    }
    
    private function loadConfig() {
        $this->config = [
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'username' => 'your_email@gmail.com',
            'password' => 'your_app_password',
            'from_email' => 'noreply@votingsystem.com',
            'from_name' => 'Voting System',
            'encryption' => 'tls',
            'debug' => false
        ];
        
        try {
            $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'email_%'");
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            if (!empty($settings)) {
                $map = [
                    'email_host' => 'host',
                    'email_port' => 'port',
                    'email_username' => 'username',
                    'email_password' => 'password',
                    'email_from' => 'from_email',
                    'email_from_name' => 'from_name',
                    'email_encryption' => 'encryption',
                    'email_debug' => 'debug'
                ];
                
                foreach ($map as $db_key => $config_key) {
                    if (isset($settings[$db_key])) {
                        $this->config[$config_key] = $settings[$db_key];
                    }
                }
                
                $this->config['port'] = (int)$this->config['port'];
                $this->config['debug'] = filter_var($this->config['debug'], FILTER_VALIDATE_BOOLEAN);
            }
        } catch (Exception $e) {
            error_log("Failed to load email settings: " . $e->getMessage());
        }
    }
    
    private function initMailer() {
        $this->mail = new PHPMailer(true);
        
        if ($this->config['debug']) {
            $this->mail->SMTPDebug = SMTP::DEBUG_SERVER;
            $this->mail->Debugoutput = 'error_log';
        }
        
        $this->mail->isSMTP();
        $this->mail->Host = $this->config['host'];
        $this->mail->SMTPAuth = true;
        $this->mail->Username = $this->config['username'];
        $this->mail->Password = $this->config['password'];
        
        $encryption = strtolower($this->config['encryption']);
        if ($encryption === 'tls') {
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($encryption === 'ssl') {
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $this->mail->SMTPSecure = false;
        }
        
        $this->mail->Port = $this->config['port'];
        $this->mail->setFrom(
            $this->config['from_email'],
            $this->config['from_name']
        );
        $this->mail->Timeout = 30;
    }
    
    public function sendNominationConfirmation($email, $nominee_name, $category_id) {
        if (empty($email)) {
            return false;
        }
        
        try {
            $stmt = $this->pdo->prepare("SELECT name FROM categories WHERE id = ?");
            $stmt->execute([$category_id]);
            $category = $stmt->fetchColumn();
            
            $this->mail->clearAddresses();
            $this->mail->addAddress($email);
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Nomination Confirmation - Voting System';
            
            $body = "
                <html>
                <body style='font-family: Arial, sans-serif;'>
                    <h2 style='color: #667eea;'>Nomination Received</h2>
                    <p>Dear Submitter,</p>
                    <p>Thank you for submitting a nomination for:</p>
                    <ul>
                        <li><strong>Nominee:</strong> {$nominee_name}</li>
                        <li><strong>Category:</strong> {$category}</li>
                    </ul>
                    <p>Your nomination is now pending review by the administrators.</p>
                    <p>You will be notified once a decision has been made.</p>
                    <br>
                    <p>Best regards,<br>Voting System Team</p>
                </body>
                </html>
            ";
            
            $this->mail->Body = $body;
            $this->mail->AltBody = strip_tags($body);
            
            return $this->mail->send();
            
        } catch (Exception $e) {
            error_log("Nomination confirmation email failed: " . $e->getMessage());
            return false;
        }
    }
}
?>