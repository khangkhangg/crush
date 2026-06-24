<?php
declare(strict_types=1);

namespace App\Settings;

final class SettingsRepo
{
    public function __construct(private \PDO $pdo) {}

    public function get(string $key, ?string $default = null): ?string
    {
        $stmt = $this->pdo->prepare('SELECT `value` FROM settings WHERE `key` = ?');
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val === false ? $default : (string) $val;
    }

    public function set(string $key, string $value): void
    {
        $this->pdo->prepare(
            'INSERT INTO settings (`key`, `value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
        )->execute([$key, $value]);
    }

    /** @return array<string,string> */
    public function all(): array
    {
        $out = [];
        foreach ($this->pdo->query('SELECT `key`, `value` FROM settings') as $row) {
            $out[$row['key']] = (string) $row['value'];
        }
        return $out;
    }
}
