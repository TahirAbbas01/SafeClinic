<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SafeClinic - Registration</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <h2>🏥 SafeClinic</h2>
        <p>Patient Registration</p>
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