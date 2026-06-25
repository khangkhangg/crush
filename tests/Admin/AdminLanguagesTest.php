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

final class AdminLanguagesTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    private function controller(Csrf $csrf): AdminController
    {
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        return new AdminController(
            $view, $csrf, new UserRepo($this->pdo(), $this->clock), new SettingsRepo($this->pdo()),
            new ThemeRepo($this->pdo()), new AbEventRepo($this->pdo(), $this->clock),
            new InviteRepo($this->pdo(), $this->clock), new BlockRepo($this->pdo(), $this->clock),
            'http://localhost', new EmailTemplateRepo($this->pdo()), new ShareTargetRepo($this->pdo()),
            new Translator($this->pdo(), 'en')
        );
    }

    private function adminId(): int
    {
        $u = (new UserRepo($this->pdo(), $this->clock))->create('a@x.test', 'B', 'magic');
        $this->pdo()->prepare('UPDATE users SET is_admin = 1 WHERE id = ?')->execute([$u['id']]);
        return $u['id'];
    }

    public function test_list_requires_admin(): void
    {
        $this->assertSame(403, $this->controller(new Csrf(new ArrayStore()))->languages(null)->status());
    }

    public function test_list_shows_languages(): void
    {
        $res = $this->controller(new Csrf(new ArrayStore()))->languages($this->adminId());
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('Tiếng Việt', $res->body());
    }

    public function test_save_upserts_translation(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf)->saveLanguage($this->adminId(), [
            'lang' => 'vi', 'keys' => ['Start'], 'values' => ['Bắt đầu'],
        ], $csrf->token());
        $this->assertSame(302, $res->status());
        $this->assertSame('Bắt đầu', (new Translator($this->pdo(), 'vi'))->t('Start'));
    }

    public function test_save_rejects_bad_csrf(): void
    {
        $this->assertSame(400, $this->controller(new Csrf(new ArrayStore()))->saveLanguage($this->adminId(), ['lang' => 'vi'], 'wrong')->status());
    }
}
