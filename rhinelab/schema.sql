-- Skema database untuk aplikasi klinik sederhana

CREATE DATABASE IF NOT EXISTS `klinik_sederhana` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `klinik_sederhana`;

CREATE TABLE IF NOT EXISTS `doctors` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `specialty` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(25) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `schedules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `doctor_id` INT NOT NULL,
  `date` DATE NOT NULL,
  `time` TIME NOT NULL,
  `slots` INT NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `patients` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(25) NOT NULL,
  `doctor_id` INT NOT NULL,
  `schedule_id` INT NOT NULL,
  `patient_profile_id` INT NULL,
  `queue_number` INT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'Reservasi',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_patients_schedule_queue` (`schedule_id`, `queue_number`),
  FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`schedule_id`) REFERENCES `schedules`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `patient_profiles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nik` VARCHAR(30) NOT NULL UNIQUE,
  `name` VARCHAR(150) NOT NULL,
  `address` TEXT NOT NULL,
  `phone` VARCHAR(25) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `medical_records` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `patient_id` INT NOT NULL,
  `doctor_id` INT NULL,
  `visit_date` DATE NOT NULL,
  `visit_time` TIME NOT NULL,
  `queue_number` INT NULL,
  `cupping_type` VARCHAR(30) NOT NULL,
  `treatment` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_medical_records_patient_date` (`patient_id`, `visit_date`),
  FOREIGN KEY (`patient_id`) REFERENCES `patient_profiles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `doctor_holidays` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `doctor_id` INT NOT NULL,
  `holiday_date` DATE NOT NULL,
  `note` VARCHAR(255) NOT NULL DEFAULT 'Libur',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_doctor_date` (`doctor_id`, `holiday_date`),
  FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sampel data awal
INSERT INTO `doctors` (`name`, `specialty`, `phone`) VALUES
('Dr. Siti Aminah', 'Umum', '081234567890'),
('Dr. Budi Santoso', 'Anak', '081298765432');

INSERT INTO `schedules` (`doctor_id`, `date`, `time`, `slots`) VALUES
(1, CURDATE(), '09:00:00', 4),
(1, CURDATE(), '14:00:00', 4),
(2, CURDATE(), '10:00:00', 3),
(2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '11:00:00', 3);
