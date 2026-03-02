-- ============================================================
-- DRTS Online Vehicle Registration System - Database Schema
-- University of Botswana | Theo T Kentsheng | 202201306
-- ============================================================

CREATE DATABASE IF NOT EXISTS drts_vrs;
USE drts_vrs;

-- -------------------------------------------------------
-- USERS TABLE (vehicle owners, officers, admins)
-- -------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    national_id VARCHAR(30) UNIQUE,
    address TEXT,
    role ENUM('public', 'officer', 'admin') NOT NULL DEFAULT 'public',
    mfa_secret VARCHAR(64),
    mfa_enabled TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- -------------------------------------------------------
-- VEHICLE TYPES TABLE
-- -------------------------------------------------------
CREATE TABLE vehicle_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255),
    registration_fee DECIMAL(10,2) NOT NULL
);

INSERT INTO vehicle_types (name, description, registration_fee) VALUES
('Private Car', 'Personal motor vehicle', 550.00),
('Light Commercial', 'Bakkies and light delivery vehicles', 750.00),
('Heavy Commercial', 'Trucks and lorries', 1200.00),
('Motorcycle', 'Motorcycles and scooters', 300.00),
('Minibus / Combi', 'Combi taxis and minibuses', 850.00),
('Trailer', 'Non-motorised trailers', 200.00);

-- -------------------------------------------------------
-- VEHICLES TABLE
-- -------------------------------------------------------
CREATE TABLE vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    vehicle_type_id INT NOT NULL,
    make VARCHAR(80) NOT NULL,
    model VARCHAR(80) NOT NULL,
    year YEAR NOT NULL,
    color VARCHAR(50) NOT NULL,
    chassis_number VARCHAR(50) UNIQUE NOT NULL,
    engine_number VARCHAR(50),
    plate_number VARCHAR(20) UNIQUE,
    fuel_type ENUM('Petrol','Diesel','Electric','Hybrid') DEFAULT 'Petrol',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_type_id) REFERENCES vehicle_types(id)
);

-- -------------------------------------------------------
-- REGISTRATION APPLICATIONS TABLE
-- -------------------------------------------------------
CREATE TABLE applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference_number VARCHAR(20) UNIQUE NOT NULL,
    vehicle_id INT NOT NULL,
    applicant_id INT NOT NULL,
    application_type ENUM('new','renewal','transfer') NOT NULL DEFAULT 'new',
    status ENUM('pending','under_review','approved','rejected','paid') NOT NULL DEFAULT 'pending',
    officer_id INT,
    officer_notes TEXT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    approved_at TIMESTAMP NULL,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
    FOREIGN KEY (applicant_id) REFERENCES users(id),
    FOREIGN KEY (officer_id) REFERENCES users(id)
);

-- Generate reference number trigger
DELIMITER //
CREATE TRIGGER before_application_insert
BEFORE INSERT ON applications
FOR EACH ROW
BEGIN
    SET NEW.reference_number = CONCAT('DRTS-', YEAR(NOW()), '-', LPAD(FLOOR(RAND()*999999), 6, '0'));
END;
//
DELIMITER ;

-- -------------------------------------------------------
-- DOCUMENTS TABLE (uploaded files)
-- -------------------------------------------------------
CREATE TABLE documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    document_type ENUM('id_copy','proof_of_address','vehicle_photo','insurance','purchase_receipt','other') NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    mime_type VARCHAR(100),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
);

-- -------------------------------------------------------
-- PAYMENTS TABLE (Stripe integration)
-- -------------------------------------------------------
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL UNIQUE,
    stripe_payment_intent_id VARCHAR(200),
    amount DECIMAL(10,2) NOT NULL,
    currency CHAR(3) DEFAULT 'BWP',
    status ENUM('pending','processing','completed','failed','refunded') DEFAULT 'pending',
    payment_method VARCHAR(50),
    receipt_url VARCHAR(500),
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES applications(id)
);

-- -------------------------------------------------------
-- SESSIONS TABLE
-- -------------------------------------------------------
CREATE TABLE sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- -------------------------------------------------------
-- AUDIT LOG TABLE
-- -------------------------------------------------------
CREATE TABLE audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- -------------------------------------------------------
-- SEED: default admin user (password: Admin@DRTS2026)
-- -------------------------------------------------------
INSERT INTO users (full_name, email, password_hash, phone, role) VALUES
('DRTS Administrator', 'admin@drts.gov.bw', '$2y$12$examplehashhere', '+267 3914100', 'admin'),
('Officer Kgosi Moyo', 'officer@drts.gov.bw', '$2y$12$examplehashhere', '+267 3914101', 'officer');
