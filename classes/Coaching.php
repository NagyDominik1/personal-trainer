<?php

/**
 * Coaching relationship between a client (role=user) and a trainer.
 *
 * Flow:
 *   1. Client sends a request from a trainer's profile (with an optional message).
 *   2. Trainer accepts or declines it on their Clients page.
 *   3. While active, the trainer may edit all of the client's personal plans
 *      and create new plans for them.
 *
 * A client may have at most ONE active coach at a time (enforced here).
 */
class Coaching
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /* ── Client side ───────────────────────────────────────────── */

    /**
     * Send a coaching request. Returns [bool ok, string error].
     * Guards: no existing active coach and no pending request for this client.
     */
    public function request(int $clientId, int $trainerId, string $message): array
    {
        if ($this->getActiveForClient($clientId)) {
            return [false, 'You already have an active coach. End that first.'];
        }
        if ($this->getPendingForClient($clientId)) {
            return [false, 'You already have a pending request. Wait for a response.'];
        }
        // Trainer must be a real, approved, non-banned trainer.
        $stmt = $this->db->prepare('SELECT id FROM users WHERE id = :id AND role = "trainer" AND is_approved = 1 AND is_banned = 0');
        $stmt->execute([':id' => $trainerId]);
        if (!$stmt->fetch()) {
            return [false, 'Trainer not found.'];
        }

        $stmt = $this->db->prepare('
            INSERT INTO coaching (client_id, trainer_id, status, message)
            VALUES (:cid, :tid, "pending", :msg)
        ');
        $ok = $stmt->execute([':cid' => $clientId, ':tid' => $trainerId, ':msg' => $message ?: null]);
        return [$ok, $ok ? '' : 'Could not send request.'];
    }

    /** The client's active coaching row, with trainer name/bio — or false. */
    public function getActiveForClient(int $clientId): array|false
    {
        $stmt = $this->db->prepare('
            SELECT co.*, u.first_name, u.last_name, u.bio
            FROM coaching co
            JOIN users u ON co.trainer_id = u.id
            WHERE co.client_id = :cid AND co.status = "active"
            LIMIT 1
        ');
        $stmt->execute([':cid' => $clientId]);
        return $stmt->fetch();
    }

    /** The client's pending request, with trainer name — or false. */
    public function getPendingForClient(int $clientId): array|false
    {
        $stmt = $this->db->prepare('
            SELECT co.*, u.first_name, u.last_name
            FROM coaching co
            JOIN users u ON co.trainer_id = u.id
            WHERE co.client_id = :cid AND co.status = "pending"
            LIMIT 1
        ');
        $stmt->execute([':cid' => $clientId]);
        return $stmt->fetch();
    }

    /**
     * Relationship state between a specific client and trainer for button rendering.
     * Returns the most recent row or false.
     */
    public function getState(int $clientId, int $trainerId): array|false
    {
        $stmt = $this->db->prepare('
            SELECT * FROM coaching
            WHERE client_id = :cid AND trainer_id = :tid
            ORDER BY created_at DESC
            LIMIT 1
        ');
        $stmt->execute([':cid' => $clientId, ':tid' => $trainerId]);
        return $stmt->fetch();
    }

    /** Client cancels their own pending request. */
    public function cancelRequest(int $clientId): bool
    {
        $stmt = $this->db->prepare('
            UPDATE coaching SET status = "declined", responded_at = NOW()
            WHERE client_id = :cid AND status = "pending"
        ');
        return $stmt->execute([':cid' => $clientId]);
    }

    /* ── Trainer side ──────────────────────────────────────────── */

    /** Pending requests addressed to this trainer (newest first). */
    public function pendingRequests(int $trainerId): array
    {
        $stmt = $this->db->prepare('
            SELECT co.*, u.first_name, u.last_name, u.email
            FROM coaching co
            JOIN users u ON co.client_id = u.id
            WHERE co.trainer_id = :tid AND co.status = "pending"
            ORDER BY co.created_at DESC
        ');
        $stmt->execute([':tid' => $trainerId]);
        return $stmt->fetchAll();
    }

    /** Active clients of this trainer, with a plan count. */
    public function activeClients(int $trainerId): array
    {
        $stmt = $this->db->prepare('
            SELECT co.*, u.first_name, u.last_name, u.email,
                (SELECT COUNT(*) FROM workouts w
                    WHERE w.user_id = co.client_id AND w.is_published = 0) AS plan_count
            FROM coaching co
            JOIN users u ON co.client_id = u.id
            WHERE co.trainer_id = :tid AND co.status = "active"
            ORDER BY co.responded_at DESC, co.created_at DESC
        ');
        $stmt->execute([':tid' => $trainerId]);
        return $stmt->fetchAll();
    }

    public function countPending(int $trainerId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM coaching WHERE trainer_id = :tid AND status = "pending"');
        $stmt->execute([':tid' => $trainerId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Trainer accepts a pending request. Verifies ownership and that the
     * client has no other active coach (declines any other pending requests).
     */
    public function accept(int $coachingId, int $trainerId): bool
    {
        $stmt = $this->db->prepare('SELECT * FROM coaching WHERE id = :id AND trainer_id = :tid AND status = "pending"');
        $stmt->execute([':id' => $coachingId, ':tid' => $trainerId]);
        $row = $stmt->fetch();
        if (!$row) return false;

        // Safety: a client must not end up with two active coaches.
        if ($this->getActiveForClient((int)$row['client_id'])) return false;

        $this->db->prepare('UPDATE coaching SET status = "active", responded_at = NOW() WHERE id = :id')
            ->execute([':id' => $coachingId]);

        // Decline any other pending requests this client may have sent elsewhere.
        $this->db->prepare('
            UPDATE coaching SET status = "declined", responded_at = NOW()
            WHERE client_id = :cid AND status = "pending" AND id != :id
        ')->execute([':cid' => $row['client_id'], ':id' => $coachingId]);

        return true;
    }

    public function decline(int $coachingId, int $trainerId): bool
    {
        $stmt = $this->db->prepare('
            UPDATE coaching SET status = "declined", responded_at = NOW()
            WHERE id = :id AND trainer_id = :tid AND status = "pending"
        ');
        return $stmt->execute([':id' => $coachingId, ':tid' => $trainerId]);
    }

    /** Either party ends an active coaching relationship. */
    public function end(int $coachingId, int $actorId): bool
    {
        $stmt = $this->db->prepare('
            UPDATE coaching SET status = "ended", responded_at = NOW()
            WHERE id = :id AND status = "active" AND (client_id = :a OR trainer_id = :a2)
        ');
        return $stmt->execute([':id' => $coachingId, ':a' => $actorId, ':a2' => $actorId]);
    }

    /* ── Permission helper ─────────────────────────────────────── */

    /** True if the trainer is the active coach of the given client. */
    public function isActiveCoach(int $trainerId, int $clientId): bool
    {
        $stmt = $this->db->prepare('
            SELECT 1 FROM coaching
            WHERE trainer_id = :tid AND client_id = :cid AND status = "active"
            LIMIT 1
        ');
        $stmt->execute([':tid' => $trainerId, ':cid' => $clientId]);
        return (bool) $stmt->fetchColumn();
    }

    /** Fetch an active coaching row owned by this trainer (for client pages). */
    public function getActiveByIdForTrainer(int $coachingId, int $trainerId): array|false
    {
        $stmt = $this->db->prepare('
            SELECT co.*, u.first_name, u.last_name, u.email
            FROM coaching co
            JOIN users u ON co.client_id = u.id
            WHERE co.id = :id AND co.trainer_id = :tid AND co.status = "active"
            LIMIT 1
        ');
        $stmt->execute([':id' => $coachingId, ':tid' => $trainerId]);
        return $stmt->fetch();
    }

    /* ── Admin side ────────────────────────────────────────────── */

    /** All active coaching relationships with client + trainer names (admin overview). */
    public function allActive(): array
    {
        return $this->db->query('
            SELECT co.id, co.client_id, co.trainer_id, co.created_at, co.responded_at,
                cu.first_name AS client_first,  cu.last_name AS client_last,  cu.email AS client_email,
                tu.first_name AS trainer_first, tu.last_name AS trainer_last,
                (SELECT COUNT(*) FROM workouts w WHERE w.user_id = co.client_id AND w.is_published = 0) AS plan_count
            FROM coaching co
            JOIN users cu ON co.client_id  = cu.id
            JOIN users tu ON co.trainer_id = tu.id
            WHERE co.status = "active"
            ORDER BY co.responded_at DESC, co.created_at DESC
        ')->fetchAll();
    }

    public function countActive(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM coaching WHERE status = "active"')->fetchColumn();
    }

    /** Admin force-ends a coaching relationship regardless of party. */
    public function adminDrop(int $coachingId): bool
    {
        $stmt = $this->db->prepare('
            UPDATE coaching SET status = "ended", responded_at = NOW()
            WHERE id = :id AND status = "active"
        ');
        return $stmt->execute([':id' => $coachingId]);
    }

    /**
     * Admin reassigns the client of an active coaching row to a different trainer.
     * Ends the current relationship and opens a new active one. Returns [ok, error].
     */
    public function adminReassign(int $coachingId, int $newTrainerId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM coaching WHERE id = :id AND status = "active"');
        $stmt->execute([':id' => $coachingId]);
        $row = $stmt->fetch();
        if (!$row) return [false, 'Relationship not found.'];
        if ((int)$row['trainer_id'] === $newTrainerId) return [false, 'Already coached by that trainer.'];

        // New trainer must be a real, approved, non-banned trainer.
        $chk = $this->db->prepare('SELECT id FROM users WHERE id = :id AND role = "trainer" AND is_approved = 1 AND is_banned = 0');
        $chk->execute([':id' => $newTrainerId]);
        if (!$chk->fetch()) return [false, 'Invalid trainer.'];

        $this->db->prepare('UPDATE coaching SET status = "ended", responded_at = NOW() WHERE id = :id')
            ->execute([':id' => $coachingId]);
        $this->db->prepare('
            INSERT INTO coaching (client_id, trainer_id, status, message, responded_at)
            VALUES (:cid, :tid, "active", "Reassigned by admin", NOW())
        ')->execute([':cid' => $row['client_id'], ':tid' => $newTrainerId]);

        return [true, ''];
    }
}
