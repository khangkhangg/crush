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

final class InviteCuisineCreateTest extends DatabaseTestCase
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
        $places = new InvitePlaceRepo($this->pdo());
        return new InviteController(
            $view, $csrf, new InviteRepo($this->pdo(), $this->clock), new UserRepo($this->pdo(), $this->clock),
            $this->clock, 'http://localhost',
            new Postman(new SpyMailer(), new IcsBuilder($this->clock), new EmailTemplateRepo($this->pdo()), 'http://localhost'),
            new RateLimiter($this->pdo(), $this->clock), new BlockRepo($this->pdo(), $this->clock),
            $places, new LinkResolver(new FakeFetcher([])),
            new ShareTargetRepo($this->pdo())
        );
    }

    public function test_form_has_cuisine_input_and_wide_card(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $uid = (new UserRepo($this->pdo(), $this->clock))->create('u@x.test', 'U', 'magic')['id'];
        $res = $this->controller($csrf)->showNew($uid);
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('card--wide', $res->body());
        $this->assertStringContainsString('places[dinner][cuisine]', $res->body());
        $this->assertStringContainsString('<datalist id="cuisines"', $res->body());
    }

    public function test_create_stores_cuisine(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $places = new InvitePlaceRepo($this->pdo());
        $uid = (new UserRepo($this->pdo(), $this->clock))->create('u2@x.test', 'U', 'magic')['id'];
        $res = $this->controller($csrf)->create($uid, [
            'crush_email' => 'c@x.test', 'date_mode' => 'instant',
            'places' => ['dinner' => ['name' => 'Tartine', 'cuisine' => 'Italian', 'url' => '']],
        ], $csrf->token());
        $this->assertSame(302, $res->status());

        $invite = (new InviteRepo($this->pdo(), $this->clock))->listBySender($uid)[0];
        $this->assertSame('Italian', $places->forMeal((int) $invite['id'], 'dinner')['cuisine']);
    }
}
