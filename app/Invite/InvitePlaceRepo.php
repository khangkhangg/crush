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
        $this->addOption($inviteId, $mealKey, $placeName, $placeUrl, $resolvedName, $resolvedAddress, $cleanUrl, $cuisine, 0);
    }

    public function addOption(
        int $inviteId, string $mealKey, string $name, ?string $url,
        ?string $rName, ?string $rAddr, ?string $clean, ?string $cuisine, int $sort = 0
    ): int {
        $this->pdo->prepare(
            'INSERT INTO invite_places
               (invite_id, meal_key, place_name, place_url, place_resolved_name, place_resolved_address, place_clean_url, cuisine, sort)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$inviteId, $mealKey, $name, $url, $rName, $rAddr, $clean, $cuisine, $sort]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string,array<int,array>> */
    public function groupedForInvite(int $inviteId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM invite_places WHERE invite_id = ? ORDER BY sort, id');
        $stmt->execute([$inviteId]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[$row['meal_key']][] = $row;
        }
        return $out;
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

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM invite_places WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}
