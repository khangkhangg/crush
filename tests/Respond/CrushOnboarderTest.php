<?php
declare(strict_types=1);

namespace Tests\Respond;

use App\Auth\MagicLink;
use App\Auth\UserRepo;
use App\Core\View;
use App\Ics\IcsBuilder;
use App\Mail\EmailTemplateRepo;
use App\Mail\Postman;
use App\Respond\CrushOnboarder;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;
use Tests\Support\SpyMailer;

final class CrushOnboarderTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    private function onboarder(SpyMailer $spy): CrushOnboarder
    {
        $users = new UserRepo($this->pdo(), $this->clock);
        $magic = new MagicLink($this->pdo(), $users, $this->clock, 900);
        $postman = new Postman($spy, new IcsBuilder($this->clock), new EmailTemplateRepo($this->pdo()), 'https://crush.app');
        return new CrushOnboarder($users, $magic, $postman, 'https://crush.app');
    }

    public function test_new_email_creates_user_and_welcomes(): void
    {
        $spy = new SpyMailer();
        $this->onboarder($spy)->onboard('crush@x.test', 'Cee');

        $user = (new UserRepo($this->pdo(), $this->clock))->findByEmail('crush@x.test');
        $this->assertNotNull($user);
        $this->assertSame('Cee', $user['name']);
        $this->assertCount(1, $spy->sent);
        $this->assertSame('crush@x.test', $spy->sent[0]->to);
        $this->assertStringContainsString('/auth/magic/', $spy->sent[0]->html);
    }

    public function test_existing_email_is_not_recreated_or_rewelcomed(): void
    {
        (new UserRepo($this->pdo(), $this->clock))->create('crush@x.test', 'Existing', 'magic');
        $spy = new SpyMailer();

        $this->onboarder($spy)->onboard('crush@x.test', 'Cee');

        $this->assertCount(0, $spy->sent);                              // no welcome
        $this->assertSame('Existing', (new UserRepo($this->pdo(), $this->clock))->findByEmail('crush@x.test')['name']); // unchanged
    }
}
