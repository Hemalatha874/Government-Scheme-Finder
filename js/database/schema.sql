-- Government Scheme Eligibility Finder Database Schema
-- Source: myScheme.gov.in, india.gov.in, data.gov.in (official government portals)

CREATE DATABASE IF NOT EXISTS govt_scheme_finder CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE govt_scheme_finder;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    mobile VARCHAR(15) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- User profiles
CREATE TABLE IF NOT EXISTS user_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    full_name VARCHAR(150),
    age INT,
    gender ENUM('Male', 'Female', 'Other', 'Any') DEFAULT 'Any',
    date_of_birth DATE,
    state VARCHAR(100),
    district VARCHAR(100),
    occupation VARCHAR(100),
    education VARCHAR(100),
    annual_income DECIMAL(12,2) DEFAULT 0,
    category ENUM('General', 'OBC', 'SC', 'ST', 'EWS', 'Any') DEFAULT 'Any',
    disability_status ENUM('Yes', 'No') DEFAULT 'No',
    is_farmer ENUM('Yes', 'No') DEFAULT 'No',
    is_student ENUM('Yes', 'No') DEFAULT 'No',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Admins table
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Government schemes (real data from official sources)
CREATE TABLE IF NOT EXISTS schemes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scheme_code VARCHAR(50) NOT NULL UNIQUE,
    scheme_name VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    benefits TEXT NOT NULL,
    min_age INT DEFAULT 0,
    max_age INT DEFAULT 120,
    income_limit DECIMAL(12,2) DEFAULT NULL,
    gender_eligibility ENUM('Male', 'Female', 'Other', 'Any') DEFAULT 'Any',
    education_requirement VARCHAR(255) DEFAULT 'Any',
    occupation VARCHAR(255) DEFAULT 'Any',
    state_ut VARCHAR(255) DEFAULT 'All India',
    caste_category VARCHAR(100) DEFAULT 'Any',
    disability_eligibility ENUM('Yes', 'No', 'Any') DEFAULT 'Any',
    farmer_eligibility ENUM('Yes', 'No', 'Any') DEFAULT 'Any',
    student_eligibility ENUM('Yes', 'No', 'Any') DEFAULT 'Any',
    required_documents TEXT,
    application_steps TEXT,
    official_website VARCHAR(500),
    official_application_link VARCHAR(500),
    helpline_number VARCHAR(50),
    pdf_path VARCHAR(255) DEFAULT NULL,
    last_updated DATE,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Eligibility rules (extended criteria)
CREATE TABLE IF NOT EXISTS eligibility_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scheme_id INT NOT NULL,
    rule_type VARCHAR(50) NOT NULL,
    rule_value VARCHAR(255) NOT NULL,
    rule_operator ENUM('eq', 'gte', 'lte', 'in', 'contains') DEFAULT 'eq',
    FOREIGN KEY (scheme_id) REFERENCES schemes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    notification_type ENUM('scheme', 'announcement', 'deadline') DEFAULT 'announcement',
    scheme_id INT DEFAULT NULL,
    deadline_date DATE DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (scheme_id) REFERENCES schemes(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- User uploaded documents
CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    document_type VARCHAR(100) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT DEFAULT 0,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Applications
CREATE TABLE IF NOT EXISTS applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    scheme_id INT NOT NULL,
    status ENUM('pending', 'submitted', 'approved', 'rejected') DEFAULT 'pending',
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (scheme_id) REFERENCES schemes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Contact messages
CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(15),
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read TINYINT(1) DEFAULT 0
) ENGINE=InnoDB;

-- Default admin (password: Admin@123)
INSERT INTO admins (username, email, password_hash, full_name) VALUES
('admin', 'admin@govtscheme.gov.in', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator')
ON DUPLICATE KEY UPDATE username=username;

-- Indexes for performance
CREATE INDEX idx_schemes_category ON schemes(category);
CREATE INDEX idx_schemes_active ON schemes(is_active);
CREATE INDEX idx_notifications_type ON notifications(notification_type);
CREATE INDEX idx_applications_user ON applications(user_id);
CREATE INDEX idx_documents_user ON documents(user_id);
