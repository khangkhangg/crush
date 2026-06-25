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

final class AdminAuthTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    private function controller(): AdminController
    {
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        return new AdminController(
            $view, new Csrf(new ArrayStore()),
            new UserRepo($this->pdo(), $this->clock),
            new SettingsRepo($this->pdo()),
            new ThemeRepo($this->pdo()),
            new AbEventRepo($this->pdo(), $this->clock),
            new InviteRepo($this->pdo(), $this->clock),
            new BlockRepo($this->pdo(), $this->clock),
            'http://localhost',
            new EmailTemplateRepo($this->pdo()),
            new ShareTargetRepo($this->pdo()),
            new Translator($this->pdo(), 'en')
        );
    }

    public function test_logged_out_is_forbidden(): void
    {
        $res = $this->controller()->dashboard(null);
        $this->assertSame(302, $res->status());
        $this->assertSame('/admin/login', $res->headers()['Location']);
    }

    public function test_non_admin_is_forbidden(): void
    {
        $user = (new UserRepo($this->pdo(), new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'))))
            ->create('plain@x.test', 'Plain', 'magic');
        $res = $this->controller()->dashboard($user['id']);
        $this->assertSame(302, $res->status());
        $this->assertSame('/admin/login', $res->headers()['Location']);
    }

    public function test_admin_sees_dashboard(): void
    {
        $pdo = $this->pdo();
        $user = (new UserRepo($pdo, new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'))))
            ->create('admin@x.test', 'Boss', 'magic');
        $pdo->prepare('UPDATE users SET is_admin = 1 WHERE id = ?')->execute([$user['id']]);

        $res = $this->controller()->dashboard($user['id']);
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('Admin', $res->body());
    }
}
