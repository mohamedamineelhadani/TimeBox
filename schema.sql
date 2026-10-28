CREATE DATABASE IF NOT EXISTS timebox CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE timebox;

-- Only one admin allowed: the primary key is always 1.
CREATE TABLE IF NOT EXISTS admin (
  id            TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CHECK (id = 1)
) ENGINE=InnoDB;

-- start_minute: minutes since 00:00 (NULL = unscheduled)
CREATE TABLE IF NOT EXISTS tasks (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(60) NOT NULL,
  duration     SMALLINT UNSIGNED NOT NULL DEFAULT 25,
  start_minute SMALLINT UNSIGNED NULL,
  status       ENUM('unscheduled','scheduled','done') NOT NULL DEFAULT 'unscheduled',
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
