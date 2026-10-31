-- ============================================================================
-- Smart School Attendance Management System
-- Database Schema & Comprehensive Sample Data
-- Database: school_attendance_db
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `school_attendance_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `school_attendance_db`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `announcements`;
DROP TABLE IF EXISTS `timetable`;
DROP TABLE IF EXISTS `holidays`;
DROP TABLE IF EXISTS `correction_requests`;
DROP TABLE IF EXISTS `teacher_attendance`;
DROP TABLE IF EXISTS `subject_attendance`;
DROP TABLE IF EXISTS `daily_attendance`;
DROP TABLE IF EXISTS `teacher_assignments`;
DROP TABLE IF EXISTS `class_subjects`;
DROP TABLE IF EXISTS `subjects`;
DROP TABLE IF EXISTS `sections`;
DROP TABLE IF EXISTS `classes`;
DROP TABLE IF EXISTS `academic_years`;
DROP TABLE IF EXISTS `students`;
DROP TABLE IF EXISTS `teachers`;
DROP TABLE IF EXISTS `principals`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `school_settings`;

SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------------------------
-- 1. Table: users (Central Authentication & RBAC)
-- ----------------------------------------------------------------------------
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'principal', 'teacher', 'student') NOT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `remember_token` VARCHAR(100) NULL,
  `last_login` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_role_status` (`role`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. Table: academic_years
-- ----------------------------------------------------------------------------
CREATE TABLE `academic_years` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `is_current` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('active', 'archived') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. Table: classes (Supports Standard 1-12 and Semester 1-8)
-- ----------------------------------------------------------------------------
CREATE TABLE `classes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `type` ENUM('school', 'college') NOT NULL DEFAULT 'school',
  `numeric_level` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_class_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. Table: sections
-- ----------------------------------------------------------------------------
CREATE TABLE `sections` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `class_id` INT NOT NULL,
  `name` VARCHAR(20) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_class_section` (`class_id`, `name`),
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5. Table: subjects
-- ----------------------------------------------------------------------------
CREATE TABLE `subjects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(150) NOT NULL,
  `type` ENUM('core', 'elective', 'practical') NOT NULL DEFAULT 'core',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 6. Table: class_subjects
-- ----------------------------------------------------------------------------
CREATE TABLE `class_subjects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `class_id` INT NOT NULL,
  `subject_id` INT NOT NULL,
  UNIQUE KEY `uk_class_subject` (`class_id`, `subject_id`),
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 7. Table: principals
-- ----------------------------------------------------------------------------
CREATE TABLE `principals` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `principal_id` VARCHAR(50) NOT NULL UNIQUE,
  `full_name` VARCHAR(150) NOT NULL,
  `photo` VARCHAR(255) NULL,
  `dob` DATE NULL,
  `gender` ENUM('male', 'female', 'other') DEFAULT 'male',
  `email` VARCHAR(120) NULL,
  `mobile` VARCHAR(25) NULL,
  `address` TEXT NULL,
  `joining_date` DATE NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 8. Table: teachers
-- ----------------------------------------------------------------------------
CREATE TABLE `teachers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `teacher_id` VARCHAR(50) NOT NULL UNIQUE,
  `full_name` VARCHAR(150) NOT NULL,
  `photo` VARCHAR(255) NULL,
  `dob` DATE NULL,
  `gender` ENUM('male', 'female', 'other') DEFAULT 'female',
  `email` VARCHAR(120) NULL,
  `mobile` VARCHAR(25) NULL,
  `qualification` VARCHAR(150) NULL,
  `address` TEXT NULL,
  `joining_date` DATE NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 9. Table: students (Note: Student ID + Password only, no email/phone required)
-- ----------------------------------------------------------------------------
CREATE TABLE `students` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `student_id` VARCHAR(50) NOT NULL UNIQUE,
  `admission_no` VARCHAR(50) NOT NULL UNIQUE,
  `roll_no` INT NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `photo` VARCHAR(255) NULL,
  `dob` DATE NULL,
  `gender` ENUM('male', 'female', 'other') DEFAULT 'male',
  `address` TEXT NULL,
  `class_id` INT NOT NULL,
  `section_id` INT NOT NULL,
  `academic_year_id` INT NOT NULL,
  `admission_date` DATE NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_class_section_roll` (`class_id`, `section_id`, `roll_no`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`section_id`) REFERENCES `sections`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 10. Table: teacher_assignments
-- ----------------------------------------------------------------------------
CREATE TABLE `teacher_assignments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `teacher_id` INT NOT NULL,
  `class_id` INT NOT NULL,
  `section_id` INT NOT NULL,
  `subject_id` INT NULL,
  `academic_year_id` INT NOT NULL,
  `is_class_teacher` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_assignment` (`teacher_id`, `class_id`, `section_id`, `subject_id`, `academic_year_id`),
  FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`section_id`) REFERENCES `sections`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 11. Table: daily_attendance
