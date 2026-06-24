<?php
declare(strict_types=1);

namespace Tests\Auth;

use App\Auth\AuthController;
use App\Auth\MagicLink;
use App\Auth\Session;
use App\Auth\UserRepo;
use App\Core\ArrayStore;
use App\Core\Csrf;
use App\Core\View;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;
use Tests\Support\SpyMailer;

final class PasswordLoginTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    private function controller(Csrf $csrf, Session $session): AuthController
    {
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $users = new UserRepo($this->pdo(), $this->clock);
        $magic = new MagicLink($this->pdo(), $users, $this->clock, 900);
        return new AuthController($view, $session, $csrf, $magic, new SpyMailer(), 'https://crush.app', $users, new \App\Security\RateLimiter($this->pdo(), $this->clock));
    }

    public function test_rate_limited_after_too_many_attempts(): void
    {
        $this->makeUser('rl@x.test', 'goodpass');
        $csrf = new Csrf(new ArrayStore());
        $ctrl = $this->controller($csrf, new Session(new ArrayStore()));
        // 5 wrong attempts on the same email exhaust the per-email cap (5/900s)
        for ($i = 0; $i < 5; $i++) {
            $ctrl->loginPassword('rl@x.test', 'wrong', $csrf->token(), '203.0.113.9');
        }
        $res = $ctrl->loginPassword('rl@x.test', 'goodpass', $csrf->token(), '203.0.113.9');
        $this->assertSame(429, $res->status());
    }

    private function makeUser(string $email, string $password): void
    {
        $users = new UserRepo($this->pdo(), $this->clock);
        $u = $users->create($email, 'U', 'password');
        $users->setPasswordHash($u['id'], password_hash($password, PASSWORD_DEFAULT));
    }

    public function test_correct_password_logs_in(): void
    {
        $this->makeUser('p@x.test', 'goodpass');
        $csrf = new Csrf(new ArrayStore());
        $session = new Session(new ArrayStore());
        $res = $this->controller($csrf, $session)->loginPassword('p@x.test', 'goodpass', $csrf->token());
        $this->assertSame(302, $res->status());
        $this->assertSame('/invites', $res->headers()['Location']);
        $this->assertTrue($session->check());
    }

    public function test_wrong_password_401(): void
    {
        $this->makeUser('p2@x.test', 'goodpass');
        $csrf = new Csrf(new ArrayStore());
        $session = new Session(new ArrayStore());
        $res = $this->controller($csrf, $session)->loginPassword('p2@x.test', 'nope', $csrf->token());
        $this->assertSame(401, $res->status());
        $this->assertFalse($session->check());
        $this->assertStringContainsString('Invalid email or password', $res->body());
    }

    public function test_unknown_email_401(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf, new Session(new ArrayStore()))->loginPassword('ghost@x.test', 'whatever', $csrf->token());
        $this->assertSame(401, $res->status());
    }

    public function test_bad_csrf_400(): void
    {
        $res = $this->controller(new Csrf(new ArrayStore()), new Session(new ArrayStore()))->loginPassword('p@x.test', 'x', 'wrong');
        $this->assertSame(400, $res->status());
    }
}
