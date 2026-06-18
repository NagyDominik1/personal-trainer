-- Batch 3 migration — run in phpMyAdmin on the `ee` database

ALTER TABLE workouts ADD COLUMN is_hidden TINYINT(1) NOT NULL DEFAULT 0;
