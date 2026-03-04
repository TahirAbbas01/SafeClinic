<?php
/**
 * SECURE LOGIN PAGE
 * 
 * STEP 3: SQL Injection Prevention with Prepared Statements
 * STEP 3: Input Validation
 * STEP 4: Secure Password Verification with Bcrypt
 * STEP 5: Authentication Logging
 */

session_start();
include('includes/db_connect.php');
include('includes/config.php');
include('includes/logger.php');

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";
$success = "";

if (($_SERVER['REQUEST_METHOD'] === 'POST') && isset($_POST['login'])) {
    
    // STEP 5: CSRF token validation
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = "Session validation failed. Please try again.";
        $logger->logSuspiciousActivity("CSRF_FAILURE", "Invalid CSRF token on login", null);
    } else {
        // STEP 3: Input Validation - Check input constraints
        $username = trim($_POST['username'] ?? "");
        $password = trim($_POST['password'] ?? "");
        
        // Validate username format
        $validation = validateInput('username', $username);
        if (!$validation['valid']) {
            $error = "Invalid username format.";
            $logger->logSuspiciousActivity("INVALID_INPUT", "Login attempt with invalid username format", null);
        } else {
            // STEP 3: SQL Injection Prevention with Prepared Statements
            // Query only by username (case-sensitive)
            $users = executePreparedQuery(
                $conn,
                "SELECT id, username, full_name, role, password_hash, login_attempts, locked_until FROM users WHERE username = ?",
                [$username],
                "s"  // "s" = string type for $username
            );
            
            if (!empty($users)) {
                $user = $users[0];
                $userId = $user['id'];
                
                // Check if account is locked (brute force protection)
                if (!empty($user['locked_until'])) {
                    $lockTime = strtotime($user['locked_until']);
                    if (time() < $lockTime) {
                        $remainingTime = ceil(($lockTime - time()) / 60);
                        $error = "Account locked. Try again in $remainingTime minutes.";
                        $logger->logAuthenticationAttempt($username, false, null, "Account locked due to brute force");
                    } else {
                        // Unlock account
                        executeModifyQuery($conn, "UPDATE users SET locked_until = NULL, login_attempts = 0 WHERE id = ?", [$userId], "i");
                        // Continue with password verification
                        verifyLoginPassword($conn, $user, $password, $username, $logger);
                    }
                } else {
                    verifyLoginPassword($conn, $user, $password, $username, $logger);
                }
            } else {
                // STEP 5: Log failed authentication attempt
                $error = "Invalid credentials.";
                $logger->logAuthenticationAttempt($username, false, null, "User not found");
            }
        }
    }
}

/**
 * Verify password and create session
 * STEP 4: Uses password_verify() with bcrypt hashes
 */
function verifyLoginPassword($conn, $user, $password, $username, $logger) {
    global $error;
    
    $userId = $user['id'];
    $storedHash = $user['password_hash'];
    
    // STEP 4: Secure password comparison using bcrypt
    // password_verify() is timing-safe and resistant to timing attacks
    if (password_verify($password, $storedHash)) {
        // Successful login
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['login_time'] = time();
        
        // Reset login attempts
        executeModifyQuery($conn, "UPDATE users SET login_attempts = 0, last_login = NOW() WHERE id = ?", [$userId], "i");
        
        // STEP 5: Log successful authentication
        $logger->logAuthenticationAttempt($username, true);
        
        header("Location: dashboard.php");
        exit();
    } else {
        // Failed password
        $error = "Invalid credentials.";
        
        // Increment login attempts for brute force protection
        $loginAttempts = $user['login_attempts'] + 1;
        
        if ($loginAttempts >= 5) {
            // Lock account for 15 minutes
            $lockTime = date('Y-m-d H:i:s', time() + 900);
            executeModifyQuery(
                $conn,
                "UPDATE users SET login_attempts = ?, locked_until = ? WHERE id = ?",
                [$loginAttempts, $lockTime, $userId],
                "isi"
            );
            $logger->logSuspiciousActivity("BRUTE_FORCE_DETECTED", "Too many failed login attempts", $username);
        } else {
            executeModifyQuery($conn, "UPDATE users SET login_attempts = ? WHERE id = ?", [$loginAttempts, $userId], "ii");
        }
        
        // STEP 5: Log failed authentication attempt
        $logger->logAuthenticationAttempt($username, false, null, "Invalid password");
    }
}

// Generate CSRF token for form
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SafeClinic - Secure Login</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 12px;
            margin: 15px 0;
            border-radius: 4px;
            font-size: 13px;
            color: #1976D2;
        }
    </style>
</head>
<body class="auth-page">
    <div class="container">
        <h2>🏥 SafeClinic</h2>
        <p>Secure Staff & Patient Portal</p>
        
        <?php if (!empty($error)): ?>
            <div style="color: #d32f2f; background: #ffebee; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                <?php echo escapeOutput($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div style="color: #388e3c; background: #e8f5e9; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <form action="index.php" method="POST">
            <!-- STEP 5: CSRF Token for form protection -->
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <input type="text" 
                   name="username" 
                   placeholder="Username" 
                   pattern="[a-zA-Z0-9_-]{3,50}"
                   title="3-50 characters: letters, numbers, _, -"
                   required>
            
            <input type="password" 
                   name="password" 
                   placeholder="Password" 
                   minlength="8"
                   required>
            
            <button type="submit" name="login">Login</button>
        </form>

        <div class="info-box">
            <strong>Test Credentials:</strong><br>
            Admin: admin_user / SecurePass123!<br>
            Doctor: dr_smith / DoctorPass456!<br>
            Patient: patient_zero / PatientPass1!
        </div>

        <div class="footer-link">
            New here? <a href="register.php">Create an account</a>
        </div>
    </div>
</body>
</html>