-- ----------------------------------------------------------------------------
CREATE TABLE `daily_attendance` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `class_id` INT NOT NULL,
  `section_id` INT NOT NULL,
  `academic_year_id` INT NOT NULL,
  `attendance_date` DATE NOT NULL,
  `status` ENUM('present', 'absent', 'late', 'half_day') NOT NULL,
  `marked_by` INT NOT NULL,
  `remarks` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_student_daily` (`student_id`, `attendance_date`),
  INDEX `idx_daily_filter` (`class_id`, `section_id`, `attendance_date`),
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`section_id`) REFERENCES `sections`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`marked_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 12. Table: subject_attendance
-- ----------------------------------------------------------------------------
CREATE TABLE `subject_attendance` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `class_id` INT NOT NULL,
  `section_id` INT NOT NULL,
  `subject_id` INT NOT NULL,
  `academic_year_id` INT NOT NULL,
  `lecture_period` INT NOT NULL,
  `attendance_date` DATE NOT NULL,
  `status` ENUM('present', 'absent', 'late', 'half_day') NOT NULL,
  `marked_by` INT NOT NULL,
  `remarks` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_student_subject` (`student_id`, `subject_id`, `attendance_date`, `lecture_period`),
  INDEX `idx_subject_filter` (`class_id`, `section_id`, `subject_id`, `attendance_date`),
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`section_id`) REFERENCES `sections`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`marked_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 13. Table: teacher_attendance
-- ----------------------------------------------------------------------------
CREATE TABLE `teacher_attendance` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `teacher_id` INT NOT NULL,
  `attendance_date` DATE NOT NULL,
  `status` ENUM('present', 'absent', 'late', 'half_day', 'on_leave') NOT NULL,
  `remarks` VARCHAR(255) NULL,
  `marked_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_teacher_date` (`teacher_id`, `attendance_date`),
  FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`marked_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 14. Table: correction_requests
-- ----------------------------------------------------------------------------
CREATE TABLE `correction_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `attendance_type` ENUM('daily', 'subject') NOT NULL,
  `attendance_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  `requested_by` INT NOT NULL,
  `requested_status` ENUM('present', 'absent', 'late', 'half_day') NOT NULL,
  `original_status` ENUM('present', 'absent', 'late', 'half_day') NOT NULL,
  `reason` TEXT NOT NULL,
  `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` INT NULL,
  `review_remarks` TEXT NULL,
  `reviewed_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`requested_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 15. Table: holidays
-- ----------------------------------------------------------------------------
CREATE TABLE `holidays` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(150) NOT NULL,
  `from_date` DATE NOT NULL,
  `to_date` DATE NOT NULL,
  `description` TEXT NULL,
  `academic_year_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 16. Table: timetable
-- ----------------------------------------------------------------------------
CREATE TABLE `timetable` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `academic_year_id` INT NOT NULL,
  `class_id` INT NOT NULL,
  `section_id` INT NOT NULL,
  `subject_id` INT NOT NULL,
  `teacher_id` INT NOT NULL,
  `day_of_week` ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday') NOT NULL,
  `period_number` INT NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `room_no` VARCHAR(50) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_timetable_slot` (`academic_year_id`, `class_id`, `section_id`, `day_of_week`, `period_number`),
  FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`section_id`) REFERENCES `sections`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 17. Table: announcements
-- ----------------------------------------------------------------------------
CREATE TABLE `announcements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(200) NOT NULL,
  `content` TEXT NOT NULL,
  `target_role` ENUM('all', 'teacher', 'student') NOT NULL DEFAULT 'all',
  `created_by` INT NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 18. Table: audit_logs
