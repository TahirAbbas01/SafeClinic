<?php
$host = "localhost";
$user = "root";
$pass = ""; // Student 1 se confirms kar lain agar unhone koi password rakha hai
$db   = "safeclinic_db";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>