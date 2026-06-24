<?php
declare(strict_types=1);

namespace Tests\Invite;

use App\Auth\UserRepo;
use App\Invite\InviteRepo;
use App\Invite\InviteState;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class InviteRepoTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    private function repo(): InviteRepo
    {
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        return new InviteRepo($this->pdo(), $this->clock);
    }

    private function sender(): int
    {
        return (new UserRepo($this->pdo(), $this->clock))->create('s@x.test', 'Sue', 'magic')['id'];
    }

    private function baseData(int $sender): array
    {
        return [
            'sender_id'          => $sender,
            'crush_email'        => 'crush@x.test',
            'crush_name'         => 'Cee',
            'is_anonymous'       => true,
            'reveal_on_response' => false,
            'date_mode'          => 'instant',
            'message'            => 'hi',
            'expires_at'         => '2026-02-01 00:00:00',
        ];
    }

    public function test_create_generates_token_and_defaults_status_sent(): void
    {
        $repo = $this->repo();
        $invite = $repo->create($this->baseData($this->sender()));

        $this->assertIsInt($invite['id']);
        $this->assertSame(64, strlen($invite['public_token']));
        $this->assertSame(InviteState::SENT, $invite['status']);
        $this->assertSame(1, $invite['is_anonymous']);
        $this->assertSame($invite['id'], $repo->findByToken($invite['public_token'])['id']);
    }

    public function test_list_by_sender_newest_first(): void
    {
        $repo = $this->repo();
        $sender = $this->sender();
        $a = $repo->create($this->baseData($sender));
        $this->clock->advance(60);
        $b = $repo->create($this->baseData($sender));

        $list = $repo->listBySender($sender);
        $this->assertCount(2, $list);
        $this->assertSame($b['id'], $list[0]['id']);
    }

    public function test_update_status_and_set_theme(): void
    {
        $repo = $this->repo();
        $invite = $repo->create($this->baseData($this->sender()));
        $repo->updateStatus($invite['id'], InviteState::OPENED);
        $repo->setTheme($invite['id'], 'midnight');

        $reloaded = $repo->findById($invite['id']);
        $this->assertSame(InviteState::OPENED, $reloaded['status']);
        $this->assertSame('midnight', $reloaded['theme_key']);
    }

    public function test_date_options_round_trip(): void
    {
        $repo = $this->repo();
        $invite = $repo->create($this->baseData($this->sender()));
        $repo->addDateOption($invite['id'], '2026-02-10 19:00:00', '2026-02-10 21:00:00');
        $repo->addDateOption($invite['id'], '2026-02-11 19:00:00', '2026-02-11 21:00:00');

        $opts = $repo->dateOptions($invite['id']);
        $this->assertCount(2, $opts);
        $this->assertSame('2026-02-10 19:00:00', $opts[0]['start_at']);
    }
}
