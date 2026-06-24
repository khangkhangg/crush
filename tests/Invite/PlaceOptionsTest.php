<?php
declare(strict_types=1);

namespace Tests\Invite;

use App\Auth\UserRepo;
use App\Invite\InvitePlaceRepo;
use App\Invite\InviteRepo;
use App\Invite\ResponseRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class PlaceOptionsTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    private function invite(): array
    {
        $sender = (new UserRepo($this->pdo(), $this->clock))->create('s@x.test', 'Sue', 'magic')['id'];
        return (new InviteRepo($this->pdo(), $this->clock))->create([
            'sender_id' => $sender, 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => false, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'expires_at' => '2026-12-01 00:00:00',
        ]);
    }

    public function test_multiple_options_per_vibe(): void
    {
        $inv = $this->invite();
        $places = new InvitePlaceRepo($this->pdo());
        $id1 = $places->addOption((int) $inv['id'], 'dinner', 'Tartine', null, null, null, null, 'Italian', 0);
        $id2 = $places->addOption((int) $inv['id'], 'dinner', 'Octo', null, null, null, null, 'Tapas', 1);
        $this->assertNotSame($id1, $id2);

        $grouped = $places->groupedForInvite((int) $inv['id']);
        $this->assertCount(2, $grouped['dinner']);
        $this->assertSame('Tartine', $grouped['dinner'][0]['place_name']);   // ordered by sort
        $this->assertSame('Octo', $grouped['dinner'][1]['place_name']);
        $this->assertSame('Tapas', $grouped['dinner'][1]['cuisine']);
    }

    public function test_response_stores_chosen_place_id(): void
    {
        $inv = $this->invite();
        $places = new InvitePlaceRepo($this->pdo());
        $pid = $places->addOption((int) $inv['id'], 'dinner', 'Tartine', null, null, null, null, 'Italian', 0);
        $responses = new ResponseRepo($this->pdo(), $this->clock);
        $responses->store((int) $inv['id'], [
            'chosen_start' => '2026-02-10 19:00:00', 'chosen_end' => '2026-02-10 21:00:00',
            'meal_choice' => 'dinner', 'chosen_place_id' => $pid,
        ]);
        $row = $responses->findByInvite((int) $inv['id']);
        $this->assertSame($pid, (int) $row['chosen_place_id']);
    }
}
