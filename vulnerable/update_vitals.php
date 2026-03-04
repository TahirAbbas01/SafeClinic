<?php
/**
 * VULNERABLE: Nurse Update Medical Records
 * Can read all records and update vitals/notes
 * Cannot delete records (role limitation)
 * Missing Input Validation, Prepared Statements, CSRF Protection
 */

session_start();
include('includes/db_connect.php');

// Vulnerable: Minimal access control
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

// Vulnerable: No role check (should verify nurse role)
// if ($_SESSION['role'] !== 'nurse') { die("Access denied"); }

$role = $_SESSION['role'];
$error = "";
$success = "";

// Handle record update (Nurse can update but not delete)
if (isset($_POST['update_record'])) {
    // Vulnerable: No CSRF token
    // Vulnerable: No input validation
    $record_id = $_POST['record_id'];
    $diagnosis = $_POST['diagnosis'];
    $treatment = $_POST['treatment'];
    
    // Vulnerable: SQL Injection
    $sql = "UPDATE medical_records 
            SET diagnosis='$diagnosis', treatment='$treatment' 
            WHERE id=$record_id";
    
    if (mysqli_query($conn, $sql)) {
        $success = "Record updated successfully!";
    } else {
        $error = "Error: " . mysqli_error($conn);
    }
}

// Fetch all medical records (Nurse can see all)
$records_res = mysqli_query($conn, 
    "SELECT m.id, m.patient_id, m.diagnosis, m.treatment, m.created_at, u.full_name 
     FROM medical_records m 
     JOIN patients p ON m.patient_id = p.id 
     JOIN users u ON p.user_id = u.id 
     ORDER BY m.created_at DESC");
$records = [];
while ($row = mysqli_fetch_assoc($records_res)) {
    $records[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SafeClinic - Nurse: Update Records</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .record-item {
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            background: white;
        }
        .record-item h4 {
            margin-top: 0;
        }
        .update-form {
            margin-top: 10px;
            padding: 10px;
            background: #f5f5f5;
            border-radius: 4px;
        }
        .update-form textarea {
            width: 100%;
            padding: 8px;
            margin: 8px 0;
            box-sizing: border-box;
        }
        .btn-update {
            background: #27ae60;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }
        .btn-update:hover {
            background: #229954;
        }
    </style>
</head>
<body class="dashboard-page">
    <div style="margin-left: 0; width: 100%; max-width: 1000px; margin: 20px auto; padding: 20px;">
        <a href="dashboard.php" style="color: #3498db; text-decoration: none;">← Back to Dashboard</a>
        <h1>Medical Records - Nurse View (Read All, Update Only)</h1>
        
        <?php if (!empty($error)): ?>
            <div style="color: #d32f2f; background: #ffebee; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div style="color: #388e3c; background: #e8f5e9; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <p style="color: #666; font-style: italic;">
                <strong>Nurse Permissions:</strong> Can READ all medical records and UPDATE vitals/notes. Cannot DELETE records.
            </p>
            
            <?php if (empty($records)): ?>
                <p>No medical records found.</p>
            <?php else: ?>
                <?php foreach ($records as $record): ?>
                    <div class="record-item">
                        <h4>Patient: <?php echo $record['full_name']; ?></h4>
                        <p><strong>Diagnosis:</strong> <?php echo $record['diagnosis']; ?></p>
                        <p><strong>Treatment/Vitals:</strong> <?php echo $record['treatment']; ?></p>
                        <small>Created: <?php echo $record['created_at']; ?></small>
                        
                        <div class="update-form">
                            <h5>Update Vitals/Notes</h5>
                            <form action="update_vitals.php" method="POST">
                                <!-- VULNERABLE: No CSRF token -->
                                
                                <input type="hidden" name="record_id" value="<?php echo $record['id']; ?>">
                                
                                <label><strong>Updated Diagnosis:</strong></label>
                                <textarea name="diagnosis" required><?php echo $record['diagnosis']; ?></textarea>
                                <!-- VULNERABLE: No input validation, XSS possible -->
                                
                                <label><strong>Updated Treatment/Vitals:</strong></label>
                                <textarea name="treatment" required><?php echo $record['treatment']; ?></textarea>
                                <!-- VULNERABLE: No input validation, XSS possible -->
                                
                                <button type="submit" name="update_record" class="btn-update">Update Vitals</button>
                                <!-- NOTE: No delete button (Nurse cannot delete) -->
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
