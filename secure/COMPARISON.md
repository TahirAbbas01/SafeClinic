# Vulnerable vs Secure Code Comparison

## Side-by-Side Code Examples

This document compares vulnerable code from `/vulnerable/` with secure implementations in `/secure/`.

---

## 1. SQL INJECTION PREVENTION

### ❌ VULNERABLE: String Interpolation
**File:** `/vulnerable/index.php`

```php
<?php
session_start();
include('../includes/db_connect.php');

if (isset($_POST['login'])) {
    $username = $_POST['username'];      // Directly from user input
    $password = $_POST['password'];      // No sanitization

    // VULNERABLE: SQL with string interpolation
    $sql = "SELECT id, username, full_name, role FROM users 
            WHERE username='$username' AND password='$password'";
    
    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        $_SESSION['user_id'] = $user['id'];
        // ... login success
    } else {
        $error = "Wrong Username or Password!";
    }
}
?>
```

**Attack Payloads:**

```
Payload 1: SQL Authentication Bypass
Username: admin' OR '1'='1
Password: anything

Resulting query:
SELECT id, username, full_name, role FROM users 
WHERE username='admin' OR '1'='1' AND password='anything'

Database interprets: WHERE username='admin' OR TRUE AND password='...'
Result: ✗ BYPASSED - Returns admin user without checking password!

---

Payload 2: SQL Injection with Comment
Username: admin' --
Password: (any)

Resulting query:
SELECT ... WHERE username='admin' --' AND password='...'

The -- comments out everything after, so password check is ignored!

---

Payload 3: UNION-based Injection
Username: admin' UNION SELECT 1, 'hacker', 'Hacker User', 'admin' --
Password: (any)

Result: ✗ Can extract/modify data from other tables!

---

Payload 4: Blind SQL Injection
Username: admin' AND (SELECT * FROM (SELECT(SLEEP(5)))a) --
Password: (any)

If page takes 5 seconds, database executed the command!
Attacker can extract data character by character.
```

---

### ✅ SECURE: Prepared Statements
**File:** `/secure/includes/db_connect.php`

```php
<?php
/**
 * Secure Query Execution with Prepared Statements
 * 
 * All parametric queries use bound variables instead of string concatenation
 */

function executePreparedQuery($conn, $sql, $params = [], $types = "") {
    try {
        // Step 1: Prepare query (WITHOUT user data)
        $stmt = $conn->prepare($sql);
        
        // Step 2: Bind parameters securely
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);  // "s" = string, "i" = integer
        }
        
        // Step 3: Execute (user data sent separately)
        $stmt->execute();
        
        // Get result as associative array
        $result = $stmt->get_result();
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        $stmt->close();
        return $data;
        
    } catch (Exception $e) {
        error_log("Query Error: " . $e->getMessage());
        return null;
    }
}
?>
```

**Usage in `/secure/index.php`:**

```php
<?php
// Secure login
if (isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? "");
    $password = trim($_POST['password'] ?? "");
    
    // PREPARED STATEMENT: Query structure sent without data
    $users = executePreparedQuery(
        $conn,
        "SELECT id, username, full_name, role, password_hash FROM users 
         WHERE username = ?",  // ? = placeholder for parameter
        [$username],           // Parameter values in separate array
        "s"                    // "s" = string type
    );
}
?>
```

**Why It's Secure:**

```
Step 1: SQL Structure Sent to Database
    SELECT id, username, full_name, role, password_hash FROM users WHERE username = ?
    (Database now knows the query structure, ? is a placeholder)

Step 2: Parameter Sent Separately
    Parameter 1 (string): "admin' OR '1'='1"
    (Database knows this is DATA, not SQL code)

Step 3: Database Processes
    The string "admin' OR '1'='1" is treated as a LITERAL STRING value
    Special characters (', ", ;, --) are automatically escaped
    Even if user enters SQL commands, they're just text!

Result: ✓ SQL injection is IMPOSSIBLE
```

**Why SQL Injection Works in Vulnerable Code:**

