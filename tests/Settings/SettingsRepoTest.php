<?php
declare(strict_types=1);

namespace Tests\Settings;

use App\Settings\SettingsRepo;
use Tests\Support\DatabaseTestCase;

final class SettingsRepoTest extends DatabaseTestCase
{
    public function test_get_set_upsert_and_all(): void
    {
        $repo = new SettingsRepo($this->pdo());
        $this->assertNull($repo->get('mail_driver'));
        $this->assertSame('php', $repo->get('mail_driver', 'php'));

        $repo->set('mail_driver', 'resend');
        $this->assertSame('resend', $repo->get('mail_driver'));

        // upsert overwrites
        $repo->set('mail_driver', 'smtp');
        $this->assertSame('smtp', $repo->get('mail_driver'));

        $repo->set('from_email', 'love@crush.app');
        $all = $repo->all();
        $this->assertSame('smtp', $all['mail_driver']);
        $this->assertSame('love@crush.app', $all['from_email']);
    }
}
