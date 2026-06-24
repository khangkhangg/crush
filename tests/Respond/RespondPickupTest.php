<?php
declare(strict_types=1);

namespace Tests\Respond;

use App\Auth\MagicLink;
use App\Auth\UserRepo;
use App\Core\ArrayStore;
use App\Core\Csrf;
use App\Core\View;
use App\Ics\IcsBuilder;
use App\Invite\InviteRepo;
use App\Invite\ResponseRepo;
use App\Mail\Postman;
use App\Maps\LinkResolver;
use App\Respond\CrushOnboarder;
use App\Respond\RespondController;
use App\Theme\AbEventRepo;
use App\Theme\ABAssigner;
use App\Theme\ThemeRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FakeFetcher;
use Tests\Support\FrozenClock;
use Tests\Support\SpyMailer;

final class RespondPickupTest extends DatabaseTestCase
{
    private FrozenClock $clock;
    private Csrf $csrf;

    private function controller(): RespondController
    {
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $this->csrf = new Csrf(new ArrayStore());
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $invites = new InviteRepo($this->pdo(), $this->clock);
        $users = new UserRepo($this->pdo(), $this->clock);
        $postman = new Postman(new SpyMailer(), new IcsBuilder($this->clock), $view, 'http://localhost');
        $fetcher = new FakeFetcher([
            'https://maps.app.goo.gl/cafe' => [
                'finalUrl' => 'https://www.google.com/maps/place/Tartine+Bakery/@37.7,-122.4,17z',
                'body'     => '<meta property="og:title" content="Tartine Bakery">',
            ],
        ]);
        return new RespondController(
            $view, $this->csrf, $invites,
            new ResponseRepo($this->pdo(), $this->clock),
            $users,
            new ABAssigner(new ThemeRepo($this->pdo()), $invites, fn(int $m) => 0),
            new AbEventRepo($this->pdo(), $this->clock),
            $this->clock,
            new LinkResolver($fetcher),
            $postman,
            new CrushOnboarder($users, new MagicLink($this->pdo(), $users, $this->clock, 900), $postman, 'http://localhost')
        );
    }

    private function makeInvite(): array
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $sender = (new UserRepo($this->pdo(), $clock))->create('sue@x.test', 'Sue', 'magic')['id'];
        return (new InviteRepo($this->pdo(), $clock))->create([
            'sender_id' => $sender, 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => false, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'expires_at' => '2026-02-01 00:00:00',
        ]);
    }

    public function test_maps_link_is_resolved_and_stored(): void
    {
        $ctrl = $this->controller();
        $invite = $this->makeInvite();
        $ctrl->submit($invite['public_token'], [
            'chosen_start' => '2026-02-10T19:00', 'meal_choice' => 'dinner',
            'pickup_raw' => 'https://maps.app.goo.gl/cafe',
        ], $this->csrf->token());

        $stored = (new ResponseRepo($this->pdo(), $this->clock))->findByInvite((int) $invite['id']);
        $this->assertSame('https://maps.app.goo.gl/cafe', $stored['pickup_raw']);
        $this->assertSame('Tartine Bakery', $stored['pickup_name']);
        $this->assertStringContainsString('google.com/maps/search/', $stored['pickup_clean_url']);
    }

    public function test_plain_address_is_stored_as_address(): void
    {
        $ctrl = $this->controller();
        $invite = $this->makeInvite();
        $ctrl->submit($invite['public_token'], [
            'chosen_start' => '2026-02-10T19:00', 'meal_choice' => 'coffee',
            'pickup_raw' => '742 Evergreen Terrace',
        ], $this->csrf->token());

        $stored = (new ResponseRepo($this->pdo(), $this->clock))->findByInvite((int) $invite['id']);
        $this->assertSame('742 Evergreen Terrace', $stored['pickup_address']);
        $this->assertNull($stored['pickup_name']);
    }
}
