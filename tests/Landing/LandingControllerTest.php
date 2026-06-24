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

final class LandingControllerTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    private function controller(Csrf $csrf, Session $session, SpyMailer $spy): LandingController
    {
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $users = new UserRepo($this->pdo(), $this->clock);
        $magic = new MagicLink($this->pdo(), $users, $this->clock, 900);
        $postman = new Postman($spy, new IcsBuilder($this->clock), new EmailTemplateRepo($this->pdo()), 'https://crush.app');
        return new LandingController($view, $csrf, $users, $magic, $session, $postman, 'https://crush.app');
    }

    public function test_home_renders_for_logged_out(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf, new Session(new ArrayStore()), new SpyMailer())->home(null);
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('name="email"', $res->body());
    }

    public function test_bad_csrf_is_400(): void
    {
        $res = $this->controller(new Csrf(new ArrayStore()), new Session(new ArrayStore()), new SpyMailer())
            ->start(['name' => 'A', 'email' => 'a@x.test'], 'wrong', '');
        $this->assertSame(400, $res->status());
    }

    public function test_invalid_email_is_422(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf, new Session(new ArrayStore()), new SpyMailer())
            ->start(['name' => 'A', 'email' => 'nope'], $csrf->token(), '');
        $this->assertSame(422, $res->status());
    }

    public function test_new_email_creates_logs_in_sets_lang_and_welcomes(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $session = new Session(new ArrayStore());
        $spy = new SpyMailer();
        $res = $this->controller($csrf, $session, $spy)
            ->start(['name' => 'New', 'email' => 'new@x.test', 'password' => 'secret1'], $csrf->token(), 'vi-VN,vi;q=0.9');

        $this->assertSame(302, $res->status());
        $this->assertSame('/invites/new', $res->headers()['Location']);
        $this->assertTrue($session->check());
        $user = (new UserRepo($this->pdo(), $this->clock))->findByEmail('new@x.test');
        $this->assertSame('vi', $user['lang']);                    // detected + stored
        $this->assertCount(1, $spy->sent);                          // welcome email
        $this->assertSame('new@x.test', $spy->sent[0]->to);
        $this->assertStringContainsString('Chao mung', $spy->sent[0]->subject); // vi welcome subject
    }

    public function test_existing_email_logs_in_without_welcome(): void
    {
        (new UserRepo($this->pdo(), $this->clock))->create('dupe@x.test', 'Dee', 'magic');
        $csrf = new Csrf(new ArrayStore());
        $session = new Session(new ArrayStore());
        $spy = new SpyMailer();
        $res = $this->controller($csrf, $session, $spy)
            ->start(['name' => 'Dee', 'email' => 'dupe@x.test', 'password' => 'secret1'], $csrf->token(), '');

        $this->assertSame(302, $res->status());
        $this->assertSame('/invites/new', $res->headers()['Location']);
        $this->assertTrue($session->check());                       // logged in
        $this->assertCount(0, $spy->sent);                          // NO welcome email
    }

    public function test_switch_account_logs_out(): void
    {
        $session = new Session(new ArrayStore());
        $session->login(7);
        $res = $this->controller(new Csrf(new ArrayStore()), $session, new SpyMailer())->switchAccount();
        $this->assertSame(302, $res->status());
        $this->assertSame('/', $res->headers()['Location']);
        $this->assertFalse($session->check());
    }
}
