<?php
declare(strict_types=1);

namespace Tests\Mail;

use App\Core\View;
use App\Ics\IcsBuilder;
use App\Mail\Postman;
use PHPUnit\Framework\TestCase;
use Tests\Support\FrozenClock;
use Tests\Support\SpyMailer;

final class PostmanTest extends TestCase
{
    private function postman(SpyMailer $spy): Postman
    {
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $ics = new IcsBuilder(new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z')));
        return new Postman($spy, $ics, $view, 'https://crush.app');
    }

    private function invite(array $over = []): array
    {
        return array_merge([
            'id' => 1, 'public_token' => 'tok123', 'crush_email' => 'crush@x.test',
            'crush_name' => 'Cee', 'is_anonymous' => 1, 'message' => 'hi', 'theme_key' => 'midnight',
        ], $over);
    }

    public function test_send_invite_emails_crush_with_link_and_hides_anon_sender(): void
    {
        $spy = new SpyMailer();
        $this->postman($spy)->sendInvite($this->invite());

        $this->assertCount(1, $spy->sent);
        $email = $spy->sent[0];
        $this->assertSame('crush@x.test', $email->to);
        $this->assertStringContainsString('https://crush.app/i/tok123', $email->html);
        $this->assertStringContainsString('secret admirer', $email->html);
    }

    public function test_send_result_attaches_ics_and_validates_href(): void
    {
        $spy = new SpyMailer();
        $invite = $this->invite(['is_anonymous' => 0]);
        $response = [
            'chosen_start' => '2026-02-10 19:00:00', 'chosen_end' => '2026-02-10 21:00:00',
            'meal_choice' => 'dinner', 'meal_wish' => 'sushi', 'crush_contact' => '@cee',
            'pickup_name' => 'Tartine', 'pickup_address' => '1 Main St',
            'pickup_clean_url' => 'javascript:alert(1)', // malicious -> must NOT become an href
        ];
        $sender = ['id' => 2, 'name' => 'Sue', 'email' => 'sue@x.test'];

        $this->postman($spy)->sendResult($invite, $response, $sender);

        $this->assertCount(1, $spy->sent);
        $email = $spy->sent[0];
        $this->assertSame('sue@x.test', $email->to);
        $this->assertNotEmpty($email->attachments);
        $this->assertSame('text/calendar', $email->attachments[0]['mime']);
        $this->assertStringContainsString('BEGIN:VCALENDAR', $email->attachments[0]['content']);
        // The javascript: url must never appear inside an href attribute.
        $this->assertStringNotContainsString('href="javascript:', $email->html);
    }

    public function test_safe_href_rejects_non_http(): void
    {
        $this->assertSame('https://x.test', Postman::safeHref('https://x.test'));
        $this->assertSame('http://x.test', Postman::safeHref('http://x.test'));
        $this->assertNull(Postman::safeHref('javascript:alert(1)'));
        $this->assertNull(Postman::safeHref(null));
    }
}
