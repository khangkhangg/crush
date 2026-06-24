<?php
declare(strict_types=1);

namespace Tests\Invite;

use App\Invite\InviteState;
use PHPUnit\Framework\TestCase;

final class InviteStateTest extends TestCase
{
    public function test_valid_transitions(): void
    {
        $this->assertTrue(InviteState::canTransition(InviteState::SENT, InviteState::OPENED));
        $this->assertTrue(InviteState::canTransition(InviteState::OPENED, InviteState::RESPONDED));
        $this->assertTrue(InviteState::canTransition(InviteState::RESPONDED, InviteState::CONFIRMED));
        $this->assertTrue(InviteState::canTransition(InviteState::RESPONDED, InviteState::PENDING_SENDER));
        $this->assertTrue(InviteState::canTransition(InviteState::PENDING_SENDER, InviteState::CONFIRMED));
        $this->assertTrue(InviteState::canTransition(InviteState::PENDING_SENDER, InviteState::DECLINED));
        $this->assertTrue(InviteState::canTransition(InviteState::CONFIRMED, InviteState::CLOSED));
    }

    public function test_invalid_transitions(): void
    {
        $this->assertFalse(InviteState::canTransition(InviteState::SENT, InviteState::CONFIRMED));
        $this->assertFalse(InviteState::canTransition(InviteState::CLOSED, InviteState::OPENED));
        $this->assertFalse(InviteState::canTransition(InviteState::CONFIRMED, InviteState::DECLINED));
    }

    public function test_assert_throws_on_illegal(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        InviteState::assert(InviteState::CLOSED, InviteState::SENT);
    }

    public function test_sent_can_expire_or_block(): void
    {
        $this->assertTrue(InviteState::canTransition(InviteState::SENT, InviteState::EXPIRED));
        $this->assertTrue(InviteState::canTransition(InviteState::OPENED, InviteState::BLOCKED));
    }
}
