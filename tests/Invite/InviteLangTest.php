<?php
declare(strict_types=1);

namespace Tests\Invite;

use App\Auth\UserRepo;
use App\Invite\InviteRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class InviteLangTest extends DatabaseTestCase
{
    public function test_create_stores_lang(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $sender = (new UserRepo($this->pdo(), $clock))->create('s@x.test', 'Sue', 'magic')['id'];
        $invites = new InviteRepo($this->pdo(), $clock);

        $withLang = $invites->create([
            'sender_id' => $sender, 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => false, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'lang' => 'ko', 'expires_at' => '2026-02-01 00:00:00',
        ]);
        $this->assertSame('ko', $invites->findById($withLang['id'])['lang']);

        $noLang = $invites->create([
            'sender_id' => $sender, 'crush_email' => 'c2@x.test', 'crush_name' => 'Dee',
            'is_anonymous' => false, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'expires_at' => '2026-02-01 00:00:00',
        ]);
        $this->assertNull($invites->findById($noLang['id'])['lang']);
    }
}
