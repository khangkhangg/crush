<?php
declare(strict_types=1);

namespace Tests\Security;

use App\Security\RateLimiter;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class RateLimiterTest extends DatabaseTestCase
{
    public function test_allows_up_to_limit_then_blocks(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $rl = new RateLimiter($this->pdo(), $clock);

        $this->assertTrue($rl->hit('test', 'a', 3, 3600));
        $this->assertTrue($rl->hit('test', 'a', 3, 3600));
        $this->assertTrue($rl->hit('test', 'a', 3, 3600));
        $this->assertFalse($rl->hit('test', 'a', 3, 3600));
    }

    public function test_separate_identifiers_are_independent(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $rl = new RateLimiter($this->pdo(), $clock);
        $this->assertTrue($rl->hit('test', 'a', 1, 3600));
        $this->assertFalse($rl->hit('test', 'a', 1, 3600));
        $this->assertTrue($rl->hit('test', 'b', 1, 3600));
    }

    public function test_window_resets_after_expiry(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $rl = new RateLimiter($this->pdo(), $clock);
        $this->assertTrue($rl->hit('test', 'a', 1, 60));
        $this->assertFalse($rl->hit('test', 'a', 1, 60));
        $clock->advance(61);
        $this->assertTrue($rl->hit('test', 'a', 1, 60));
    }
}