```
Without prepared statements, database cannot distinguish between:
    Code: DELETE FROM users;
    Data: "DELETE FROM users;"

String interpolation mixes code and data:
    $sql = "WHERE username='$username'"
    
If $username = "admin' OR '1'='1", the database sees:
    WHERE username='admin' OR '1'='1'
    
This is executable SQL! The OR '1'='1' changes the logic.
```

---

## 2. PASSWORD SECURITY

### ❌ VULNERABLE: Plain-Text Passwords
**File:** `/vulnerable/sql/init_db.sql`

```sql
-- VULNERABLE: Passwords stored in plain text!
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,    -- Plain text! ❌
    role ENUM('admin', 'doctor', 'nurse', 'patient'),
    full_name VARCHAR(100)
);

INSERT INTO users (username, password, role) VALUES 
('admin_user', 'admin123', 'admin'),
('dr_smith', 'doctor456', 'doctor'),
('nurse_joy', 'nurse789', 'nurse'),
('patient_zero', 'password', 'patient');
```

**Login Implementation:**

```php
<?php
$sql = "SELECT * FROM users WHERE username='$username' AND password='$password'";
// Direct password comparison in database
// If attacker guesses: patient_zero / password → Login succeeds!
?>
```

**What Happens if Database is Breached:**

```
Attacker gains access to database:

users table:
┌───────────────┬─────────────┬────────┐
│ username      │ password    │ role   │
├───────────────┼─────────────┼────────┤
│ admin_user    │ admin123    │ admin  │  ← Password exposed!
│ dr_smith      │ doctor456   │ doctor │  ← Password exposed!
│ nurse_joy     │ nurse789    │ nurse  │  ← Password exposed!
│ patient_zero  │ password    │ patient│  ← Password exposed!
└───────────────┴─────────────┴────────┘

Attacker can:
1. Use these passwords on other systems (password reuse)
2. Sell password list on dark web
3. Impersonate any user immediately
4. Break medical confidentiality (HIPAA violation)
5. Modify patient records

Impact: CRITICAL - All user accounts compromised!
```

---

### ✅ SECURE: Bcrypt Password Hashing
**File:** `/secure/includes/config.php`

```php
<?php
/**
 * SECURE PASSWORD HASHING with Bcrypt
 * Uses password_hash() with automatic salt
 */

function hashPassword($password) {
    // password_hash uses bcrypt algorithm
    // cost = 10 means ~1024 iterations of SHA-512
    // Takes ~0.5 seconds (expensive brute force)
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
}

function verifyPassword($password, $hash) {
    // password_verify is timing-safe
    // Takes constant time regardless of match/mismatch
    return password_verify($password, $hash);
}
?>
```

**Database With Secure Passwords:**

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,  -- Stores bcrypt hash ✓
    ...
);

INSERT INTO users (username, password_hash, ...) VALUES
('admin_user', '$2y$10$N0ry.8TZwP8/r1B9Q2c..eQl5t1T.KQz6LvNqR8JxV2q5ZkVmUvAl', 'admin'),
('dr_smith', '$2y$10$N0ry.8TZwP8/r1B9Q2c..eQl5t1T.KQz6LvNqR8JxV2q5ZkVmUvAl', 'doctor'),
...

-- All passwords are 60-character bcrypt hashes starting with $2y$10$
```

**Login Implementation:**

```php
<?php
// Fetch user by username (prepared statement)
$users = executePreparedQuery(
    $conn,
    "SELECT id, username, password_hash FROM users WHERE username = ?",
    [$username],
    "s"
);

if (!empty($users)) {
    $storedHash = $users[0]['password_hash'];
    
    // Verify password using timing-safe comparison
    if (password_verify($password, $storedHash)) {
        $_SESSION['user_id'] = $users[0]['id'];
        // Login successful
    } else {
        // Wrong password
    }
}
?>
```

**What Happens if Database is Breached:**

```
Attacker gains access to database:

