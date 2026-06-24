<?php
declare(strict_types=1);

namespace Tests\Theme;

use App\Auth\UserRepo;
use App\Invite\InviteRepo;
use App\Theme\ABAssigner;
use App\Theme\ThemeRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class ABAssignerTest extends DatabaseTestCase
{
    private function invite(?string $themeKey = null): array
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $sender = (new UserRepo($this->pdo(), $clock))->create('s@x.test', 'Sue', 'magic')['id'];
        return (new InviteRepo($this->pdo(), $clock))->create([
            'sender_id' => $sender, 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => false, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'theme_key' => $themeKey, 'expires_at' => '2026-02-01 00:00:00',
        ]);
    }

    public function test_assigns_and_pins_when_unset(): void
    {
        $invites = new InviteRepo($this->pdo(), new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z')));
        // randInt(2) => 0 lands on the first active theme (bubblegum, weights all 1).
        $assigner = new ABAssigner(new ThemeRepo($this->pdo()), $invites, fn(int $max) => 0);

        $invite = $this->invite(null);
        $key = $assigner->assignTo($invite);
        $this->assertSame('bubblegum', $key);
        $this->assertSame('bubblegum', $invites->findById($invite['id'])['theme_key']);
    }

    public function test_picks_last_theme_at_max_random(): void
    {
        $invites = new InviteRepo($this->pdo(), new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z')));
        // Sum of weights is 3; randInt(2) => 2 lands on the third theme (midnight).
        $assigner = new ABAssigner(new ThemeRepo($this->pdo()), $invites, fn(int $max) => 2);
        $key = $assigner->assignTo($this->invite(null));
        $this->assertSame('midnight', $key);
    }

    public function test_keeps_existing_theme(): void
    {
        $invites = new InviteRepo($this->pdo(), new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z')));
        $assigner = new ABAssigner(new ThemeRepo($this->pdo()), $invites, fn(int $max) => 0);
        $key = $assigner->assignTo($this->invite('love-letter'));
        $this->assertSame('love-letter', $key);
    }
}
