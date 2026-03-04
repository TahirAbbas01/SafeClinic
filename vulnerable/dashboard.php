<?php
session_start();
include('../includes/db_connect.php'); // Path updated
 
// Agar user login nahi hai toh wapis bhej do
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}
 
$role = $_SESSION['role'];
$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'];

// Get patient id if patient
$patient_id = null;
if ($role == 'patient') {
    $p_res = mysqli_query($conn, "SELECT id FROM patients WHERE user_id=$user_id");
    if($p_row = mysqli_fetch_assoc($p_res)) {
        $patient_id = $p_row['id'];
    }
}

// Fetch stats based on roles
if ($role == 'admin') {
    $total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users"))['c'];
    $total_patients = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM patients"))['c'];
    $total_records = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM medical_records"))['c'];
} elseif ($role == 'doctor') {
    $total_patients = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM patients"))['c'];
    $total_records = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM medical_records"))['c'];
    $pending_reports = 3; // Static mockup
} else {
    $p_dob = mysqli_fetch_assoc(mysqli_query($conn, "SELECT date_of_birth FROM patients WHERE id='$patient_id'"))['date_of_birth'];
    $my_records = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM medical_records WHERE patient_id='$patient_id'"))['c'];
    $db_dob = $p_dob ? $p_dob : 'N/A';
}
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
            <p>Welcome, <b><?php echo $_SESSION['username']; ?></b></p>
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
                <a href="view_record.php?id=<?php echo $patient_id; ?>">📁 My Medical Records</a>
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
                <div class="card"><h3>Total Registered Users</h3><p><?php echo $total_users; ?></p></div>
                <div class="card"><h3>Registered Patients</h3><p><?php echo $total_patients; ?></p></div>
                <div class="card"><h3>Total Medical Records</h3><p><?php echo $total_records; ?></p></div>
            <?php elseif($role == 'doctor'): ?>
                <div class="card"><h3>Patients Registered</h3><p><?php echo $total_patients; ?></p></div>
                <div class="card"><h3>Total Medical Records</h3><p><?php echo $total_records; ?></p></div>
                <div class="card"><h3>Pending Reports</h3><p><?php echo $pending_reports; ?></p></div>
            <?php else: ?>
                <div class="card"><h3>Date of Birth</h3><p><?php echo $db_dob; ?></p></div>
                <div class="card"><h3>Total Records</h3><p><?php echo $my_records; ?></p></div>
                <div class="card"><h3>Upcoming Appointment</h3><p>March 15</p></div>
            <?php endif; ?>
        </div>
 
        <div class="table-section">
            <h3>Recent Activities</h3>
            <table>
                <thead>
                    <tr>
                        <th>Date / Info</th>
                        <th>Activity</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($role == 'admin'): 
                        $res = mysqli_query($conn, "SELECT id, username, role FROM users ORDER BY id DESC LIMIT 5");
                        while($row = mysqli_fetch_assoc($res)) {
                            echo "<tr><td>ID: ".$row['id']."</td><td>New User: ".$row['username']."</td><td>Role: ".$row['role']."</td></tr>";
                        }
                    elseif($role == 'doctor'): 
                        $res = mysqli_query($conn, "SELECT u.full_name, m.diagnosis, m.created_at FROM medical_records m JOIN patients p ON m.patient_id = p.id JOIN users u ON u.id = p.user_id ORDER BY m.id DESC LIMIT 5");
                        while($row = mysqli_fetch_assoc($res)) {
                            echo "<tr><td>".$row['created_at']."</td><td>Diaganosis: ".$row['diagnosis']."</td><td>Patient: ".$row['full_name']."</td></tr>";
                        }
                    else: 
                        $res = mysqli_query($conn, "SELECT diagnosis, treatment, created_at FROM medical_records WHERE patient_id='$patient_id' ORDER BY id DESC LIMIT 5");
                        if(mysqli_num_rows($res) > 0){
                            while($row = mysqli_fetch_assoc($res)) {
                                echo "<tr><td>".$row['created_at']."</td><td>".$row['diagnosis']."</td><td>".$row['treatment']."</td></tr>";
                            }
                        } else {
                            echo "<tr><td colspan='3'>No medical records found.</td></tr>";
                        }
                    endif; ?>
                </tbody>
            </table>
        </div>
    </div>
 
</body>
</html>