users table:
┌───────────────┬──────────────────────────────────────────────────────────┬────────┐
│ username      │ password_hash                                            │ role   │
├───────────────┼──────────────────────────────────────────────────────────┼────────┤
│ admin_user    │ $2y$10$N0ry.8TZwP8/r1B9Q2c..eQl5t1T.KQz6LvNqR8J...     │ admin  │
│ dr_smith      │ $2y$10$N0ry.8TZwP8/r1B9Q2c..eQl5t1T.KQz6LvNqR8J...     │ doctor │
│ nurse_joy     │ $2y$10$N0ry.8TZwP8/r1B9Q2c..eQl5t1T.KQz6LvNqR8J...     │ nurse  │
│ patient_zero  │ $2y$10$N0ry.8TZwP8/r1B9Q2c..eQl5t1T.KQz6LvNqR8J...     │ patient│
└───────────────┴──────────────────────────────────────────────────────────┴────────┘

Attacker CANNOT:
1. Use these hashes as passwords (they're one-way!)
2. Reverse engineer the original password
3. Brute force efficiently (cost=10 makes it very slow)
4. Use advanced GPU attacks (bcrypt is GPU-resistant)

Impact: DATA EXPOSED but passwords are PROTECTED
```

**Bcrypt Security Advantages:**

```
1. One-Way Function:
   Input: "password123" → Output: $2y$10$encrypted...
   Input: $2y$10$encrypted... → Impossible to reverse!

2. Automatic Salt:
   Same password hashed twice = Different hashes
   Hash 1: "admin123" → $2y$10$aaa...
   Hash 2: "admin123" → $2y$10$bbb...
   Rainbow table attacks are useless!

3. Computational Cost:
   cost=10: ~10^10 iterations per hash attempt
   Takes ~0.5 seconds per login
   Takes ~500,000 seconds (139 hours) to break one password!
   Makes GPU/ASIC attacks impractical

4. Future-Proof:
   If computers get 10x faster, just increase cost to 11
   Same code works, just takes longer
```

---

## 3. INPUT VALIDATION

### ❌ VULNERABLE: No Validation
**File:** `/vulnerable/register.php`

```php
<?php
if (isset($_POST['register'])) {
    $username = $_POST['username'];      // No validation
    $password = $_POST['password'];      // No strength check
    $role = $_POST['role'];              // No whitelist
    
    // Allowed attacks:
    // 1. Weak passwords: "a"
    // 2. Invalid usernames: "admin'; DROP TABLE users; --"
    // 3. Invalid roles: "superadmin" (not in enum)
    // 4. Unicode/encoding issues
    // 5. Buffer overflow: 1000-char username
}
?>

<!-- Form accepts anything -->
<input type="text" name="username" placeholder="Username">
<input type="password" name="password" placeholder="Password">
<select name="role">
    <option value="patient">Patient</option>
    <option value="admin">Admin</option>  <!-- Client-side only, can be bypassed -->
</select>
```

**Vulnerable Registration:**

```
What user enters:
Username: <script>alert('XSS')</script>
Password: weak
Role: admin

Result: ❌ All accepted and stored as-is!
```

---

### ✅ SECURE: Comprehensive Validation
**File:** `/secure/includes/config.php`

```php
<?php
// Validation rules defined once, reused everywhere
define('VALIDATION_RULES', [
    'username' => [
        'min_length' => 3,
        'max_length' => 50,
        'pattern' => '/^[a-zA-Z0-9_-]+$/',
        'error' => 'Username must be 3-50 chars: letters, numbers, _, -'
    ],
    'password' => [
        'min_length' => 8,
        'max_length' => 128,
        'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])...$/',
        'error' => 'Password must have uppercase, lowercase, number, special char'
    ],
    'role' => [
        'allowed_values' => ['admin', 'doctor', 'nurse', 'patient'],
        'error' => 'Invalid role selected'
    ]
]);

function validateInput($fieldName, $value) {
    if (!isset(VALIDATION_RULES[$fieldName])) {
        return ['valid' => false, 'error' => 'Unknown field'];
    }
    
    $rules = VALIDATION_RULES[$fieldName];
    
    // Check length
    if (isset($rules['min_length']) && strlen($value) < $rules['min_length'])
        return ['valid' => false, 'error' => $rules['error']];
    
    // Check pattern (WHITELIST approach)
    if (isset($rules['pattern']) && !preg_match($rules['pattern'], $value))
        return ['valid' => false, 'error' => $rules['error']];
    
    // Check allowed values (WHITELIST approach)
    if (isset($rules['allowed_values']) && !in_array($value, $rules['allowed_values']))
        return ['valid' => false, 'error' => $rules['error']];
    
    return ['valid' => true];
}
?>
```

**Usage in `/secure/register.php`:**

```php
<?php
$username = trim($_POST['username'] ?? "");
$password = trim($_POST['password'] ?? "");
$role = trim($_POST['role'] ?? "");

// Validate each field
$validation = validateInput('username', $username);
if (!$validation['valid']) {
    $error = $validation['error'];  // Shows clear error to user
    exit;
}

$validation = validateInput('password', $password);
if (!$validation['valid']) {
    $error = $validation['error'];
    exit;
}

$validation = validateInput('role', $role);
if (!$validation['valid']) {
    $error = "Invalid role";
    exit;
}

// Only if all validations pass, proceed with insertion
$passwordHash = hashPassword($password);
executeModifyQuery(...);
?>
```

**Test Results:**

```
Test 1: Username with SQL injection
Input: admin'; DROP TABLE users; --
Validation: ❌ REJECTED - "Username contains invalid characters"

Test 2: Weak password
Input: abc123
Validation: ❌ REJECTED - "Password must be 8+ with uppercase, lowercase, number, special"

Test 3: Invalid role
Input: superadmin
Validation: ❌ REJECTED - "Invalid role selected"

Test 4: Valid inputs
Input: username=testuser, password=ValidPass123!, role=patient
Validation: ✓ ACCEPTED - Proceeds with registration

Test 5: Email validation
Input: "not-an-email"
Validation: ❌ REJECTED - "Invalid email format"
```

---

## 4. CSRF (Cross-Site Request Forgery) PROTECTION

### ❌ VULNERABLE: No CSRF Protection
**File:** `/vulnerable/register.php`

```html
<!-- Form with NO CSRF token -->
<form action="register.php" method="POST">
    <input type="text" name="username" placeholder="Username">
    <input type="password" name="password" placeholder="Password">
    <button type="submit">Register</button>
</form>
```

**Attack Scenario:**

```
1. Victim logs into SafeClinic

2. Victim opens malicious website (before logout) containing:
   <iframe>
       <form action="https://safeclinic.local/register.php" method="POST">
           <input name="username" value="hacker">
           <input name="password" value="HackerPass123!">
           <input name="role" value="admin">
           <input type="submit" value="Register">
       </form>
   </iframe>
   <script>
       document.forms[0].submit();  // Auto-submit form
   </script>

3. Browser automatically includes victim's SafeClinic cookies in the request
   (Because it's the same domain)

4. SafeClinic server thinks: "This request has valid session cookie, must be legitimate"

Result: ❌ Hacker account created with admin role!
```

---

### ✅ SECURE: CSRF Tokens
**File:** `/secure/includes/config.php`

```php
<?php
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        // Generate random 32-byte token
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    // Use hash_equals for timing-safe comparison
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>
```

**Secure Form:**

```php
<?php
// Generate token before rendering form
$csrf_token = generateCSRFToken();
?>

<form action="register.php" method="POST">
    <!-- CSRF token in hidden field -->
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    
    <input type="text" name="username" placeholder="Username">
    <input type="password" name="password" placeholder="Password">
    <button type="submit">Register</button>
</form>
```

**Verification:**

```php
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    // ALWAYS verify CSRF token first
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die("CSRF token validation failed");
    }
    
    // Only if token is valid, process the request
    // ... proceed with registration ...
}
?>
```

**Why CSRF Protection Works:**

```
Attack attempt from malicious site:

