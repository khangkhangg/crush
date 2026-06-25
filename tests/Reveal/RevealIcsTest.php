<?php
declare(strict_types=1);

namespace Tests\Reveal;

use App\Auth\UserRepo;
use App\Core\View;
use App\Ics\IcsBuilder;
use App\Invite\InvitePlaceRepo;
use App\Invite\InviteRepo;
use App\Invite\ResponseRepo;
use App\Reveal\RevealController;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class RevealIcsTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    private function controller(): RevealController
    {
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        return new RevealController(
            $view, new UserRepo($this->pdo(), $this->clock),
            new InviteRepo($this->pdo(), $this->clock),
            new ResponseRepo($this->pdo(), $this->clock),
            new IcsBuilder($this->clock),
            new InvitePlaceRepo($this->pdo())
        );
    }

    private function seedWithResponse(bool $completeProfile): array
    {
        $users = new UserRepo($this->pdo(), $this->clock);
        $sender = $users->create('sue@x.test', 'Sue', 'magic');
        $invite = (new InviteRepo($this->pdo(), $this->clock))->create([
            'sender_id' => $sender['id'], 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => false, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'expires_at' => '2026-02-01 00:00:00',
        ]);
        (new ResponseRepo($this->pdo(), $this->clock))->store($invite['id'], [
            'chosen_start' => '2026-02-10 19:00:00', 'chosen_end' => '2026-02-10 21:00:00',
            'meal_choice' => 'dinner', 'pickup_name' => 'Tartine',
        ]);
        if ($completeProfile) {
            $users->saveProfile($sender['id'], 'fox', null, 'hi', null);
        }
        return [$sender['id'], $invite];
    }

    public function test_complete_profile_downloads_ics(): void
    {
        [$senderId, $invite] = $this->seedWithResponse(true);
        $res = $this->controller()->downloadIcs($senderId, $invite['public_token']);
        $this->assertSame(200, $res->status());
        $this->assertSame('text/calendar; charset=utf-8', $res->headers()['Content-Type']);
        $this->assertStringContainsString('attachment; filename="Date.ics"', $res->headers()['Content-Disposition']);
        $this->assertStringContainsString('BEGIN:VCALENDAR', $res->body());
        $this->assertStringContainsString('SUMMARY:Date with Cee', $res->body());
    }

    public function test_incomplete_profile_is_redirected(): void
    {
        [$senderId, $invite] = $this->seedWithResponse(false);
        $res = $this->controller()->downloadIcs($senderId, $invite['public_token']);
        $this->assertSame(302, $res->status());
        $this->assertStringContainsString('/response', $res->headers()['Location']);
    }
}
