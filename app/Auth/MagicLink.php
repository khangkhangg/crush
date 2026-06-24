<?php
declare(strict_types=1);

namespace App\Auth;

use App\Core\Clock;

final class MagicLink
{
    public function __construct(
        private \PDO $pdo,
        private UserRepo $users,
        private Clock $clock,
        private int $ttlSeconds = 900,
    ) {}

    public function start(string $email): string
    {
        $user = $this->users->findByEmail($email)
            ?? $this->users->create($email, null, 'magic');

        $raw  = bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw);
        $expires = $this->clock->now()
            ->modify("+{$this->ttlSeconds} seconds")
            ->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'INSERT INTO magic_tokens (user_id, token_hash, expires_at, used_at)
             VALUES (?, ?, ?, NULL)'
        );
        $stmt->execute([$user['id'], $hash, $expires]);

        return $raw;
    }

    public function complete(string $rawToken): ?array
    {
        $hash = hash('sha256', $rawToken);
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, expires_at, used_at FROM magic_tokens WHERE token_hash = ?'
        );
        $stmt->execute([$hash]);
        $row = $stmt->fetch();

        if ($row === false || $row['used_at'] !== null) {
            return null;
        }
        if ($row['expires_at'] < $this->clock->now()->format('Y-m-d H:i:s')) {
            return null;
        }

        $mark = $this->pdo->prepare('UPDATE magic_tokens SET used_at = ? WHERE id = ?');
        $mark->execute([$this->clock->now()->format('Y-m-d H:i:s'), $row['id']]);

        return $this->users->findById((int) $row['user_id']);
    }
}