-- ----------------------------------------------------------------------------
CREATE TABLE `audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `action` VARCHAR(100) NOT NULL,
  `details` TEXT NOT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 19. Table: school_settings
-- ----------------------------------------------------------------------------
CREATE TABLE `school_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT NOT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SEED DATA
-- Default password for all seed accounts is: password123
-- Hash: $2y$10$w7Gm2zlCPJKJT.vaHk2EYetrYHmTpvcg9jj0SCkue5yfk5z6wnUwS
-- ============================================================================

-- School Settings
INSERT INTO `school_settings` (`setting_key`, `setting_value`) VALUES
('school_name', 'St. Xavier International Academy & College'),
('school_tagline', 'Excellence in Digital Education & Character'),
('school_email', 'admin@stxavier.edu'),
('school_phone', '+1 (555) 019-2834'),
('school_address', '100 University Avenue, Tech Park Campus, CA 94105'),
('attendance_threshold', '75'),
('default_academic_year', '1'),
('school_logo', 'assets/img/logo.svg');

-- Academic Years
INSERT INTO `academic_years` (`id`, `name`, `start_date`, `end_date`, `is_current`, `status`) VALUES
(1, '2026-2027', '2026-06-01', '2027-05-31', 1, 'active'),
(2, '2025-2026', '2025-06-01', '2026-05-31', 0, 'archived');

-- Classes (School: Standards 1 to 12; College: Semesters 1 to 8)
INSERT INTO `classes` (`id`, `name`, `type`, `numeric_level`) VALUES
(1, 'Standard 1', 'school', 1),
(2, 'Standard 2', 'school', 2),
(3, 'Standard 3', 'school', 3),
(4, 'Standard 4', 'school', 4),
(5, 'Standard 5', 'school', 5),
(6, 'Standard 6', 'school', 6),
(7, 'Standard 7', 'school', 7),
(8, 'Standard 8', 'school', 8),
(9, 'Standard 9', 'school', 9),
(10, 'Standard 10', 'school', 10),
(11, 'Standard 11', 'school', 11),
(12, 'Standard 12', 'school', 12),
(13, 'Semester 1 (B.Tech CS)', 'college', 1),
(14, 'Semester 2 (B.Tech CS)', 'college', 2),
(15, 'Semester 3 (B.Tech CS)', 'college', 3),
(16, 'Semester 4 (B.Tech CS)', 'college', 4),
(17, 'Semester 5 (B.Tech CS)', 'college', 5),
(18, 'Semester 6 (B.Tech CS)', 'college', 6),
(19, 'Semester 7 (B.Tech CS)', 'college', 7),
(20, 'Semester 8 (B.Tech CS)', 'college', 8);

-- Sections
INSERT INTO `sections` (`id`, `class_id`, `name`) VALUES
-- Standard 10
(1, 10, 'A'),
(2, 10, 'B'),
(3, 10, 'C'),
-- Standard 9
(4, 9, 'A'),
(5, 9, 'B'),
-- Standard 12
(6, 12, 'A'),
(7, 12, 'B'),
-- Semester 4
(8, 16, 'A'),
(9, 16, 'B');

-- Subjects
INSERT INTO `subjects` (`id`, `code`, `name`, `type`) VALUES
(1, 'MATH-10', 'Advanced Mathematics', 'core'),
(2, 'SCI-10', 'General Science & Physics', 'core'),
(3, 'ENG-10', 'English Literature & Grammar', 'core'),
(4, 'SOC-10', 'Social Sciences & Civics', 'core'),
(5, 'CS-10', 'Computer Applications & Coding', 'practical'),
(6, 'CS-401', 'Data Structures & Algorithms', 'core'),
(7, 'CS-402', 'Database Management Systems', 'core'),
(8, 'CS-403', 'Full Stack Web Engineering', 'practical'),
(9, 'CS-404', 'Discrete Mathematical Structures', 'elective');

