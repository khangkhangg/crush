<?php
declare(strict_types=1);

namespace Tests\Respond;

use App\Auth\UserRepo;
use App\Invite\InvitePlaceRepo;
use App\Invite\InviteRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;
use Tests\Support\RespondControllerFactory;

final class FocusedOptionsTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    public function test_focused_shows_place_option_radios(): void
    {
        $sender = (new UserRepo($this->pdo(), $this->clock))->create('s@x.test', 'Sue', 'magic')['id'];
        $invite = (new InviteRepo($this->pdo(), $this->clock))->create([
            'sender_id' => $sender, 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => false, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'theme_key' => 'bubblegum', 'expires_at' => '2026-12-01 00:00:00',
        ]);
        $places = new InvitePlaceRepo($this->pdo());
        $places->addOption((int) $invite['id'], 'dinner', 'Tartine', null, null, null, null, 'Italian', 0);
        $places->addOption((int) $invite['id'], 'dinner', 'Octo', null, null, null, null, 'Tapas', 1);

        $body = RespondControllerFactory::make($this->pdo(), $this->clock)->open($invite['public_token'])->body();
        $this->assertStringContainsString('name="chosen_place"', $body);   // place-option radios
        $this->assertStringContainsString('Tartine', $body);
        $this->assertStringContainsString('Octo', $body);
        $this->assertStringContainsString('type="hidden" name="meal_choice" value="dinner"', $body);
    }
}
