<?php
declare(strict_types=1);

namespace Tests\Auth;

use App\Auth\MagicLink;
use App\Auth\UserRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class MagicLinkTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    private function service(): MagicLink
    {
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        return new MagicLink($this->pdo(), new UserRepo($this->pdo(), $this->clock), $this->clock, 900);
    }

    public function test_start_creates_user_and_complete_logs_in(): void
    {
        $svc = $this->service();
        $token = $svc->start('new@x.test');
        $this->assertNotEmpty($token);

        $user = $svc->complete($token);
        $this->assertNotNull($user);
        $this->assertSame('new@x.test', $user['email']);
    }

    public function test_wrong_token_returns_null(): void
    {
        $svc = $this->service();
        $svc->start('a@x.test');
        $this->assertNull($svc->complete('not-a-real-token'));
    }

    public function test_token_is_single_use(): void
    {
        $svc = $this->service();
        $token = $svc->start('a@x.test');
        $this->assertNotNull($svc->complete($token));
        $this->assertNull($svc->complete($token));
    }

    public function test_expired_token_returns_null(): void
    {
        $svc = $this->service();
        $token = $svc->start('a@x.test');
        $this->clock->advance(901);
        $this->assertNull($svc->complete($token));
    }
}
