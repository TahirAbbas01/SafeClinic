-- Create the database
CREATE DATABASE safeclinic_db;
USE safeclinic_db;

-- Table: users (Vulnerable version: Plain-text passwords)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL, -- Stored without hashing [cite: 50]
    role ENUM('admin', 'doctor', 'nurse', 'patient') NOT NULL,
    full_name VARCHAR(100)
);

-- Table: patients (Business entity linked to users) [cite: 14, 36]
CREATE TABLE patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    date_of_birth DATE,
    address TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Table: medical_records (Second business table) [cite: 36]
CREATE TABLE medical_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT,
    diagnosis TEXT,
    treatment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id)
);

-- Insert Test Data [cite: 42]
INSERT INTO users (username, password, role, full_name) VALUES 
('admin_user', 'admin123', 'admin', 'System Administrator'),
('dr_smith', 'doctor456', 'doctor', 'Dr. John Smith'),
('nurse_joy', 'nurse789', 'nurse', 'Joy Nurse'),
('patient_zero', 'password', 'patient', 'Alice Patient');

INSERT INTO patients (user_id, date_of_birth, address) VALUES (4, '1995-05-15', '123 Health St.');
INSERT INTO medical_records (patient_id, diagnosis, treatment) VALUES (1, 'Seasonal Flu', 'Rest and hydration');