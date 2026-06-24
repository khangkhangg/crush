<?php
declare(strict_types=1);

namespace Tests\Core;

use Tests\Support\DatabaseTestCase;

final class LangSchemaTest extends DatabaseTestCase
{
    public function test_lang_columns_exist(): void
    {
        $cols = fn(string $t) => array_column($this->pdo()->query("SHOW COLUMNS FROM `{$t}`")->fetchAll(), 'Field');
        $this->assertContains('lang', $cols('users'));
        $this->assertContains('lang', $cols('invites'));
    }
}
