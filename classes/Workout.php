<?php

class Workout
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAllByTrainer(int $userId): array
    {
        $stmt = $this->db->prepare('
            SELECT w.*, c.name AS category_name,
                (SELECT COUNT(*) FROM user_workouts WHERE workout_id = w.id) AS save_count,
                (SELECT COUNT(*) FROM workout_days WHERE workout_id = w.id) AS day_count,
                (SELECT COUNT(*) FROM workout_day_exercises wde
                    JOIN workout_days wd ON wde.workout_day_id = wd.id
                    WHERE wd.workout_id = w.id) AS exercise_count
            FROM workouts w
            LEFT JOIN categories c ON w.category_id = c.id
            WHERE w.user_id = :uid
            ORDER BY w.created_at DESC
        ');
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query('
            SELECT w.*, c.name AS category_name, u.first_name, u.last_name,
                (SELECT COUNT(*) FROM workout_days WHERE workout_id = w.id) AS day_count,
                (SELECT COUNT(*) FROM workout_day_exercises wde
                    JOIN workout_days wd ON wde.workout_day_id = wd.id
                    WHERE wd.workout_id = w.id) AS exercise_count,
                (SELECT ROUND(AVG(rating),1) FROM workout_reviews WHERE workout_id = w.id) AS avg_rating,
                (SELECT COUNT(*) FROM workout_reviews WHERE workout_id = w.id) AS review_count
            FROM workouts w
            LEFT JOIN categories c ON w.category_id = c.id
            LEFT JOIN users u ON w.user_id = u.id
            WHERE w.is_published = 1 AND w.is_hidden = 0
            ORDER BY w.created_at DESC
        ');
        return $stmt->fetchAll();
    }

    public function getById(int $id): array|false
    {
        $stmt = $this->db->prepare('
            SELECT w.*, c.name AS category_name, u.first_name, u.last_name
            FROM workouts w
            LEFT JOIN categories c ON w.category_id = c.id
            LEFT JOIN users u ON w.user_id = u.id
            WHERE w.id = :id
        ');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function getDays(int $workoutId): array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM workout_days WHERE workout_id = :id ORDER BY day_number
        ');
        $stmt->execute([':id' => $workoutId]);
        return $stmt->fetchAll();
    }

    public function getDayExercises(int $dayId): array
    {
        $stmt = $this->db->prepare('
            SELECT wde.id AS assignment_id, e.title, e.description, e.duration_minutes, e.video_url
            FROM workout_day_exercises wde
            JOIN exercises e ON wde.exercise_id = e.id
            WHERE wde.workout_day_id = :day_id
            ORDER BY wde.sort_order
        ');
        $stmt->execute([':day_id' => $dayId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int|false
    {
        $stmt = $this->db->prepare('
            INSERT INTO workouts (user_id, category_id, title, description, difficulty)
            VALUES (:user_id, :category_id, :title, :description, :difficulty)
        ');
        $ok = $stmt->execute([
            ':user_id'     => $data['user_id'],
            ':category_id' => $data['category_id'] ?: null,
            ':title'       => $data['title'],
            ':description' => $data['description'] ?: null,
            ':difficulty'  => $data['difficulty'] ?: null,
        ]);
        return $ok ? (int) $this->db->lastInsertId() : false;
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare('
            UPDATE workouts
            SET category_id = :category_id, title = :title,
                description = :description, difficulty = :difficulty
            WHERE id = :id AND user_id = :user_id
        ');
        return $stmt->execute([
            ':category_id' => $data['category_id'] ?: null,
            ':title'       => $data['title'],
            ':description' => $data['description'] ?: null,
            ':difficulty'  => $data['difficulty'] ?: null,
            ':id'          => $id,
            ':user_id'     => $data['user_id'],
        ]);
    }

    public function togglePublish(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare('
            UPDATE workouts SET is_published = 1 - is_published
            WHERE id = :id AND user_id = :user_id
        ');
        return $stmt->execute([':id' => $id, ':user_id' => $userId]);
    }

    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM workouts WHERE id = :id AND user_id = :user_id');
        return $stmt->execute([':id' => $id, ':user_id' => $userId]);
    }

    public function addDay(int $workoutId, int $dayNumber, string $title): int|false
    {
        $stmt = $this->db->prepare('
            INSERT INTO workout_days (workout_id, day_number, title)
            VALUES (:wid, :day, :title)
        ');
        $ok = $stmt->execute([':wid' => $workoutId, ':day' => $dayNumber, ':title' => $title]);
        return $ok ? (int) $this->db->lastInsertId() : false;
    }

    public function updateDay(int $dayId, string $title, int $userId): bool
    {
        $stmt = $this->db->prepare('
            UPDATE workout_days wd
            JOIN workouts w ON wd.workout_id = w.id
            SET wd.title = :title
            WHERE wd.id = :day_id AND w.user_id = :user_id
        ');
        return $stmt->execute([':title' => $title, ':day_id' => $dayId, ':user_id' => $userId]);
    }

    public function deleteDay(int $dayId, int $userId): bool
    {
        $stmt = $this->db->prepare('
            DELETE wd FROM workout_days wd
            JOIN workouts w ON wd.workout_id = w.id
            WHERE wd.id = :day_id AND w.user_id = :user_id
        ');
        return $stmt->execute([':day_id' => $dayId, ':user_id' => $userId]);
    }

    public function addExerciseToDay(int $dayId, int $exerciseId): bool
    {
        $stmt = $this->db->prepare('
            SELECT COALESCE(MAX(sort_order), 0) + 1 FROM workout_day_exercises WHERE workout_day_id = :did
        ');
        $stmt->execute([':did' => $dayId]);
        $order = (int) $stmt->fetchColumn();

        $stmt = $this->db->prepare('
            INSERT INTO workout_day_exercises (workout_day_id, exercise_id, sort_order)
            VALUES (:day_id, :exercise_id, :sort_order)
        ');
        return $stmt->execute([':day_id' => $dayId, ':exercise_id' => $exerciseId, ':sort_order' => $order]);
    }

    public function removeExerciseFromDay(int $assignmentId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM workout_day_exercises WHERE id = :id');
        return $stmt->execute([':id' => $assignmentId]);
    }

    /* ── Coaching support ───────────────────────────────────────
       These methods do NOT enforce ownership themselves; callers must
       verify the actor is the plan owner or the client's active coach. */

    /** Create a personal (unpublished) plan owned by a client, assigned by a coach. */
    public function createForClient(int $clientId, int $coachId, array $data): int|false
    {
        $stmt = $this->db->prepare('
            INSERT INTO workouts (user_id, coach_id, category_id, title, description, difficulty, is_published)
            VALUES (:user_id, :coach_id, :category_id, :title, :description, :difficulty, 0)
        ');
        $ok = $stmt->execute([
            ':user_id'     => $clientId,
            ':coach_id'    => $coachId,
            ':category_id' => $data['category_id'] ?: null,
            ':title'       => $data['title'],
            ':description' => $data['description'] ?: null,
            ':difficulty'  => $data['difficulty'] ?: null,
        ]);
        return $ok ? (int) $this->db->lastInsertId() : false;
    }

    /** Update plan meta without an ownership constraint (caller pre-checks permission). */
    public function updateMeta(int $id, array $data): bool
    {
        $stmt = $this->db->prepare('
            UPDATE workouts
            SET category_id = :category_id, title = :title,
                description = :description, difficulty = :difficulty
            WHERE id = :id
        ');
        return $stmt->execute([
            ':category_id' => $data['category_id'] ?: null,
            ':title'       => $data['title'],
            ':description' => $data['description'] ?: null,
            ':difficulty'  => $data['difficulty'] ?: null,
            ':id'          => $id,
        ]);
    }

    public function updateDayById(int $dayId, string $title): bool
    {
        $stmt = $this->db->prepare('UPDATE workout_days SET title = :title WHERE id = :id');
        return $stmt->execute([':title' => $title, ':id' => $dayId]);
    }

    public function deleteDayById(int $dayId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM workout_days WHERE id = :id');
        return $stmt->execute([':id' => $dayId]);
    }

    public function deleteById(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM workouts WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    /** All personal (unpublished) plans for a user, with day/exercise counts. */
    public function getPersonalPlans(int $userId): array
    {
        $stmt = $this->db->prepare('
            SELECT w.*, c.name AS category_name,
                (SELECT COUNT(*) FROM workout_days WHERE workout_id = w.id) AS day_count,
                (SELECT COUNT(*) FROM workout_day_exercises wde
                    JOIN workout_days wd ON wde.workout_day_id = wd.id
                    WHERE wd.workout_id = w.id) AS exercise_count
            FROM workouts w
            LEFT JOIN categories c ON w.category_id = c.id
            WHERE w.user_id = :uid AND w.is_published = 0
            ORDER BY w.created_at DESC
        ');
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function isSaved(int $userId, int $workoutId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM user_workouts WHERE user_id = :uid AND workout_id = :wid');
        $stmt->execute([':uid' => $userId, ':wid' => $workoutId]);
        return (bool) $stmt->fetch();
    }

    public function save(int $userId, int $workoutId): bool
    {
        $stmt = $this->db->prepare('
            INSERT IGNORE INTO user_workouts (user_id, workout_id) VALUES (:uid, :wid)
        ');
        return $stmt->execute([':uid' => $userId, ':wid' => $workoutId]);
    }

    public function unsave(int $userId, int $workoutId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM user_workouts WHERE user_id = :uid AND workout_id = :wid');
        return $stmt->execute([':uid' => $userId, ':wid' => $workoutId]);
    }

    public function getSaved(int $userId): array
    {
        $stmt = $this->db->prepare('
            SELECT w.*, c.name AS category_name, u.first_name, u.last_name,
                uw.notes,
                (SELECT ROUND(AVG(rating),1) FROM workout_reviews WHERE workout_id = w.id) AS avg_rating,
                (SELECT COUNT(*) FROM workout_reviews WHERE workout_id = w.id) AS review_count
            FROM user_workouts uw
            JOIN workouts w ON uw.workout_id = w.id
            LEFT JOIN categories c ON w.category_id = c.id
            LEFT JOIN users u ON w.user_id = u.id
            WHERE uw.user_id = :uid AND w.is_published = 1
            ORDER BY uw.saved_at DESC
        ');
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function getPublishedByTrainer(int $trainerId): array
    {
        $stmt = $this->db->prepare('
            SELECT w.*, c.name AS category_name,
                (SELECT COUNT(*) FROM user_workouts WHERE workout_id = w.id) AS save_count,
                (SELECT COUNT(*) FROM workout_days WHERE workout_id = w.id) AS day_count,
                (SELECT ROUND(AVG(rating),1) FROM workout_reviews WHERE workout_id = w.id) AS avg_rating,
                (SELECT COUNT(*) FROM workout_reviews WHERE workout_id = w.id) AS review_count
            FROM workouts w
            LEFT JOIN categories c ON w.category_id = c.id
            WHERE w.user_id = :uid AND w.is_published = 1 AND w.is_hidden = 0
            ORDER BY w.created_at DESC
        ');
        $stmt->execute([':uid' => $trainerId]);
        return $stmt->fetchAll();
    }

    public function getAllForAdmin(): array
    {
        $stmt = $this->db->query('
            SELECT w.*, c.name AS category_name, u.first_name, u.last_name,
                (SELECT COUNT(*) FROM user_workouts WHERE workout_id = w.id) AS save_count,
                (SELECT COUNT(*) FROM workout_reviews WHERE workout_id = w.id) AS review_count
            FROM workouts w
            LEFT JOIN categories c ON w.category_id = c.id
            LEFT JOIN users u ON w.user_id = u.id
            ORDER BY w.is_hidden ASC, w.created_at DESC
        ');
        return $stmt->fetchAll();
    }

    public function toggleHidden(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE workouts SET is_hidden = 1 - is_hidden WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    /* ── Progress ──────────────────────────────────────────────── */

    public function toggleDayComplete(int $userId, int $workoutId, int $dayId): void
    {
        $stmt = $this->db->prepare('SELECT id FROM workout_progress WHERE user_id = :uid AND day_id = :did');
        $stmt->execute([':uid' => $userId, ':did' => $dayId]);
        if ($stmt->fetch()) {
            $this->db->prepare('DELETE FROM workout_progress WHERE user_id = :uid AND day_id = :did')
                ->execute([':uid' => $userId, ':did' => $dayId]);
        } else {
            $this->db->prepare('INSERT INTO workout_progress (user_id, workout_id, day_id) VALUES (:uid, :wid, :did)')
                ->execute([':uid' => $userId, ':wid' => $workoutId, ':did' => $dayId]);
        }
    }

    public function getCompletedDays(int $userId, int $workoutId): array
    {
        $stmt = $this->db->prepare('SELECT day_id FROM workout_progress WHERE user_id = :uid AND workout_id = :wid');
        $stmt->execute([':uid' => $userId, ':wid' => $workoutId]);
        return array_column($stmt->fetchAll(), 'day_id');
    }

    /* ── Reviews ───────────────────────────────────────────────── */

    public function submitReview(int $userId, int $workoutId, int $rating, string $comment): bool
    {
        $stmt = $this->db->prepare('
            INSERT INTO workout_reviews (user_id, workout_id, rating, comment)
            VALUES (:uid, :wid, :r, :c)
            ON DUPLICATE KEY UPDATE rating = :r2, comment = :c2
        ');
        return $stmt->execute([':uid' => $userId, ':wid' => $workoutId, ':r' => $rating, ':c' => $comment, ':r2' => $rating, ':c2' => $comment]);
    }

    public function getReviews(int $workoutId): array
    {
        $stmt = $this->db->prepare('
            SELECT r.*, u.first_name, u.last_name
            FROM workout_reviews r
            JOIN users u ON r.user_id = u.id
            WHERE r.workout_id = :wid
            ORDER BY r.created_at DESC
        ');
        $stmt->execute([':wid' => $workoutId]);
        return $stmt->fetchAll();
    }

    public function getUserReview(int $userId, int $workoutId): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM workout_reviews WHERE user_id = :uid AND workout_id = :wid');
        $stmt->execute([':uid' => $userId, ':wid' => $workoutId]);
        return $stmt->fetch();
    }

    /* ── Notes ─────────────────────────────────────────────────── */

    public function saveNote(int $userId, int $workoutId, string $notes): bool
    {
        $stmt = $this->db->prepare('UPDATE user_workouts SET notes = :notes WHERE user_id = :uid AND workout_id = :wid');
        return $stmt->execute([':notes' => $notes, ':uid' => $userId, ':wid' => $workoutId]);
    }

    public function getNote(int $userId, int $workoutId): string
    {
        $stmt = $this->db->prepare('SELECT notes FROM user_workouts WHERE user_id = :uid AND workout_id = :wid');
        $stmt->execute([':uid' => $userId, ':wid' => $workoutId]);
        return (string)($stmt->fetchColumn() ?: '');
    }
}
