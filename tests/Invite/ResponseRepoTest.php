<?php
declare(strict_types=1);

namespace Tests\Invite;

use App\Auth\UserRepo;
use App\Invite\InviteRepo;
use App\Invite\ResponseRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class ResponseRepoTest extends DatabaseTestCase
{
    private function ids(): array
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $sender = (new UserRepo($this->pdo(), $clock))->create('s@x.test', 'Sue', 'magic')['id'];
        $invite = (new InviteRepo($this->pdo(), $clock))->create([
            'sender_id' => $sender, 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => false, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'expires_at' => '2026-02-01 00:00:00',
        ]);
        return [$invite['id'], new ResponseRepo($this->pdo(), $clock)];
    }

    public function test_store_and_find(): void
    {
        [$inviteId, $repo] = $this->ids();
        $resp = $repo->store($inviteId, [
            'chosen_start' => '2026-02-10 19:00:00',
            'chosen_end'   => '2026-02-10 21:00:00',
            'meal_choice'  => 'sushi',
            'meal_wish'    => 'surprise me',
            'crush_contact'=> '@cee',
            'pickup_name'  => 'Sushi Place',
            'pickup_address'=> '1 Main St',
            'pickup_clean_url' => 'https://maps.google.com/?q=Sushi+Place',
        ]);

        $this->assertIsInt($resp['id']);
        $this->assertSame($inviteId, $resp['invite_id']);
        $this->assertSame('sushi', $resp['meal_choice']);

        $found = $repo->findByInvite($inviteId);
        $this->assertSame('Sushi Place', $found['pickup_name']);
        $this->assertNull($repo->findByInvite(999999));
    }

    public function test_store_with_minimal_data(): void
    {
        [$inviteId, $repo] = $this->ids();
        $resp = $repo->store($inviteId, ['meal_choice' => 'coffee']);
        $this->assertSame('coffee', $resp['meal_choice']);
        $this->assertNull($resp['pickup_address']);
    }
}
