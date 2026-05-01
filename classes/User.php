<?php

class User
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Insert a new user and return the activation token on success, or false on failure.
     * Trainers start with is_approved = 0 and require admin approval to post workouts.
     * Regular users get is_approved = 1 automatically.
     */
    public function register(array $data): string|false
    {
        $token      = bin2hex(random_bytes(32));
        $isTrainer  = (bool) $data['is_trainer'];
        $role       = $isTrainer ? 'trainer' : 'user';
        $isApproved = $isTrainer ? 0 : 1;

        $stmt = $this->db->prepare('
            INSERT INTO users
                (first_name, last_name, email, phone, password_hash, role, is_approved, activation_token)
            VALUES
                (:first_name, :last_name, :email, :phone, :password_hash, :role, :is_approved, :activation_token)
        ');

        $ok = $stmt->execute([
            ':first_name'       => $data['first_name'],
            ':last_name'        => $data['last_name'],
            ':email'            => $data['email'],
            ':phone'            => $data['phone'] ?: null,
            ':password_hash'    => password_hash($data['password'], PASSWORD_BCRYPT),
            ':role'             => $role,
            ':is_approved'      => $isApproved,
            ':activation_token' => $token,
        ]);

        return $ok ? $token : false;
    }

    public function findByEmail(string $email): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        return $stmt->fetch();
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function activateByToken(string $token): bool
    {
        $stmt = $this->db->prepare('
            UPDATE users
            SET    is_active = 1, activation_token = NULL
            WHERE  activation_token = :token AND is_active = 0
        ');
        $stmt->execute([':token' => $token]);
        return $stmt->rowCount() > 0;
    }

    public function updateProfile(int $id, array $data): bool
    {
        $stmt = $this->db->prepare('
            UPDATE users
            SET    first_name = :first_name,
                   last_name  = :last_name,
                   phone      = :phone,
                   bio        = :bio
            WHERE  id = :id
        ');
        return $stmt->execute([
            ':first_name' => $data['first_name'],
            ':last_name'  => $data['last_name'],
            ':phone'      => $data['phone'] ?: null,
            ':bio'        => $data['bio']   ?: null,
            ':id'         => $id,
        ]);
    }

    public function updatePassword(int $id, string $newPassword): bool
    {
        $stmt = $this->db->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        return $stmt->execute([
            ':hash' => password_hash($newPassword, PASSWORD_BCRYPT),
            ':id'   => $id,
        ]);
    }

    public function setResetToken(int $id, string $token, string $expiresAt): bool
    {
        $stmt = $this->db->prepare('
            UPDATE users
            SET    reset_token = :token, reset_token_expires_at = :expires
            WHERE  id = :id
        ');
        return $stmt->execute([':token' => $token, ':expires' => $expiresAt, ':id' => $id]);
    }

    public function findByResetToken(string $token): array|false
    {
        $stmt = $this->db->prepare('
            SELECT * FROM users
            WHERE  reset_token = :token
              AND  reset_token_expires_at > NOW()
            LIMIT 1
        ');
        $stmt->execute([':token' => $token]);
        return $stmt->fetch();
    }

    public function clearResetToken(int $id): bool
    {
        $stmt = $this->db->prepare('
            UPDATE users
            SET    reset_token = NULL, reset_token_expires_at = NULL
            WHERE  id = :id
        ');
        return $stmt->execute([':id' => $id]);
    }
}
