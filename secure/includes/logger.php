<?php
/**
 * STEP 5: Security Logging Mechanism
 * 
 * Logs important events for audit trail and incident detection:
 * - Authentication attempts (successful and failed)
 * - Sensitive data access (medical records)
 * - Data modifications (inserts, updates, deletes)
 * - Administrative actions
 * 
 * Logs are stored separately to prevent tampering via application exploits
 */

class SecurityLogger {
    private $logFile;
    
    public function __construct() {
        // Use absolute path outside webroot if possible
        $this->logFile = "/var/log/safeclinic_security.log";
        
        // Create log file if it doesn't exist
        if (!file_exists($this->logFile)) {
            touch($this->logFile);
            chmod($this->logFile, 0600); // Read/write for owner only
        }
    }
    
    /**
     * Log authentication attempts
     * 
     * @param string $username Username attempting to login
     * @param bool $success Whether login was successful
     * @param string $ip Client IP address
     * @param string $reason Reason for failure (if failed)
     */
    public function logAuthenticationAttempt($username, $success, $ip = null, $reason = "") {
        $ip = $ip ?: ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN');
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
        $status = $success ? 'SUCCESS' : 'FAILED';
        $reason_str = !empty($reason) ? " - Reason: $reason" : "";
        
        $message = "[" . date('Y-m-d H:i:s') . "] AUTH_ATTEMPT | Status: $status | User: $username | IP: $ip | UA: " . substr($userAgent, 0, 50) . "$reason_str\n";
        
        $this->writeLog($message);
    }
    
    /**
     * Log sensitive data access
     * 
     * @param string $dataType Type of data accessed (medical_record, user_profile, etc.)
     * @param string $resourceId ID of the resource accessed
     * @param string $userId User ID accessing the data
     * @param string $action Action performed (view, edit, delete)
     */
    public function logDataAccess($dataType, $resourceId, $userId, $action = 'view') {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        
        $message = "[" . date('Y-m-d H:i:s') . "] DATA_ACCESS | Type: $dataType | ResourceID: $resourceId | User: $userId | Action: $action | IP: $ip\n";
        
        $this->writeLog($message);
    }
    
    /**
     * Log data modifications
     * 
     * @param string $table Database table modified
     * @param string $action Action (INSERT, UPDATE, DELETE)
     * @param string $userId User performing action
     * @param string $details Additional details
     */
    public function logDataModification($table, $action, $userId, $details = "") {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $details_str = !empty($details) ? " | Details: $details" : "";
        
        $message = "[" . date('Y-m-d H:i:s') . "] DATA_MODIFY | Table: $table | Action: $action | User: $userId | IP: $ip$details_str\n";
        
        $this->writeLog($message);
    }
    
    /**
     * Log suspicious activities
     * 
     * @param string $activityType Type of suspicious activity
     * @param string $description Description
     * @param string $userId User involved (if known)
     */
    public function logSuspiciousActivity($activityType, $description, $userId = null) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $user_str = $userId ? " | User: $userId" : "";
        
        $message = "[" . date('Y-m-d H:i:s') . "] SUSPICIOUS | Type: $activityType | Desc: $description | IP: $ip$user_str\n";
        
        $this->writeLog($message);
    }
    
    /**
     * Write log entry to file
     */
    private function writeLog($message) {
        if (file_exists($this->logFile) && is_writable($this->logFile)) {
            error_log($message, 3, $this->logFile);
        } else {
            // Fallback: write to PHP error log
            error_log($message);
        }
    }
}

// Global logger instance
$logger = new SecurityLogger();
?>
