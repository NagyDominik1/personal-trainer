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
            SELECT w.*, c.name AS category_name
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
            SELECT w.*, c.name AS category_name, u.first_name, u.last_name
            FROM workouts w
            LEFT JOIN categories c ON w.category_id = c.id
            LEFT JOIN users u ON w.user_id = u.id
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
            SELECT wde.id AS assignment_id, e.title, e.duration_minutes
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
            INSERT INTO workouts (user_id, category_id, title, description)
            VALUES (:user_id, :category_id, :title, :description)
        ');
        $ok = $stmt->execute([
            ':user_id'     => $data['user_id'],
            ':category_id' => $data['category_id'] ?: null,
            ':title'       => $data['title'],
            ':description' => $data['description'] ?: null,
        ]);
        return $ok ? (int) $this->db->lastInsertId() : false;
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare('
            UPDATE workouts
            SET category_id = :category_id, title = :title, description = :description
            WHERE id = :id AND user_id = :user_id
        ');
        return $stmt->execute([
            ':category_id' => $data['category_id'] ?: null,
            ':title'       => $data['title'],
            ':description' => $data['description'] ?: null,
            ':id'          => $id,
            ':user_id'     => $data['user_id'],
        ]);
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
            SELECT w.*, c.name AS category_name, u.first_name, u.last_name
            FROM user_workouts uw
            JOIN workouts w ON uw.workout_id = w.id
            LEFT JOIN categories c ON w.category_id = c.id
            LEFT JOIN users u ON w.user_id = u.id
            WHERE uw.user_id = :uid
            ORDER BY uw.saved_at DESC
        ');
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }
}
