<?php
declare(strict_types=1);

namespace Tests\Theme;

use Tests\Support\DatabaseTestCase;

final class ThemeSchemaTest extends DatabaseTestCase
{
    public function test_themes_seeded_and_ab_events_exist(): void
    {
        $keys = array_column($this->pdo()->query('SELECT `key` FROM themes ORDER BY `key`')->fetchAll(), 'key');
        $this->assertSame(['bubblegum', 'love-letter', 'midnight'], $keys);

        $active = (int) $this->pdo()->query('SELECT COUNT(*) AS c FROM themes WHERE is_active = 1')->fetch()['c'];
        $this->assertSame(3, $active);

        $cols = array_column($this->pdo()->query('SHOW COLUMNS FROM ab_events')->fetchAll(), 'Field');
        foreach (['id', 'invite_id', 'theme_key', 'event', 'created_at'] as $c) {
            $this->assertContains($c, $cols);
        }
    }
}
