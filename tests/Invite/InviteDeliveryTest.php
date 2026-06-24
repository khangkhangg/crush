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

final class InviteDeliveryTest extends DatabaseTestCase
{
    private FrozenClock $clock;
    private SpyMailer $spy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $this->spy = new SpyMailer();
    }

    private function controller(Csrf $csrf): InviteController
    {
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        return new InviteController(
            $view, $csrf, new InviteRepo($this->pdo(), $this->clock), new UserRepo($this->pdo(), $this->clock),
            $this->clock, 'http://localhost',
            new Postman($this->spy, new IcsBuilder($this->clock), new EmailTemplateRepo($this->pdo()), 'http://localhost'),
            new RateLimiter($this->pdo(), $this->clock), new BlockRepo($this->pdo(), $this->clock),
            new InvitePlaceRepo($this->pdo()), new LinkResolver(new FakeFetcher([])),
            new ShareTargetRepo($this->pdo())
        );
    }

    private function uid(string $email = 'u@x.test'): int
    {
        return (new UserRepo($this->pdo(), $this->clock))->create($email, 'U', 'magic')['id'];
    }

    public function test_form_has_delivery_toggle(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $body = $this->controller($csrf)->showNew($this->uid())->body();
        $this->assertStringContainsString('name="delivery"', $body);
        $this->assertStringContainsString('value="link"', $body);
    }

    public function test_email_mode_requires_email_and_sends(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $uid = $this->uid();
        // missing email -> 422
        $bad = $this->controller($csrf)->create($uid, ['delivery' => 'email', 'date_mode' => 'instant'], $csrf->token());
        $this->assertSame(422, $bad->status());
        // valid email -> 302 + email sent
        $ok = $this->controller($csrf)->create($uid, ['delivery' => 'email', 'crush_email' => 'c@x.test', 'date_mode' => 'instant'], $csrf->token());
        $this->assertSame(302, $ok->status());
        $this->assertCount(1, $this->spy->sent);
        $this->assertSame('c@x.test', $this->spy->sent[0]->to);
    }

    public function test_link_mode_allows_blank_email_and_sends_nothing(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $uid = $this->uid('u2@x.test');
        $res = $this->controller($csrf)->create($uid, ['delivery' => 'link', 'date_mode' => 'instant'], $csrf->token());
        $this->assertSame(302, $res->status());
        $this->assertCount(0, $this->spy->sent);                                  // no email
        $invite = (new InviteRepo($this->pdo(), $this->clock))->listBySender($uid)[0];
        $this->assertNull($invite['crush_email']);                                // stored null
    }

    public function test_link_mode_rejects_malformed_email(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf)->create($this->uid('u3@x.test'), ['delivery' => 'link', 'crush_email' => 'nope', 'date_mode' => 'instant'], $csrf->token());
        $this->assertSame(422, $res->status());
    }
}
