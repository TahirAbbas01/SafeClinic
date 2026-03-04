<?php
/**
 * SECURE VIEW MEDICAL RECORDS PAGE
 * 
 * STEP 3: SQL Injection Prevention with Prepared Statements
 * STEP 3: Input Validation
 * STEP 5: IDOR (Insecure Direct Object Reference) Prevention
 * STEP 5: Access Control and Logging
 */

session_start();
include('includes/db_connect.php');
include('includes/config.php');
include('includes/logger.php');

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Session timeout check
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 1800)) {
    session_destroy();
    header("Location: index.php?timeout=1");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$error_message = "";
$record = null;

// Get record ID from URL and validate it's a number
$record_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// STEP 3: Validate input
if ($record_id <= 0) {
    $error_message = "Invalid record ID.";
    $logger->logSuspiciousActivity("INVALID_RECORD_ID", "Invalid record ID parameter: " . ($_GET['id'] ?? 'empty'), $user_id);
} else {
    // STEP 5: IDOR Prevention - Fetch patient-doctor relationship
    // First, determine user's patient ID or provider access
    $userPatientId = null;
    
    if ($role === 'patient') {
        // Patient can only view their own records
        $patients = executePreparedQuery(
            $conn,
            "SELECT id FROM patients WHERE user_id = ?",
            [$user_id],
            "i"
        );
        
        if (!empty($patients)) {
            $userPatientId = $patients[0]['id'];
        } else {
            $error_message = "Patient profile not found.";
        }
    }
    
    if (empty($error_message)) {
        // STEP 3: SQL Injection Prevention - Use Prepared Statements
        // Fetch record with access control
        
        if ($role === 'patient') {
            // Patients can only view their own records
            $records = executePreparedQuery(
                $conn,
                "SELECT m.id, m.diagnosis, m.treatment, m.notes, m.created_at, 
                        p.date_of_birth, p.address, u.full_name
                 FROM medical_records m
                 JOIN patients p ON m.patient_id = p.id
                 JOIN users u ON p.user_id = u.id
                 WHERE m.id = ? AND m.patient_id = ?",
                [$record_id, $userPatientId],
                "ii"
            );
        } elseif ($role === 'doctor' || $role === 'admin') {
            // Doctors and admins can view any record (with logging)
            $records = executePreparedQuery(
                $conn,
                "SELECT m.id, m.diagnosis, m.treatment, m.notes, m.created_at,
                        p.date_of_birth, p.address, u.full_name
                 FROM medical_records m
                 JOIN patients p ON m.patient_id = p.id
                 JOIN users u ON p.user_id = u.id
                 WHERE m.id = ?",
                [$record_id],
                "i"
            );
        } else {
            // Unknown role
            $error_message = "Insufficient permissions.";
            $logger->logSuspiciousActivity("UNAUTHORIZED_ACCESS", "Unknown role attempting record access: $role", $user_id);
        }
        
        if (!empty($records)) {
            $record = $records[0];
            
            // STEP 5: Log sensitive data access
            $patientId = $record['patient_id'] ?? 'unknown';
            $logger->logDataAccess('medical_records', $record_id, $user_id, 'view');
            
        } else {
            // IDOR Prevention: Record doesn't exist OR user doesn't have access
            $error_message = "Record not found or you don't have permission to view it.";
            $logger->logSuspiciousActivity("ACCESS_DENIED", "Unauthorized access attempt to record: $record_id", $user_id);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SafeClinic - View Medical Record</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .record-detail {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        .detail-row {
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .detail-label {
            color: #7f8c8d;
            font-size: 12px;
            text-transform: uppercase;
            font-weight: bold;
        }
        .detail-value {
            color: #2c3e50;
            font-size: 14px;
            margin-top: 5px;
        }
        .back-btn {
            display: inline-block;
            background: #3498db;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 20px;
            transition: 0.3s;
        }
        .back-btn:hover {
            background: #2980b9;
        }
    </style>
</head>
<body class="dashboard-page">
    <div style="margin-left: 0; max-width: 900px; margin: 40px auto; padding: 20px;">
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        
        <h1>Medical Record Details</h1>
        
        <?php if (!empty($error_message)): ?>
            <div style="color: #d32f2f; background: #ffebee; padding: 15px; border-radius: 5px;">
                <strong>Error:</strong> <?php echo escapeOutput($error_message); ?>
            </div>
        <?php elseif ($record): ?>
            <div class="record-detail">
                <h2 style="margin-bottom: 20px;">Patient: <?php echo escapeOutput($record['full_name']); ?></h2>
                
                <div class="detail-row">
                    <div class="detail-label">Date of Birth</div>
                    <div class="detail-value"><?php echo escapeOutput($record['date_of_birth']); ?></div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Address</div>
                    <div class="detail-value"><?php echo escapeOutput($record['address']); ?></div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Diagnosis</div>
                    <div class="detail-value"><?php echo escapeOutput($record['diagnosis']); ?></div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Treatment</div>
                    <div class="detail-value"><?php echo escapeOutput($record['treatment']); ?></div>
                </div>
                
                <?php if (!empty($record['notes'])): ?>
                <div class="detail-row">
                    <div class="detail-label">Notes</div>
                    <div class="detail-value"><?php echo escapeOutput($record['notes']); ?></div>
                </div>
                <?php endif; ?>
                
                <div class="detail-row" style="border-bottom: none;">
                    <div class="detail-label">Record Date</div>
                    <div class="detail-value"><?php echo escapeOutput($record['created_at']); ?></div>
                </div>
            </div>
        <?php else: ?>
            <div style="background: #e8f5e9; padding: 15px; border-radius: 5px; color: #2e7d32;">
                No record data to display.
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
