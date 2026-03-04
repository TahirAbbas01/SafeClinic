-- VULNERABLE VERSION DATABASE SCHEMA
-- This schema demonstrates common security mistakes:
-- 1. Plain-text passwords (NO hashing)
-- 2. No audit logging tables
-- 3. Minimal input validation
-- 4. No foreign key constraints

CREATE DATABASE IF NOT EXISTS safeclinic_db_vulnerable;
USE safeclinic_db_vulnerable;

-- Table: users (Plain-text passwords - VULNERABLE!)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,  -- Plain-text passwords - SECURITY ISSUE!
    role ENUM('admin', 'doctor', 'nurse', 'patient') NOT NULL,
    full_name VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table: patients
CREATE TABLE patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    date_of_birth DATE,
    address TEXT,
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table: medical_records (No audit fields)
CREATE TABLE medical_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT,
    diagnosis TEXT,
    treatment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert Test Data with PLAIN-TEXT passwords
INSERT INTO users (username, password, role, full_name) VALUES 
('admin_user', 'admin123', 'admin', 'System Administrator'),
('dr_smith', 'doctor456', 'doctor', 'Dr. John Smith'),
('nurse_joy', 'nurse789', 'nurse', 'Nurse Joy'),
('patient_zero', 'password', 'patient', 'Alice Patient'),
('patient_one', 'password123', 'patient', 'Bob Johnson');

INSERT INTO patients (user_id, date_of_birth, address, phone) VALUES 
(4, '1995-05-15', '123 Health St.', '+1-555-0100'),
(5, '1988-03-20', '456 Wellness Ave.', '+1-555-0101');

INSERT INTO medical_records (patient_id, diagnosis, treatment) VALUES 
(1, 'Seasonal Flu', 'Rest and hydration'),
(2, 'Hypertension', 'Lisinopril 10mg daily, low-sodium diet');
