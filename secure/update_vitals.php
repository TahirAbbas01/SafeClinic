<?php
session_start();
require_once '../includes/db_connect.php';
require_once 'includes/config.php';
require_once 'includes/logger.php';

$logger = new SecurityLogger();

// Check authentication and nurse role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'nurse') {
    $logger->logSuspiciousActivity($_SESSION['user_id'] ?? null, 'Unauthorized access attempt to update_vitals.php');
    header('Location: index.php');
    exit;
}

$nurse_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle vitals update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_vitals') {
    // CSRF token validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'CSRF token validation failed.';
        $logger->logSuspiciousActivity($nurse_id, 'CSRF token mismatch on vitals update');
    } else {
        $record_id = intval($_POST['record_id'] ?? 0);
        $treatment = trim($_POST['treatment'] ?? '');

        // Validate inputs
        $validationErrors = [];
        
        if ($record_id <= 0) {
            $validationErrors[] = 'Invalid record ID';
        }
        
        if (strlen($treatment) < 5 || strlen($treatment) > 500) {
            $validationErrors[] = 'Treatment notes must be 5-500 characters';
        }

        if (empty($validationErrors)) {
            // Update only the treatment field (vitals)
            $updateResult = executeModifyQuery(
                'UPDATE medical_records SET treatment = ?, updated_by = ?, updated_at = NOW() WHERE id = ?',
                'sii',
                [$treatment, $nurse_id, $record_id]
            );

            if ($updateResult) {
                $message = 'Vitals and treatment notes updated successfully';
                $logger->logModification($nurse_id, "UPDATED_VITALS", "record_id: $record_id");
            } else {
                $error = 'Failed to update vitals. Please try again.';
            }
        } else {
            $error = implode(' ', $validationErrors);
        }
    }
}

// Regenerate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Fetch all medical records (nurses can see all)
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
    <title>Update Vitals - SafeClinic</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .info-box { background: #e3f2fd; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #2196F3; }
        .form-section { background: #f5f5f5; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; }
        .form-group input, .form-group textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px; font-family: Arial, sans-serif; }
        .form-group textarea { resize: vertical; min-height: 100px; }
        .form-group button { background: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer; }
        .form-group button:hover { background: #45a049; }
        .message { padding: 15px; margin-bottom: 20px; border-radius: 3px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table th, table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        table th { background: #f5f5f5; font-weight: bold; }
        table tr:hover { background: #f9f9f9; }
        .edit-btn { background: #2196F3; color: white; padding: 5px 10px; border: none; border-radius: 3px; cursor: pointer; font-size: 12px; }
        .edit-btn:hover { background: #0b7dda; }
        .back-link { margin-bottom: 20px; }
        .back-link a { color: #2196F3; text-decoration: none; }
        .back-link a:hover { text-decoration: underline; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); }
        .modal.show { display: block; }
        .modal-content { background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 500px; border-radius: 5px; }
        .close-btn { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close-btn:hover { color: black; }
        .read-only { background: #f0f0f0; padding: 10px; border-radius: 3px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="back-link">
            <a href="dashboard.php">← Back to Dashboard</a>
        </div>

        <h1>Update Vitals and Treatment Notes</h1>

        <div class="info-box">
            <strong>Nurse Role:</strong> You have read-only access to all patient records and can update treatment notes and vitals only. You cannot create or delete records.
        </div>

        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div>
            <h2>All Patient Records</h2>
            <?php if (!empty($records)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Diagnosis</th>
                            <th>Current Treatment/Vitals</th>
                            <th>Last Updated</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $record): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($record['username']); ?></td>
                                <td><?php echo htmlspecialchars($record['diagnosis']); ?></td>
                                <td><?php echo htmlspecialchars(substr($record['treatment'], 0, 50)) . '...'; ?></td>
                                <td><?php echo htmlspecialchars($record['created_at']); ?></td>
                                <td>
                                    <button class="edit-btn" onclick="openEditModal(<?php echo htmlspecialchars($record['id']); ?>, <?php echo htmlspecialchars(json_encode($record)); ?>)">Update Vitals</button>
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
            <h2>Update Treatment Notes and Vitals</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update_vitals">
                <input type="hidden" name="record_id" id="edit_record_id">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div class="read-only">
                    <p><strong>Diagnosis (Read-Only):</strong></p>
                    <p id="view_diagnosis"></p>
                </div>

                <div class="form-group">
                    <label for="edit_treatment">Treatment Notes & Vitals:</label>
                    <textarea id="edit_treatment" name="treatment" required placeholder="Update treatment notes and vitals..."></textarea>
                </div>

                <div class="form-group">
                    <button type="submit">Update Vitals</button>
                    <button type="button" onclick="closeEditModal()" style="background: #999; margin-left: 10px;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(recordId, record) {
            document.getElementById('edit_record_id').value = recordId;
            document.getElementById('view_diagnosis').textContent = record.diagnosis;
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
