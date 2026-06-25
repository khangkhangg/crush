<?php
declare(strict_types=1);

namespace Tests\Admin;

use App\Admin\AdminController;
use App\Auth\UserRepo;
use App\Core\ArrayStore;
use App\Core\Csrf;
use App\Core\View;
use App\I18n\Translator;
use App\Invite\InviteRepo;
use App\Mail\EmailTemplateRepo;
use App\Security\BlockRepo;
use App\Settings\SettingsRepo;
use App\Share\ShareTargetRepo;
use App\Theme\AbEventRepo;
use App\Theme\ThemeRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class AdminMailTestTest extends DatabaseTestCase
{
    private function admin(): array
    {
        $pdo = $this->pdo();
        $user = (new UserRepo($pdo, new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'))))
            ->create('admin@x.test', 'Boss', 'magic');
        $pdo->prepare('UPDATE users SET is_admin = 1 WHERE id = ?')->execute([$user['id']]);
        return $user;
    }

    private function controller(Csrf $csrf): AdminController
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        return new AdminController(
            $view, $csrf, new UserRepo($this->pdo(), $clock), new SettingsRepo($this->pdo()),
            new ThemeRepo($this->pdo()), new AbEventRepo($this->pdo(), $clock),
            new InviteRepo($this->pdo(), $clock), new BlockRepo($this->pdo(), $clock), 'http://localhost',
            new EmailTemplateRepo($this->pdo()), new ShareTargetRepo($this->pdo()),
            new Translator($this->pdo(), 'en')
        );
    }

    public function test_telegram_unconfigured_returns_failure_flash(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $admin = $this->admin();
        $ctrl = $this->controller($csrf);

        // Telegram not configured (no bot token / chat id in settings)
        $res = $ctrl->testProvider($admin['id'], 'telegram', $csrf->token());

        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('Telegram test failed', $res->body());
    }

    public function test_resend_empty_key_returns_failure_flash(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $admin = $this->admin();
        $ctrl = $this->controller($csrf);

        // resend_api_key is empty by default — verify() will throw
        $res = $ctrl->testProvider($admin['id'], 'resend', $csrf->token());

        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('Resend test failed', $res->body());
    }

    public function test_non_admin_returns_403_redirect(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $nonAdmin = (new UserRepo($this->pdo(), $clock))->create('user@x.test', 'User', 'magic');
        $ctrl = $this->controller($csrf);

        $res = $ctrl->testProvider($nonAdmin['id'], 'resend', $csrf->token());

        // forbidden() returns a 302 redirect to /admin/login
        $this->assertSame(302, $res->status());
    }

    public function test_bad_csrf_returns_400(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $admin = $this->admin();
        $ctrl = $this->controller($csrf);

        $res = $ctrl->testProvider($admin['id'], 'resend', 'bad-token');

        $this->assertSame(400, $res->status());
    }

    public function test_unknown_provider_returns_flash(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $admin = $this->admin();
        $ctrl = $this->controller($csrf);

        $res = $ctrl->testProvider($admin['id'], 'foobar', $csrf->token());

        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('Unknown provider', $res->body());
    }
}
