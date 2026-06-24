<?php
declare(strict_types=1);

namespace Tests\Invite;

use App\Auth\UserRepo;
use App\Invite\InvitePlaceRepo;
use App\Invite\InviteRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class InvitePlaceCuisineTest extends DatabaseTestCase
{
    public function test_cuisine_round_trips(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $sender = (new UserRepo($this->pdo(), $clock))->create('s@x.test', 'Sue', 'magic')['id'];
        $invite = (new InviteRepo($this->pdo(), $clock))->create([
            'sender_id' => $sender, 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => false, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'expires_at' => '2026-02-01 00:00:00',
        ]);
        $places = new InvitePlaceRepo($this->pdo());
        $places->add((int) $invite['id'], 'dinner', 'Tartine', null, null, null, null, 'Italian');

        $row = $places->forMeal((int) $invite['id'], 'dinner');
        $this->assertSame('Italian', $row['cuisine']);

        // default null when omitted
        $places->add((int) $invite['id'], 'lunch', 'Noodle Bar', null, null, null, null);
        $this->assertNull($places->forMeal((int) $invite['id'], 'lunch')['cuisine']);
    }
}
