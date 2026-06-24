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

final class AuthControllerTest extends DatabaseTestCase
{
    private function controller(Session $session, Csrf $csrf): AuthController
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $users = new UserRepo($this->pdo(), $clock);
        $magic = new MagicLink($this->pdo(), $users, $clock, 900);
        $view  = new View(\dirname(__DIR__, 2) . '/templates');
        return new AuthController($view, $session, $csrf, $magic, new SpyMailer(), 'http://localhost');
    }

    public function test_show_login_renders_form_with_csrf(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $ctrl = $this->controller(new Session(new ArrayStore()), $csrf);
        $res = $ctrl->showLogin();
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString($csrf->token(), $res->body());
        $this->assertStringContainsString('name="email"', $res->body());
    }

    public function test_start_magic_rejects_bad_csrf(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $ctrl = $this->controller(new Session(new ArrayStore()), $csrf);
        $res = $ctrl->startMagic('a@x.test', 'wrong-csrf');
        $this->assertSame(400, $res->status());
    }

    public function test_complete_magic_logs_in_and_redirects(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $session = new Session(new ArrayStore());
        $ctrl = $this->controller($session, $csrf);

        // Issue a token directly via the same DB, then complete it through the controller.
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $magic = new MagicLink($this->pdo(), new UserRepo($this->pdo(), $clock), $clock, 900);
        $token = $magic->start('a@x.test');

        $res = $ctrl->completeMagic($token);
        $this->assertSame(302, $res->status());
        $this->assertTrue($session->check());
    }
}
