-- Batch 5 migration — run in phpMyAdmin on the `ee` database
-- Support inbox: users can message the admin (rate-limited to once per day in app code).

CREATE TABLE IF NOT EXISTS support_messages (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    message    TEXT NOT NULL,
    is_read    TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