-- Class-Subject Mappings
INSERT INTO `class_subjects` (`class_id`, `subject_id`) VALUES
(10, 1),
(10, 2),
(10, 3),
(10, 4),
(10, 5),
(16, 6),
(16, 7),
(16, 8),
(16, 9);

-- Central Users
-- Password for all is: password123
INSERT INTO `users` (`id`, `user_id`, `password_hash`, `role`, `status`) VALUES
(1, 'ADM001', '$2y$10$w7Gm2zlCPJKJT.vaHk2EYetrYHmTpvcg9jj0SCkue5yfk5z6wnUwS', 'admin', 'active'),
(2, 'admin', '$2y$10$w7Gm2zlCPJKJT.vaHk2EYetrYHmTpvcg9jj0SCkue5yfk5z6wnUwS', 'admin', 'active'),
(3, 'PRN101', '$2y$10$w7Gm2zlCPJKJT.vaHk2EYetrYHmTpvcg9jj0SCkue5yfk5z6wnUwS', 'principal', 'active'),
(4, 'TCH1001', '$2y$10$w7Gm2zlCPJKJT.vaHk2EYetrYHmTpvcg9jj0SCkue5yfk5z6wnUwS', 'teacher', 'active'),
(5, 'TCH1002', '$2y$10$w7Gm2zlCPJKJT.vaHk2EYetrYHmTpvcg9jj0SCkue5yfk5z6wnUwS', 'teacher', 'active'),
(6, 'TCH1003', '$2y$10$w7Gm2zlCPJKJT.vaHk2EYetrYHmTpvcg9jj0SCkue5yfk5z6wnUwS', 'teacher', 'active'),
(7, 'STU2026001', '$2y$10$w7Gm2zlCPJKJT.vaHk2EYetrYHmTpvcg9jj0SCkue5yfk5z6wnUwS', 'student', 'active'),
(8, 'STU2026002', '$2y$10$w7Gm2zlCPJKJT.vaHk2EYetrYHmTpvcg9jj0SCkue5yfk5z6wnUwS', 'student', 'active'),
(9, 'STU2026003', '$2y$10$w7Gm2zlCPJKJT.vaHk2EYetrYHmTpvcg9jj0SCkue5yfk5z6wnUwS', 'student', 'active'),
(10, 'STU2026004', '$2y$10$w7Gm2zlCPJKJT.vaHk2EYetrYHmTpvcg9jj0SCkue5yfk5z6wnUwS', 'student', 'active'),
(11, 'STU2026005', '$2y$10$w7Gm2zlCPJKJT.vaHk2EYetrYHmTpvcg9jj0SCkue5yfk5z6wnUwS', 'student', 'active'),
(12, 'STU2026006', '$2y$10$w7Gm2zlCPJKJT.vaHk2EYetrYHmTpvcg9jj0SCkue5yfk5z6wnUwS', 'student', 'active');

-- Principal Profile
INSERT INTO `principals` (`id`, `user_id`, `principal_id`, `full_name`, `dob`, `gender`, `email`, `mobile`, `address`, `joining_date`) VALUES
(1, 3, 'PRN101', 'Dr. Arthur Vance, Ph.D.', '1975-04-12', 'male', 'principal@stxavier.edu', '+1 (555) 111-2233', '45 Academic Row, Suite 10, CA', '2018-06-01');

-- Teacher Profiles
INSERT INTO `teachers` (`id`, `user_id`, `teacher_id`, `full_name`, `dob`, `gender`, `email`, `mobile`, `qualification`, `address`, `joining_date`) VALUES
(1, 4, 'TCH1001', 'Prof. Sarah Jenkins', '1984-08-20', 'female', 'sarah.jenkins@stxavier.edu', '+1 (555) 234-7788', 'M.Sc. Mathematics, B.Ed.', '78 Elm Street, CA', '2020-07-15'),
(2, 5, 'TCH1002', 'Mr. Robert Davis', '1988-11-05', 'male', 'robert.davis@stxavier.edu', '+1 (555) 345-8899', 'M.Sc. Physics, M.Ed.', '12 Oak Ridge Rd, CA', '2021-08-01'),
(3, 6, 'TCH1003', 'Dr. Emily Clarke', '1986-02-14', 'female', 'emily.clarke@stxavier.edu', '+1 (555) 456-9900', 'Ph.D. Computer Science', '90 Bayview Blvd, CA', '2019-09-10');

