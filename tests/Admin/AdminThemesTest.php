<?php
declare(strict_types=1);

namespace Tests\Admin;

use App\Theme\AbEventRepo;
use App\Theme\ThemeRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class AdminThemesTest extends DatabaseTestCase
{
    public function test_theme_repo_admin_methods(): void
    {
        $repo = new ThemeRepo($this->pdo());
        $this->assertCount(3, $repo->all());

        $repo->setWeight('midnight', 5);
        $repo->setActive('love-letter', false);

        $byKey = [];
        foreach ($repo->all() as $t) { $byKey[$t['key']] = $t; }
        $this->assertSame(5, $byKey['midnight']['weight']);
        $this->assertSame(0, $byKey['love-letter']['is_active']);
        // listActive now excludes the deactivated theme
        $this->assertCount(2, $repo->listActive());
    }

    public function test_funnel_counts(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $events = new AbEventRepo($this->pdo(), $clock);
        $events->log(1, 'midnight', 'opened');
        $events->log(2, 'midnight', 'opened');
        $events->log(1, 'midnight', 'completed');
        $this->assertSame(2, $events->count('midnight', 'opened'));
        $this->assertSame(1, $events->count('midnight', 'completed'));
    }
}
