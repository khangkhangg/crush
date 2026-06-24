<?php
declare(strict_types=1);

namespace Tests\Respond;

use App\Auth\MagicLink;
use App\Auth\UserRepo;
use App\Mail\EmailTemplateRepo;
use App\Mail\Postman;
use App\Respond\CrushOnboarder;
use App\Core\View;
use App\Ics\IcsBuilder;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;
use Tests\Support\SpyMailer;

final class CrushLangTest extends DatabaseTestCase
{
    public function test_onboard_sets_crush_lang_and_localized_welcome(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $users = new UserRepo($this->pdo(), $clock);
        $spy = new SpyMailer();
        $postman = new Postman($spy, new IcsBuilder($clock), new EmailTemplateRepo($this->pdo()), 'http://localhost');
        $onboarder = new CrushOnboarder($users, new MagicLink($this->pdo(), $users, $clock, 900), $postman, 'http://localhost');

        $onboarder->onboard('crush@x.test', 'Cee', 'vi');

        $this->assertSame('vi', $users->findByEmail('crush@x.test')['lang']);
        $this->assertStringContainsString('Chào mừng', $spy->sent[0]->subject); // vi welcome (accented)
    }
}
