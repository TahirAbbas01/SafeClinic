<?php
session_start();
include('includes/db_connect.php');

if (isset($_POST['register'])) {
    // Vulnerable to SQL Injection and XSS
    $username = $_POST['username'];
    $password = $_POST['password'];
    $role = $_POST['role'];

    // Check if user exists (vulnerable way)
    $check_sql = "SELECT id FROM users WHERE username='$username'";
    $check_res = mysqli_query($conn, $check_sql);

    if (mysqli_num_rows($check_res) > 0) {
        $error = "Username already exists.";
    } else {
        // Insert user (vulnerable)
        $sql = "INSERT INTO users (username, password, role) VALUES ('$username', '$password', '$role')";
        if (mysqli_query($conn, $sql)) {
            $user_id = mysqli_insert_id($conn);
            
            // If patient, add to patients table
            if ($role === 'patient') {
                $patient_sql = "INSERT INTO patients (user_id) VALUES ($user_id)";
                mysqli_query($conn, $patient_sql);
            }
            $success = "Registration successful! <a href='index.php'>Login here</a>";
        } else {
            $error = "Error: " . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SafeClinic - Registration</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="auth-page">
    <div class="container">
        <h2>🏥 SafeClinic</h2>
        <p>Patient Registration</p>
        
        <?php 
        if(isset($error)) echo "<p style='color:red;'>$error</p>"; 
        if(isset($success)) echo "<p style='color:green;'>$success</p>"; 
        ?>

        <form action="register.php" method="POST">
            <input type="text" name="username" placeholder="Choose Username" required>
            <input type="password" name="password" placeholder="Create Password" required>
            <select name="role">
                <option value="patient">Standard Patient</option>
                <option value="admin">Administrator/Doctor</option>
            </select>
            <button type="submit" name="register">Sign Up</button>
        </form>
        <div class="footer-link">
            Already have an account? <a href="index.php">Login</a>
        </div>
    </div>
</body>
</html>