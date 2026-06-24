<?php
declare(strict_types=1);

namespace Tests\Landing;

use App\Auth\MagicLink;
use App\Auth\Session;
use App\Auth\UserRepo;
use App\Core\ArrayStore;
use App\Core\Csrf;
use App\Core\View;
use App\Landing\LandingController;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;
use Tests\Support\SpyMailer;

final class LandingControllerTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    private function controller(Csrf $csrf, Session $session, SpyMailer $spy): LandingController
    {
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $users = new UserRepo($this->pdo(), $this->clock);
        $magic = new MagicLink($this->pdo(), $users, $this->clock, 900);
        return new LandingController($view, $csrf, $users, $magic, $session, $spy, 'https://crush.app');
    }

    public function test_home_renders_for_logged_out(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf, new Session(new ArrayStore()), new SpyMailer())->home(null);
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString($csrf->token(), $res->body());
        $this->assertStringContainsString('name="email"', $res->body());
    }

    public function test_home_redirects_logged_in_to_dashboard(): void
    {
        $res = $this->controller(new Csrf(new ArrayStore()), new Session(new ArrayStore()), new SpyMailer())->home(42);
        $this->assertSame(302, $res->status());
        $this->assertSame('/invites', $res->headers()['Location']);
    }

    public function test_start_bad_csrf_is_400(): void
    {
        $res = $this->controller(new Csrf(new ArrayStore()), new Session(new ArrayStore()), new SpyMailer())
            ->start(['name' => 'Ann', 'email' => 'a@x.test'], 'wrong');
        $this->assertSame(400, $res->status());
    }

    public function test_start_invalid_email_is_422(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf, new Session(new ArrayStore()), new SpyMailer())
            ->start(['name' => 'Ann', 'email' => 'nope'], $csrf->token());
        $this->assertSame(422, $res->status());
    }

    public function test_new_email_creates_logs_in_and_redirects(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $session = new Session(new ArrayStore());
        $spy = new SpyMailer();
        $ctrl = $this->controller($csrf, $session, $spy);

        $res = $ctrl->start(['name' => 'New', 'email' => 'new@x.test'], $csrf->token());

        $this->assertSame(302, $res->status());
        $this->assertSame('/invites/new', $res->headers()['Location']);
        $this->assertTrue($session->check());
        $this->assertCount(1, $spy->sent);                       // magic link emailed
        $this->assertSame('new@x.test', $spy->sent[0]->to);
        $user = (new UserRepo($this->pdo(), $this->clock))->findByEmail('new@x.test');
        $this->assertSame('New', $user['name']);
    }

    public function test_existing_email_emails_link_without_login(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $session = new Session(new ArrayStore());
        $spy = new SpyMailer();
        (new UserRepo($this->pdo(), new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'))))
            ->create('dupe@x.test', 'Dee', 'magic');

        $res = $this->controller($csrf, $session, $spy)
            ->start(['name' => 'Dee', 'email' => 'dupe@x.test'], $csrf->token());

        $this->assertSame(200, $res->status());
        $this->assertFalse($session->check());                    // NOT logged in
        $this->assertCount(1, $spy->sent);
        $this->assertStringContainsString('check your email', strtolower($res->body()));
    }
}
