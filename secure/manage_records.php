<?php
session_start();
require_once '../includes/db_connect.php';
require_once 'includes/config.php';
require_once 'includes/logger.php';

$logger = new SecurityLogger();

// Check authentication and doctor role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    $logger->logSuspiciousActivity($_SESSION['user_id'] ?? null, 'Unauthorized access attempt to manage_records.php');
    header('Location: index.php');
    exit;
}

$doctor_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle record creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    // CSRF token validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'CSRF token validation failed.';
        $logger->logSuspiciousActivity($doctor_id, 'CSRF token mismatch on record creation');
    } else {
        $patient_id = intval($_POST['patient_id'] ?? 0);
        $diagnosis = trim($_POST['diagnosis'] ?? '');
        $treatment = trim($_POST['treatment'] ?? '');

        // Validate inputs
        $validationErrors = [];
        
        if ($patient_id <= 0) {
            $validationErrors[] = 'Invalid patient selected';
        }
        
        if (strlen($diagnosis) < 5 || strlen($diagnosis) > 500) {
            $validationErrors[] = 'Diagnosis must be 5-500 characters';
        }
        
        if (strlen($treatment) < 5 || strlen($treatment) > 500) {
            $validationErrors[] = 'Treatment must be 5-500 characters';
        }

        if (empty($validationErrors)) {
            // Verify patient exists
            $patientResult = executePreparedQuery(
                'SELECT id FROM patients WHERE user_id = ?',
                'i',
                [$patient_id]
            );

            if ($patientResult && mysqli_num_rows($patientResult) > 0) {
                // Create medical record
                $insertResult = executeModifyQuery(
                    'INSERT INTO medical_records (patient_id, diagnosis, treatment, created_by) VALUES (?, ?, ?, ?)',
                    'issi',
                    [$patient_id, $diagnosis, $treatment, $doctor_id]
                );

                if ($insertResult) {
                    $message = 'Medical record created successfully';
                    $logger->logModification($doctor_id, "CREATED_RECORD", "patient_id: $patient_id");
                } else {
                    $error = 'Failed to create record. Please try again.';
                }
            } else {
                $error = 'Invalid patient selected.';
            }
        } else {
            $error = implode(' ', $validationErrors);
        }
    }
}

// Handle record update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    // CSRF token validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'CSRF token validation failed.';
        $logger->logSuspiciousActivity($doctor_id, 'CSRF token mismatch on record update');
    } else {
        $record_id = intval($_POST['record_id'] ?? 0);
        $diagnosis = trim($_POST['diagnosis'] ?? '');
        $treatment = trim($_POST['treatment'] ?? '');

        // Validate inputs
        $validationErrors = [];
        
        if ($record_id <= 0) {
            $validationErrors[] = 'Invalid record ID';
        }
        
        if (strlen($diagnosis) < 5 || strlen($diagnosis) > 500) {
            $validationErrors[] = 'Diagnosis must be 5-500 characters';
        }
        
        if (strlen($treatment) < 5 || strlen($treatment) > 500) {
            $validationErrors[] = 'Treatment must be 5-500 characters';
        }

        if (empty($validationErrors)) {
            // Update record
            $updateResult = executeModifyQuery(
                'UPDATE medical_records SET diagnosis = ?, treatment = ?, updated_by = ?, updated_at = NOW() WHERE id = ?',
                'ssii',
                [$diagnosis, $treatment, $doctor_id, $record_id]
            );

            if ($updateResult) {
                $message = 'Medical record updated successfully';
                $logger->logModification($doctor_id, "UPDATED_RECORD", "record_id: $record_id");
            } else {
                $error = 'Failed to update record. Please try again.';
            }
        } else {
            $error = implode(' ', $validationErrors);
        }
    }
}

// Handle record deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    // CSRF token validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'CSRF token validation failed.';
        $logger->logSuspiciousActivity($doctor_id, 'CSRF token mismatch on record deletion');
    } else {
        $record_id = intval($_POST['record_id'] ?? 0);

        if ($record_id <= 0) {
            $error = 'Invalid record ID.';
        } else {
            // Delete record
            $deleteResult = executeModifyQuery(
                'DELETE FROM medical_records WHERE id = ?',
                'i',
                [$record_id]
            );

            if ($deleteResult) {
                $message = 'Medical record deleted successfully';
                $logger->logModification($doctor_id, "DELETED_RECORD", "record_id: $record_id");
            } else {
                $error = 'Failed to delete record. Please try again.';
            }
        }
    }
}

// Regenerate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Fetch all patients for dropdown
$patientsResult = executePreparedQuery(
    'SELECT p.user_id, u.username FROM patients p JOIN users u ON p.user_id = u.id WHERE u.role = ? ORDER BY u.username',
    's',
    ['patient']
);

