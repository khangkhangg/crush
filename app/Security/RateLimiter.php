<?php
declare(strict_types=1);

namespace App\Security;

use App\Core\Clock;

final class RateLimiter
{
    public function __construct(private \PDO $pdo, private Clock $clock) {}

    public function hit(string $scope, string $identifier, int $limit, int $windowSeconds): bool
    {
        $now = $this->clock->now();
        $nowStr = $now->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'SELECT id, window_start, count FROM rate_limits WHERE scope = ? AND identifier = ?'
        );
        $stmt->execute([$scope, $identifier]);
        $row = $stmt->fetch();

        if ($row === false) {
            $this->pdo->prepare(
                'INSERT INTO rate_limits (scope, identifier, window_start, count) VALUES (?, ?, ?, 1)'
            )->execute([$scope, $identifier, $nowStr]);
            return true;
        }

        $windowStart = new \DateTimeImmutable((string) $row['window_start'], new \DateTimeZone('UTC'));
        $elapsed = $now->getTimestamp() - $windowStart->getTimestamp();

        if ($elapsed >= $windowSeconds) {
            $this->pdo->prepare('UPDATE rate_limits SET window_start = ?, count = 1 WHERE id = ?')
                ->execute([$nowStr, $row['id']]);
            return true;
        }

        if ((int) $row['count'] >= $limit) {
            return false;
        }

        $this->pdo->prepare('UPDATE rate_limits SET count = count + 1 WHERE id = ?')
            ->execute([$row['id']]);
        return true;
    }
}
