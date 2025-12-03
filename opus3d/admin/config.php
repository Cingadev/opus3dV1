<?php
/**
 * Database Configuration
 * Opus3D Admin Panel
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'opus3d');

// Site Configuration
define('SITE_URL', 'http://localhost/opus3d');
define('ADMIN_URL', SITE_URL . '/admin');
define('UPLOADS_URL', ADMIN_URL . '/uploads');

// Security
define('SESSION_LIFETIME', 3600 * 24); // 24 hours
define('PASSWORD_MIN_LENGTH', 8);

// Database Connection
function getDBConnection() {
    static $conn = null;
    
    if ($conn === null) {
        try {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($conn->connect_error) {
                die("Connessione fallita: " . $conn->connect_error);
            }
            
            $conn->set_charset("utf8mb4");
        } catch (Exception $e) {
            die("Errore di connessione al database: " . $e->getMessage());
        }
    }
    
    return $conn;
}

// Start Session
function startAdminSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// Check if Admin is Logged In
function isAdminLoggedIn() {
    startAdminSession();
    return isset($_SESSION['admin_id']) && isset($_SESSION['admin_username']);
}

// Require Admin Login
function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

// Get Current Admin ID
function getCurrentAdminId() {
    startAdminSession();
    return $_SESSION['admin_id'] ?? null;
}

// Get Current Admin Username
function getCurrentAdminUsername() {
    startAdminSession();
    return $_SESSION['admin_username'] ?? null;
}

// Logout Admin
function logoutAdmin() {
    startAdminSession();
    session_unset();
    session_destroy();
}

// Sanitize Input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Validate Email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Log Admin Action
function logAdminAction($action, $table_name = null, $record_id = null, $description = null) {
    $conn = getDBConnection();
    $admin_id = getCurrentAdminId();
    
    $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, table_name, record_id, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $stmt->bind_param("ississs", $admin_id, $action, $table_name, $record_id, $description, $ip_address, $user_agent);
    $stmt->execute();
    $stmt->close();
}

