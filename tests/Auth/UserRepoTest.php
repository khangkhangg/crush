<?php
declare(strict_types=1);

namespace Tests\Auth;

use App\Auth\UserRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class UserRepoTest extends DatabaseTestCase
{
    private function repo(): UserRepo
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        return new UserRepo($this->pdo(), $clock);
    }

    public function test_create_then_find_by_id_and_email(): void
    {
        $repo = $this->repo();
        $user = $repo->create('a@x.test', 'Ann', 'magic');
        $this->assertIsInt($user['id']);
        $this->assertSame('a@x.test', $user['email']);

        $this->assertSame($user['id'], $repo->findById($user['id'])['id']);
        $this->assertSame('Ann', $repo->findByEmail('a@x.test')['name']);
        $this->assertNull($repo->findByEmail('missing@x.test'));
    }

    public function test_find_by_google_id_and_link(): void
    {
        $repo = $this->repo();
        $this->assertNull($repo->findByGoogleId('g-123'));

        $user = $repo->create('b@x.test', 'Bo', 'magic');
        $repo->linkGoogle($user['id'], 'g-123', 'http://img/avatar.png');

        $linked = $repo->findByGoogleId('g-123');
        $this->assertSame($user['id'], $linked['id']);
        $this->assertSame('http://img/avatar.png', $linked['avatar_url']);
    }

    public function test_create_with_google_fields(): void
    {
        $repo = $this->repo();
        $user = $repo->create('c@x.test', 'Cy', 'google', 'g-999', 'http://img/c.png');
        $this->assertSame('g-999', $repo->findByGoogleId('g-999')['google_id']);
        $this->assertSame('google', $user['auth_provider']);
    }
}
