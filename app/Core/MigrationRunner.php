<?php
declare(strict_types=1);

namespace App\Core;

final class MigrationRunner
{
    public function __construct(private \PDO $pdo, private string $migrationsDir) {}

    /** @return string[] filenames applied this run */
    public function run(): array
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (filename TEXT PRIMARY KEY, applied_at TEXT NOT NULL)'
        );

        $done = [];
        foreach ($this->pdo->query('SELECT filename FROM schema_migrations') as $row) {
            $done[$row['filename']] = true;
        }

        $files = glob($this->migrationsDir . '/*.sql') ?: [];
        sort($files);

        $applied = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (isset($done[$name])) {
                continue;
            }
            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new \RuntimeException("Cannot read migration: {$name}");
            }
            $this->pdo->exec($sql);
            $stmt = $this->pdo->prepare(
                'INSERT INTO schema_migrations (filename, applied_at) VALUES (?, ?)'
            );
            $stmt->execute([$name, gmdate('c')]);
            $applied[] = $name;
        }
        return $applied;
    }
}
