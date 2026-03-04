<?php
/**
 * SECURE DASHBOARD PAGE
 * 
 * STEP 3: SQL Injection Prevention with Prepared Statements
 * STEP 5: Access Control and Activity Logging
 */

session_start();
include('../includes/db_connect.php');
include('../includes/config.php');
include('../includes/logger.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// STEP 5: Session timeout check (30 minutes)
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 1800)) {
    session_destroy();
    header("Location: index.php?timeout=1");
    exit();
}

$role = $_SESSION['role'];
$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'];
$dashboard_error = "";

// STEP 3: Fetch patient_id using Prepared Statement (if patient)
$patient_id = null;
if ($role === 'patient') {
    $patients = executePreparedQuery(
        $conn,
        "SELECT id FROM patients WHERE user_id = ?",
        [$user_id],
        "i"
    );
    if (!empty($patients)) {
        $patient_id = $patients[0]['id'];
        // STEP 5: Log dashboard access
        $logger->logDataAccess('dashboard', $patient_id, $user_id, 'view');
    }
}

// STEP 3: Fetch stats using Prepared Statements
$stats = array();

if ($role === 'admin') {
    // Admin can see all stats
    $users = executePreparedQuery($conn, "SELECT COUNT(*) as count FROM users");
    $patients_data = executePreparedQuery($conn, "SELECT COUNT(*) as count FROM patients");
    $records = executePreparedQuery($conn, "SELECT COUNT(*) as count FROM medical_records");
    
    $stats['total_users'] = $users[0]['count'] ?? 0;
    $stats['total_patients'] = $patients_data[0]['count'] ?? 0;
    $stats['total_records'] = $records[0]['count'] ?? 0;
    
} elseif ($role === 'doctor') {
    // Doctor can see patient and record stats
    $patients_data = executePreparedQuery($conn, "SELECT COUNT(*) as count FROM patients");
    $records = executePreparedQuery($conn, "SELECT COUNT(*) as count FROM medical_records");
    
    $stats['total_patients'] = $patients_data[0]['count'] ?? 0;
    $stats['total_records'] = $records[0]['count'] ?? 0;
    $stats['pending_reports'] = 3; // Mock data
    
} else {
    // Patient can only see their own data
    if ($patient_id) {
        $patient_data = executePreparedQuery(
            $conn,
            "SELECT date_of_birth FROM patients WHERE id = ?",
            [$patient_id],
            "i"
        );
        $records = executePreparedQuery(
            $conn,
            "SELECT COUNT(*) as count FROM medical_records WHERE patient_id = ?",
            [$patient_id],
            "i"
        );
        
        $stats['date_of_birth'] = $patient_data[0]['date_of_birth'] ?? 'N/A';
        $stats['my_records'] = $records[0]['count'] ?? 0;
    }
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
        <p style="color: #999; font-size: 12px; text-align: center;">Secure Version</p>
        
        <div class="user-info">
            <p>Welcome, <b><?php echo escapeOutput($_SESSION['username']); ?></b></p>
            <span class="role-badge"><?php echo ucfirst($role); ?></span>
        </div>
        
        <nav>
            <?php if ($role === 'admin'): ?>
                <a href="manage_users.php">👥 Manage Users</a>
            <?php elseif ($role === 'doctor'): ?>
                <a href="manage_records.php">📋 Manage Medical Records</a>
            <?php elseif ($role === 'nurse'): ?>
                <a href="update_vitals.php">💊 Update Vitals</a>
            <?php else: ?>
                <a href="view_records.php?id=<?php echo $patient_id; ?>">📁 My Medical Records</a>
            <?php endif; ?>
            
            <a href="logout.php" class="logout">🚪 Logout</a>
        </nav>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1><?php echo ucfirst($role); ?> Dashboard</h1>
            <p>Current Status: System Online • Session: Active</p>
        </div>

        <?php if (!empty($dashboard_error)): ?>
            <div style="color: #d32f2f; background: #ffebee; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <?php echo escapeOutput($dashboard_error); ?>
            </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <?php if ($role === 'admin'): ?>
                <div class="card">
                    <h3>Total Registered Users</h3>
                    <p><?php echo $stats['total_users'] ?? 0; ?></p>
                </div>
                <div class="card">
                    <h3>Registered Patients</h3>
                    <p><?php echo $stats['total_patients'] ?? 0; ?></p>
                </div>
                <div class="card">
                    <h3>Total Medical Records</h3>
                    <p><?php echo $stats['total_records'] ?? 0; ?></p>
                </div>
            <?php elseif ($role === 'doctor'): ?>
                <div class="card">
                    <h3>Patients Registered</h3>
                    <p><?php echo $stats['total_patients'] ?? 0; ?></p>
                </div>
                <div class="card">
                    <h3>Total Medical Records</h3>
                    <p><?php echo $stats['total_records'] ?? 0; ?></p>
                </div>
            <?php else: ?>
                <div class="card">
                    <h3>Date of Birth</h3>
                    <p><?php echo $stats['date_of_birth'] ?? 'N/A'; ?></p>
                </div>
                <div class="card">
                    <h3>Your Medical Records</h3>
                    <p><?php echo $stats['my_records'] ?? 0; ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="table-section">
            <h3>Security Features Implemented</h3>
            <table>
                <thead>
                    <tr>
                        <th>Feature</th>
                        <th>Description</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>SQL Injection Prevention</td>
                        <td>Prepared statements with parameterized queries</td>
                        <td style="color: #27ae60;">✓ Active</td>
                    </tr>
                    <tr>
                        <td>Input Validation</td>
                        <td>Strict validation on username, password, and data types</td>
                        <td style="color: #27ae60;">✓ Active</td>
                    </tr>
                    <tr>
                        <td>Password Hashing</td>
                        <td>Bcrypt hashing with salt (cost=10)</td>
                        <td style="color: #27ae60;">✓ Active</td>
                    </tr>
                    <tr>
                        <td>CSRF Protection</td>
                        <td>Token-based CSRF protection on all forms</td>
                        <td style="color: #27ae60;">✓ Active</td>
                    </tr>
                    <tr>
                        <td>Brute Force Protection</td>
                        <td>Account lockdown after 5 failed login attempts</td>
                        <td style="color: #27ae60;">✓ Active</td>
                    </tr>
                    <tr>
                        <td>Security Logging</td>
                        <td>All auth attempts and sensitive actions logged</td>
                        <td style="color: #27ae60;">✓ Active</td>
                    </tr>
                    <tr>
                        <td>Session Management</td>
                        <td>HTTPOnly cookies, 30-minute timeout</td>
                        <td style="color: #27ae60;">✓ Active</td>
                    </tr>
                    <tr>
                        <td>Output Escaping</td>
                        <td>XSS prevention via HTML entity encoding</td>
                        <td style="color: #27ae60;">✓ Active</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
