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

final class AdminModerationTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    private function adminId(): int
    {
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $u = (new UserRepo($this->pdo(), $this->clock))->create('admin@x.test', 'Boss', 'magic');
        $this->pdo()->prepare('UPDATE users SET is_admin = 1 WHERE id = ?')->execute([$u['id']]);
        return $u['id'];
    }

    private function controller(Csrf $csrf): AdminController
    {
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        return new AdminController(
            $view, $csrf, new UserRepo($this->pdo(), $this->clock), new SettingsRepo($this->pdo()),
            new ThemeRepo($this->pdo()), new AbEventRepo($this->pdo(), $this->clock),
            new InviteRepo($this->pdo(), $this->clock), new BlockRepo($this->pdo(), $this->clock), 'http://localhost',
            new EmailTemplateRepo($this->pdo()), new ShareTargetRepo($this->pdo())
        );
    }

    public function test_moderation_lists_invites(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $adminId = $this->adminId();
        (new InviteRepo($this->pdo(), $this->clock))->create([
            'sender_id' => $adminId, 'crush_email' => 'target@x.test', 'crush_name' => 'T',
            'is_anonymous' => false, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'expires_at' => '2026-02-01 00:00:00',
        ]);

        $res = $this->controller($csrf)->moderation($adminId);
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('target@x.test', $res->body());
    }

    public function test_admin_block_records_block(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $adminId = $this->adminId();
        $res = $this->controller($csrf)->blockFromAdmin($adminId, [
            'sender_id' => (string) $adminId, 'crush_email' => 'x@x.test',
        ], $csrf->token());
        $this->assertSame(302, $res->status());
        $this->assertTrue((new BlockRepo($this->pdo(), $this->clock))->isBlocked($adminId, 'x@x.test'));
    }
}
