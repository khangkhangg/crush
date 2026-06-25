<?php
declare(strict_types=1);

namespace App\I18n;

final class Translator
{
    /** @var array<string,string>|null */
    private ?array $map = null;

    public function __construct(private \PDO $pdo, private string $lang) {}

    public function lang(): string
    {
        return $this->lang;
    }

    public function t(string $english): string
    {
        if ($this->lang === 'en') {
            return $english;
        }
        if ($this->map === null) {
            $this->map = $this->all($this->lang);
        }
        return $this->map[$english] ?? $english;
    }

    /** @return array<string,string> */
    public function all(string $lang): array
    {
        $stmt = $this->pdo->prepare('SELECT `key`, value FROM ui_translations WHERE lang = ?');
        $stmt->execute([$lang]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(string) $row['key']] = (string) $row['value'];
        }
        return $out;
    }
}
