<?php
declare(strict_types=1);

namespace Tests\Mail;

use App\Ics\IcsBuilder;
use App\Mail\EmailTemplateRepo;
use App\Mail\Postman;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;
use Tests\Support\SpyMailer;

final class PostmanTest extends DatabaseTestCase
{
    private function postman(SpyMailer $spy): Postman
    {
        $ics = new IcsBuilder(new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z')));
        return new Postman($spy, $ics, new EmailTemplateRepo($this->pdo()), 'https://crush.app');
    }

    public function test_welcome_uses_template_and_lang(): void
    {
        $spy = new SpyMailer();
        $this->postman($spy)->sendWelcome('a@x.test', 'Ann', 'https://crush.app/auth/magic/t', 'vi');
        $this->assertCount(1, $spy->sent);
        $this->assertStringContainsString('Chào mừng', $spy->sent[0]->subject); // vi subject (accented)
        $this->assertStringContainsString('https://crush.app/auth/magic/t', $spy->sent[0]->html);
    }

    public function test_invite_hides_anonymous_and_uses_invite_lang(): void
    {
        $spy = new SpyMailer();
        $invite = ['public_token' => 'tok', 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
                   'is_anonymous' => 1, 'message' => 'hi', 'theme_key' => 'midnight', 'lang' => 'en'];
        $this->postman($spy)->sendInvite($invite);
        $email = $spy->sent[0];
        $this->assertSame('c@x.test', $email->to);
        $this->assertStringContainsString('secret admirer', $email->html);
        $this->assertStringContainsString('https://crush.app/i/tok', $email->html);
        $this->assertStringContainsString('https://crush.app/unsubscribe/tok', $email->html);
    }

    public function test_result_attaches_ics_and_safe_href(): void
    {
        $spy = new SpyMailer();
        $invite = ['public_token' => 'tok', 'crush_name' => 'Cee', 'is_anonymous' => 0];
        $response = ['chosen_start' => '2026-02-10 19:00:00', 'chosen_end' => '2026-02-10 21:00:00',
                     'meal_choice' => 'dinner', 'pickup_name' => 'Tartine', 'pickup_address' => '1 Main St',
                     'pickup_clean_url' => 'javascript:alert(1)'];
        $sender = ['id' => 2, 'name' => 'Sue', 'email' => 'sue@x.test', 'lang' => 'en'];
        $this->postman($spy)->sendResult($invite, $response, $sender);
        $email = $spy->sent[0];
        $this->assertSame('sue@x.test', $email->to);
        $this->assertNotEmpty($email->attachments);
        $this->assertStringContainsString('BEGIN:VCALENDAR', $email->attachments[0]['content']);
        $this->assertStringNotContainsString('href="javascript:', $email->html); // unsafe href dropped
    }

    public function test_send_magic(): void
    {
        $spy = new SpyMailer();
        $this->postman($spy)->sendMagic('a@x.test', 'https://crush.app/auth/magic/t', 'ko');
        $this->assertCount(1, $spy->sent);
        $this->assertStringContainsString('https://crush.app/auth/magic/t', $spy->sent[0]->html);
    }

    public function test_safe_href_static(): void
    {
        $this->assertSame('https://x.test', Postman::safeHref('https://x.test'));
        $this->assertNull(Postman::safeHref('javascript:alert(1)'));
        $this->assertNull(Postman::safeHref(null));
    }
}