-- Student Profiles (No email or mobile required for students)
INSERT INTO `students` (`id`, `user_id`, `student_id`, `admission_no`, `roll_no`, `full_name`, `dob`, `gender`, `address`, `class_id`, `section_id`, `academic_year_id`, `admission_date`) VALUES
(1, 7, 'STU2026001', 'ADM-2026-001', 1, 'Alex Morgan', '2010-03-15', 'male', '12 Greenfield Ave, CA', 10, 1, 1, '2024-06-10'),
(2, 8, 'STU2026002', 'ADM-2026-002', 2, 'Sophia Patel', '2010-06-22', 'female', '55 Sunset Terrace, CA', 10, 1, 1, '2024-06-10'),
(3, 9, 'STU2026003', 'ADM-2026-003', 3, 'Lucas Rivera', '2010-01-30', 'male', '88 Maple Street, CA', 10, 1, 1, '2024-06-12'),
(4, 10, 'STU2026004', 'ADM-2026-004', 4, 'Emma Watson', '2010-09-08', 'female', '23 Pine Ridge, CA', 10, 1, 1, '2024-06-15'),
(5, 11, 'STU2026005', 'ADM-2026-005', 1, 'Daniel Kim', '2005-12-04', 'male', '101 Silicon Valley Way, CA', 16, 8, 1, '2023-08-20'),
(6, 12, 'STU2026006', 'ADM-2026-006', 2, 'Chloe Bennet', '2005-07-19', 'female', '304 Redwood Highway, CA', 16, 8, 1, '2023-08-20');

-- Teacher Assignments
INSERT INTO `teacher_assignments` (`teacher_id`, `class_id`, `section_id`, `subject_id`, `academic_year_id`, `is_class_teacher`) VALUES
-- Sarah Jenkins: Class teacher of Standard 10-A, teaches Mathematics
(1, 10, 1, 1, 1, 1),
-- Robert Davis: Teaches Science to Standard 10-A
(2, 10, 1, 2, 1, 0),
-- Emily Clarke: Class teacher of Semester 4-A (College), teaches Web Engineering
(3, 16, 8, 8, 1, 1),
-- Emily Clarke: Also teaches Computer Applications to Standard 10-A
(3, 10, 1, 5, 1, 0);

-- Timetable
INSERT INTO `timetable` (`academic_year_id`, `class_id`, `section_id`, `subject_id`, `teacher_id`, `day_of_week`, `period_number`, `start_time`, `end_time`, `room_no`) VALUES
(1, 10, 1, 1, 1, 'Monday', 1, '09:00:00', '09:45:00', 'Room 101'),
(1, 10, 1, 2, 2, 'Monday', 2, '09:50:00', '10:35:00', 'Lab 2'),
(1, 10, 1, 5, 3, 'Monday', 3, '10:50:00', '11:35:00', 'Comp Lab 1'),
(1, 10, 1, 1, 1, 'Tuesday', 1, '09:00:00', '09:45:00', 'Room 101'),
(1, 10, 1, 2, 2, 'Wednesday', 1, '09:00:00', '09:45:00', 'Lab 2'),
(1, 16, 8, 8, 3, 'Monday', 1, '11:00:00', '12:00:00', 'Auditorium B');

