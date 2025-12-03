<?php
/**
 * Admin Login
 * Opus3D Admin Panel
 */

require_once 'config.php';
startAdminSession();

// Redirect if already logged in
if (isAdminLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($username) || empty($password)) {
        $error = 'Inserisci username e password.';
    } else {
        $conn = getDBConnection();
        
        // Get admin by username or email
        $stmt = $conn->prepare("SELECT id, username, email, password, full_name, role, status FROM admins WHERE (username = ? OR email = ?) AND status = 'active'");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $admin = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $admin['password'])) {
                // Set session
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['admin_full_name'] = $admin['full_name'];
                $_SESSION['admin_role'] = $admin['role'];
                
                // Update last login
                $stmt = $conn->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
                $stmt->bind_param("i", $admin['id']);
                $stmt->execute();
                $stmt->close();
                
                // Create session token for remember me
                if ($remember) {
                    $session_token = bin2hex(random_bytes(32));
                    $expires_at = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
                    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
                    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
                    
                    $stmt = $conn->prepare("INSERT INTO admin_sessions (admin_id, session_token, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("issss", $admin['id'], $session_token, $ip_address, $user_agent, $expires_at);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Set cookie (30 days)
                    setcookie('admin_session_token', $session_token, time() + (86400 * 30), '/', '', false, true);
                }
                
                // Log login
                logAdminAction('admin_login', null, null, "Login effettuato da: " . $admin['username']);
                
                // Redirect to dashboard
                header('Location: index.php');
                exit;
            } else {
                $error = 'Username o password non corretti.';
            }
        } else {
            $error = 'Username o password non corretti.';
        }
        
        $stmt->close();
    }
}

// Check for remember me cookie
if (empty($error) && !isAdminLoggedIn() && isset($_COOKIE['admin_session_token'])) {
    $conn = getDBConnection();
    $token = $_COOKIE['admin_session_token'];
    
    $stmt = $conn->prepare("SELECT a.id, a.username, a.email, a.full_name, a.role FROM admins a INNER JOIN admin_sessions s ON a.id = s.admin_id WHERE s.session_token = ? AND s.expires_at > NOW() AND a.status = 'active'");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $admin = $result->fetch_assoc();
        
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_email'] = $admin['email'];
        $_SESSION['admin_full_name'] = $admin['full_name'];
        $_SESSION['admin_role'] = $admin['role'];
        
        header('Location: index.php');
        exit;
    }
    
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Opus3D</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;800&family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>Opus3D</h1>
                <h2>Accesso Admin</h2>
                <p>Inserisci le tue credenziali per accedere</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="auth-form">
                <div class="form-group">
                    <label for="username">Username o Email</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        value="<?php echo htmlspecialchars($username ?? ''); ?>" 
                        required 
                        autocomplete="username"
                        autofocus
                    >
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required 
                        autocomplete="current-password"
                    >
                </div>

                <div class="form-group checkbox-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember" value="1">
                        <span>Ricordami</span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-full">Accedi</button>
            </form>

            <div class="auth-footer">
                <p>Non hai un account? <a href="register.php">Registrati qui</a></p>
            </div>
        </div>
    </div>
</body>
</html>

