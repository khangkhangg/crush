<?php
declare(strict_types=1);

namespace Tests\Auth;

use Tests\Support\DatabaseTestCase;

final class DatabaseHarnessTest extends DatabaseTestCase
{
    public function test_migrations_applied_to_mysql(): void
    {
        $tables = $this->pdo()->query(
            "SELECT table_name AS t FROM information_schema.tables WHERE table_schema = DATABASE()"
        )->fetchAll();
        $names = array_column($tables, 't');
        $this->assertContains('settings', $names);
        $this->assertContains('schema_migrations', $names);
    }
}
