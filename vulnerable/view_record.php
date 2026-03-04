<?php
session_start();
include('includes/db_connect.php');

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

$record_id = $_GET['id']; // Vulnerable to IDOR and SQLi

// Vulnerable SQL query (No parameterization, directly interpolates the user input)
$sql = "SELECT m.*, p.date_of_birth, p.address, u.full_name 
        FROM medical_records m 
        JOIN patients p ON m.patient_id = p.id 
        JOIN users u ON p.user_id = u.id 
        WHERE m.id = $record_id";
        
$result = mysqli_query($conn, $sql);
$record = mysqli_fetch_assoc($result);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SafeClinic - View Record</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="dashboard-page">

<div class="main-content" style="margin-left: 0; max-width: 800px; margin: 40px auto; display: block;">
    <div class="header">
        <h1>Medical Record Details</h1>
        <a href="dashboard.php" style="text-decoration: none; color: #fff; background: #007bff; padding: 5px 15px; border-radius: 5px;">Back to Dashboard</a>
    </div>

    <?php if ($record): ?>
        <div class="card" style="margin-top: 20px;">
            <h3>Patient: <?php echo $record['full_name']; ?> // Vulnerable to Reflected XSS if data is tainted</h3>
            <p><strong>Date of Birth:</strong> <?php echo $record['date_of_birth']; ?></p>
            <p><strong>Address:</strong> <?php echo $record['address']; ?></p>
            <hr>
            <p><strong>Diagnosis:</strong> <?php echo $record['diagnosis']; ?></p>
            <p><strong>Treatment:</strong> <?php echo $record['treatment']; ?></p>
            <p><strong>Date of Record:</strong> <?php echo $record['created_at']; ?></p>
        </div>
    <?php else: ?>
        <div class="card" style="margin-top: 20px;">
            <p style='color:red;'>Record not found or an error occurred.</p>
            <?php 
                // Exposing DB errors directly to the user
                if (mysqli_error($conn)) {
                    echo "<p>DB Error: " . mysqli_error($conn) . "</p>";
                }
            ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
