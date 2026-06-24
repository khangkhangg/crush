<?php
declare(strict_types=1);

namespace Tests\Security;

use App\Admin\BlockController;
use App\Auth\UserRepo;
use App\Core\View;
use App\Invite\InviteRepo;
use App\Invite\InviteState;
use App\Security\BlockRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class BlockFlowTest extends DatabaseTestCase
{
    public function test_report_link_blocks_sender_and_marks_invite(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $invites = new InviteRepo($this->pdo(), $clock);
        $blocks = new BlockRepo($this->pdo(), $clock);
        $sender = (new UserRepo($this->pdo(), $clock))->create('s@x.test', 'Sue', 'magic')['id'];
        $invite = $invites->create([
            'sender_id' => $sender, 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => true, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'expires_at' => '2026-02-01 00:00:00',
        ]);

        $ctrl = new BlockController($view, $invites, $blocks);
        $res = $ctrl->report($invite['public_token']);

        $this->assertSame(200, $res->status());
        $this->assertTrue($blocks->isBlocked($sender, 'c@x.test'));
        $this->assertSame(InviteState::BLOCKED, $invites->findByToken($invite['public_token'])['status']);
    }

    public function test_unknown_token_is_404(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $ctrl = new BlockController(
            new View(\dirname(__DIR__, 2) . '/templates'),
            new InviteRepo($this->pdo(), $clock),
            new BlockRepo($this->pdo(), $clock)
        );
        $this->assertSame(404, $ctrl->report('nope')->status());
    }
}
