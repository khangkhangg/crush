<?php
declare(strict_types=1);

namespace Tests\Invite;

use App\Auth\UserRepo;
use App\Core\ArrayStore;
use App\Core\Csrf;
use App\Core\View;
use App\Ics\IcsBuilder;
use App\Invite\InviteController;
use App\Invite\InvitePlaceRepo;
use App\Invite\InviteRepo;
use App\Mail\EmailTemplateRepo;
use App\Mail\Postman;
use App\Maps\LinkResolver;
use App\Security\BlockRepo;
use App\Security\RateLimiter;
use App\Share\ShareTargetRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FakeFetcher;
use Tests\Support\FrozenClock;
use Tests\Support\SpyMailer;

final class InviteBannerTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    private function controller(): InviteController
    {
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $invites = new InviteRepo($this->pdo(), $this->clock);
        $users = new UserRepo($this->pdo(), $this->clock);
        $postman = new Postman(new SpyMailer(), new IcsBuilder($this->clock), new EmailTemplateRepo($this->pdo()), 'http://localhost');
        return new InviteController(
            $view, new Csrf(new ArrayStore()), $invites, $users, $this->clock, 'http://localhost', $postman,
            new RateLimiter($this->pdo(), $this->clock), new BlockRepo($this->pdo(), $this->clock),
            new InvitePlaceRepo($this->pdo()), new LinkResolver(new FakeFetcher([])),
            new ShareTargetRepo($this->pdo())
        );
    }

    public function test_banner_shows_current_user_name(): void
    {
        $repo = new UserRepo($this->pdo(), $this->clock);
        $u = $repo->create('khang@x.test', 'Khang', 'magic');
        $repo->saveProfile($u['id'], 'fox', null, 'hi', null);

        $res = $this->controller()->showNew($u['id']);
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('Khang', $res->body());     // identity shown
        $this->assertStringContainsString('/switch', $res->body());   // "use a different email"
    }
}
