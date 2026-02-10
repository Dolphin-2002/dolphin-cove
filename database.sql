-- ============================================================================
-- DOLPHIN COVE - MySQL Database Schema
-- Import this via phpMyAdmin or mysql CLI
-- ============================================================================

CREATE DATABASE IF NOT EXISTS dolphin_cove CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dolphin_cove;

-- ============================================================================
-- USERS TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) UNIQUE NOT NULL,
  username VARCHAR(50) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(100) NOT NULL,
  avatar_url VARCHAR(500) DEFAULT NULL,
  cover_photo VARCHAR(500) DEFAULT NULL,
  bio TEXT DEFAULT NULL,
  location VARCHAR(200) DEFAULT NULL,
  website VARCHAR(500) DEFAULT NULL,
  skills TEXT DEFAULT NULL,
  hourly_rate DECIMAL(10,2) DEFAULT NULL,
  is_freelancer TINYINT(1) DEFAULT 0,
  freelancer_title VARCHAR(200) DEFAULT NULL,
  freelancer_availability ENUM('available','busy','unavailable') DEFAULT 'available',
  completed_jobs INT DEFAULT 0,
  rating DECIMAL(3,2) DEFAULT 0.00,
  total_reviews INT DEFAULT 0,
  bank_name VARCHAR(200) DEFAULT NULL,
  bank_account_number VARCHAR(200) DEFAULT NULL,
  bank_account_holder VARCHAR(200) DEFAULT NULL,
  is_verified TINYINT(1) DEFAULT 0,
  is_online TINYINT(1) DEFAULT 0,
  last_seen DATETIME DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_email (email),
  INDEX idx_username (username),
  INDEX idx_is_freelancer (is_freelancer)
) ENGINE=InnoDB;

-- ============================================================================
-- CONNECTIONS TABLE (for followers/following/connections)
-- ============================================================================
CREATE TABLE IF NOT EXISTS connections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  requester_id INT NOT NULL,
  receiver_id INT NOT NULL,
  status ENUM('pending','accepted','rejected') DEFAULT 'pending',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_connection (requester_id, receiver_id)
) ENGINE=InnoDB;

-- ============================================================================
-- POSTS TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  author_id INT NOT NULL,
  content TEXT NOT NULL,
  image VARCHAR(500) DEFAULT NULL,
  visibility ENUM('public','connections','private') DEFAULT 'public',
  is_freelance_post TINYINT(1) DEFAULT 0,
  freelance_title VARCHAR(200) DEFAULT NULL,
  freelance_budget VARCHAR(100) DEFAULT NULL,
  freelance_deadline VARCHAR(100) DEFAULT NULL,
  freelance_skills TEXT DEFAULT NULL,
  freelance_project_type ENUM('fixed','hourly') DEFAULT 'fixed',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_author (author_id),
  INDEX idx_created (created_at DESC)
) ENGINE=InnoDB;

-- ============================================================================
-- POST LIKES TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS post_likes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  post_id INT NOT NULL,
  user_id INT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_like (post_id, user_id)
) ENGINE=InnoDB;

-- ============================================================================
-- COMMENTS TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  post_id INT NOT NULL,
  author_id INT NOT NULL,
  content TEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_post (post_id)
) ENGINE=InnoDB;

-- ============================================================================
-- COMMENT LIKES TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS comment_likes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  comment_id INT NOT NULL,
  user_id INT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_comment_like (comment_id, user_id)
) ENGINE=InnoDB;

-- ============================================================================
-- SAVED POSTS TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS saved_posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  post_id INT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  UNIQUE KEY unique_save (user_id, post_id)
) ENGINE=InnoDB;

-- ============================================================================
-- FREELANCE JOBS TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS freelance_jobs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  poster_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT NOT NULL,
  category VARCHAR(100) DEFAULT NULL,
  skills_required TEXT DEFAULT NULL,
  budget_min DECIMAL(10,2) NOT NULL DEFAULT 0,
  budget_max DECIMAL(10,2) NOT NULL DEFAULT 0,
  duration VARCHAR(100) DEFAULT NULL,
  project_type ENUM('fixed','hourly') DEFAULT 'fixed',
  attachment_url VARCHAR(500) DEFAULT NULL,
  status ENUM('open','in_progress','completed','cancelled') DEFAULT 'open',
  hired_freelancer_id INT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (poster_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (hired_freelancer_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_poster (poster_id),
  INDEX idx_status (status),
  INDEX idx_created (created_at DESC)
) ENGINE=InnoDB;

-- ============================================================================
-- JOB APPLICATIONS TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS job_applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  job_id INT NOT NULL,
  applicant_id INT NOT NULL,
  cover_letter TEXT NOT NULL,
  proposed_budget DECIMAL(10,2) DEFAULT NULL,
  proposed_duration VARCHAR(100) DEFAULT NULL,
  status ENUM('pending','accepted','rejected','withdrawn') DEFAULT 'pending',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (job_id) REFERENCES freelance_jobs(id) ON DELETE CASCADE,
  FOREIGN KEY (applicant_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_application (job_id, applicant_id)
) ENGINE=InnoDB;

-- ============================================================================
-- TASKS TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS tasks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  job_id INT NOT NULL,
  client_id INT NOT NULL,
  freelancer_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT DEFAULT NULL,
  budget DECIMAL(10,2) DEFAULT 0,
  status ENUM('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  due_date DATE DEFAULT NULL,
  completed_at DATETIME DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (job_id) REFERENCES freelance_jobs(id) ON DELETE CASCADE,
  FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (freelancer_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_client (client_id),
  INDEX idx_freelancer (freelancer_id),
  INDEX idx_status (status)
) ENGINE=InnoDB;

-- ============================================================================
-- TASK MILESTONES TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS task_milestones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  task_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT DEFAULT NULL,
  due_date DATE DEFAULT NULL,
  amount DECIMAL(10,2) DEFAULT 0,
  status ENUM('pending','in_progress','completed') DEFAULT 'pending',
  completed_at DATETIME DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
-- MESSAGES TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sender_id INT NOT NULL,
  receiver_id INT NOT NULL,
  content TEXT NOT NULL,
  is_read TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_sender (sender_id),
  INDEX idx_receiver (receiver_id),
  INDEX idx_created (created_at DESC)
) ENGINE=InnoDB;

-- ============================================================================
-- NOTIFICATIONS TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  from_user_id INT DEFAULT NULL,
  type VARCHAR(50) DEFAULT 'like',
  message TEXT NOT NULL,
  link VARCHAR(500) DEFAULT NULL,
  is_read TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_user (user_id),
  INDEX idx_read (is_read)
) ENGINE=InnoDB;
