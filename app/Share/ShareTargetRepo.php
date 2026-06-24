<?php
declare(strict_types=1);

namespace App\Share;

final class ShareTargetRepo
{
    private const ALLOWED_SCHEMES = ['http', 'https', 'sms', 'mailto'];

    public function __construct(private \PDO $pdo) {}

    /** @return array<int,array> */
    public function listEnabled(): array
    {
        return $this->pdo->query('SELECT * FROM share_targets WHERE enabled = 1 ORDER BY sort, id')->fetchAll();
    }

    /** @return array<int,array> */
    public function all(): array
    {
        return $this->pdo->query('SELECT * FROM share_targets ORDER BY sort, id')->fetchAll();
    }

    public function getExact(string $key): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM share_targets WHERE `key` = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function update(string $key, string $label, string $urlTemplate, bool $enabled): void
    {
        $this->pdo->prepare(
            'INSERT INTO share_targets (`key`, label, icon, url_template, sort, enabled)
             VALUES (?, ?, ?, ?, 0, ?)
             ON DUPLICATE KEY UPDATE label = VALUES(label), url_template = VALUES(url_template), enabled = VALUES(enabled)'
        )->execute([$key, $label, $key, $urlTemplate, $enabled ? 1 : 0]);
    }

    public function setEnabled(string $key, bool $enabled): void
    {
        $this->pdo->prepare('UPDATE share_targets SET enabled = ? WHERE `key` = ?')->execute([$enabled ? 1 : 0, $key]);
    }

    public function render(string $template, string $url): string
    {
        $enc = rawurlencode($url);
        return str_replace(['{url}', '{text}'], [$enc, $enc], $template);
    }

    public static function isAllowed(string $urlTemplate): bool
    {
        $scheme = strtolower((string) parse_url($urlTemplate, PHP_URL_SCHEME));
        return in_array($scheme, self::ALLOWED_SCHEMES, true);
    }
}
