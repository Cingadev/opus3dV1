<?php
/**
 * Database Configuration
 * Opus3D Frontend
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

// Sanitize Input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

