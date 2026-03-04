<?php
/**
 * Security Configuration File
 * 
 * STEP 3-5: Input Validation, Session Security, and Logging
 * Centralized configuration for security settings and validation rules
 */

// Session security settings
ini_set('session.use_strict_mode', 1);           // Prevent session fixation
ini_set('session.use_only_cookies', 1);          // Only use cookies for sessions
ini_set('session.cookie_httponly', 1);           // Prevent JavaScript access to cookies
ini_set('session.cookie_secure', 0);             // Set to 1 when using HTTPS
ini_set('session.cookie_samesite', 'Strict');    // CSRF protection
ini_set('session.gc_maxlifetime', 1800);         // 30 minutes timeout

// Input validation rules
define('VALIDATION_RULES', [
    'username' => [
        'min_length' => 3,
        'max_length' => 50,
        'pattern' => '/^[a-zA-Z0-9_-]+$/',        // Alphanumeric, underscore, hyphen only
        'error' => 'Username must be 3-50 characters, containing only letters, numbers, _, -'
    ],
    'password' => [
        'min_length' => 8,
        'max_length' => 128,
        'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[a-zA-Z\d@$!%*?&]{8,}$/',
        'error' => 'Password must be 8+ characters with uppercase, lowercase, number, special char'
    ],
    'email' => [
        'filter' => FILTER_VALIDATE_EMAIL,
        'error' => 'Invalid email format'
    ],
    'full_name' => [
        'max_length' => 100,
        'pattern' => '/^[a-zA-Z\s\-\']+$/',       // Letters, spaces, hyphens, apostrophes
        'error' => 'Full name contains invalid characters'
    ],
    'role' => [
        'allowed_values' => ['admin', 'doctor', 'nurse', 'patient'],
        'error' => 'Invalid role selected'
    ]
]);

/**
 * STEP 3: Input Validation Function
 * Validates input against defined rules
 * 
 * @param string $fieldName
 * @param string $value
 * @return array ['valid' => bool, 'error' => string]
 */
function validateInput($fieldName, $value) {
    if (!isset(VALIDATION_RULES[$fieldName])) {
        return ['valid' => false, 'error' => 'Unknown field'];
    }
    
    $rules = VALIDATION_RULES[$fieldName];
    
    // Check minimum length
    if (isset($rules['min_length']) && strlen($value) < $rules['min_length']) {
        return ['valid' => false, 'error' => $rules['error']];
    }
    
    // Check maximum length
    if (isset($rules['max_length']) && strlen($value) > $rules['max_length']) {
        return ['valid' => false, 'error' => $rules['error']];
    }
    
    // Check pattern (regex)
    if (isset($rules['pattern']) && !preg_match($rules['pattern'], $value)) {
        return ['valid' => false, 'error' => $rules['error']];
    }
    
    // Check email format
    if (isset($rules['filter'])) {
        if (!filter_var($value, $rules['filter'])) {
            return ['valid' => false, 'error' => $rules['error']];
        }
    }
    
    // Check allowed values
    if (isset($rules['allowed_values']) && !in_array($value, $rules['allowed_values'], true)) {
        return ['valid' => false, 'error' => $rules['error']];
    }
    
    return ['valid' => true];
}

/**
 * STEP 4: Secure Password Hashing
 * Uses password_hash() with bcrypt algorithm
 * 
 * Benefits:
 * - Bcrypt includes salt automatically and is slow (computational cost)
 * - Resistant to GPU/ASIC attacks due to high computation time
 * - Can increase cost parameter as hardware improves
 * - Default cost of 10 = ~0.5s per hash (secure against brute force)
 * 
 * Limitations:
 * - Computing time: ~0.5s per authentication
 * - Password stored as 60-character hash (requires VARCHAR(255) in DB)
 * - If DB is compromised, hashes still provide protection
 * - Still vulnerable to weak passwords (user's responsibility)
 */
function hashPassword($password) {
    // password_hash uses bcrypt algorithm with automatic salt
    // cost parameter = 10 (default): ~10^10 iterations makes GPU attacks expensive
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
}

/**
 * Verify password against stored hash
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Secure output escaping for HTML context
 * Prevents Reflected and Stored XSS attacks
 */
function escapeOutput($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>
