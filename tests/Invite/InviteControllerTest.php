<?php
declare(strict_types=1);

namespace Tests\Invite;

use App\Auth\UserRepo;
use App\Core\ArrayStore;
use App\Core\Csrf;
use App\Core\View;
use App\Ics\IcsBuilder;
use App\Invite\InviteController;
use App\Invite\InviteRepo;
use App\Mail\Postman;
use App\Security\BlockRepo;
use App\Security\RateLimiter;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;
use Tests\Support\SpyMailer;

final class InviteControllerTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    private function controller(Csrf $csrf): InviteController
    {
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        return new InviteController(
            $view, $csrf,
            new InviteRepo($this->pdo(), $this->clock),
            new UserRepo($this->pdo(), $this->clock),
            $this->clock, 'http://localhost',
            new Postman(new SpyMailer(), new IcsBuilder($this->clock), $view, 'http://localhost'),
            new RateLimiter($this->pdo(), $this->clock),
            new BlockRepo($this->pdo(), $this->clock),
        );
    }

    private function sender(): int
    {
        $c = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        return (new UserRepo($this->pdo(), $c))->create('s@x.test', 'Sue', 'magic')['id'];
    }

    public function test_dashboard_redirects_when_anonymous(): void
    {
        $res = $this->controller(new Csrf(new ArrayStore()))->dashboard(null);
        $this->assertSame(302, $res->status());
        $this->assertSame('/login', $res->headers()['Location']);
    }

    public function test_new_form_renders_with_csrf(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf)->showNew($this->sender());
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString($csrf->token(), $res->body());
        $this->assertStringContainsString('name="crush_email"', $res->body());
    }

    public function test_create_rejects_bad_csrf(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf)->create($this->sender(), [
            'crush_email' => 'c@x.test', 'date_mode' => 'instant',
        ], 'wrong');
        $this->assertSame(400, $res->status());
    }

    public function test_create_rejects_invalid_email(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf)->create($this->sender(), [
            'crush_email' => 'not-an-email', 'date_mode' => 'instant',
        ], $csrf->token());
        $this->assertSame(422, $res->status());
    }

    public function test_create_success_redirects_to_created(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $ctrl = $this->controller($csrf);
        $res = $ctrl->create($this->sender(), [
            'crush_email' => 'c@x.test', 'crush_name' => 'Cee', 'message' => 'hi',
            'date_mode' => 'instant', 'is_anonymous' => '1',
        ], $csrf->token());

        $this->assertSame(302, $res->status());
        $this->assertStringContainsString('/created', $res->headers()['Location']);
    }
}
