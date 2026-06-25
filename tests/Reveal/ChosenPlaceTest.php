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

final class ChosenPlaceTest extends DatabaseTestCase
{
    public function test_detail_shows_chosen_place(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $users = new UserRepo($this->pdo(), $clock);
        $invites = new InviteRepo($this->pdo(), $clock);
        $responses = new ResponseRepo($this->pdo(), $clock);
        $places = new InvitePlaceRepo($this->pdo());

        $sender = $users->create('s@x.test', 'Sue', 'magic');
        $users->saveProfile($sender['id'], 'fox', null, 'hi', null);          // complete profile so reveal unlocks
        $invite = $invites->create([
            'sender_id' => $sender['id'], 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => false, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'expires_at' => '2026-12-01 00:00:00',
        ]);
        $pid = $places->addOption((int) $invite['id'], 'dinner', 'Octo Tapas', null, null, null, 'https://www.google.com/maps/search/?api=1&query=Octo', 'Tapas', 0);
        $responses->store((int) $invite['id'], [
            'chosen_start' => '2026-02-10 19:00:00', 'chosen_end' => '2026-02-10 21:00:00',
            'meal_choice' => 'dinner', 'chosen_place_id' => $pid,
        ]);

        $ctrl = new RevealController($this->view(), $users, $invites, $responses, new IcsBuilder($clock), $places, new Csrf(new ArrayStore()));
        $body = $ctrl->show($sender['id'], $invite['public_token'])->body();
        $this->assertStringContainsString('Octo Tapas', $body);
        $this->assertStringContainsString('Tapas', $body);
    }

    private function view(): View
    {
        return new View(\dirname(__DIR__, 2) . '/templates');
    }
}
