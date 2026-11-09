
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;




DROP TABLE IF EXISTS `admin_notes`;
CREATE TABLE IF NOT EXISTS `admin_notes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `title` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'general',
  `is_pinned` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `shared_with_id` int DEFAULT NULL,
  `shared_with_role` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shared_at` timestamp NULL DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `shared_from_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_pinned` (`is_pinned`),
  KEY `idx_shared` (`shared_with_id`,`is_read`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




DROP TABLE IF EXISTS `admin_note_attachments`;
CREATE TABLE IF NOT EXISTS `admin_note_attachments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `note_id` int NOT NULL,
  `file_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` int NOT NULL,
  `file_mime` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_image` tinyint(1) DEFAULT '0',
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_note` (`note_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




DROP TABLE IF EXISTS `antenatal_visits`;
CREATE TABLE IF NOT EXISTS `antenatal_visits` (
  `id` int NOT NULL AUTO_INCREMENT,
  `mother_id` int NOT NULL,
  `visit_number` int NOT NULL,
  `visit_date` date NOT NULL,
  `gestational_age` decimal(4,1) DEFAULT NULL,
  `weight` decimal(5,1) DEFAULT NULL,
  `blood_pressure` varchar(20) DEFAULT NULL,
  `hemoglobin` decimal(4,1) DEFAULT NULL,
  `urine_protein` varchar(20) DEFAULT NULL,
  `urine_sugar` varchar(20) DEFAULT NULL,
  `fetal_heart_rate` int DEFAULT NULL,
  `fundal_height` decimal(5,1) DEFAULT NULL,
  `presentation` varchar(50) DEFAULT NULL,
  `next_visit_date` date DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `mother_id` (`mother_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;



DROP TABLE IF EXISTS `appointments`;
CREATE TABLE IF NOT EXISTS `appointments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `appointment_id` varchar(50) DEFAULT NULL,
  `user_id` int NOT NULL,
  `child_id` int DEFAULT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `facility` varchar(150) DEFAULT NULL,
  `health_worker_id` int DEFAULT NULL,
  `notes` text,
  `status` enum('scheduled','confirmed','in_progress','completed','cancelled','no_show') DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reminder_sent` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `appointment_id` (`appointment_id`),
  KEY `user_id` (`user_id`),
  KEY `child_id` (`child_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;




DROP TABLE IF EXISTS `children`;
CREATE TABLE IF NOT EXISTS `children` (
  `id` int NOT NULL AUTO_INCREMENT,
  `mother_id` int NOT NULL,
  `child_id` varchar(50) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('male','female') NOT NULL,
  `birth_weight` decimal(5,2) DEFAULT NULL,
  `birth_length` decimal(5,2) DEFAULT NULL,
  `place_of_birth` varchar(150) DEFAULT NULL,
  `delivery_type` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `child_id` (`child_id`),
  KEY `mother_id` (`mother_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;




DROP TABLE IF EXISTS `child_documents`;
CREATE TABLE IF NOT EXISTS `child_documents` (
  `id` int NOT NULL AUTO_INCREMENT,
  `child_id` int NOT NULL,
  `uploaded_by` int NOT NULL,
  `doc_type` varchar(50) NOT NULL,
  `doc_title` varchar(150) DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` int DEFAULT '0',
  `file_mime` varchar(100) DEFAULT NULL,
  `notes` text,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `child_id` (`child_id`),
  KEY `uploaded_by` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;



DROP TABLE IF EXISTS `documents`;
CREATE TABLE IF NOT EXISTS `documents` (
  `id` int NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int NOT NULL,
  `doc_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `doc_title` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` int NOT NULL,
  `file_mime` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `uploaded_by` int NOT NULL,
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_entity` (`entity_type`,`entity_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



DROP TABLE IF EXISTS `growth_records`;
CREATE TABLE IF NOT EXISTS `growth_records` (
  `id` int NOT NULL AUTO_INCREMENT,
  `child_id` int NOT NULL,
  `record_date` date NOT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `height` decimal(5,2) DEFAULT NULL,
  `head_circumference_cm` decimal(5,1) DEFAULT NULL,
  `bmi` decimal(5,2) DEFAULT NULL,
  `nutritional_status` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `child_id` (`child_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;



DROP TABLE IF EXISTS `health_reports`;
CREATE TABLE IF NOT EXISTS `health_reports` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `report_type` varchar(100) NOT NULL,
  `report_date` date NOT NULL,
  `findings` text,
  `recommendations` text,
  `doctor_name` varchar(100) DEFAULT NULL,
  `status` enum('draft','final','archived') DEFAULT 'final',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;



DROP TABLE IF EXISTS `health_workers`;
CREATE TABLE IF NOT EXISTS `health_workers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `worker_id` varchar(20) NOT NULL,
  `specialization` varchar(100) DEFAULT 'General',
  `facility_name` varchar(150) DEFAULT NULL,
  `license_number` varchar(50) DEFAULT NULL,
  `years_of_experience` int DEFAULT '0',
  `status` enum('active','inactive','on_leave') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `photo` varchar(191) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `worker_id` (`worker_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;




DROP TABLE IF EXISTS `immunizations`;
CREATE TABLE IF NOT EXISTS `immunizations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `child_id` int NOT NULL,
  `vaccine_name` varchar(100) NOT NULL,
  `due_date` date NOT NULL,
  `date_given` date DEFAULT NULL,
  `status` enum('pending','done') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reminder_sent` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `child_id` (`child_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;




DROP TABLE IF EXISTS `linked_devices`;
CREATE TABLE IF NOT EXISTS `linked_devices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `device_name` varchar(150) DEFAULT NULL,
  `device_type` enum('phone','tablet','watch','desktop','other') DEFAULT 'other',
  `pairing_token` varchar(64) NOT NULL,
  `user_agent` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `linked_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_seen` datetime DEFAULT CURRENT_TIMESTAMP,
  `status` enum('active','revoked') DEFAULT 'active',
  `short_code` varchar(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_token` (`pairing_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;




DROP TABLE IF EXISTS `ma_alert_thresholds`;
CREATE TABLE IF NOT EXISTS `ma_alert_thresholds` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `metric` varchar(30) NOT NULL,
  `min_value` decimal(6,2) DEFAULT NULL,
  `max_value` decimal(6,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `metric` (`metric`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;




DROP TABLE IF EXISTS `ma_devices`;
CREATE TABLE IF NOT EXISTS `ma_devices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `device_type` varchar(50) DEFAULT 'smartwatch',
  `device_name` varchar(100) DEFAULT NULL,
  `device_token` varchar(64) NOT NULL,
  `push_token` text,
  `paired_at` datetime DEFAULT NULL,
  `last_seen` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `device_token` (`device_token`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;



DROP TABLE IF EXISTS `ma_vitals`;
CREATE TABLE IF NOT EXISTS `ma_vitals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `child_id` int DEFAULT NULL,
  `heart_rate` int DEFAULT NULL,
  `spo2` int DEFAULT NULL,
  `bp_sys` int DEFAULT NULL,
  `bp_dia` int DEFAULT NULL,
  `temperature` decimal(4,1) DEFAULT NULL,
  `recorded_at` datetime DEFAULT NULL,
  `source` enum('wearable','manual','clinic') DEFAULT 'wearable',
  `alert_sent` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `child_id` (`child_id`),
  KEY `recorded_at` (`recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;



DROP TABLE IF EXISTS `mothers`;
CREATE TABLE IF NOT EXISTS `mothers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `national_id` varchar(50) DEFAULT NULL,
  `marital_status` enum('single','married','divorced','widowed') DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `next_of_kin` varchar(100) DEFAULT NULL,
  `next_of_kin_phone` varchar(20) DEFAULT NULL,
  `emergency_contact` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `photo` varchar(191) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;




DROP TABLE IF EXISTS `notes_sharing_log`;
CREATE TABLE IF NOT EXISTS `notes_sharing_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `note_id` int NOT NULL,
  `from_user_id` int NOT NULL,
  `to_user_id` int NOT NULL,
  `shared_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_note` (`note_id`),
  KEY `idx_to` (`to_user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




DROP TABLE IF EXISTS `notifications_log`;
CREATE TABLE IF NOT EXISTS `notifications_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `notification_type` varchar(50) DEFAULT NULL,
  `message` text,
  `status` enum('queued','sent','sent_push','failed') DEFAULT 'queued',
  `error_message` text,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `notification_type` (`notification_type`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;




DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;




DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `profile_picture` varchar(191) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('mother','healthworker','admin') NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_primary` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


DROP TRIGGER IF EXISTS `prevent_second_admin_insert`;
DELIMITER $$
CREATE TRIGGER `prevent_second_admin_insert` BEFORE INSERT ON `users` FOR EACH ROW BEGIN
    IF NEW.role = 'admin' AND NEW.is_primary = 1 THEN
        IF (SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_primary = 1) >= 1 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Only one administrator account is allowed.';
        END IF;
    END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `prevent_second_admin_update`;
DELIMITER $$
CREATE TRIGGER `prevent_second_admin_update` BEFORE UPDATE ON `users` FOR EACH ROW BEGIN
    IF NEW.role = 'admin' AND NEW.is_primary = 1 AND OLD.is_primary = 0 THEN
        IF (SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_primary = 1 AND id != NEW.id) >= 1 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Only one administrator account is allowed.';
        END IF;
    END IF;
END
$$
DELIMITER ;



DROP TABLE IF EXISTS `user_credentials`;
CREATE TABLE IF NOT EXISTS `user_credentials` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `credential_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `public_key` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `device_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'My Fingerprint',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_used` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `credential_id` (`credential_id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




DROP TABLE IF EXISTS `user_passkeys`;
CREATE TABLE IF NOT EXISTS `user_passkeys` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `credential_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `public_key` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `credential_id` (`credential_id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


ALTER TABLE `antenatal_visits`
  ADD CONSTRAINT `fk_anc_mother` FOREIGN KEY (`mother_id`) REFERENCES `mothers` (`id`) ON DELETE CASCADE;

ALTER TABLE `children`
  ADD CONSTRAINT `fk_child_mother` FOREIGN KEY (`mother_id`) REFERENCES `mothers` (`id`) ON DELETE CASCADE;

ALTER TABLE `child_documents`
  ADD CONSTRAINT `fk_doc_child` FOREIGN KEY (`child_id`) REFERENCES `children` (`id`) ON DELETE CASCADE;

ALTER TABLE `growth_records`
  ADD CONSTRAINT `fk_growth_child` FOREIGN KEY (`child_id`) REFERENCES `children` (`id`) ON DELETE CASCADE;

ALTER TABLE `health_workers`
  ADD CONSTRAINT `fk_worker_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `immunizations`
  ADD CONSTRAINT `fk_imm_child` FOREIGN KEY (`child_id`) REFERENCES `children` (`id`) ON DELETE CASCADE;

ALTER TABLE `mothers`
  ADD CONSTRAINT `fk_mother_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

INSERT IGNORE INTO `system_settings` (`id`, `setting_key`, `setting_value`, `description`) VALUES
(1, 'system_name', 'MA-LIFE', 'System Name'),
(2, 'system_version', 'v1.0.0', 'Current Version'),
(3, 'maintenance_mode', 'false', 'Enable maintenance mode'),
(4, 'allow_registration', 'true', 'Allow new user registration'),
(5, 'default_language', 'en', 'Default system language'),
(6, 'timezone', 'Africa/Kampala', 'Default timezone'),
(7, 'date_format', 'Y-m-d', 'Default date format'),
(8, 'session_timeout', '1800', 'Session timeout (seconds)');
