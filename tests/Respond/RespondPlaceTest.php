<?php
declare(strict_types=1);

namespace Tests\Respond;

use App\Auth\MagicLink;
use App\Auth\UserRepo;
use App\Core\ArrayStore;
use App\Core\Csrf;
use App\Core\View;
use App\Ics\IcsBuilder;
use App\Invite\InvitePlaceRepo;
use App\Mail\EmailTemplateRepo;
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

final class RespondPlaceTest extends DatabaseTestCase
{
    private FrozenClock $clock;
    private Csrf $csrf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $this->csrf = new Csrf(new ArrayStore());
    }

    private function controller(InvitePlaceRepo $places): RespondController
    {
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $invites = new InviteRepo($this->pdo(), $this->clock);
        $users = new UserRepo($this->pdo(), $this->clock);
        $postman = new Postman(new SpyMailer(), new IcsBuilder($this->clock), new EmailTemplateRepo($this->pdo()), 'http://localhost');
        $onboarder = new CrushOnboarder($users, new MagicLink($this->pdo(), $users, $this->clock, 900), $postman, 'http://localhost');
        return new RespondController(
            $view, $this->csrf, $invites, new ResponseRepo($this->pdo(), $this->clock), $users,
            new ABAssigner(new ThemeRepo($this->pdo()), $invites, fn(int $m) => 0),
            new AbEventRepo($this->pdo(), $this->clock), $this->clock,
            new LinkResolver(new FakeFetcher([])), $postman, $onboarder, $places
        );
    }

    public function test_chosen_vibe_place_copied_into_response_when_no_typed_pickup(): void
    {
        $places = new InvitePlaceRepo($this->pdo());
        $ctrl = $this->controller($places);
        $invites = new InviteRepo($this->pdo(), $this->clock);
        $sender = (new UserRepo($this->pdo(), $this->clock))->create('sue@x.test', 'Sue', 'magic')['id'];
        $invite = $invites->create([
            'sender_id' => $sender, 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => false, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'expires_at' => '2026-02-01 00:00:00',
        ]);
        $places->add((int) $invite['id'], 'dinner', 'Tartine', null, 'Tartine Bakery', '1 Main St', 'https://maps.google.com/?q=Tartine');

        $ctrl->submit($invite['public_token'], [
            'chosen_start' => '2026-02-10T19:00', 'meal_choice' => 'dinner', // no pickup_raw
        ], $this->csrf->token());

        $stored = (new ResponseRepo($this->pdo(), $this->clock))->findByInvite((int) $invite['id']);
        $this->assertSame('Tartine Bakery', $stored['pickup_name']);
        $this->assertStringContainsString('maps.google.com', $stored['pickup_clean_url']);
    }

    public function test_typed_pickup_takes_precedence_over_vibe_place(): void
    {
        $places = new InvitePlaceRepo($this->pdo());
        $ctrl = $this->controller($places);
        $invites = new InviteRepo($this->pdo(), $this->clock);
        $sender = (new UserRepo($this->pdo(), $this->clock))->create('sue@x.test', 'Sue', 'magic')['id'];
        $invite = $invites->create([
            'sender_id' => $sender, 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => false, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'expires_at' => '2026-02-01 00:00:00',
        ]);
        $places->add((int) $invite['id'], 'dinner', 'Tartine', null, 'Tartine Bakery', '1 Main St', null);

        $ctrl->submit($invite['public_token'], [
            'chosen_start' => '2026-02-10T19:00', 'meal_choice' => 'dinner',
            'pickup_raw' => '742 Evergreen Terrace',
        ], $this->csrf->token());

        $stored = (new ResponseRepo($this->pdo(), $this->clock))->findByInvite((int) $invite['id']);
        $this->assertSame('742 Evergreen Terrace', $stored['pickup_raw']);
        $this->assertSame('742 Evergreen Terrace', $stored['pickup_address']); // typed address wins, not Tartine
    }
}
