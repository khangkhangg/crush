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

final class RevealControllerTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    private function controller(): RevealController
    {
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        return new RevealController(
            $view,
            new UserRepo($this->pdo(), $this->clock),
            new InviteRepo($this->pdo(), $this->clock),
            new ResponseRepo($this->pdo(), $this->clock),
            new IcsBuilder($this->clock),
            new InvitePlaceRepo($this->pdo()),
            new Csrf(new ArrayStore())
        );
    }

    /** @return array{0:int,1:array} senderId, invite */
    private function seed(): array
    {
        $users = new UserRepo($this->pdo(), $this->clock);
        $sender = $users->create('sue@x.test', 'Sue', 'magic');
        $invite = (new InviteRepo($this->pdo(), $this->clock))->create([
            'sender_id' => $sender['id'], 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => false, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'expires_at' => '2026-02-01 00:00:00',
        ]);
        return [$sender['id'], $invite];
    }

    private function addResponse(int $inviteId): void
    {
        (new ResponseRepo($this->pdo(), $this->clock))->store($inviteId, [
            'chosen_start' => '2026-02-10 19:00:00', 'chosen_end' => '2026-02-10 21:00:00',
            'meal_choice' => 'dinner', 'meal_wish' => 'sushi', 'crush_contact' => '@cee',
            'pickup_name' => 'Tartine', 'pickup_address' => '1 Main St',
        ]);
    }

    public function test_logged_out_redirects(): void
    {
        [, $invite] = $this->seed();
        $res = $this->controller()->show(null, $invite['public_token']);
        $this->assertSame(302, $res->status());
        $this->assertSame('/login', $res->headers()['Location']);
    }

    public function test_other_users_invite_is_404(): void
    {
        [, $invite] = $this->seed();
        $stranger = (new UserRepo($this->pdo(), $this->clock))->create('x@x.test', 'X', 'magic')['id'];
        $this->assertSame(404, $this->controller()->show($stranger, $invite['public_token'])->status());
    }

    public function test_no_response_shows_waiting(): void
    {
        [$senderId, $invite] = $this->seed();
        $res = $this->controller()->show($senderId, $invite['public_token']);
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('waiting', strtolower($res->body()));
        $this->assertStringNotContainsString('dinner', $res->body());
    }

    public function test_response_but_incomplete_profile_is_locked_and_hides_choices(): void
    {
        [$senderId, $invite] = $this->seed();
        $this->addResponse($invite['id']);
        $res = $this->controller()->show($senderId, $invite['public_token']);
        $this->assertSame(200, $res->status());
        // Teaser must NOT leak the crush's actual choices.
        $this->assertStringNotContainsString('dinner', $res->body());
        $this->assertStringNotContainsString('Tartine', $res->body());
        $this->assertStringContainsString('action="/profile"', $res->body()); // inline form
    }

    public function test_response_and_complete_profile_reveals(): void
    {
        [$senderId, $invite] = $this->seed();
        $this->addResponse($invite['id']);
        (new UserRepo($this->pdo(), $this->clock))->saveProfile($senderId, 'fox', null, 'hi', null);

        $res = $this->controller()->show($senderId, $invite['public_token']);
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('dinner', $res->body());
        $this->assertStringContainsString('Tartine', $res->body());
        $this->assertStringContainsString('calendar', strtolower($res->body())); // download link
    }
}
