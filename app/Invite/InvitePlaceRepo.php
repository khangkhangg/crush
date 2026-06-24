<?php
declare(strict_types=1);

namespace App\Invite;

final class InvitePlaceRepo
{
    public function __construct(private \PDO $pdo) {}

    public function add(
        int $inviteId,
        string $mealKey,
        string $placeName,
        ?string $placeUrl,
        ?string $resolvedName,
        ?string $resolvedAddress,
        ?string $cleanUrl,
        ?string $cuisine = null
    ): void {
        $this->pdo->prepare(
            'INSERT INTO invite_places
               (invite_id, meal_key, place_name, place_url, place_resolved_name, place_resolved_address, place_clean_url, cuisine)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               place_name = VALUES(place_name),
               place_url = VALUES(place_url),
               place_resolved_name = VALUES(place_resolved_name),
               place_resolved_address = VALUES(place_resolved_address),
               place_clean_url = VALUES(place_clean_url),
               cuisine = VALUES(cuisine)'
        )->execute([$inviteId, $mealKey, $placeName, $placeUrl, $resolvedName, $resolvedAddress, $cleanUrl, $cuisine]);
    }

    /** @return array<string,array> keyed by meal_key */
    public function forInvite(int $inviteId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM invite_places WHERE invite_id = ?');
        $stmt->execute([$inviteId]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[$row['meal_key']] = $row;
        }
        return $out;
    }

    public function forMeal(int $inviteId, string $mealKey): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM invite_places WHERE invite_id = ? AND meal_key = ?');
        $stmt->execute([$inviteId, $mealKey]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}
