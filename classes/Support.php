<?php

/**
 * Support inbox — users message the admin team.
 * Each user may send at most one message per calendar day.
 */
class Support
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** True if the user has not yet sent a message today. */
    public function canSendToday(int $userId): bool
    {
        $stmt = $this->db->prepare('
            SELECT COUNT(*) FROM support_messages
            WHERE user_id = :uid AND DATE(created_at) = CURDATE()
        ');
        $stmt->execute([':uid' => $userId]);
        return (int) $stmt->fetchColumn() === 0;
    }

    /** Store a message. Returns [bool ok, string error]. */
    public function send(int $userId, string $message): array
    {
        $message = trim($message);
        if ($message === '') {
            return [false, 'Message cannot be empty.'];
        }
        if (!$this->canSendToday($userId)) {
            return [false, 'You can only send one message per day. Please try again tomorrow.'];
        }
        $stmt = $this->db->prepare('INSERT INTO support_messages (user_id, message) VALUES (:uid, :msg)');
        $ok = $stmt->execute([':uid' => $userId, ':msg' => substr($message, 0, 2000)]);
        return [$ok, $ok ? '' : 'Could not send your message.'];
    }

    /** A single user's own message history (newest first). */
    public function forUser(int $userId): array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM support_messages WHERE user_id = :uid ORDER BY created_at DESC
        ');
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    /** All messages with sender info (admin view, newest first). */
    public function allRecent(): array
    {
        return $this->db->query('
            SELECT sm.*, u.first_name, u.last_name, u.email, u.role
            FROM support_messages sm
            JOIN users u ON sm.user_id = u.id
            ORDER BY sm.is_read ASC, sm.created_at DESC
        ')->fetchAll();
    }

    public function countUnread(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM support_messages WHERE is_read = 0')->fetchColumn();
    }

    public function markRead(int $id): bool
    {
        return $this->db->prepare('UPDATE support_messages SET is_read = 1 WHERE id = :id')
            ->execute([':id' => $id]);
    }

    public function markAllRead(): bool
    {
        return $this->db->query('UPDATE support_messages SET is_read = 1 WHERE is_read = 0') !== false;
    }
}
