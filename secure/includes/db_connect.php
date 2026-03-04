<?php
/**
 * Secure Database Connection Handler
 * 
 * STEP 3-4: SQL Injection Prevention & Prepared Statements
 * Uses MySQLi with prepared statements to prevent SQL injection
 * Implements proper error handling without exposing sensitive details
 */

// Database configuration
$host = "localhost";
$user = "root";
$pass = ""; // Change if needed
$db = "safeclinic_db_secure";

// Enable MySQLi exceptions for better error handling
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = mysqli_connect($host, $user, $pass, $db);
    
    // Set charset to UTF-8 for proper encoding
    mysqli_set_charset($conn, "utf8mb4");
    
} catch (mysqli_sql_exception $e) {
    // Log error to file instead of displaying to user
    error_log("Database Connection Error: " . $e->getMessage(), 3, "/var/log/safeclinic_errors.log");
    die("Database connection failed. Please contact administrator.");
}

/**
 * Secure Query Execution with Prepared Statements
 * 
 * STEP 3: SQL Injection Prevention
 * All parametric queries use bound variables instead of string concatenation
 * 
 * @param mysqli $connection Database connection
 * @param string $sql SQL query with placeholders (?, s for string, i for int, d for double)
 * @param array $params Parameter values
 * @param string $types Parameter types (e.g., "ssi" for string, string, int)
 * @return array|null
 */
function executePreparedQuery($conn, $sql, $params = [], $types = "") {
    try {
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        // Bind parameters if provided
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        // Execute query
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        // Get result and fetch as associative array
        $result = $stmt->get_result();
        $data = [];
        
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        $stmt->close();
        return $data;
        
    } catch (Exception $e) {
        error_log("Query Error: " . $e->getMessage(), 3, "/var/log/safeclinic_errors.log");
        return null;
    }
}

/**
 * Secure Insert/Update/Delete with Prepared Statements
 * Returns affected rows or false on failure
 */
function executeModifyQuery($conn, $sql, $params = [], $types = "") {
    try {
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected;
        
    } catch (Exception $e) {
        error_log("Modify Query Error: " . $e->getMessage(), 3, "/var/log/safeclinic_errors.log");
        return false;
    }
}

/**
 * Get last inserted ID safely
 */
function getLastInsertId($conn) {
    return $conn->insert_id;
}
?>
