<?php
declare(strict_types=1);

namespace Tests\Auth;

use App\Auth\Session;
use App\Core\ArrayStore;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
    public function test_login_sets_user_and_check_true(): void
    {
        $s = new Session(new ArrayStore());
        $this->assertFalse($s->check());
        $this->assertNull($s->userId());

        $s->login(42);
        $this->assertTrue($s->check());
        $this->assertSame(42, $s->userId());
    }

    public function test_logout_clears_user(): void
    {
        $s = new Session(new ArrayStore());
        $s->login(7);
        $s->logout();
        $this->assertFalse($s->check());
        $this->assertNull($s->userId());
    }
}
