<?php
/**
 * VULNERABLE: Doctor Medical Records Management
 * Missing Prepared Statements, Input Validation, Access Control
 * Vulnerable to SQL Injection, CSRF, XSS, IDOR
 */

session_start();
include('includes/db_connect.php');

// Vulnerable: No proper access control
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

// Vulnerable: No role check (should check if doctor)
// if ($_SESSION['role'] !== 'doctor') { die("Access denied"); }

$role = $_SESSION['role'];
$error = "";
$success = "";

// Handle record creation
if (isset($_POST['create_record'])) {
    // Vulnerable: No CSRF token
    // Vulnerable: No input validation
    $patient_id = $_POST['patient_id'];
    $diagnosis = $_POST['diagnosis'];
    $treatment = $_POST['treatment'];
    
    // Vulnerable: SQL Injection
    $sql = "INSERT INTO medical_records (patient_id, diagnosis, treatment) 
            VALUES ($patient_id, '$diagnosis', '$treatment')";
    
    if (mysqli_query($conn, $sql)) {
        $success = "Medical record created successfully!";
    } else {
        $error = "Error: " . mysqli_error($conn);
    }
}

// Handle record update
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

// Handle record deletion
if (isset($_POST['delete_record'])) {
    // Vulnerable: No CSRF token
    $record_id = $_POST['record_id'];
    
    // Vulnerable: SQL Injection
    $sql = "DELETE FROM medical_records WHERE id=$record_id";
    
    if (mysqli_query($conn, $sql)) {
        $success = "Record deleted successfully!";
    } else {
        $error = "Error: " . mysqli_error($conn);
    }
}

// Fetch all patients
$patients_res = mysqli_query($conn, "SELECT id, user_id FROM patients");
$patients = [];
while ($row = mysqli_fetch_assoc($patients_res)) {
    $patients[] = $row;
}

// Fetch all medical records
$records_res = mysqli_query($conn, 
    "SELECT m.id, m.diagnosis, m.treatment, m.created_at, u.full_name 
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
    <title>SafeClinic - Manage Medical Records</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .content-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .record-item {
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .record-item h4 {
            margin-top: 0;
        }
        .btn-group {
            display: flex;
            gap: 5px;
            margin-top: 10px;
        }
        button {
            padding: 8px 12px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }
        .btn-edit {
            background: #3498db;
            color: white;
        }
        .btn-delete {
            background: #e74c3c;
            color: white;
        }
    </style>
</head>
<body class="dashboard-page">
    <div style="margin-left: 0; width: 100%; padding: 20px;">
        <a href="dashboard.php" style="color: #3498db; text-decoration: none;">← Back to Dashboard</a>
        <h1>Medical Records Management (Doctor)</h1>
        
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
        
        <div class="content-section">
            <h3>Create New Medical Record</h3>
            <form action="manage_records.php" method="POST" class="form-grid">
                <!-- VULNERABLE: No CSRF token -->
                
                <div>
                    <label><strong>Select Patient:</strong></label>
                    <select name="patient_id" required>
                        <option value="">-- Choose Patient --</option>
                        <!-- VULNERABLE: Shows all patients without filtering -->
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?php echo $patient['id']; ?>">Patient ID: <?php echo $patient['id']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label><strong>Diagnosis:</strong></label>
                    <input type="text" name="diagnosis" placeholder="Enter diagnosis" required>
                    <!-- VULNERABLE: No input validation, XSS possible -->
                </div>
                
                <div style="grid-column: 1 / -1;">
                    <label><strong>Treatment:</strong></label>
                    <textarea name="treatment" placeholder="Enter treatment plan" required></textarea>
                    <!-- VULNERABLE: No input validation, XSS possible -->
                </div>
                
                <button type="submit" name="create_record">Create Record</button>
            </form>
        </div>
        
        <div class="content-section">
            <h3>All Medical Records (CRUD Operations)</h3>
            
            <?php if (empty($records)): ?>
                <p>No medical records found.</p>
            <?php else: ?>
                <?php foreach ($records as $record): ?>
                    <div class="record-item">
                        <h4><?php echo $record['full_name']; ?></h4>
                        <p><strong>Diagnosis:</strong> <?php echo $record['diagnosis']; ?></p>
                        <p><strong>Treatment:</strong> <?php echo $record['treatment']; ?></p>
                        <small>Created: <?php echo $record['created_at']; ?></small>
                        
                        <div class="btn-group">
                            <form action="manage_records.php" method="POST" style="display: inline;">
                                <!-- VULNERABLE: No CSRF token -->
                                <input type="hidden" name="record_id" value="<?php echo $record['id']; ?>">
                                <input type="hidden" name="diagnosis" value="<?php echo htmlspecialchars($record['diagnosis']); ?>">
                                <input type="hidden" name="treatment" value="<?php echo htmlspecialchars($record['treatment']); ?>">
                                <button type="submit" class="btn-edit" name="update_record">Update</button>
                            </form>
                            
                            <form action="manage_records.php" method="POST" style="display: inline;">
                                <!-- VULNERABLE: No CSRF token -->
                                <input type="hidden" name="record_id" value="<?php echo $record['id']; ?>">
                                <button type="submit" class="btn-delete" name="delete_record" onclick="return confirm('Delete this record?');">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
