<?php
declare(strict_types=1);

namespace Tests\Admin;

use App\Admin\AdminController;
use App\Auth\UserRepo;
use App\Core\ArrayStore;
use App\Core\Csrf;
use App\Core\View;
use App\Invite\InviteRepo;
use App\Mail\EmailTemplateRepo;
use App\Security\BlockRepo;
use App\Settings\SettingsRepo;
use App\Share\ShareTargetRepo;
use App\Theme\AbEventRepo;
use App\Theme\ThemeRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class AdminShareCreateTest extends DatabaseTestCase
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
            'http://localhost', new EmailTemplateRepo($this->pdo()), new ShareTargetRepo($this->pdo())
        );
    }

    private function adminId(): int
    {
        $u = (new UserRepo($this->pdo(), $this->clock))->create('admin@x.test', 'Boss', 'magic');
        $this->pdo()->prepare('UPDATE users SET is_admin = 1 WHERE id = ?')->execute([$u['id']]);
        return $u['id'];
    }

    public function test_create_adds_target(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $ctrl = $this->controller($csrf);
        $res = $ctrl->createShare($this->adminId(), [
            'key' => 'reddit', 'label' => 'Reddit', 'icon' => 'ic-share',
            'url_template' => 'https://www.reddit.com/submit?url={url}', 'enabled' => '1',
        ], $csrf->token());
        $this->assertSame(302, $res->status());
        $this->assertNotNull((new ShareTargetRepo($this->pdo()))->getExact('reddit'));
    }

    public function test_create_rejects_unsafe(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf)->createShare($this->adminId(), [
            'key' => 'evil', 'label' => 'Evil', 'icon' => 'ic-share', 'url_template' => 'javascript:alert(1)',
        ], $csrf->token());
        $this->assertSame(422, $res->status());
    }

    public function test_create_rejects_duplicate_key(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf)->createShare($this->adminId(), [
            'key' => 'whatsapp', 'label' => 'Dup', 'icon' => 'ic-whatsapp', 'url_template' => 'https://wa.me/?text={url}',
        ], $csrf->token());
        $this->assertSame(422, $res->status());
    }

    public function test_create_requires_admin(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $this->assertSame(403, $this->controller($csrf)->createShare(null, [], $csrf->token())->status());
    }
}
