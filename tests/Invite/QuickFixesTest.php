<?php
declare(strict_types=1);

namespace Tests\Invite;

use App\Auth\UserRepo;
use App\Core\ArrayStore;
use App\Core\Csrf;
use App\Core\View;
use App\Ics\IcsBuilder;
use App\Invite\InviteController;
use App\Invite\InvitePlaceRepo;
use App\Invite\InviteRepo;
use App\Mail\EmailTemplateRepo;
use App\Mail\Postman;
use App\Maps\LinkResolver;
use App\Security\BlockRepo;
use App\Security\RateLimiter;
use App\Share\ShareTargetRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FakeFetcher;
use Tests\Support\FrozenClock;
use Tests\Support\SpyMailer;

final class QuickFixesTest extends DatabaseTestCase
{
    public function test_crush_name_required_and_panel_animates(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $ctrl = new InviteController(
            $view, new Csrf(new ArrayStore()), new InviteRepo($this->pdo(), $clock), new UserRepo($this->pdo(), $clock),
            $clock, 'http://localhost',
            new Postman(new SpyMailer(), new IcsBuilder($clock), new EmailTemplateRepo($this->pdo()), 'http://localhost'),
            new RateLimiter($this->pdo(), $clock), new BlockRepo($this->pdo(), $clock),
            new InvitePlaceRepo($this->pdo()), new LinkResolver(new FakeFetcher([])), new ShareTargetRepo($this->pdo())
        );
        $uid = (new UserRepo($this->pdo(), $clock))->create('u@x.test', 'U', 'magic')['id'];
        $body = $ctrl->showNew($uid)->body();
        $this->assertMatchesRegularExpression('/name="crush_name"[^>]*\brequired\b/', $body);
        $this->assertStringNotContainsString('#placePanel.hide { display:none; }', $body);  // no hard toggle
        $this->assertStringContainsString('#placePanel.show', $body);                        // animated open
    }

    public function test_copy_button_stretches_to_input_height(): void
    {
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $html = $view->render('invite/created', ['title' => 'x', 'link' => 'https://c.app/i/t', 'invite' => ['crush_name' => 'A', 'crush_email' => 'a@x.test'], 'shareLinks' => []]);
        $this->assertStringContainsString('align-items:stretch', $html);   // copy row stretches the button
    }
}
