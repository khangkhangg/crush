<?php
declare(strict_types=1);

namespace Tests\Invite;

use App\Auth\UserRepo;
use App\Core\ArrayStore;
use App\Core\Csrf;
use App\Core\View;
use App\Ics\IcsBuilder;
use App\Invite\InviteController;
use App\Mail\EmailTemplateRepo;
use App\Invite\InvitePlaceRepo;
use App\Invite\InviteRepo;
use App\Mail\Postman;
use App\Maps\LinkResolver;
use App\Security\BlockRepo;
use App\Security\RateLimiter;
use App\Share\ShareTargetRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FakeFetcher;
use Tests\Support\FrozenClock;
use Tests\Support\SpyMailer;

final class InvitePlacesCreateTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    public function test_create_stores_resolved_places_per_vibe(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $invites = new InviteRepo($this->pdo(), $this->clock);
        $users = new UserRepo($this->pdo(), $this->clock);
        $postman = new Postman(new SpyMailer(), new IcsBuilder($this->clock), new EmailTemplateRepo($this->pdo()), 'http://localhost');
        $placeRepo = new InvitePlaceRepo($this->pdo());
        $fetcher = new FakeFetcher([
            'https://maps.app.goo.gl/dinner' => [
                'finalUrl' => 'https://www.google.com/maps/place/Tartine+Bakery/@1,2,17z',
                'body' => '<meta property="og:title" content="Tartine Bakery">',
            ],
        ]);
        $ctrl = new InviteController(
            $view, $csrf, $invites, $users, $this->clock, 'http://localhost', $postman,
            new RateLimiter($this->pdo(), $this->clock), new BlockRepo($this->pdo(), $this->clock),
            $placeRepo, new LinkResolver($fetcher),
            new ShareTargetRepo($this->pdo())
        );

        $sender = $users->create('sue@x.test', 'Sue', 'magic')['id'];
        $ctrl->create($sender, [
            'crush_email' => 'c@x.test', 'date_mode' => 'instant',
            'place_mode' => 'focused', 'focus_vibe' => 'dinner',
            'opts' => [
                ['name' => 'Tartine', 'url' => 'https://maps.app.goo.gl/dinner', 'cuisine' => ''],
                ['name' => '', 'url' => ''], // empty -> skipped
            ],
        ], $csrf->token());

        $invite = $invites->listBySender($sender)[0];
        $grouped = $placeRepo->groupedForInvite((int) $invite['id']);
        $this->assertArrayHasKey('dinner', $grouped);
        $this->assertCount(1, $grouped['dinner']); // only non-empty row
        $this->assertSame('Tartine Bakery', $grouped['dinner'][0]['place_resolved_name']); // resolved
    }
}
