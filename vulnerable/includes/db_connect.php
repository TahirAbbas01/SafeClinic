<?php
/**
 * VULNERABLE DATABASE CONNECTION
 * 
 * This version demonstrates security vulnerabilities:
 * - Connects to safeclinic_db_vulnerable with plain-text passwords
 * - No prepared statements
 * - Basic error handling
 */

$host = "localhost";
$user = "root";
$pass = ""; // Change if needed
$db = "safeclinic_db_vulnerable";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset
mysqli_set_charset($conn, "utf8mb4");
?>
