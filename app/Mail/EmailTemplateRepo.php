<?php
declare(strict_types=1);

namespace App\Mail;

final class EmailTemplateRepo
{
    public function __construct(private \PDO $pdo) {}

    public function get(string $key, string $lang): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM email_templates WHERE `key` = ? AND lang = ?');
        $stmt->execute([$key, $lang]);
        $row = $stmt->fetch();
        if ($row !== false) {
            return $row;
        }
        if ($lang !== 'en') {
            $stmt->execute([$key, 'en']);
            $row = $stmt->fetch();
            if ($row !== false) {
                return $row;
            }
        }
        return null;
    }

    /** @return array{subject:string,html:string} */
    public function render(string $key, string $lang, array $vars): array
    {
        $tpl = $this->get($key, $lang);
        if ($tpl === null) {
            throw new \RuntimeException("Email template not found: {$key}");
        }
        $subject = (string) $tpl['subject'];
        $html = (string) $tpl['body_html'];
        foreach ($vars as $name => $value) {
            $value = (string) $value;
            $html = str_replace('{{' . $name . '}}', htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $html);
            $subject = str_replace('{{' . $name . '}}', str_replace(["\r", "\n"], ' ', $value), $subject);
        }
        return ['subject' => $subject, 'html' => $html];
    }

    public function getExact(string $key, string $lang): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM email_templates WHERE `key` = ? AND lang = ?');
        $stmt->execute([$key, $lang]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function update(string $key, string $lang, string $subject, string $bodyHtml): void
    {
        $this->pdo->prepare(
            'INSERT INTO email_templates (`key`, lang, subject, body_html) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE subject = VALUES(subject), body_html = VALUES(body_html)'
        )->execute([$key, $lang, $subject, $bodyHtml]);
    }

    /** @return array<int,array> */
    public function all(): array
    {
        return $this->pdo->query('SELECT * FROM email_templates ORDER BY `key`, lang')->fetchAll();
    }
}