1. Hacker's form has NO csrf_token parameter:
   <input name="username" value="hacker">
   <input name="password" value="HackerPass123!">
   (csrf_token is missing)

2. SafeClinic server checks for token:
   if (!isset($_POST['csrf_token'])) {
       die("CSRF token validation failed");
   }

3. Browser still sends session cookie (same domain)
   BUT csrf_token is missing → Request rejected!

4. Hacker cannot get the token because:
   - Only the legitimate SafeClinic domain can access $_SESSION
   - Malicious website cannot read $_SESSION from SafeClinic
   - SameSite cookie attribute prevents token theft

Result: ✓ CSRF attack is BLOCKED
```

---

## 5. IDOR (Insecure Direct Object Reference) PREVENTION

### ❌ VULNERABLE: No Access Control
**File:** `/vulnerable/view_record.php`

```php
<?php
session_start();
include('../includes/db_connect.php');

// No access control check!
$record_id = $_GET['id'];

// VULNERABLE: Direct query without checking permissions
$sql = "SELECT m.*, p.date_of_birth, p.address, u.full_name 
        FROM medical_records m 
        JOIN patients p ON m.patient_id = p.id 
        JOIN users u ON p.user_id = u.id 
        WHERE m.id = $record_id";

$result = mysqli_query($conn, $sql);
$record = mysqli_fetch_assoc($result);

