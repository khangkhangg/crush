<?php
declare(strict_types=1);

namespace Tests\Reveal;

use App\Auth\UserRepo;
use App\Core\ArrayStore;
use App\Core\Csrf;
use App\Core\View;
use App\Ics\IcsBuilder;
use App\Invite\InvitePlaceRepo;
use App\Invite\InviteRepo;
use App\Invite\ResponseRepo;
use App\Reveal\RevealController;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class InlineProfileTest extends DatabaseTestCase
{
    public function test_locked_state_shows_inline_profile_form(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $users = new UserRepo($this->pdo(), $clock);
        $invites = new InviteRepo($this->pdo(), $clock);
        $responses = new ResponseRepo($this->pdo(), $clock);

        $sender = $users->create('s@x.test', 'Sue', 'magic');           // profile NOT complete -> locked
        $invite = $invites->create([
            'sender_id' => $sender['id'], 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => false, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'expires_at' => '2026-12-01 00:00:00',
        ]);
        $responses->store((int) $invite['id'], ['chosen_start' => '2026-02-10 19:00:00', 'meal_choice' => 'dinner']);

        $ctrl = new RevealController(
            new View(\dirname(__DIR__, 2) . '/templates'), $users, $invites, $responses,
            new IcsBuilder($clock), new InvitePlaceRepo($this->pdo()), new Csrf(new ArrayStore())
        );
        $body = $ctrl->show($sender['id'], $invite['public_token'])->body();
        $this->assertStringContainsString('answered', $body);                 // context heading
        $this->assertStringContainsString('action="/profile"', $body);        // inline form
        $this->assertStringContainsString('/invites/' . $invite['public_token'] . '/response', $body); // return_to
        $this->assertStringNotContainsString('Complete my profile', $body);   // old CTA gone
    }
}
