<?php
/**
 * Admin Logout
 * Opus3D Admin Panel
 */

require_once 'config.php';
startAdminSession();

// Log logout action if logged in
if (isAdminLoggedIn()) {
    logAdminAction('admin_logout', null, null, "Logout effettuato da: " . getCurrentAdminUsername());
    
    // Delete session token if exists
    if (isset($_COOKIE['admin_session_token'])) {
        $conn = getDBConnection();
        $token = $_COOKIE['admin_session_token'];
        $stmt = $conn->prepare("DELETE FROM admin_sessions WHERE session_token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $stmt->close();
        
        // Delete cookie
        setcookie('admin_session_token', '', time() - 3600, '/', '', false, true);
    }
}

// Destroy session
logoutAdmin();

// Redirect to login
header('Location: login.php');
exit;

