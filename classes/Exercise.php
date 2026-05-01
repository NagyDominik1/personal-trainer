<?php

class Exercise
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAllByTrainer(int $userId): array
    {
        $stmt = $this->db->prepare('
            SELECT e.*, c.name AS category_name
            FROM exercises e
            LEFT JOIN categories c ON e.category_id = c.id
            WHERE e.user_id = :uid
            ORDER BY e.created_at DESC
        ');
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query('
            SELECT e.*, c.name AS category_name, u.first_name, u.last_name
            FROM exercises e
            LEFT JOIN categories c ON e.category_id = c.id
            LEFT JOIN users u ON e.user_id = u.id
            ORDER BY e.created_at DESC
        ');
        return $stmt->fetchAll();
    }

    public function getById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM exercises WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function create(array $data): bool
    {
        $stmt = $this->db->prepare('
            INSERT INTO exercises (user_id, category_id, title, description, duration_minutes, video_url)
            VALUES (:user_id, :category_id, :title, :description, :duration_minutes, :video_url)
        ');
        return $stmt->execute([
            ':user_id'          => $data['user_id'],
            ':category_id'      => $data['category_id']      ?: null,
            ':title'            => $data['title'],
            ':description'      => $data['description']      ?: null,
            ':duration_minutes' => $data['duration_minutes'] ?: null,
            ':video_url'        => $data['video_url']        ?: null,
        ]);
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare('
            UPDATE exercises
            SET category_id      = :category_id,
                title            = :title,
                description      = :description,
                duration_minutes = :duration_minutes,
                video_url        = :video_url
            WHERE id = :id AND user_id = :user_id
        ');
        return $stmt->execute([
            ':category_id'      => $data['category_id']      ?: null,
            ':title'            => $data['title'],
            ':description'      => $data['description']      ?: null,
            ':duration_minutes' => $data['duration_minutes'] ?: null,
            ':video_url'        => $data['video_url']        ?: null,
            ':id'               => $id,
            ':user_id'          => $data['user_id'],
        ]);
    }

    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM exercises WHERE id = :id AND user_id = :user_id');
        return $stmt->execute([':id' => $id, ':user_id' => $userId]);
    }
}