-- Teacher Attendance (Recent days)
INSERT INTO `teacher_attendance` (`teacher_id`, `attendance_date`, `status`, `remarks`, `marked_by`) VALUES
(1, '2026-10-27', 'present', 'On time', 3),
(2, '2026-10-27', 'present', 'On time', 3),
(3, '2026-10-27', 'present', 'On time', 3),
(1, '2026-10-28', 'present', 'On time', 3),
(2, '2026-10-28', 'late', '15 mins traffic delay', 3),
(3, '2026-10-28', 'present', 'On time', 3),
(1, '2026-10-29', 'present', 'On time', 3),
(2, '2026-10-29', 'present', 'On time', 3),
(3, '2026-10-29', 'on_leave', 'Conference attendance', 3),
(1, '2026-10-30', 'present', 'Present in morning assembly', 3),
(2, '2026-10-30', 'present', 'On time', 3),
(3, '2026-10-30', 'present', 'Returned from conference', 3);

-- Daily Attendance (Students for past week)
-- Alex Morgan (STU2026001): 100% Present
-- Sophia Patel (STU2026002): Present with 1 Late
-- Lucas Rivera (STU2026003): Below 75% (Several Absences to trigger Low Attendance alert)
-- Emma Watson (STU2026004): 100% Present
-- Daniel Kim (STU2026005): Present
-- Chloe Bennet (STU2026006): Present with 1 Half Day
INSERT INTO `daily_attendance` (`student_id`, `class_id`, `section_id`, `academic_year_id`, `attendance_date`, `status`, `marked_by`) VALUES
-- 2026-10-20
(1, 10, 1, 1, '2026-10-20', 'present', 4),
(2, 10, 1, 1, '2026-10-20', 'present', 4),
(3, 10, 1, 1, '2026-10-20', 'absent', 4),
(4, 10, 1, 1, '2026-10-20', 'present', 4),
-- 2026-10-21
(1, 10, 1, 1, '2026-10-21', 'present', 4),
(2, 10, 1, 1, '2026-10-21', 'present', 4),
(3, 10, 1, 1, '2026-10-21', 'absent', 4),
(4, 10, 1, 1, '2026-10-21', 'present', 4),
-- 2026-10-22
(1, 10, 1, 1, '2026-10-22', 'present', 4),
(2, 10, 1, 1, '2026-10-22', 'late', 4),
(3, 10, 1, 1, '2026-10-22', 'absent', 4),
(4, 10, 1, 1, '2026-10-22', 'present', 4),
-- 2026-10-23
(1, 10, 1, 1, '2026-10-23', 'present', 4),
(2, 10, 1, 1, '2026-10-23', 'present', 4),
(3, 10, 1, 1, '2026-10-23', 'present', 4),
(4, 10, 1, 1, '2026-10-23', 'present', 4),
-- 2026-10-26
(1, 10, 1, 1, '2026-10-26', 'present', 4),
(2, 10, 1, 1, '2026-10-26', 'present', 4),
(3, 10, 1, 1, '2026-10-26', 'absent', 4),
(4, 10, 1, 1, '2026-10-26', 'present', 4),
-- 2026-10-27
(1, 10, 1, 1, '2026-10-27', 'present', 4),
(2, 10, 1, 1, '2026-10-27', 'present', 4),
(3, 10, 1, 1, '2026-10-27', 'present', 4),
(4, 10, 1, 1, '2026-10-27', 'present', 4),
-- 2026-10-28
(1, 10, 1, 1, '2026-10-28', 'present', 4),
(2, 10, 1, 1, '2026-10-28', 'present', 4),
(3, 10, 1, 1, '2026-10-28', 'absent', 4),
(4, 10, 1, 1, '2026-10-28', 'present', 4),
-- 2026-10-29
(1, 10, 1, 1, '2026-10-29', 'present', 4),
(2, 10, 1, 1, '2026-10-29', 'present', 4),
(3, 10, 1, 1, '2026-10-29', 'half_day', 4),
(4, 10, 1, 1, '2026-10-29', 'present', 4),
-- 2026-10-30 (Today)
(1, 10, 1, 1, '2026-10-30', 'present', 4),
(2, 10, 1, 1, '2026-10-30', 'present', 4),
(3, 10, 1, 1, '2026-10-30', 'absent', 4),
(4, 10, 1, 1, '2026-10-30', 'present', 4),
-- College Students (Sem 4)
(5, 16, 8, 1, '2026-10-28', 'present', 6),
(6, 16, 8, 1, '2026-10-28', 'half_day', 6),
(5, 16, 8, 1, '2026-10-29', 'present', 6),
(6, 16, 8, 1, '2026-10-29', 'present', 6),
(5, 16, 8, 1, '2026-10-30', 'present', 6),
(6, 16, 8, 1, '2026-10-30', 'present', 6);

