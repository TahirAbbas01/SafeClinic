<?php
session_start();
require_once '../includes/db_connect.php';
require_once 'includes/config.php';
require_once 'includes/logger.php';

$logger = new SecurityLogger();

// Check authentication and admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $logger->logSuspiciousActivity($_SESSION['user_id'] ?? null, 'Unauthorized access attempt to manage_users.php');
    header('Location: index.php');
    exit;
}

$admin_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle user creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    // CSRF token validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'CSRF token validation failed.';
        $logger->logSuspiciousActivity($admin_id, 'CSRF token mismatch on user creation');
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';

        // Validate inputs
        $validationErrors = [];
        
        if (!validateInput($username, 'username')) {
            $validationErrors[] = 'Username must be 3-50 characters (alphanumeric, underscore, hyphen only)';
        }
        
        if (!validateInput($password, 'password')) {
            $validationErrors[] = 'Password must be 8+ characters with uppercase, lowercase, number, and special character';
        }
        
        if (!in_array($role, ['admin', 'doctor', 'nurse', 'patient'], true)) {
            $validationErrors[] = 'Invalid role selected';
        }

        if (empty($validationErrors)) {
            // Check if username already exists
            $result = executePreparedQuery(
                'SELECT id FROM users WHERE username = ?',
                's',
                [$username]
            );

            if ($result && mysqli_num_rows($result) > 0) {
                $error = 'Username already exists.';
            } else {
                // Hash password using bcrypt
                $passwordHash = hashPassword($password);

                // Create user
                $insertResult = executeModifyQuery(
                    'INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)',
                    'sss',
                    [$username, $passwordHash, $role]
                );

                if ($insertResult) {
                    $message = "User '$username' created successfully with role '$role'";
                    $logger->logModification($admin_id, "CREATED_USER", "username: $username, role: $role");
                } else {
                    $error = 'Failed to create user. Please try again.';
                }
            }
        } else {
            $error = implode(' ', $validationErrors);
        }
    }
}

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    // CSRF token validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'CSRF token validation failed.';
        $logger->logSuspiciousActivity($admin_id, 'CSRF token mismatch on user deletion');
    } else {
        $delete_user_id = intval($_POST['user_id'] ?? 0);

        if ($delete_user_id <= 0) {
            $error = 'Invalid user ID.';
        } elseif ($delete_user_id === $admin_id) {
            $error = 'You cannot delete your own account.';
        } else {
            // Get username for logging
            $userResult = executePreparedQuery(
                'SELECT username FROM users WHERE id = ?',
                'i',
                [$delete_user_id]
            );

            if ($userResult && $userData = mysqli_fetch_assoc($userResult)) {
                $username = $userData['username'];

                // Delete user
                $deleteResult = executeModifyQuery(
                    'DELETE FROM users WHERE id = ?',
                    'i',
                    [$delete_user_id]
                );

                if ($deleteResult) {
                    $message = "User '$username' deleted successfully";
                    $logger->logModification($admin_id, "DELETED_USER", "user_id: $delete_user_id, username: $username");
                } else {
                    $error = 'Failed to delete user. Please try again.';
                }
            } else {
                $error = 'User not found.';
            }
        }
    }
}

// Regenerate CSRF token for next request
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Fetch all users for display
$usersResult = executePreparedQuery(
    'SELECT id, username, role, created_at FROM users ORDER BY created_at DESC',
    '',
    []
);

$users = [];
if ($usersResult) {
    while ($row = mysqli_fetch_assoc($usersResult)) {
        $users[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - SafeClinic</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .container { max-width: 1000px; margin: 0 auto; padding: 20px; }
        .form-section { background: #f5f5f5; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; }
        .form-group input, .form-group select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px; }
        .form-group button { background: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer; }
        .form-group button:hover { background: #45a049; }
        .message { padding: 15px; margin-bottom: 20px; border-radius: 3px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table th, table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        table th { background: #f5f5f5; font-weight: bold; }
        table tr:hover { background: #f9f9f9; }
        .delete-btn { background: #f44336; color: white; padding: 5px 10px; border: none; border-radius: 3px; cursor: pointer; font-size: 12px; }
        .delete-btn:hover { background: #da190b; }
        .back-link { margin-bottom: 20px; }
        .back-link a { color: #2196F3; text-decoration: none; }
        .back-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <div class="back-link">
            <a href="dashboard.php">← Back to Dashboard</a>
        </div>

        <h1>Manage Users</h1>

        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="form-section">
            <h2>Create New User</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required placeholder="3-50 characters, alphanumeric + underscore/hyphen">
                </div>

                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required placeholder="8+ chars with uppercase, lowercase, number, special char">
                </div>

                <div class="form-group">
                    <label for="role">Role:</label>
                    <select id="role" name="role" required>
                        <option value="">Select a role</option>
                        <option value="admin">Admin</option>
                        <option value="doctor">Doctor</option>
                        <option value="nurse">Nurse</option>
                        <option value="patient">Patient</option>
                    </select>
                </div>

                <div class="form-group">
                    <button type="submit">Create User</button>
                </div>
            </form>
        </div>

        <div>
            <h2>Existing Users</h2>
            <?php if (!empty($users)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Created</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['role']); ?></td>
                                <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                                <td>
                                    <?php if ($user['id'] !== $admin_id): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id']); ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            <button type="submit" class="delete-btn">Delete</button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: #999;">Current User</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No users found.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
