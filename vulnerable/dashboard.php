<?php
session_start();
include('../includes/db_connect.php'); // Path updated
 
// Agar user login nahi hai toh wapis bhej do
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}
 
$role = $_SESSION['role'];
$full_name = $_SESSION['full_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SafeClinic - <?php echo ucfirst($role); ?> Dashboard</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="dashboard-page">
 
    <div class="sidebar">
        <h2>🏥 SafeClinic</h2>
       
        <div class="user-info">
            <p>Welcome, <b><?php echo explode('@', $username)[0]; ?></b></p>
            <span class="role-badge"><?php echo ucfirst($role); ?></span>
        </div>
 
        <nav>
            <a href="#">🏠 Overview</a>
           
            <?php if($role == 'admin'): ?>
                <a href="#">👥 Manage Users</a>
                <a href="#">🛡️ Security Logs</a>
                <a href="#">⚙️ System Settings</a>
            <?php elseif($role == 'doctor'): ?>
                <a href="#">📅 Appointments</a>
                <a href="#">🩺 Patient Records</a>
                <a href="#">💊 Prescriptions</a>
            <?php else: ?>
                <a href="#">📁 My Medical Records</a>
                <a href="#">📅 Book Visit</a>
                <a href="#">💳 Billing</a>
            <?php endif; ?>
 
            <a href="logout.php" class="logout">🚪 Logout</a>
        </nav>
    </div>
 
    <div class="main-content">
        <div class="header">
            <h1><?php echo ucfirst($role); ?> Dashboard</h1>
            <p>Current Status: System Online</p>
        </div>
 
        <div class="stats-grid">
            <?php if($role == 'admin'): ?>
                <div class="card"><h3>Total Registered Users</h3><p>124</p></div>
                <div class="card"><h3>Active Sessions</h3><p>08</p></div>
                <div class="card"><h3>Security Alerts</h3><p style="color:red;">02</p></div>
            <?php elseif($role == 'doctor'): ?>
                <div class="card"><h3>Today's Appointments</h3><p>12</p></div>
                <div class="card"><h3>Patients Treated</h3><p>450</p></div>
                <div class="card"><h3>Pending Reports</h3><p>05</p></div>
            <?php else: ?>
                <div class="card"><h3>Last Visit Date</h3><p>Feb 28, 2026</p></div>
                <div class="card"><h3>Current Medications</h3><p>02</p></div>
                <div class="card"><h3>Upcoming Appointment</h3><p>March 15</p></div>
            <?php endif; ?>
        </div>
 
        <div class="table-section">
            <h3>Recent Activities</h3>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Activity</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($role == 'admin'): ?>
                        <tr><td>2026-03-04</td><td>New user registered</td><td>Success</td></tr>
                        <tr><td>2026-03-04</td><td>Database Backup</td><td>Completed</td></tr>
                    <?php elseif($role == 'doctor'): ?>
                        <tr><td>2026-03-04</td><td>Appointment: Patient Ali</td><td>Done</td></tr>
                        <tr><td>2026-03-04</td><td>Lab Report Uploaded</td><td>Pending Review</td></tr>
                    <?php else: ?>
                        <tr><td>2026-02-28</td><td>General Checkup</td><td>Completed</td></tr>
                        <tr><td>2026-02-15</td><td>Blood Test</td><td>Normal</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
 
</body>
</html>