<?php
declare(strict_types=1);

namespace Tests\Landing;

use App\Auth\MagicLink;
use App\Auth\Session;
use App\Auth\UserRepo;
use App\Core\ArrayStore;
use App\Core\Csrf;
use App\Core\View;
use App\Ics\IcsBuilder;
use App\Landing\LandingController;
use App\Mail\EmailTemplateRepo;
use App\Mail\Postman;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;
use Tests\Support\SpyMailer;

final class LandingPasswordTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    private function controller(Csrf $csrf, Session $session): LandingController
    {
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $users = new UserRepo($this->pdo(), $this->clock);
        $magic = new MagicLink($this->pdo(), $users, $this->clock, 900);
        $postman = new Postman(new SpyMailer(), new IcsBuilder($this->clock), new EmailTemplateRepo($this->pdo()), 'https://crush.app');
        return new LandingController($view, $csrf, $users, $magic, $session, $postman, 'https://crush.app');
    }

    public function test_short_password_rejected(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf, new Session(new ArrayStore()))
            ->start(['name' => 'A', 'email' => 'a@x.test', 'password' => '123'], $csrf->token(), '');
        $this->assertSame(422, $res->status());
    }

    public function test_new_user_sets_password_and_continues(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $session = new Session(new ArrayStore());
        $res = $this->controller($csrf, $session)
            ->start(['name' => 'New', 'email' => 'new@x.test', 'password' => 'secret1'], $csrf->token(), '');
        $this->assertSame(302, $res->status());
        $this->assertSame('/invites/new', $res->headers()['Location']);
        $this->assertTrue($session->check());
        $user = (new UserRepo($this->pdo(), $this->clock))->findByEmail('new@x.test');
        $this->assertTrue(password_verify('secret1', $user['password_hash']));
    }

    public function test_returning_user_correct_password_logs_in(): void
    {
        $users = new UserRepo($this->pdo(), $this->clock);
        $u = $users->create('back@x.test', 'Back', 'magic');
        $users->setPasswordHash($u['id'], password_hash('rightpass', PASSWORD_DEFAULT));
        $csrf = new Csrf(new ArrayStore());
        $session = new Session(new ArrayStore());
        $res = $this->controller($csrf, $session)
            ->start(['name' => 'Back', 'email' => 'back@x.test', 'password' => 'rightpass'], $csrf->token(), '');
        $this->assertSame(302, $res->status());
        $this->assertTrue($session->check());
    }

    public function test_returning_user_wrong_password_rejected(): void
    {
        $users = new UserRepo($this->pdo(), $this->clock);
        $u = $users->create('back2@x.test', 'Back', 'magic');
        $users->setPasswordHash($u['id'], password_hash('rightpass', PASSWORD_DEFAULT));
        $csrf = new Csrf(new ArrayStore());
        $session = new Session(new ArrayStore());
        $res = $this->controller($csrf, $session)
            ->start(['name' => 'Back', 'email' => 'back2@x.test', 'password' => 'wrongpass'], $csrf->token(), '');
        $this->assertSame(401, $res->status());
        $this->assertFalse($session->check());
    }

    public function test_legacy_passwordless_user_claims_password(): void
    {
        $users = new UserRepo($this->pdo(), $this->clock);
        $users->create('legacy@x.test', 'Leg', 'magic');           // no password_hash
        $csrf = new Csrf(new ArrayStore());
        $session = new Session(new ArrayStore());
        $res = $this->controller($csrf, $session)
            ->start(['name' => 'Leg', 'email' => 'legacy@x.test', 'password' => 'newpass1'], $csrf->token(), '');
        $this->assertSame(302, $res->status());
        $this->assertTrue($session->check());
        $this->assertTrue(password_verify('newpass1', $users->findByEmail('legacy@x.test')['password_hash']));
    }
}
