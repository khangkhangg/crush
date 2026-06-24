<?php
declare(strict_types=1);

namespace App\Theme;

use App\Core\Clock;

final class AbEventRepo
{
    public function __construct(private \PDO $pdo, private Clock $clock) {}

    public function log(int $inviteId, string $themeKey, string $event): void
    {
        $this->pdo->prepare(
            'INSERT INTO ab_events (invite_id, theme_key, event, created_at) VALUES (?, ?, ?, ?)'
        )->execute([$inviteId, $themeKey, $event, $this->clock->now()->format('Y-m-d H:i:s')]);
    }

    public function count(string $themeKey, string $event): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) AS c FROM ab_events WHERE theme_key = ? AND event = ?');
        $stmt->execute([$themeKey, $event]);
        return (int) $stmt->fetch()['c'];
    }
}
