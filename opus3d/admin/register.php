<?php
/**
 * Admin Registration
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
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = sanitizeInput($_POST['full_name'] ?? '');
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Tutti i campi sono obbligatori.';
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $error = 'Lo username deve essere tra 3 e 50 caratteri.';
    } elseif (!validateEmail($email)) {
        $error = 'Email non valida.';
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $error = 'La password deve essere di almeno ' . PASSWORD_MIN_LENGTH . ' caratteri.';
    } elseif ($password !== $confirm_password) {
        $error = 'Le password non corrispondono.';
    } else {
        $conn = getDBConnection();
        
        // Check if username exists
        $stmt = $conn->prepare("SELECT id FROM admins WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Username già esistente.';
            $stmt->close();
        } else {
            $stmt->close();
            
            // Check if email exists
            $stmt = $conn->prepare("SELECT id FROM admins WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = 'Email già registrata.';
                $stmt->close();
            } else {
                $stmt->close();
                
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new admin
                $role = 'admin'; // Default role for new registrations
                $stmt = $conn->prepare("INSERT INTO admins (username, email, password, full_name, role, status) VALUES (?, ?, ?, ?, ?, 'active')");
                $stmt->bind_param("sssss", $username, $email, $hashed_password, $full_name, $role);
                
                if ($stmt->execute()) {
                    $admin_id = $conn->insert_id;
                    
                    // Log registration
                    logAdminAction('admin_registered', 'admins', $admin_id, "Nuovo admin registrato: $username");
                    
                    $success = 'Registrazione completata con successo! Puoi ora effettuare il login.';
                    
                    // Clear form data
                    $username = $email = $full_name = '';
                } else {
                    $error = 'Errore durante la registrazione. Riprova più tardi.';
                }
                
                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrazione Admin - Opus3D</title>
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
                <h2>Registrazione Admin</h2>
                <p>Crea un nuovo account amministratore</p>
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

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="auth-form">
                <div class="form-group">
                    <label for="username">Username *</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        value="<?php echo htmlspecialchars($username ?? ''); ?>" 
                        required 
                        minlength="3" 
                        maxlength="50"
                        autocomplete="username"
                    >
                </div>

                <div class="form-group">
                    <label for="email">Email *</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        value="<?php echo htmlspecialchars($email ?? ''); ?>" 
                        required
                        autocomplete="email"
                    >
                </div>

                <div class="form-group">
                    <label for="full_name">Nome Completo</label>
                    <input 
                        type="text" 
                        id="full_name" 
                        name="full_name" 
                        value="<?php echo htmlspecialchars($full_name ?? ''); ?>"
                        autocomplete="name"
                    >
                </div>

                <div class="form-group">
                    <label for="password">Password *</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required 
                        minlength="<?php echo PASSWORD_MIN_LENGTH; ?>"
                        autocomplete="new-password"
                    >
                    <small>Minimo <?php echo PASSWORD_MIN_LENGTH; ?> caratteri</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Conferma Password *</label>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        required 
                        minlength="<?php echo PASSWORD_MIN_LENGTH; ?>"
                        autocomplete="new-password"
                    >
                </div>

                <button type="submit" class="btn btn-primary btn-full">Registrati</button>
            </form>

            <div class="auth-footer">
                <p>Hai già un account? <a href="login.php">Accedi qui</a></p>
            </div>
        </div>
    </div>
</body>
</html>

