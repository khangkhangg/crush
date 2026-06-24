<?php
declare(strict_types=1);

namespace App\Invite;

use App\Core\Clock;

final class ResponseRepo
{
    private const FIELDS = [
        'chosen_start', 'chosen_end', 'meal_choice', 'meal_wish', 'crush_contact',
        'pickup_raw', 'pickup_name', 'pickup_address', 'pickup_clean_url',
        'chosen_place_id',
    ];

    public function __construct(private \PDO $pdo, private Clock $clock) {}

    public function store(int $inviteId, array $data): array
    {
        $values = [$inviteId];
        foreach (self::FIELDS as $f) {
            $values[] = $data[$f] ?? null;
        }
        $values[] = $this->clock->now()->format('Y-m-d H:i:s');

        $cols = 'invite_id, ' . implode(', ', self::FIELDS) . ', created_at';
        $marks = implode(', ', array_fill(0, count(self::FIELDS) + 2, '?'));

        $this->pdo->prepare("INSERT INTO responses ({$cols}) VALUES ({$marks})")->execute($values);
        return $this->findByInvite($inviteId);
    }

    public function findByInvite(int $inviteId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM responses WHERE invite_id = ?');
        $stmt->execute([$inviteId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $row['id'] = (int) $row['id'];
        $row['invite_id'] = (int) $row['invite_id'];
        return $row;
    }
}
