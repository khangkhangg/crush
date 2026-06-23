<?php
declare(strict_types=1);

namespace Tests\Core;

use App\Core\DB;
use App\Core\MigrationRunner;
use PHPUnit\Framework\TestCase;

final class MigrationRunnerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/crush_mig_' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        file_put_contents($this->dir . '/0001_init.sql',
            "CREATE TABLE widgets (id INTEGER PRIMARY KEY, name TEXT NOT NULL);");
        file_put_contents($this->dir . '/0002_more.sql',
            "INSERT INTO widgets (name) VALUES ('a');");
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*'));
        rmdir($this->dir);
    }

    public function test_applies_all_migrations_then_is_idempotent(): void
    {
        $pdo = DB::sqliteMemory();
        $runner = new MigrationRunner($pdo, $this->dir);

        $applied = $runner->run();
        $this->assertSame(['0001_init.sql', '0002_more.sql'], $applied);

        $count = (int) $pdo->query('SELECT COUNT(*) AS c FROM widgets')->fetch()['c'];
        $this->assertSame(1, $count);

        // Second run applies nothing.
        $this->assertSame([], $runner->run());
    }
}
