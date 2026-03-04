<?php
/**
 * VULNERABLE: Admin User Management
 * Missing Input Validation & Access Control
 * Vulnerable to SQL Injection, CSRF, XSS
 */

session_start();
include('includes/db_connect.php');

// Vulnerable: No proper access control check
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

// Vulnerable: No role check (anyone logged in can access this!)
// Should check: if ($_SESSION['role'] !== 'admin')

$role = $_SESSION['role'];
$error = "";
$success = "";

// Handle user creation
if (isset($_POST['create_user'])) {
    // Vulnerable: No CSRF token
    // Vulnerable: No input validation
    $new_username = $_POST['username'];
    $new_password = $_POST['password'];
    $new_role = $_POST['role'];
    $full_name = $_POST['full_name'];
    
    // Vulnerable: Direct SQL interpolation - SQL Injection possible!
    $check_sql = "SELECT id FROM users WHERE username='$new_username'";
    $check_res = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_res) > 0) {
        $error = "Username already exists.";
    } else {
        // Vulnerable: Plain-text password storage
        $sql = "INSERT INTO users (username, password, role, full_name) 
                VALUES ('$new_username', '$new_password', '$new_role', '$full_name')";
        
        if (mysqli_query($conn, $sql)) {
            $new_user_id = mysqli_insert_id($conn);
            
            // If patient, create patient profile
            if ($new_role === 'patient') {
                $patient_sql = "INSERT INTO patients (user_id) VALUES ($new_user_id)";
                mysqli_query($conn, $patient_sql);
            }
            
            $success = "User created successfully!";
        } else {
            $error = "Error: " . mysqli_error($conn);
        }
    }
}

// Handle user deletion
if (isset($_POST['delete_user'])) {
    // Vulnerable: No CSRF token
    // Vulnerable: No validation
    $user_id = $_POST['user_id']; // Vulnerable: Direct use without validation
    
    // Vulnerable: SQL Injection
    $sql = "DELETE FROM users WHERE id=$user_id";
    
    if (mysqli_query($conn, $sql)) {
        $success = "User deleted successfully!";
    } else {
        $error = "Error deleting user: " . mysqli_error($conn);
    }
}

// Fetch all users (vulnerable SQL)
$users_res = mysqli_query($conn, "SELECT id, username, role, full_name FROM users ORDER BY id");
$users = [];
while ($row = mysqli_fetch_assoc($users_res)) {
    $users[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SafeClinic - Manage Users</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .admin-container {
            display: flex;
            gap: 20px;
            margin-top: 20px;
        }
        .form-section {
            flex: 1;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .form-section h3 {
            margin-top: 0;
        }
        .users-list {
            flex: 1;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .user-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .danger-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
        }
        .danger-btn:hover {
            background: #c0392b;
        }
    </style>
</head>
<body class="dashboard-page">
    <div style="margin-left: 0; width: 100%; padding: 20px;">
        <a href="dashboard.php" style="color: #3498db; text-decoration: none;">← Back to Dashboard</a>
        <h1>Manage Users (Admin Only)</h1>
        
        <?php if (!empty($error)): ?>
            <div style="color: #d32f2f; background: #ffebee; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div style="color: #388e3c; background: #e8f5e9; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <div class="admin-container">
            <div class="form-section">
                <h3>Create New User</h3>
                <form action="manage_users.php" method="POST">
                    <!-- VULNERABLE: No CSRF token -->
                    
                    <label><strong>Username:</strong></label>
                    <input type="text" name="username" placeholder="Enter username" required>
                    <!-- VULNERABLE: No validation on client or server -->
                    
                    <label><strong>Password:</strong></label>
                    <input type="password" name="password" placeholder="Enter password" required>
                    <!-- VULNERABLE: Plain-text password, no strength check -->
                    
                    <label><strong>Full Name:</strong></label>
                    <input type="text" name="full_name" placeholder="Enter full name">
                    
                    <label><strong>Role:</strong></label>
                    <select name="role" required>
                        <option value="">-- Select Role --</option>
                        <option value="admin">Administrator</option>
                        <option value="doctor">Doctor</option>
                        <option value="nurse">Nurse</option>
                        <option value="patient">Patient</option>
                    </select>
                    
                    <button type="submit" name="create_user" style="margin-top: 10px;">Create User</button>
                </form>
            </div>
            
            <div class="users-list">
                <h3>All Users</h3>
                <?php foreach ($users as $user): ?>
                    <div class="user-row">
                        <div>
                            <strong><?php echo $user['username']; ?></strong>
                            (<?php echo ucfirst($user['role']); ?>)<br>
                            <small><?php echo $user['full_name']; ?></small>
                        </div>
                        <form action="manage_users.php" method="POST" style="display: inline;">
                            <!-- VULNERABLE: No CSRF token -->
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            <!-- VULNERABLE: No confirmation, easy to accidentally delete -->
                            <button type="submit" name="delete_user" class="danger-btn" onclick="return confirm('Delete this user?');">Delete</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>
