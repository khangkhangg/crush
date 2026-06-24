<?php
declare(strict_types=1);

namespace Tests\Invite;

use App\Auth\UserRepo;
use App\Core\ArrayStore;
use App\Core\Csrf;
use App\Core\View;
use App\Ics\IcsBuilder;
use App\Invite\InviteController;
use App\Invite\InviteRepo;
use App\Mail\Postman;
use App\Security\RateLimiter;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;
use Tests\Support\SpyMailer;

final class InviteRateLimitTest extends DatabaseTestCase
{
    public function test_per_email_limit_blocks_fourth_invite(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $csrf  = new Csrf(new ArrayStore());
        $view  = new View(\dirname(__DIR__, 2) . '/templates');
        $invites = new InviteRepo($this->pdo(), $clock);
        $users   = new UserRepo($this->pdo(), $clock);
        $postman = new Postman(new SpyMailer(), new IcsBuilder($clock), $view, 'http://localhost');
        $limiter = new RateLimiter($this->pdo(), $clock);
        $ctrl = new InviteController($view, $csrf, $invites, $users, $clock, 'http://localhost', $postman, $limiter);

        $sender = $users->create('s@x.test', 'Sue', 'magic')['id'];
        $data = ['crush_email' => 'crush@x.test', 'date_mode' => 'instant'];

        // per-email limit is 3/day; the 4th to the same email is blocked
        for ($i = 0; $i < 3; $i++) {
            $res = $ctrl->create($sender, $data, $csrf->token());
            $this->assertSame(302, $res->status());
        }
        $res = $ctrl->create($sender, $data, $csrf->token());
        $this->assertSame(429, $res->status());
    }
}
