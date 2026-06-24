<?php
declare(strict_types=1);

namespace App\Auth;

use App\Core\Clock;

final class UserRepo
{
    public function __construct(private \PDO $pdo, private Clock $clock) {}

    public function findById(int $id): ?array
    {
        return $this->one('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public function findByEmail(string $email): ?array
    {
        return $this->one('SELECT * FROM users WHERE email = ?', [$email]);
    }

    public function findByGoogleId(string $googleId): ?array
    {
        return $this->one('SELECT * FROM users WHERE google_id = ?', [$googleId]);
    }

    public function create(
        string $email,
        ?string $name,
        string $provider,
        ?string $googleId = null,
        ?string $avatarUrl = null
    ): array {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (email, name, auth_provider, google_id, avatar_url, is_admin, created_at)
             VALUES (?, ?, ?, ?, ?, 0, ?)'
        );
        $stmt->execute([
            $email, $name, $provider, $googleId, $avatarUrl,
            $this->clock->now()->format('Y-m-d H:i:s'),
        ]);
        return $this->findById((int) $this->pdo->lastInsertId());
    }

    public function linkGoogle(int $userId, string $googleId, ?string $avatarUrl): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET google_id = ?, avatar_url = COALESCE(?, avatar_url) WHERE id = ?'
        );
        $stmt->execute([$googleId, $avatarUrl, $userId]);
    }

    private function one(string $sql, array $params): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $row['id'] = (int) $row['id'];
        $row['is_admin'] = (int) $row['is_admin'];
        return $row;
    }
}
