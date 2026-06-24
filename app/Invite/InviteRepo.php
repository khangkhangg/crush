<?php
declare(strict_types=1);

namespace App\Invite;

use App\Core\Clock;

final class InviteRepo
{
    public function __construct(private \PDO $pdo, private Clock $clock) {}

    public function create(array $data): array
    {
        $token = bin2hex(random_bytes(32));
        $stmt = $this->pdo->prepare(
            'INSERT INTO invites
             (public_token, sender_id, crush_email, crush_name, is_anonymous,
              reveal_on_response, date_mode, status, theme_key, message, lang, created_at, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $token,
            $data['sender_id'],
            $data['crush_email'],
            $data['crush_name'] ?? null,
            !empty($data['is_anonymous']) ? 1 : 0,
            !empty($data['reveal_on_response']) ? 1 : 0,
            $data['date_mode'],
            $data['status'] ?? InviteState::SENT,
            $data['theme_key'] ?? null,
            $data['message'] ?? null,
            $data['lang'] ?? null,
            $this->clock->now()->format('Y-m-d H:i:s'),
            $data['expires_at'],
        ]);
        return $this->findById((int) $this->pdo->lastInsertId());
    }

    public function findById(int $id): ?array
    {
        return $this->one('SELECT * FROM invites WHERE id = ?', [$id]);
    }

    public function findByToken(string $token): ?array
    {
        return $this->one('SELECT * FROM invites WHERE public_token = ?', [$token]);
    }

    /** @return array<int,array> */
    public function listBySender(int $senderId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM invites WHERE sender_id = ? ORDER BY created_at DESC, id DESC'
        );
        $stmt->execute([$senderId]);
        return array_map([$this, 'cast'], $stmt->fetchAll());
    }

    public function updateStatus(int $id, string $status): void
    {
        $this->pdo->prepare('UPDATE invites SET status = ? WHERE id = ?')->execute([$status, $id]);
    }

    public function setTheme(int $id, string $themeKey): void
    {
        $this->pdo->prepare('UPDATE invites SET theme_key = ? WHERE id = ?')->execute([$themeKey, $id]);
    }

    public function addDateOption(int $inviteId, string $startAt, string $endAt): void
    {
        $this->pdo->prepare(
            'INSERT INTO invite_date_options (invite_id, start_at, end_at) VALUES (?, ?, ?)'
        )->execute([$inviteId, $startAt, $endAt]);
    }

    /** @return array<int,array> */
    public function dateOptions(int $inviteId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM invite_date_options WHERE invite_id = ? ORDER BY start_at ASC, id ASC'
        );
        $stmt->execute([$inviteId]);
        return $stmt->fetchAll();
    }

    /** @return array<int,array> */
    public function recent(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM invites ORDER BY created_at DESC, id DESC LIMIT ?');
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'cast'], $stmt->fetchAll());
    }

    /** @return array<int,array> */
    public function searchByCrushEmail(string $email): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM invites WHERE crush_email LIKE ? ORDER BY created_at DESC, id DESC LIMIT 50');
        $stmt->execute(['%' . $email . '%']);
        return array_map([$this, 'cast'], $stmt->fetchAll());
    }

    private function one(string $sql, array $params): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $this->cast($row);
    }

    private function cast(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['sender_id'] = (int) $row['sender_id'];
        $row['is_anonymous'] = (int) $row['is_anonymous'];
        $row['reveal_on_response'] = (int) $row['reveal_on_response'];
        return $row;
    }
}
