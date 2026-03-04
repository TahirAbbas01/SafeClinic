<?php
/**
 * SECURE LOGOUT PAGE
 * 
 * STEP 5: Proper session cleanup and logging
 */

session_start();
include('../includes/logger.php');

// Log logout event
if (isset($_SESSION['user_id'])) {
    $logger->logAuthenticationAttempt($_SESSION['username'] ?? 'unknown', true, null, "User logged out");
}

// Destroy session securely
$_SESSION = array();

if (ini_get("session.use_cookies")) {
    setcookie(session_name(), "", time() - 42000, "/");
}

session_destroy();

// Redirect to login
header("Location: index.php?logged_out=1");
exit();
?>
