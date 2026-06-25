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

final class CuisineChipsTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    private function controller(Csrf $csrf): InviteController
    {
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        return new InviteController(
            $view, $csrf, new InviteRepo($this->pdo(), $this->clock), new UserRepo($this->pdo(), $this->clock),
            $this->clock, 'http://localhost',
            new Postman(new SpyMailer(), new IcsBuilder($this->clock), new EmailTemplateRepo($this->pdo()), 'http://localhost'),
            new RateLimiter($this->pdo(), $this->clock), new BlockRepo($this->pdo(), $this->clock),
            new InvitePlaceRepo($this->pdo()), new LinkResolver(new FakeFetcher([])), new ShareTargetRepo($this->pdo())
        );
    }

    private function uid(string $e = 'u@x.test'): int
    {
        return (new UserRepo($this->pdo(), $this->clock))->create($e, 'U', 'magic')['id'];
    }

    public function test_form_has_cuisine_chips_and_other(): void
    {
        $body = $this->controller(new Csrf(new ArrayStore()))->showNew($this->uid())->body();
        $this->assertStringContainsString('class="chips"', $body);
        $this->assertStringContainsString('value="Italian"', $body);
        $this->assertStringContainsString('value="__other__"', $body);
        $this->assertStringContainsString('opts[0][cuisine_custom]', $body);
    }

    public function test_chip_cuisine_is_stored(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $uid = $this->uid('a@x.test');
        $this->controller($csrf)->create($uid, [
            'crush_email' => 'c@x.test', 'date_mode' => 'instant', 'place_mode' => 'focused', 'focus_vibe' => 'dinner',
            'opts' => [['name' => 'Tartine', 'cuisine' => 'Italian', 'url' => '']],
        ], $csrf->token());
        $inv = (new InviteRepo($this->pdo(), $this->clock))->listBySender($uid)[0];
        $this->assertSame('Italian', (new InvitePlaceRepo($this->pdo()))->groupedForInvite((int) $inv['id'])['dinner'][0]['cuisine']);
    }

    public function test_other_cuisine_uses_custom_text(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $uid = $this->uid('b@x.test');
        $this->controller($csrf)->create($uid, [
            'crush_email' => 'c@x.test', 'date_mode' => 'instant', 'place_mode' => 'focused', 'focus_vibe' => 'dinner',
            'opts' => [['name' => 'Pho House', 'cuisine' => '__other__', 'cuisine_custom' => 'Fusion', 'url' => '']],
        ], $csrf->token());
        $inv = (new InviteRepo($this->pdo(), $this->clock))->listBySender($uid)[0];
        $this->assertSame('Fusion', (new InvitePlaceRepo($this->pdo()))->groupedForInvite((int) $inv['id'])['dinner'][0]['cuisine']);
    }
}
