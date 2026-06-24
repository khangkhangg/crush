<?php
declare(strict_types=1);

namespace App\Security;

use App\Core\Clock;

final class BlockRepo
{
    public function __construct(private \PDO $pdo, private Clock $clock) {}

    public function block(int $senderId, string $crushEmail, ?string $reason = null): void
    {
        $this->pdo->prepare(
            'INSERT INTO blocks (sender_id, crush_email, reason, created_at) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE reason = VALUES(reason)'
        )->execute([$senderId, strtolower($crushEmail), $reason, $this->clock->now()->format('Y-m-d H:i:s')]);
    }

    public function isBlocked(int $senderId, string $crushEmail): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM blocks WHERE sender_id = ? AND crush_email = ?');
        $stmt->execute([$senderId, strtolower($crushEmail)]);
        return $stmt->fetchColumn() !== false;
    }

    /** @return array<int,array> */
    public function recent(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM blocks ORDER BY created_at DESC, id DESC LIMIT ?');
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
