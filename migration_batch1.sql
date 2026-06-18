-- Batch 1 migration — run in phpMyAdmin on the `ee` database

ALTER TABLE workouts
    ADD COLUMN difficulty   ENUM('beginner','intermediate','advanced') DEFAULT NULL AFTER description,
    ADD COLUMN is_published TINYINT(1) NOT NULL DEFAULT 0                           AFTER difficulty;
