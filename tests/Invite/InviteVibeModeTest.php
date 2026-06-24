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

final class InviteVibeModeTest extends DatabaseTestCase
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

    private function uid(): int
    {
        return (new UserRepo($this->pdo(), $this->clock))->create('u@x.test', 'U', 'magic')['id'];
    }

    public function test_form_has_mode_toggle_and_repeater(): void
    {
        $body = $this->controller(new Csrf(new ArrayStore()))->showNew($this->uid())->body();
        $this->assertStringContainsString('name="place_mode"', $body);
        $this->assertStringContainsString('name="focus_vibe"', $body);
        $this->assertStringContainsString('id="addPlace"', $body);          // repeater control
    }

    public function test_focused_mode_saves_multiple_options(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $uid = $this->uid();
        $this->controller($csrf)->create($uid, [
            'crush_email' => 'c@x.test', 'date_mode' => 'instant',
            'place_mode' => 'focused', 'focus_vibe' => 'dinner',
            'opts' => [['name' => 'Tartine', 'cuisine' => 'Italian', 'url' => ''], ['name' => 'Octo', 'cuisine' => 'Tapas', 'url' => '']],
        ], $csrf->token());

        $inv = (new InviteRepo($this->pdo(), $this->clock))->listBySender($uid)[0];
        $grouped = (new InvitePlaceRepo($this->pdo()))->groupedForInvite((int) $inv['id']);
        $this->assertCount(2, $grouped['dinner']);
    }

    public function test_open_mode_saves_no_places(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $uid = $this->uid();
        $this->controller($csrf)->create($uid, [
            'crush_email' => 'c@x.test', 'date_mode' => 'instant', 'place_mode' => 'open',
        ], $csrf->token());
        $inv = (new InviteRepo($this->pdo(), $this->clock))->listBySender($uid)[0];
        $this->assertSame([], (new InvitePlaceRepo($this->pdo()))->groupedForInvite((int) $inv['id']));
    }
}
