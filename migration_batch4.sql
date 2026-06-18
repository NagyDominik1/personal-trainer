-- Batch 4 migration — run in phpMyAdmin on the `ee` database
-- Adds the user ⇄ trainer coaching relationship and marks coach-assigned plans.
-- NOTE: client_id / trainer_id are INT UNSIGNED to match users.id.

-- A coaching relationship: a client (role=user) hires a trainer.
-- One active coach per client is enforced in application code.
CREATE TABLE IF NOT EXISTS coaching (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id    INT UNSIGNED NOT NULL,
    trainer_id   INT UNSIGNED NOT NULL,
    status       ENUM('pending','active','declined','ended') NOT NULL DEFAULT 'pending',
    message      TEXT DEFAULT NULL,                         -- client's intro note to the trainer
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    responded_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (client_id)  REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (trainer_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Marks workouts a coach created/assigned for a client (NULL = self-made personal plan).
ALTER TABLE workouts ADD COLUMN coach_id INT UNSIGNED DEFAULT NULL;

-- The whole UI offers a "No category" option, but category_id was NOT NULL —
-- creating a plan without a category would fail. Make it nullable to match.
ALTER TABLE workouts MODIFY COLUMN category_id INT UNSIGNED DEFAULT NULL;
