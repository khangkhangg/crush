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

final class ShareScreenTest extends DatabaseTestCase
{
    public function test_share_screen_lists_targets_with_invite_link(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $csrf = new Csrf(new ArrayStore());
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $invites = new InviteRepo($this->pdo(), $clock);
        $users = new UserRepo($this->pdo(), $clock);
        $ctrl = new InviteController(
            $view, $csrf, $invites, $users, $clock, 'https://crush.app',
            new Postman(new SpyMailer(), new IcsBuilder($clock), new EmailTemplateRepo($this->pdo()), 'https://crush.app'),
            new RateLimiter($this->pdo(), $clock), new BlockRepo($this->pdo(), $clock),
            new InvitePlaceRepo($this->pdo()), new LinkResolver(new FakeFetcher([])),
            new ShareTargetRepo($this->pdo())
        );
        $uid = $users->create('u@x.test', 'U', 'magic')['id'];
        $invite = $invites->create([
            'sender_id' => $uid, 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => false, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'expires_at' => '2026-12-01 00:00:00',
        ]);

        $res = $ctrl->showCreated($uid, $invite['public_token']);
        $this->assertSame(200, $res->status());
        $body = $res->body();
        $encoded = rawurlencode('https://crush.app/i/' . $invite['public_token']);
        $this->assertStringContainsString('wa.me', $body);                  // a target rendered
        $this->assertStringContainsString($encoded, $body);                 // with the encoded link
        $this->assertStringContainsString('navigator.share', $body);        // native share hook
    }
}
