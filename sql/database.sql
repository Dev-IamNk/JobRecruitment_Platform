
CREATE DATABASE recruitment_rpa;
USE recruitment_rpa;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    user_type ENUM('candidate', 'recruiter') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Jobs table
CREATE TABLE jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recruiter_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    required_skills TEXT NOT NULL,
    location VARCHAR(255),
    salary_range VARCHAR(100),
    status ENUM('open', 'closed') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recruiter_id) REFERENCES users(id)
);

-- Applications table
CREATE TABLE applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    candidate_id INT NOT NULL,
    resume_path VARCHAR(500) NOT NULL,
    cover_letter TEXT,
    extracted_skills TEXT,
    score DECIMAL(5,2) DEFAULT 0,
    status ENUM('pending', 'scored', 'shortlisted', 'rejected') DEFAULT 'pending',
    email_sent TINYINT(1) DEFAULT 0,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES jobs(id),
    FOREIGN KEY (candidate_id) REFERENCES users(id)
);

-- Insert sample recruiter
INSERT INTO users (email, password, full_name, user_type) 
VALUES ('recruiter@company.com', MD5('password123'), 'John Recruiter', 'recruiter');

-- Insert sample candidate
INSERT INTO users (email, password, full_name, user_type) 
VALUES ('candidate@email.com', MD5('password123'), 'Jane Candidate', 'candidate');
