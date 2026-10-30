<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/security_headers.php';

SecurityHeaders::apply();

$host = 'localhost';
$dbname = 'voting_system';
$username = 'root';
$password = '';
$pdo = null;

try {
    $pdo = new PDO("mysql:host=$host;", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123');

require_once __DIR__.'/config/auto_setup.php';

function getSetting($pdo, $key) {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['setting_value'] : 'closed';
    } catch (Exception $e) {
        return 'closed';
    }
}

function updateSetting($pdo, $key, $value) {
    try {
        $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        return $stmt->execute([$value, $key]);
    } catch (Exception $e) {
        return false;
    }
}

function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

function getLocationFromIP($ip) {
    $parts = explode('.', $ip);
    if (count($parts) == 4) {
        $zone = $parts[0] . '.' . $parts[1];
        return "Zone " . $zone;
    }
    return "Unknown Zone";
}
?>