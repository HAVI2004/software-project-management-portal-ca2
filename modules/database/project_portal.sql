CREATE DATABASE IF NOT EXISTS `ca2`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `ca2`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'user') NOT NULL DEFAULT 'user',
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `projects` (
  `project_id` INT NOT NULL AUTO_INCREMENT,
  `project_name` VARCHAR(150) NOT NULL,
  `description` TEXT NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  PRIMARY KEY (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tasks` (
  `task_id` INT NOT NULL AUTO_INCREMENT,
  `project_id` INT DEFAULT NULL,
  `team_id` INT DEFAULT NULL,
  `assigned_to` INT NOT NULL,
  `task_name` VARCHAR(255) NOT NULL,
  `status` ENUM('Pending', 'In Progress', 'Completed') NOT NULL DEFAULT 'Pending',
  `deadline` DATE NOT NULL,
  `progress_percent` INT NOT NULL DEFAULT 0,
  `progress_note` TEXT DEFAULT NULL,
  `admin_message` TEXT DEFAULT NULL,
  `user_message` TEXT DEFAULT NULL,
  `attachment_path` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`task_id`),
  KEY `tasks_project_id_index` (`project_id`),
  KEY `tasks_team_id_index` (`team_id`),
  KEY `tasks_assigned_to_index` (`assigned_to`),
  KEY `tasks_deadline_index` (`deadline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `teams` (
  `team_id` INT NOT NULL AUTO_INCREMENT,
  `team_name` VARCHAR(150) NOT NULL,
  `leader_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`team_id`),
  KEY `teams_leader_id_index` (`leader_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `team_members` (
  `team_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  PRIMARY KEY (`team_id`, `user_id`),
  KEY `team_members_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `team_messages` (
  `message_id` INT NOT NULL AUTO_INCREMENT,
  `team_id` INT NOT NULL,
  `sender_id` INT NOT NULL,
  `message_text` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`message_id`),
  KEY `team_messages_team_id_index` (`team_id`),
  KEY `team_messages_sender_id_index` (`sender_id`),
  KEY `team_messages_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`name`, `email`, `password`, `role`)
SELECT 'Administrator', 'admin@portal.local', 'admin123', 'admin'
WHERE NOT EXISTS (
  SELECT 1
  FROM `users`
  WHERE `email` = 'admin@portal.local'
);
