<?php
declare(strict_types=1);

namespace Tests\Respond;

use App\Auth\UserRepo;
use App\Invite\InvitePlaceRepo;
use App\Invite\InviteRepo;
use App\Respond\RespondController;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;
use Tests\Support\RespondControllerFactory;

final class CuratedVibesTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    private function makeInvite(array $placedMeals): array
    {
        $sender = (new UserRepo($this->pdo(), $this->clock))->create('s@x.test', 'Sue', 'magic')['id'];
        $invite = (new InviteRepo($this->pdo(), $this->clock))->create([
            'sender_id' => $sender, 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => false, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'theme_key' => 'bubblegum', 'expires_at' => '2026-12-01 00:00:00',
        ]);
        $places = new InvitePlaceRepo($this->pdo());
        foreach ($placedMeals as $key => $cuisine) {
            $places->add((int) $invite['id'], $key, ucfirst($key) . ' Spot', null, null, null, null, $cuisine);
        }
        return $invite;
    }

    public function test_zero_curated_shows_all_six(): void
    {
        $invite = $this->makeInvite([]);
        $body = RespondControllerFactory::make($this->pdo(), $this->clock)->open($invite['public_token'])->body();
        foreach (['coffee', 'brunch', 'lunch', 'dinner', 'dessert', 'drinks'] as $k) {
            $this->assertStringContainsString('value="' . $k . '"', $body);
        }
    }

    public function test_multiple_curated_shows_only_those(): void
    {
        $invite = $this->makeInvite(['dinner' => 'Italian', 'coffee' => null]);
        $body = RespondControllerFactory::make($this->pdo(), $this->clock)->open($invite['public_token'])->body();
        $this->assertStringContainsString('value="dinner"', $body);
        $this->assertStringContainsString('value="coffee"', $body);
        $this->assertStringNotContainsString('value="lunch"', $body);
        $this->assertStringContainsString('Italian', $body);          // cuisine surfaced
    }

    public function test_single_curated_collapses_to_hidden_choice(): void
    {
        $invite = $this->makeInvite(['dinner' => 'Korean']);
        $body = RespondControllerFactory::make($this->pdo(), $this->clock)->open($invite['public_token'])->body();
        $this->assertStringContainsString('type="hidden" name="meal_choice" value="dinner"', $body);
        $this->assertStringNotContainsString('type="radio" name="meal_choice"', $body);
        $this->assertStringContainsString('Korean', $body);
    }
}
