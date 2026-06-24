<?php
declare(strict_types=1);

namespace Tests\Auth;

use App\Auth\UserRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class UserLangTest extends DatabaseTestCase
{
    public function test_set_lang(): void
    {
        $repo = new UserRepo($this->pdo(), new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z')));
        $user = $repo->create('a@x.test', 'Ann', 'magic');
        $this->assertNull($user['lang']);
        $repo->setLang($user['id'], 'vi');
        $this->assertSame('vi', $repo->findById($user['id'])['lang']);
    }
}
