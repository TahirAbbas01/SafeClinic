<?php
/**
 * SECURE REGISTRATION PAGE
 * 
 * STEP 3: SQL Injection Prevention with Prepared Statements
 * STEP 3: Comprehensive Input Validation
 * STEP 4: Secure Password Hashing with Bcrypt
 * STEP 5: Registration Logging
 * STEP 5: CSRF Protection
 */

session_start();
include('includes/db_connect.php');
include('includes/config.php');
include('../includes/logger.php');

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";
$success = "";

if (($_SERVER['REQUEST_METHOD'] === 'POST') && isset($_POST['register'])) {
    
    // STEP 5: CSRF Token Validation
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = "Session validation failed. Please try again.";
        $logger->logSuspiciousActivity("CSRF_FAILURE", "Invalid CSRF token on registration", null);
    } else {
        // Get input values
        $username = trim($_POST['username'] ?? "");
        $password = trim($_POST['password'] ?? "");
        $confirm_password = trim($_POST['confirm_password'] ?? "");
        $role = trim($_POST['role'] ?? "");
        $full_name = trim($_POST['full_name'] ?? "");
        $email = trim($_POST['email'] ?? "");
        
        // STEP 3: Comprehensive Input Validation
        $validationPassed = true;
        
        // Validate username
        $validation = validateInput('username', $username);
        if (!$validation['valid']) {
            $error = $validation['error'];
            $validationPassed = false;
        }
        
        // Validate password strength
        if ($validationPassed) {
            $validation = validateInput('password', $password);
            if (!$validation['valid']) {
                $error = $validation['error'];
                $validationPassed = false;
            }
        }
        
        // Verify password confirmation
        if ($validationPassed && $password !== $confirm_password) {
            $error = "Passwords do not match.";
            $validationPassed = false;
        }
        
        // Validate role
        if ($validationPassed) {
            $validation = validateInput('role', $role);
            if (!$validation['valid']) {
                $error = $validation['error'];
                $validationPassed = false;
                $logger->logSuspiciousActivity("INVALID_ROLE", "Registration attempt with invalid role: $role", null);
            }
        }
        
        // Validate full name (optional but if provided, must be valid)
        if ($validationPassed && !empty($full_name)) {
            $validation = validateInput('full_name', $full_name);
            if (!$validation['valid']) {
                $error = $validation['error'];
                $validationPassed = false;
            }
        }
        
        // Validate email if provided
        if ($validationPassed && !empty($email)) {
            $validation = validateInput('email', $email);
            if (!$validation['valid']) {
                $error = $validation['error'];
                $validationPassed = false;
            }
        }
        
        if ($validationPassed) {
            // STEP 3: Check if username already exists using Prepared Statement
            $existingUsers = executePreparedQuery(
                $conn,
                "SELECT id FROM users WHERE username = ?",
                [$username],
                "s"
            );
            
            if (!empty($existingUsers)) {
                $error = "Username already exists. Please choose another.";
                $logger->logSuspiciousActivity("DUPLICATE_USERNAME", "Registration attempt with existing username: $username", null);
            } else {
                // STEP 4: Hash password securely with bcrypt
                $passwordHash = hashPassword($password);
                
                // STEP 3: Insert user with Prepared Statement (SQL Injection Prevention)
                $result = executeModifyQuery(
                    $conn,
                    "INSERT INTO users (username, password_hash, email, role, full_name) VALUES (?, ?, ?, ?, ?)",
                    [$username, $passwordHash, $email, $role, $full_name],
                    "sssss"
                );
                
                if ($result !== false) {
                    $userId = getLastInsertId($conn);
                    
                    // If patient role, create patient profile
                    if ($role === 'patient') {
                        executeModifyQuery(
                            $conn,
                            "INSERT INTO patients (user_id) VALUES (?)",
                            [$userId],
                            "i"
                        );
                    }
                    
                    // STEP 5: Log successful registration
                    $logger->logDataModification('users', 'INSERT', 'SYSTEM', "New user registered: $username (Role: $role)");
                    
                    $success = "Registration successful! <a href='index.php' style='color: #0066cc; text-decoration: underline;'>Login here</a>";
                } else {
                    $error = "Registration failed. Please try again later.";
                    $logger->logDataModification('users', 'INSERT_FAILED', 'SYSTEM', "Registration failed for username: $username");
                }
            }
        }
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SafeClinic - Secure Registration</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .container {
            width: 450px;
        }
        .password-help {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .requirements {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 5px;
            font-size: 12px;
            margin-top: 10px;
            color: #555;
        }
    </style>
</head>
<body class="auth-page">
    <div class="container">
        <h2>🏥 SafeClinic</h2>
        <p>Secure Patient Registration</p>
        
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

        <form action="register.php" method="POST">
            <!-- STEP 5: CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <!-- Username Input with validation -->
            <label for="username"><strong>Username:</strong></label>
            <input type="text" 
                   id="username"
                   name="username" 
                   placeholder="Choose a username (3-50 chars)"
                   pattern="[a-zA-Z0-9_-]{3,50}"
                   title="3-50 characters: letters, numbers, _, -"
                   required>
            <div class="password-help">3-50 characters: letters, numbers, underscore, hyphen</div>
            
            <!-- Email Input -->
            <label for="email"><strong>Email (optional):</strong></label>
            <input type="email" 
                   id="email"
                   name="email" 
                   placeholder="example@domain.com">
            
            <!-- Full Name Input -->
            <label for="fullname"><strong>Full Name (optional):</strong></label>
            <input type="text" 
                   id="fullname"
                   name="full_name" 
                   placeholder="Your full name">
            
            <!-- Password Input with strong validation -->
            <label for="password"><strong>Password:</strong></label>
            <input type="password" 
                   id="password"
                   name="password" 
                   placeholder="Create a strong password"
                   minlength="8"
                   required>
            <div class="password-help">Must be at least 8 characters</div>
            <div class="requirements">
                <strong>Password Requirements:</strong<br>
                ✓ At least 8 characters<br>
                ✓ One uppercase letter (A-Z)<br>
                ✓ One lowercase letter (a-z)<br>
                ✓ One number (0-9)<br>
                ✓ One special character (@$!%*?&)
            </div>
            
            <!-- Confirm Password -->
            <label for="confirm"><strong>Confirm Password:</strong></label>
            <input type="password" 
                   id="confirm"
                   name="confirm_password" 
                   placeholder="Confirm password"
                   minlength="8"
                   required>
            
            <!-- Role Selection (Patients Only) -->
            <label for="role"><strong>User Type:</strong></label>
            <select name="role" id="role" required>
                <option value="">-- Select User Type --</option>
                <option value="patient">Patient</option>
                <!-- Other roles require admin registration -->
            </select>
            <div class="password-help">Contact administrator for doctor/admin roles</div>
            
            <button type="submit" name="register">Create Account</button>
        </form>

        <div class="footer-link">
            Already have an account? <a href="index.php">Login here</a>
        </div>
    </div>
</body>
</html>