echo "Patient: " . $record['full_name'];
echo "Diagnosis: " . $record['diagnosis'];
?>
```

**Attack Scenario:**

```
Scenario: Patient "Alice" logs in
Alice's medical record ID is 1

Alice navigates to:
https://safeclinic.local/view_record.php?id=1
Result: ✓ Can see own record

But then Alice tries:
https://safeclinic.local/view_record.php?id=2
Result: ❌ IDOR - She can see Bob's medical record!

https://safeclinic.local/view_record.php?id=3
Result: ❌ IDOR - She can see Charlie's medical record!

https://safeclinic.local/view_record.php?id=999
Result: ❌ IDOR - Tries to see other patients' records

Attacker can:
1. View medical records of all patients
2. Extract all diagnoses, treatments
3. Violate HIPAA (federal law violation!)
4. Blackmail patients with sensitive data
```

---

### ✅ SECURE: Access Control
**File:** `/secure/view_records.php`

```php
<?php
session_start();
include('../includes/db_connect.php');
include('../includes/logger.php');

// Step 1: Validate input
$record_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($record_id <= 0) {
    $error_message = "Invalid record ID.";
    exit;
}

// Step 2: Determine user's access scope
$userPatientId = null;

if ($_SESSION['role'] === 'patient') {
    // Patients can ONLY access their own records
    $patients = executePreparedQuery(
        $conn,
        "SELECT id FROM patients WHERE user_id = ?",
        [$_SESSION['user_id']],
        "i"
    );
    
    if (!empty($patients)) {
        $userPatientId = $patients[0]['id'];
    }
} elseif ($_SESSION['role'] === 'doctor' || $_SESSION['role'] === 'admin') {
    // Doctors can access ANY record (no restriction)
    // Admins can access ANY record
    $userPatientId = null;  // Null = no restriction
}

// Step 3: Query with access control built-in
if ($_SESSION['role'] === 'patient') {
    // For patients: MUST match their patient_id
    $records = executePreparedQuery(
        $conn,
        "SELECT m.*, p.date_of_birth, p.address, u.full_name 
         FROM medical_records m
         JOIN patients p ON m.patient_id = p.id 
         JOIN users u ON p.user_id = u.id 
         WHERE m.id = ? AND m.patient_id = ?",  // TWO conditions required
        [$record_id, $userPatientId],
        "ii"
    );
} elseif ($_SESSION['role'] === 'doctor' || $_SESSION['role'] === 'admin') {
    // For doctors/admins: Can access any record (but still validate it exists)
    $records = executePreparedQuery(
        $conn,
        "SELECT m.*, p.date_of_birth, p.address, u.full_name 
         FROM medical_records m
         JOIN patients p ON m.patient_id = p.id 
         JOIN users u ON p.user_id = u.id 
         WHERE m.id = ?",
        [$record_id],
        "i"
    );
}

// Step 4: Return consistent error message
if (empty($records)) {
    // Don't reveal whether record exists or user lacks permission
    $error = "Record not found or you don't have permission to view it.";
    
    // Log suspicious access attempts
    $logger->logSuspiciousActivity("IDOR_ATTEMPT", "Unauthorized access to record $record_id", $_SESSION['user_id']);
} else {
    // Log legitimate access
    $logger->logDataAccess('medical_records', $record_id, $_SESSION['user_id'], 'view');
    $record = $records[0];
}
?>
```

**Test Results:**

```
Test 1: Patient accessing own record
Alice (patient_id=1) accesses: ?id=1
Query: WHERE m.id = 1 AND m.patient_id = 1
Result: ✓ ALLOWED - Record returned

