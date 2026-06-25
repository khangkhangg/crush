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

final class AdminTemplatesTest extends DatabaseTestCase
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
        $u = (new UserRepo($this->pdo(), $this->clock))->create('admin@x.test', 'Boss', 'magic');
        $this->pdo()->prepare('UPDATE users SET is_admin = 1 WHERE id = ?')->execute([$u['id']]);
        return $u['id'];
    }

    public function test_templates_list_requires_admin(): void
    {
        $res = $this->controller(new Csrf(new ArrayStore()))->templates(null);
        $this->assertSame(302, $res->status());
        $this->assertSame('/admin/login', $res->headers()['Location']);
    }

    public function test_templates_list_renders(): void
    {
        $res = $this->controller(new Csrf(new ArrayStore()))->templates($this->adminId());
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('welcome', $res->body());
    }

    public function test_edit_renders_exact_row(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf)->editTemplate($this->adminId(), 'welcome', 'vi');
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString($csrf->token(), $res->body());
        $this->assertStringContainsString('name="body_html"', $res->body());
    }

    public function test_save_updates_and_redirects(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $ctrl = $this->controller($csrf);
        $adminId = $this->adminId();
        $res = $ctrl->saveTemplate($adminId, [
            'key' => 'welcome', 'lang' => 'en', 'subject' => 'Hi {{name}}', 'body_html' => '<p>{{link}}</p>',
        ], $csrf->token());
        $this->assertSame(302, $res->status());
        $this->assertSame('Hi {{name}}', (new EmailTemplateRepo($this->pdo()))->getExact('welcome', 'en')['subject']);
    }

    public function test_save_rejects_bad_csrf(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf)->saveTemplate($this->adminId(), ['key' => 'welcome', 'lang' => 'en'], 'wrong');
        $this->assertSame(400, $res->status());
    }

    public function test_edit_unknown_template_returns_404(): void
    {
        $res = $this->controller(new Csrf(new ArrayStore()))->editTemplate($this->adminId(), 'nonexistent', 'xx');
        $this->assertSame(404, $res->status());
    }
}
