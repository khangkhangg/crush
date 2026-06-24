<?php
declare(strict_types=1);

namespace Tests\Auth;

use App\Auth\Session;
use App\Core\ArrayStore;
use App\Core\RegeneratesId;
use App\Core\Store;
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

    public function test_login_calls_regenerate_id_when_store_supports_it(): void
    {
        $fakeStore = new class implements Store, RegeneratesId {
            private array $data = [];
            public bool $regenerated = false;

            public function get(string $key, mixed $default = null): mixed
            {
                return $this->data[$key] ?? $default;
            }

            public function set(string $key, mixed $value): void
            {
                $this->data[$key] = $value;
            }

            public function regenerateId(): void
            {
                $this->regenerated = true;
            }
        };

        $s = new Session($fakeStore);
        $s->login(99);
        $this->assertTrue($fakeStore->regenerated);
        $this->assertSame(99, $s->userId());
    }

    public function test_login_does_not_require_regenerate_id(): void
    {
        // ArrayStore does NOT implement RegeneratesId — must not throw
        $s = new Session(new ArrayStore());
        $s->login(5);
        $this->assertTrue($s->check());
    }
}
