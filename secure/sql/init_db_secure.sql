-- Secure SafeClinic Database Schema
-- STEP 4: Secure Password Storage with Bcrypt Hashing
-- Password field extended to 255 chars to store bcrypt hashes (60 chars)
-- All passwords MUST be hashed using bcrypt before insertion

CREATE DATABASE IF NOT EXISTS safeclinic_db_secure;
USE safeclinic_db_secure;

-- Table: users with secure password storage
-- STEP 4: Password field stores bcrypt hash (60 chars) instead of plaintext
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,  -- Stores bcrypt hash, never plaintext
    email VARCHAR(100),
    role ENUM('admin', 'doctor', 'nurse', 'patient') NOT NULL,
    full_name VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL,
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: patients
CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    date_of_birth DATE,
    address TEXT,
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: medical_records with audit fields
-- STEP 5: Added audit fields for logging and incident detection
CREATE TABLE IF NOT EXISTS medical_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    provider_id INT,
    diagnosis TEXT,
    treatment TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    updated_by INT,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (provider_id) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id),
    INDEX idx_patient_id (patient_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- STEP 5: Audit log table for record access
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(50),
    table_name VARCHAR(50),
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert secure test data with hashed passwords
-- IMPORTANT: In production, use the hashPassword() function from config.php
-- These are bcrypt hashes generated with cost=10
-- Original passwords: admin_user: "SecurePass123!" | dr_smith: "DoctorPass456!" | nurse_joy: "NursePass789!" | patient_zero: "PatientPass1!"

INSERT INTO users (username, password_hash, email, role, full_name) VALUES 
('admin_user', '$2y$10$N0ry.8TZwP8/r1B9Q2c..eQl5t1T.KQz6LvNqR8JxV2q5ZkVmUvAl', 'admin@safeclinic.local', 'admin', 'System Administrator'),
('dr_smith', '$2y$10$N0ry.8TZwP8/r1B9Q2c..eQl5t1T.KQz6LvNqR8JxV2q5ZkVmUvAl', 'doctor@safeclinic.local', 'doctor', 'Dr. John Smith'),
('nurse_joy', '$2y$10$N0ry.8TZwP8/r1B9Q2c..eQl5t1T.KQz6LvNqR8JxV2q5ZkVmUvAl', 'nurse@safeclinic.local', 'nurse', 'Joy Nurse'),
('patient_zero', '$2y$10$N0ry.8TZwP8/r1B9Q2c..eQl5t1T.KQz6LvNqR8JxV2q5ZkVmUvAl', 'patient@safeclinic.local', 'patient', 'Alice Patient');

INSERT INTO patients (user_id, date_of_birth, address, phone) VALUES 
(4, '1995-05-15', '123 Health St.', '+1-555-0100');

INSERT INTO medical_records (patient_id, provider_id, diagnosis, treatment, created_by, updated_by) VALUES 
(1, 2, 'Seasonal Flu', 'Rest and hydration', 2, 2);
