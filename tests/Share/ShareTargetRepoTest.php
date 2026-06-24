<?php
declare(strict_types=1);

namespace Tests\Share;

use App\Share\ShareTargetRepo;
use Tests\Support\DatabaseTestCase;

final class ShareTargetRepoTest extends DatabaseTestCase
{
    public function test_seeded_targets_enabled(): void
    {
        $repo = new ShareTargetRepo($this->pdo());
        $keys = array_column($repo->listEnabled(), 'key');
        foreach (['whatsapp', 'telegram', 'messenger', 'sms', 'x', 'line'] as $k) {
            $this->assertContains($k, $keys);
        }
    }

    public function test_render_encodes_url(): void
    {
        $repo = new ShareTargetRepo($this->pdo());
        $out = $repo->render('https://wa.me/?text={url}', 'https://crush.app/i/AB?x=1');
        $this->assertStringContainsString('https%3A%2F%2Fcrush.app%2Fi%2FAB%3Fx%3D1', $out);
        $this->assertStringNotContainsString('{url}', $out);
    }

    public function test_scheme_allowlist(): void
    {
        $this->assertTrue(ShareTargetRepo::isAllowed('https://wa.me/?text={url}'));
        $this->assertTrue(ShareTargetRepo::isAllowed('sms:?body={url}'));
        $this->assertFalse(ShareTargetRepo::isAllowed('javascript:alert(1)'));
    }

    public function test_set_enabled_and_update(): void
    {
        $repo = new ShareTargetRepo($this->pdo());
        $repo->setEnabled('telegram', false);
        $this->assertNotContains('telegram', array_column($repo->listEnabled(), 'key'));
        $repo->update('telegram', 'Telegram', 'https://t.me/share/url?url={url}', true);
        $this->assertContains('telegram', array_column($repo->listEnabled(), 'key'));
    }
}
