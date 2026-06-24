<?php
declare(strict_types=1);

namespace Tests\Theme;

use App\Theme\AbEventRepo;
use App\Theme\ThemeRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class ThemeRepoTest extends DatabaseTestCase
{
    public function test_list_active_returns_three_seeded_themes(): void
    {
        $repo = new ThemeRepo($this->pdo());
        $active = $repo->listActive();
        $this->assertCount(3, $active);
        $this->assertSame('bubblegum', $active[0]['key']);
        $this->assertSame(1, $active[0]['weight']);
        $this->assertTrue($repo->exists('midnight'));
        $this->assertFalse($repo->exists('nope'));
    }

    public function test_ab_event_log_and_count(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $events = new AbEventRepo($this->pdo(), $clock);
        $events->log(1, 'midnight', 'opened');
        $events->log(2, 'midnight', 'opened');
        $events->log(3, 'midnight', 'completed');

        $this->assertSame(2, $events->count('midnight', 'opened'));
        $this->assertSame(1, $events->count('midnight', 'completed'));
        $this->assertSame(0, $events->count('bubblegum', 'opened'));
    }
}
