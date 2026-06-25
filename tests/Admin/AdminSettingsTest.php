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

final class AdminSettingsTest extends DatabaseTestCase
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

    public function test_save_settings_persists_whitelisted_keys(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $admin = $this->admin();
        $ctrl = $this->controller($csrf);

        $res = $ctrl->saveSettings($admin['id'], [
            'mail_driver' => 'resend', 'from_email' => 'love@crush.app', 'evil_key' => 'nope',
        ], $csrf->token());

        $this->assertSame(302, $res->status());
        $settings = new SettingsRepo($this->pdo());
        $this->assertSame('resend', $settings->get('mail_driver'));
        $this->assertSame('love@crush.app', $settings->get('from_email'));
        $this->assertNull($settings->get('evil_key')); // not whitelisted
    }

    public function test_save_settings_rejects_bad_csrf(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $admin = $this->admin();
        $this->assertSame(400, $this->controller($csrf)->saveSettings($admin['id'], [], 'wrong')->status());
    }
}
