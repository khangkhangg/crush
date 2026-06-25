<?php
declare(strict_types=1);

namespace Tests\Respond;

use App\Auth\UserRepo;
use App\Invite\InviteRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;
use Tests\Support\RespondControllerFactory;

final class MapsPreviewTest extends DatabaseTestCase
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

    public function test_unknown_token_is_404(): void
    {
        $res = RespondControllerFactory::make($this->pdo(), $this->clock)->mapsPreview('nope', '123 Main St');
        $this->assertSame(404, $res->status());
    }

    public function test_valid_token_resolves_address(): void
    {
        $inv = $this->invite();
        $res = RespondControllerFactory::make($this->pdo(), $this->clock)->mapsPreview($inv['public_token'], '123 Main St');
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('application/json', implode(' ', $res->headers()));
        $this->assertStringContainsString('123 Main St', $res->body());     // plain address echoed back
    }

    public function test_form_has_map_modal_and_pickup_hook(): void
    {
        $inv = $this->invite();
        $body = RespondControllerFactory::make($this->pdo(), $this->clock)->open($inv['public_token'])->body();
        $this->assertStringContainsString('id="rfMapModal"', $body);
        $this->assertStringContainsString('output=embed', $body);
        $this->assertStringContainsString('data-maps', $body);              // pickup field hook
    }
}