-- Subject-Wise Attendance
INSERT INTO `subject_attendance` (`student_id`, `class_id`, `section_id`, `subject_id`, `academic_year_id`, `lecture_period`, `attendance_date`, `status`, `marked_by`) VALUES
(1, 10, 1, 1, 1, 1, '2026-10-28', 'present', 4),
(2, 10, 1, 1, 1, 1, '2026-10-28', 'present', 4),
(3, 10, 1, 1, 1, 1, '2026-10-28', 'absent', 4),
(4, 10, 1, 1, 1, 1, '2026-10-28', 'present', 4),
(1, 10, 1, 2, 1, 2, '2026-10-28', 'present', 5),
(2, 10, 1, 2, 1, 2, '2026-10-28', 'late', 5),
(3, 10, 1, 2, 1, 2, '2026-10-28', 'absent', 5),
(4, 10, 1, 2, 1, 2, '2026-10-28', 'present', 5),
(1, 10, 1, 1, 1, 1, '2026-10-29', 'present', 4),
(2, 10, 1, 1, 1, 1, '2026-10-29', 'present', 4),
(3, 10, 1, 1, 1, 1, '2026-10-29', 'present', 4),
(4, 10, 1, 1, 1, 1, '2026-10-29', 'present', 4),
(5, 16, 8, 8, 1, 1, '2026-10-30', 'present', 6),
(6, 16, 8, 8, 1, 1, '2026-10-30', 'present', 6);

-- Correction Requests (Sample workflow)
INSERT INTO `correction_requests` (`id`, `attendance_type`, `attendance_id`, `student_id`, `requested_by`, `requested_status`, `original_status`, `reason`, `status`, `reviewed_by`, `review_remarks`, `reviewed_at`) VALUES
(1, 'daily', 3, 3, 4, 'present', 'absent', 'Student arrived with medical note from clinic. Please correct.', 'pending', NULL, NULL, NULL),
(2, 'daily', 7, 2, 4, 'present', 'late', 'Bus breakdown documented by school transport manager.', 'approved', 3, 'Approved as per transport report.', '2026-10-23 14:00:00');

-- Holidays
INSERT INTO `holidays` (`title`, `from_date`, `to_date`, `description`, `academic_year_id`) VALUES
('Thanksgiving & Fall Break', '2026-11-26', '2026-11-29', 'Official school closure for Thanksgiving.', 1),
('Winter Holidays', '2026-12-24', '2027-01-03', 'Annual Winter vacation for students and staff.', 1),
('Spring Break', '2027-03-29', '2027-04-04', 'Spring recess and Easter holiday.', 1);

-- Announcements
INSERT INTO `announcements` (`title`, `content`, `target_role`, `created_by`, `is_active`) VALUES
('Mid-Term Examination Schedule Announced', 'All teachers and students are requested to review the midterm schedule published on the notice board. Attendance during examinations is mandatory.', 'all', 3, 1),
('Faculty Meeting on Friday at 3:30 PM', 'Mandatory staff meeting in the main conference room regarding the new digital attendance portal and term progress.', 'teacher', 3, 1),
('Library Book Return Deadline', 'Students of standard 10 and college semesters must return all checked-out books before next Monday.', 'student', 1, 1);

-- Audit Logs
INSERT INTO `audit_logs` (`user_id`, `action`, `details`, `ip_address`) VALUES
(1, 'SYSTEM_INIT', 'Smart Attendance Management System initialized with default configuration.', '127.0.0.1'),
(3, 'ATTENDANCE_CORRECTION_APPROVED', 'Principal Dr. Vance approved correction request #2 for Sophia Patel (late -> present).', '127.0.0.1'),
(4, 'DAILY_ATTENDANCE_MARKED', 'Teacher Sarah Jenkins marked daily attendance for Standard 10 - Section A.', '127.0.0.1');
