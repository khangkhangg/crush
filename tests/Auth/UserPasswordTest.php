<?php
declare(strict_types=1);

namespace Tests\Auth;

use App\Auth\UserRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class UserPasswordTest extends DatabaseTestCase
{
    public function test_set_and_verify_password_hash(): void
    {
        $repo = new UserRepo($this->pdo(), new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z')));
        $user = $repo->create('a@x.test', 'Ann', 'magic');
        $this->assertArrayHasKey('password_hash', $user);
        $this->assertNull($user['password_hash']);

        $repo->setPasswordHash($user['id'], password_hash('Sushi08!', PASSWORD_DEFAULT));
        $reloaded = $repo->findByEmail('a@x.test');

        $this->assertNotNull($reloaded['password_hash']);
        $this->assertTrue(password_verify('Sushi08!', $reloaded['password_hash']));
        $this->assertFalse(password_verify('wrong', $reloaded['password_hash']));
    }
}
