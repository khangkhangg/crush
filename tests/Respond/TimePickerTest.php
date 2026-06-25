<?php
declare(strict_types=1);

namespace Tests\Respond;

use App\Auth\UserRepo;
use App\Invite\InviteRepo;
use App\Invite\ResponseRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;
use Tests\Support\RespondControllerFactory;

final class TimePickerTest extends DatabaseTestCase
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
            'message' => null, 'theme_key' => 'bubblegum', 'expires_at' => '2026-12-01 00:00:00',
        ]);
    }

    public function test_form_has_date_and_time_inputs(): void
    {
        $inv = $this->invite();
        $body = RespondControllerFactory::make($this->pdo(), $this->clock)->open($inv['public_token'])->body();
        $this->assertStringContainsString('name="chosen_date"', $body);
        $this->assertStringContainsString('name="chosen_time"', $body);
        $this->assertStringContainsString('data-time="19:00"', $body);     // Evening quick-pick
    }

    public function test_submit_combines_date_and_time(): void
    {
        $inv = $this->invite();
        $csrf = new \App\Core\Csrf(new \App\Core\ArrayStore());
        $ctrl = RespondControllerFactory::make($this->pdo(), $this->clock, $csrf);
        $res = $ctrl->submit($inv['public_token'], [
            'chosen_date' => '2026-06-30', 'chosen_time' => '19:00', 'meal_choice' => 'dinner',
        ], $csrf->token());
        $this->assertContains($res->status(), [302, 200]);
        $row = (new ResponseRepo($this->pdo(), $this->clock))->findByInvite((int) $inv['id']);
        $this->assertNotNull($row);
        $this->assertStringStartsWith('2026-06-30 19:00', (string) $row['chosen_start']);
    }
}
