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

final class InviteSenderLangTest extends DatabaseTestCase
{
    public function test_invite_lang_copied_from_sender(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $csrf = new Csrf(new ArrayStore());
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $invites = new InviteRepo($this->pdo(), $clock);
        $users = new UserRepo($this->pdo(), $clock);
        $postman = new Postman(new SpyMailer(), new IcsBuilder($clock), new EmailTemplateRepo($this->pdo()), 'http://localhost');
        $ctrl = new InviteController(
            $view, $csrf, $invites, $users, $clock, 'http://localhost', $postman,
            new RateLimiter($this->pdo(), $clock), new BlockRepo($this->pdo(), $clock),
            new InvitePlaceRepo($this->pdo()), new LinkResolver(new FakeFetcher([])),
            new ShareTargetRepo($this->pdo())
        );

        $sender = $users->create('sue@x.test', 'Sue', 'magic');
        $users->setLang($sender['id'], 'ko');
        $ctrl->create($sender['id'], ['crush_email' => 'c@x.test', 'date_mode' => 'instant'], $csrf->token());

        $invite = $invites->listBySender($sender['id'])[0];
        $this->assertSame('ko', $invite['lang']); // invite carries the sender's language
    }
}
