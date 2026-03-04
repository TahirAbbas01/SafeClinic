<?php
session_start();

// Static credentials jo aapne bataye
$static_user = "tahirabbas3016@gmail.com";
$static_pass = "123";

if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if ($username === $static_user && $password === $static_pass) {
        $_SESSION['user_id'] = 1;
        $_SESSION['username'] = "Tahir Abbas";
        $_SESSION['role'] = "admin"; // Static role
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Wronge Username ya Password!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SafeClinic - Login</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <h2>🏥 SafeClinic</h2>
        <p>Staff & Patient Portal</p>
        
        <?php if(isset($error)) echo "<p style='color:red;'>$error</p>"; ?>

        <form action="index.php" method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" name="login">Login</button>
        </form>
        <div class="footer-link">
            New here? <a href="register.php">Create an account</a>
        </div>
    </div>
</body>
</html>