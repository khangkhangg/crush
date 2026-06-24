<?php
declare(strict_types=1);

namespace Tests\Security;

use Tests\Support\DatabaseTestCase;

final class SecuritySchemaTest extends DatabaseTestCase
{
    public function test_tables_and_columns(): void
    {
        $cols = fn(string $t) => array_column($this->pdo()->query("SHOW COLUMNS FROM `{$t}`")->fetchAll(), 'Field');
        foreach (['id', 'scope', 'identifier', 'window_start', 'count'] as $c) {
            $this->assertContains($c, $cols('rate_limits'), "rate_limits.$c");
        }
        foreach (['id', 'sender_id', 'crush_email', 'reason', 'created_at'] as $c) {
            $this->assertContains($c, $cols('blocks'), "blocks.$c");
        }
    }
}
