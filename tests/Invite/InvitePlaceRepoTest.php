<?php
declare(strict_types=1);

namespace Tests\Invite;

use App\Auth\UserRepo;
use App\Invite\InvitePlaceRepo;
use App\Invite\InviteRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class InvitePlaceRepoTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    private function inviteId(): int
    {
        $sender = (new UserRepo($this->pdo(), $this->clock))->create('s@x.test', 'Sue', 'magic')['id'];
        return (new InviteRepo($this->pdo(), $this->clock))->create([
            'sender_id' => $sender, 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => false, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'expires_at' => '2026-02-01 00:00:00',
        ])['id'];
    }

    public function test_add_forinvite_formeal(): void
    {
        $repo = new InvitePlaceRepo($this->pdo());
        $id = $this->inviteId();

        $repo->add($id, 'dinner', 'Tartine', 'https://maps.app.goo.gl/x', 'Tartine Bakery', '1 Main St', 'https://maps.google.com/?q=Tartine');
        $repo->add($id, 'coffee', 'Blue Bottle', null, null, null, null);

        $byMeal = $repo->forInvite($id);
        $this->assertArrayHasKey('dinner', $byMeal);
        $this->assertArrayHasKey('coffee', $byMeal);
        $this->assertSame('Tartine', $byMeal['dinner']['place_name']);
        $this->assertSame('Tartine Bakery', $byMeal['dinner']['place_resolved_name']);

        $this->assertSame('Blue Bottle', $repo->forMeal($id, 'coffee')['place_name']);
        $this->assertNull($repo->forMeal($id, 'lunch'));
    }

    public function test_add_is_upsert_per_meal(): void
    {
        $repo = new InvitePlaceRepo($this->pdo());
        $id = $this->inviteId();
        $repo->add($id, 'dinner', 'First', null, null, null, null);
        $repo->add($id, 'dinner', 'Second', null, null, null, null);

        $this->assertSame('Second', $repo->forMeal($id, 'dinner')['place_name']);
        $this->assertCount(1, $repo->forInvite($id));
    }
}
