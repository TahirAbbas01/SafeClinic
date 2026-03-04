<?php
/**
 * VULNERABLE: Logout handler
 * Basic session cleanup
 */

session_start();
session_destroy();
header("Location: index.php");
exit();
?>
