<?php
declare(strict_types=1);

namespace App\Theme;

final class ThemeRepo
{
    public function __construct(private \PDO $pdo) {}

    /** @return array<int,array> */
    public function listActive(): array
    {
        $rows = $this->pdo->query(
            'SELECT `key`, name, is_active, weight FROM themes WHERE is_active = 1 ORDER BY `key` ASC'
        )->fetchAll();
        return array_map(static function (array $r): array {
            $r['is_active'] = (int) $r['is_active'];
            $r['weight'] = (int) $r['weight'];
            return $r;
        }, $rows);
    }

    public function exists(string $key): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM themes WHERE `key` = ?');
        $stmt->execute([$key]);
        return $stmt->fetchColumn() !== false;
    }

    /** @return array<int,array> */
    public function all(): array
    {
        $rows = $this->pdo->query('SELECT `key`, name, is_active, weight FROM themes ORDER BY `key` ASC')->fetchAll();
        return array_map(static function (array $r): array {
            $r['is_active'] = (int) $r['is_active'];
            $r['weight'] = (int) $r['weight'];
            return $r;
        }, $rows);
    }

    public function setActive(string $key, bool $active): void
    {
        $this->pdo->prepare('UPDATE themes SET is_active = ? WHERE `key` = ?')->execute([$active ? 1 : 0, $key]);
    }

    public function setWeight(string $key, int $weight): void
    {
        $this->pdo->prepare('UPDATE themes SET weight = ? WHERE `key` = ?')->execute([max(0, $weight), $key]);
    }
}
