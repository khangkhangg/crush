<?php
declare(strict_types=1);

namespace Tests\Mail;

use App\Auth\MagicLink;
use App\Auth\UserRepo;
use App\Core\ArrayStore;
use App\Core\Csrf;
use App\Core\View;
use App\Ics\IcsBuilder;
use App\Invite\InvitePlaceRepo;
use App\Mail\EmailTemplateRepo;
use App\Invite\InviteRepo;
use App\Invite\ResponseRepo;
use App\Mail\Postman;
use App\Maps\LinkResolver;
use App\Respond\CrushOnboarder;
use App\Respond\RespondController;
use App\Theme\AbEventRepo;
use App\Theme\ABAssigner;
use App\Theme\ThemeRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FakeFetcher;
use Tests\Support\FrozenClock;
use Tests\Support\SpyMailer;

final class MailWiringTest extends DatabaseTestCase
{
    public function test_submit_sends_result_email_to_sender(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $csrf  = new Csrf(new ArrayStore());
        $view  = new View(\dirname(__DIR__, 2) . '/templates');
        $invites = new InviteRepo($this->pdo(), $clock);
        $users   = new UserRepo($this->pdo(), $clock);
        $spy = new SpyMailer();
        $postman = new Postman($spy, new IcsBuilder($clock), new EmailTemplateRepo($this->pdo()), 'https://crush.app');

        $ctrl = new RespondController(
            $view, $csrf, $invites, new ResponseRepo($this->pdo(), $clock), $users,
            new ABAssigner(new ThemeRepo($this->pdo()), $invites, fn(int $m) => 0),
            new AbEventRepo($this->pdo(), $clock), $clock,
            new LinkResolver(new FakeFetcher([])), $postman,
            new CrushOnboarder($users, new MagicLink($this->pdo(), $users, $clock, 900), $postman, 'http://localhost'),
            new InvitePlaceRepo($this->pdo())
        );

        $sender = $users->create('sue@x.test', 'Sue', 'magic');
        $invite = $invites->create([
            'sender_id' => $sender['id'], 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => false, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'expires_at' => '2026-02-01 00:00:00',
        ]);

        $ctrl->submit($invite['public_token'], [
            'chosen_start' => '2026-02-10T19:00', 'meal_choice' => 'dinner',
        ], $csrf->token());

        // result email to sender + confirmation email to crush + welcome email to crush
        $this->assertCount(3, $spy->sent);
        $recipients = array_map(fn($e) => $e->to, $spy->sent);
        $this->assertContains('sue@x.test', $recipients); // sender gets the result
        $this->assertContains('c@x.test', $recipients);   // crush gets the confirmation
        $resultEmail = $spy->sent[array_search('sue@x.test', $recipients)];
        $this->assertNotEmpty($resultEmail->attachments);
    }
}