$patients = [];
if ($patientsResult) {
    while ($row = mysqli_fetch_assoc($patientsResult)) {
        $patients[] = $row;
    }
}

// Fetch all medical records
$recordsResult = executePreparedQuery(
    'SELECT mr.id, mr.patient_id, u.username, mr.diagnosis, mr.treatment, mr.created_at 
     FROM medical_records mr 
     JOIN users u ON mr.patient_id = u.id 
     ORDER BY mr.created_at DESC',
    '',
    []
);

$records = [];
if ($recordsResult) {
    while ($row = mysqli_fetch_assoc($recordsResult)) {
        $records[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Medical Records - SafeClinic</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .form-section { background: #f5f5f5; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px; font-family: Arial, sans-serif; }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .form-group button { background: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer; }
        .form-group button:hover { background: #45a049; }
        .message { padding: 15px; margin-bottom: 20px; border-radius: 3px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table th, table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        table th { background: #f5f5f5; font-weight: bold; }
        table tr:hover { background: #f9f9f9; }
        .action-btn { padding: 5px 10px; margin-right: 5px; border: none; border-radius: 3px; cursor: pointer; font-size: 12px; }
        .edit-btn { background: #2196F3; color: white; }
        .edit-btn:hover { background: #0b7dda; }
        .delete-btn { background: #f44336; color: white; }
        .delete-btn:hover { background: #da190b; }
        .back-link { margin-bottom: 20px; }
        .back-link a { color: #2196F3; text-decoration: none; }
        .back-link a:hover { text-decoration: underline; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); }
        .modal.show { display: block; }
        .modal-content { background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 500px; border-radius: 5px; }
        .close-btn { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close-btn:hover { color: black; }
    </style>
</head>
<body>
    <div class="container">
        <div class="back-link">
            <a href="dashboard.php">← Back to Dashboard</a>
        </div>

        <h1>Manage Medical Records</h1>

        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="form-section">
            <h2>Create New Record</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div class="form-group">
                    <label for="patient_id">Patient:</label>
                    <select id="patient_id" name="patient_id" required>
                        <option value="">Select a patient</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?php echo htmlspecialchars($patient['user_id']); ?>">
                                <?php echo htmlspecialchars($patient['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="diagnosis">Diagnosis:</label>
                    <textarea id="diagnosis" name="diagnosis" required placeholder="Enter diagnosis (5-500 characters)"></textarea>
                </div>

                <div class="form-group">
                    <label for="treatment">Treatment:</label>
                    <textarea id="treatment" name="treatment" required placeholder="Enter treatment plan (5-500 characters)"></textarea>
                </div>

                <div class="form-group">
                    <button type="submit">Create Record</button>
                </div>
            </form>
        </div>

        <div>
            <h2>Medical Records</h2>
            <?php if (!empty($records)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Diagnosis</th>
                            <th>Treatment</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $record): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($record['username']); ?></td>
                                <td><?php echo htmlspecialchars(substr($record['diagnosis'], 0, 50)) . '...'; ?></td>
                                <td><?php echo htmlspecialchars(substr($record['treatment'], 0, 50)) . '...'; ?></td>
                                <td><?php echo htmlspecialchars($record['created_at']); ?></td>
                                <td>
                                    <button class="action-btn edit-btn" onclick="editRecord(<?php echo htmlspecialchars($record['id']); ?>, <?php echo htmlspecialchars(json_encode($record)); ?>)">Edit</button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="record_id" value="<?php echo htmlspecialchars($record['id']); ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <button type="submit" class="action-btn delete-btn">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No medical records found.</p>
            <?php endif; ?>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeEditModal()">&times;</span>
            <h2>Edit Medical Record</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="record_id" id="edit_record_id">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div class="form-group">
                    <label for="edit_diagnosis">Diagnosis:</label>
                    <textarea id="edit_diagnosis" name="diagnosis" required></textarea>
                </div>

                <div class="form-group">
                    <label for="edit_treatment">Treatment:</label>
                    <textarea id="edit_treatment" name="treatment" required></textarea>
                </div>

                <div class="form-group">
                    <button type="submit">Update Record</button>
                    <button type="button" onclick="closeEditModal()" style="background: #999; margin-left: 10px;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function editRecord(recordId, record) {
            document.getElementById('edit_record_id').value = recordId;
            document.getElementById('edit_diagnosis').value = record.diagnosis;
            document.getElementById('edit_treatment').value = record.treatment;
            document.getElementById('editModal').classList.add('show');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
        }

        window.onclick = function(event) {
            var modal = document.getElementById('editModal');
            if (event.target == modal) {
                modal.classList.remove('show');
            }
        }
    </script>
</body>
</html>
