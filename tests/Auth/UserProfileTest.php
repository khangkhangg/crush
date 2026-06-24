<?php
declare(strict_types=1);

namespace Tests\Auth;

use App\Auth\UserRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class UserProfileTest extends DatabaseTestCase
{
    public function test_save_profile_sets_fields_and_stamps_completion(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $repo = new UserRepo($this->pdo(), $clock);
        $user = $repo->create('a@x.test', 'Ann', 'magic');

        $this->assertFalse(UserRepo::isProfileComplete($user));

        $repo->saveProfile($user['id'], 'fox', 'she/her', 'i like long walks to the fridge', '@ann');
        $reloaded = $repo->findById($user['id']);

        $this->assertSame('fox', $reloaded['avatar_key']);
        $this->assertSame('she/her', $reloaded['pronouns']);
        $this->assertSame('i like long walks to the fridge', $reloaded['bio']);
        $this->assertSame('@ann', $reloaded['contact']);
        $this->assertNotNull($reloaded['profile_completed_at']);
        $this->assertTrue(UserRepo::isProfileComplete($reloaded));
    }

    public function test_is_profile_complete_false_when_unset(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $repo = new UserRepo($this->pdo(), $clock);
        $user = $repo->create('b@x.test', 'Bo', 'magic');
        $this->assertFalse(UserRepo::isProfileComplete($repo->findById($user['id'])));
    }
}