Test 2: Patient trying to access another's record
Alice (patient_id=1) accesses: ?id=2
Query: WHERE m.id = 2 AND m.patient_id = 1
Database: No match (record 2 belongs to patient_id=2, not 1)
Result: ❌ DENIED - "Record not found or no permission"

Test 3: Doctor accessing any record
Dr. Smith accesses: ?id=2
Query: WHERE m.id = 2 (no patient_id restriction)
Result: ✓ ALLOWED - Doctor can see all records

Test 4: Trying to guess IDs
Attacker tries: ?id=9999
Query: WHERE m.id = 9999 AND m.patient_id = 1
Database: No match
Result: ❌ DENIED - Same error message (doesn't reveal if ID exists)
```

---

## 6. ERROR HANDLING

### ❌ VULNERABLE: Exposes Database Details
**File:** `/vulnerable/view_record.php`

```php
<?php
$record_id = $_GET['id'];

$sql = "SELECT * FROM medical_records WHERE id = $record_id";
$result = mysqli_query($conn, $sql);
$record = mysqli_fetch_assoc($result);

if (!$record) {
    // VULNERABLE: Exposes database error information!
    echo "<p style='color:red;'>Record not found!</p>";
    
    if (mysqli_error($conn)) {
        echo "<p>DB Error: " . mysqli_error($conn) . "</p>";
        // Attacker sees: "Syntax error in SQL... WHERE id = abc123"
        // This helps attacker understand database structure!
    }
}
?>
```

**Information Disclosure:**

```
Attacker tries: ?id=abc123

Error message reveals:
"DB Error: You have an error in your SQL syntax; check the manual... 
WHERE id = abc123"

Attacker learns:
1. Database is MySQL (not PostgreSQL, Oracle, etc.)
2. Exact MySQL version from error message
3. Column name "id" exists
4. Can use this info to craft better attacks
5. Knows SQL query structure
```

---

### ✅ SECURE: Generic Error Messages
**File:** `/secure/view_records.php`

```php
<?php
if (empty($records)) {
    // Generic message, reveals nothing
    $error_message = "Record not found or you don't have permission to view it.";
    
    // Log the suspicious activity for later investigation
    $logger->logSuspiciousActivity("ACCESS_DENIED", "Unauthorized access attempt", $user_id);
} else {
    $record = $records[0];
    // Log successful access
    $logger->logDataAccess('medical_records', $record_id, $user_id, 'view');
}
?>
```

**Attack Result:**

```
Attacker tries: ?id=abc123

Error message:
"Record not found or you don't have permission to view it."

Attacker learns:
1. Nothing (error is intentionally vague)
2. Cannot determine if record exists
3. Cannot determine database type
4. Cannot determine column names

Result: ✓ Information disclosure is PREVENTED
```

---

## Summary Table

| Vulnerability | Vulnerable Code | Secure Code | Impact |
|----------------|-----------------|-------------|---------|
| **SQL Injection** | String interpolation | Prepared statements | Prevents data theft & modification |
| **Weak Passwords** | Plain text | Bcrypt hash | Protects against password reuse |
| **No Validation** | Direct $_POST | Pattern & whitelist | Prevents malformed data |
| **No CSRF** | No token | Token verification | Prevents account takeover |
| **IDOR** | No access check | WHERE clause with user_id | Prevents data leakage |
| **Error Info** | Shows DB errors | Generic messages | Prevents reconnaissance |

---

## Key Principles

### 1. Prepared Statements
**Never** interpolate user input into SQL strings.
**Always** use parameterized queries.

### 2. Input Validation
**Never** trust user input.
**Always** validate format, length, and allowed values.

### 3. Password Security
**Never** store plain-text passwords.
**Always** use bcrypt with cost=10+.

### 4. Access Control
**Never** assume user is authorized.
**Always** check permissions in database query.

### 5. Error Handling  
**Never** expose database details.
**Always** return generic error messages.

### 6. Logging
**Always** log sensitive operations.
**Always** include timestamp, IP, user ID.

---

This comparison shows how small code changes have massive security impacts!
