<?php
declare(strict_types=1);

namespace Tests\Admin;

use App\Admin\AdminAuthController;
use App\Auth\Session;
use App\Auth\UserRepo;
use App\Core\ArrayStore;
use App\Core\Csrf;
use App\Core\View;
use App\Security\RateLimiter;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class AdminAuthControllerTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    private function controller(Csrf $csrf, Session $session): AdminAuthController
    {
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        return new AdminAuthController(
            $view, $csrf, new UserRepo($this->pdo(), $this->clock), $session,
            new RateLimiter($this->pdo(), $this->clock)
        );
    }

    private function admin(string $pw): void
    {
        $repo = new UserRepo($this->pdo(), $this->clock);
        $u = $repo->create('admin@x.test', 'Boss', 'magic');
        $repo->setPasswordHash($u['id'], password_hash($pw, PASSWORD_DEFAULT));
        $this->pdo()->prepare('UPDATE users SET is_admin = 1 WHERE id = ?')->execute([$u['id']]);
    }

    public function test_show_login_renders_form(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf, new Session(new ArrayStore()))->showLogin();
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString($csrf->token(), $res->body());
        $this->assertStringContainsString('name="password"', $res->body());
    }

    public function test_bad_csrf_is_400(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf, new Session(new ArrayStore()))
            ->login(['email' => 'admin@x.test', 'password' => 'Sushi08!'], 'wrong', '1.1.1.1');
        $this->assertSame(400, $res->status());
    }

    public function test_correct_admin_logs_in(): void
    {
        $this->admin('Sushi08!');
        $csrf = new Csrf(new ArrayStore());
        $session = new Session(new ArrayStore());
        $res = $this->controller($csrf, $session)
            ->login(['email' => 'admin@x.test', 'password' => 'Sushi08!'], $csrf->token(), '1.1.1.1');
        $this->assertSame(302, $res->status());
        $this->assertSame('/admin', $res->headers()['Location']);
        $this->assertTrue($session->check());
    }

    public function test_wrong_password_is_generic_401(): void
    {
        $this->admin('Sushi08!');
        $csrf = new Csrf(new ArrayStore());
        $session = new Session(new ArrayStore());
        $res = $this->controller($csrf, $session)
            ->login(['email' => 'admin@x.test', 'password' => 'nope'], $csrf->token(), '1.1.1.1');
        $this->assertSame(401, $res->status());
        $this->assertFalse($session->check());
        $this->assertStringContainsString('Invalid email or password', $res->body());
    }

    public function test_non_admin_with_correct_password_is_rejected(): void
    {
        $repo = new UserRepo($this->pdo(), $this->clock);
        $u = $repo->create('plain@x.test', 'Plain', 'magic');
        $repo->setPasswordHash($u['id'], password_hash('Sushi08!', PASSWORD_DEFAULT)); // NOT admin
        $csrf = new Csrf(new ArrayStore());
        $session = new Session(new ArrayStore());
        $res = $this->controller($csrf, $session)
            ->login(['email' => 'plain@x.test', 'password' => 'Sushi08!'], $csrf->token(), '1.1.1.1');
        $this->assertSame(401, $res->status());
        $this->assertFalse($session->check());
    }

    public function test_rate_limited_after_repeated_attempts(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $ctrl = $this->controller($csrf, new Session(new ArrayStore()));
        // per-email cap is 5/900s; the 6th attempt to the same email is 429
        for ($i = 0; $i < 5; $i++) {
            $ctrl->login(['email' => 'admin@x.test', 'password' => 'x'], $csrf->token(), '9.9.9.9');
        }
        $res = $ctrl->login(['email' => 'admin@x.test', 'password' => 'x'], $csrf->token(), '9.9.9.9');
        $this->assertSame(429, $res->status());
    }
}
