USE itr_data_base;

ALTER TABLE itr_registeruser 
ADD COLUMN college_id VARCHAR(50) DEFAULT NULL,
ADD COLUMN branch VARCHAR(100) DEFAULT NULL,
ADD COLUMN section VARCHAR(10) DEFAULT NULL,
ADD COLUMN phone VARCHAR(15) DEFAULT NULL,
ADD COLUMN address TEXT DEFAULT NULL,
ADD COLUMN assigned_faculty_id INT DEFAULT NULL,
ADD COLUMN profile_updated TINYINT DEFAULT 0;

CREATE TABLE IF NOT EXISTS branches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_code VARCHAR(10) NOT NULL,
    branch_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO branches (branch_code, branch_name) VALUES
('CSE', 'Computer Science Engineering'),
('CSE-DS', 'Computer Science Engineering - Data Science'),
('CSE-AI', 'Computer Science Engineering - Artificial Intelligence'),
('CSE-IOT', 'Computer Science Engineering - Internet of Things');

CREATE TABLE IF NOT EXISTS sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_name VARCHAR(10) NOT NULL,
    branch_id INT,
    faculty_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (faculty_id) REFERENCES itr_facultyuser(id)
);

INSERT INTO sections (section_name, branch_id, faculty_id) VALUES
('A', 1, 1), ('B', 1, 2), ('C', 1, 3), ('D', 1, 1), ('E', 1, 2), ('F', 1, 3),
('A', 2, 1), ('B', 2, 2),
('A', 3, 2), ('B', 3, 3),
('A', 4, 1);

CREATE TABLE IF NOT EXISTS project_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    project_name VARCHAR(255) NOT NULL,
    project_description TEXT,
    leader_name VARCHAR(100) NOT NULL,
    leader_email VARCHAR(100) NOT NULL,
    leader_roll VARCHAR(50) NOT NULL,
    leader_branch VARCHAR(100) NOT NULL,
    member2_name VARCHAR(100) DEFAULT NULL,
    member2_email VARCHAR(100) DEFAULT NULL,
    member2_roll VARCHAR(50) DEFAULT NULL,
    member2_branch VARCHAR(100) DEFAULT NULL,
    member3_name VARCHAR(100) DEFAULT NULL,
    member3_email VARCHAR(100) DEFAULT NULL,
    member3_roll VARCHAR(50) DEFAULT NULL,
    member3_branch VARCHAR(100) DEFAULT NULL,
    member4_name VARCHAR(100) DEFAULT NULL,
    member4_email VARCHAR(100) DEFAULT NULL,
    member4_roll VARCHAR(50) DEFAULT NULL,
    member4_branch VARCHAR(100) DEFAULT NULL,
    file_path VARCHAR(500) DEFAULT NULL,
    file_name VARCHAR(255) DEFAULT NULL,
    file_type VARCHAR(50) DEFAULT NULL,
    submission_status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    faculty_id INT DEFAULT NULL,
    faculty_remarks TEXT DEFAULT NULL,
    presentation_date DATE DEFAULT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES itr_registeruser(id),
    FOREIGN KEY (faculty_id) REFERENCES itr_facultyuser(id)
);

UPDATE itr_facultyuser SET department = 'Computer Science' WHERE id = 1;
UPDATE itr_facultyuser SET department = 'Electronics' WHERE id = 2;
UPDATE itr_facultyuser SET department = 'Mechanical' WHERE id = 3;

CREATE TABLE IF NOT EXISTS student_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    faculty_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES itr_registeruser(id),
    FOREIGN KEY (faculty_id) REFERENCES itr_facultyuser(id)
);
