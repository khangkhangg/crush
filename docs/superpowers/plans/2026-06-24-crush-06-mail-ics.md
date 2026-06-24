# Crush — Plan 6: Mail + Calendar (.ics) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Send the two transactional emails — the themed invite to the crush, and the result (with a calendar `.ics` attachment) to the sender — through an admin-selectable mail driver (Resend API / SMTP / PHP `mail()`). Replace the dev magic-link file bridge with real email.

**Architecture:** New `app/Mail` (an `Email` DTO, a `Mailer` interface with three thin driver adapters, a `MailerFactory` that reads the active driver + credentials from a new `SettingsRepo`, and a `Postman` that composes + sends the two emails) and `app/Ics` (`IcsBuilder`, pure RFC-5545 generation). Composition logic is tested with a `SpyMailer`; the network drivers are thin and manually verified.

**Tech Stack:** PHP 8.1+, PDO MySQL, PHPUnit 10, cURL. Adds `phpmailer/phpmailer` (only for the SMTP driver — matches the original spec's dependency list).

## Global Constraints

- PHP floor 8.1. PSR-4. Only new dependency: `phpmailer/phpmailer`.
- **Icons only — never emojis** in email HTML. All dynamic values HTML-escaped in email templates via `App\Core\e()`.
- **`href` scheme safety:** `pickup_clean_url` may contain raw user input (e.g. `javascript:`). Before using ANY url as an `href` in an email, validate the scheme is `http`/`https`; otherwise render it as plain text only. (Carried from Plan 5.)
- Mailer credentials/driver come from the `settings` table (admin-managed in Plan 7), never committed.
- Resolution/sending must not crash the request: a mail failure is caught and logged, not fatal to the user's action.
- Integration tests use MySQL `crush_test`. `.ics` uses CRLF line endings and UTC timestamps.

## File Structure

- `app/Settings/SettingsRepo.php` — key/value settings access.
- `app/Ics/IcsBuilder.php` — RFC-5545 VEVENT generation.
- `app/Mail/Email.php` — DTO (to, subject, html, attachments).
- `app/Mail/Mailer.php` — interface.
- `app/Mail/ResendMailer.php`, `SmtpMailer.php`, `PhpMailMailer.php` — drivers.
- `app/Mail/MailerFactory.php` — builds the active driver from settings.
- `app/Mail/Postman.php` — composes + sends invite/result emails.
- `tests/Support/SpyMailer.php` — records sent emails.
- `templates/email/invite.php`, `templates/email/result.php`.
- Modify: `app/Auth/AuthController.php`, `app/Invite/InviteController.php`, `app/Respond/RespondController.php`, `public/index.php`.

---

### Task 1: SettingsRepo

**Files:**
- Create: `app/Settings/SettingsRepo.php`
- Test: `tests/Settings/SettingsRepoTest.php`

**Interfaces:**
- Consumes: `\PDO`.
- Produces: `App\Settings\SettingsRepo` with `__construct(\PDO $pdo)`, `get(string $key, ?string $default = null): ?string`, `set(string $key, string $value): void` (upsert), `all(): array<string,string>`.

- [ ] **Step 1: Write the failing test** — `tests/Settings/SettingsRepoTest.php`

```php
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
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter SettingsRepoTest`
Expected: FAIL — `Class "App\Settings\SettingsRepo" not found`.

- [ ] **Step 3: Write `app/Settings/SettingsRepo.php`**

```php
<?php
declare(strict_types=1);

namespace App\Settings;

final class SettingsRepo
{
    public function __construct(private \PDO $pdo) {}

    public function get(string $key, ?string $default = null): ?string
    {
        $stmt = $this->pdo->prepare('SELECT `value` FROM settings WHERE `key` = ?');
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val === false ? $default : (string) $val;
    }

    public function set(string $key, string $value): void
    {
        $this->pdo->prepare(
            'INSERT INTO settings (`key`, `value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
        )->execute([$key, $value]);
    }

    /** @return array<string,string> */
    public function all(): array
    {
        $out = [];
        foreach ($this->pdo->query('SELECT `key`, `value` FROM settings') as $row) {
            $out[$row['key']] = (string) $row['value'];
        }
        return $out;
    }
}
```

- [ ] **Step 4: Run to verify it passes** — Run: `vendor/bin/phpunit --filter SettingsRepoTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Settings/SettingsRepo.php tests/Settings/SettingsRepoTest.php
git commit -m "feat(settings): SettingsRepo key/value access"
```

---

### Task 2: IcsBuilder

**Files:**
- Create: `app/Ics/IcsBuilder.php`
- Test: `tests/Ics/IcsBuilderTest.php`

**Interfaces:**
- Consumes: `App\Core\Clock`.
- Produces: `App\Ics\IcsBuilder` with `__construct(Clock $clock)` and `build(array $event): string`. `$event` keys: `uid` (string), `summary` (string), `start` (`Y-m-d H:i:s`, UTC), `end` (`Y-m-d H:i:s`, UTC), `location` (?string), `description` (?string). Output is a full VCALENDAR with one VEVENT, a `-PT1H` VALARM, CRLF line endings, ICS-escaped text fields, and UTC timestamps (`Ymd\THis\Z`).

- [ ] **Step 1: Write the failing test** — `tests/Ics/IcsBuilderTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Ics;

use App\Ics\IcsBuilder;
use PHPUnit\Framework\TestCase;
use Tests\Support\FrozenClock;

final class IcsBuilderTest extends TestCase
{
    private function builder(): IcsBuilder
    {
        return new IcsBuilder(new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z')));
    }

    public function test_builds_valid_vevent(): void
    {
        $ics = $this->builder()->build([
            'uid'         => 'abc-123',
            'summary'     => 'Date with Sue',
            'start'       => '2026-02-10 19:00:00',
            'end'         => '2026-02-10 21:00:00',
            'location'    => 'Tartine Bakery, 1 Main St',
            'description' => 'Dinner; sushi',
        ]);

        $this->assertStringContainsString("BEGIN:VCALENDAR", $ics);
        $this->assertStringContainsString("BEGIN:VEVENT", $ics);
        $this->assertStringContainsString("UID:abc-123", $ics);
        $this->assertStringContainsString("SUMMARY:Date with Sue", $ics);
        $this->assertStringContainsString("DTSTART:20260210T190000Z", $ics);
        $this->assertStringContainsString("DTEND:20260210T210000Z", $ics);
        $this->assertStringContainsString("DTSTAMP:20260101T000000Z", $ics);
        // ICS escaping: comma and semicolon are backslash-escaped.
        $this->assertStringContainsString("LOCATION:Tartine Bakery\\, 1 Main St", $ics);
        $this->assertStringContainsString("DESCRIPTION:Dinner\\; sushi", $ics);
        $this->assertStringContainsString("BEGIN:VALARM", $ics);
        $this->assertStringContainsString("TRIGGER:-PT1H", $ics);
        $this->assertStringContainsString("\r\n", $ics);
    }

    public function test_optional_fields_omitted_when_null(): void
    {
        $ics = $this->builder()->build([
            'uid' => 'x', 'summary' => 'S',
            'start' => '2026-02-10 19:00:00', 'end' => '2026-02-10 20:00:00',
            'location' => null, 'description' => null,
        ]);
        $this->assertStringNotContainsString("LOCATION:", $ics);
        $this->assertStringNotContainsString("DESCRIPTION:", $ics);
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter IcsBuilderTest`
Expected: FAIL — `Class "App\Ics\IcsBuilder" not found`.

- [ ] **Step 3: Write `app/Ics/IcsBuilder.php`**

```php
<?php
declare(strict_types=1);

namespace App\Ics;

use App\Core\Clock;

final class IcsBuilder
{
    public function __construct(private Clock $clock) {}

    public function build(array $event): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Crush//Date Invite//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:REQUEST',
            'BEGIN:VEVENT',
            'UID:' . $this->text((string) $event['uid']),
            'DTSTAMP:' . $this->stamp($this->clock->now()->format('Y-m-d H:i:s')),
            'DTSTART:' . $this->stamp((string) $event['start']),
            'DTEND:' . $this->stamp((string) $event['end']),
            'SUMMARY:' . $this->text((string) $event['summary']),
        ];

        if (!empty($event['location'])) {
            $lines[] = 'LOCATION:' . $this->text((string) $event['location']);
        }
        if (!empty($event['description'])) {
            $lines[] = 'DESCRIPTION:' . $this->text((string) $event['description']);
        }

        $lines = array_merge($lines, [
            'BEGIN:VALARM',
            'TRIGGER:-PT1H',
            'ACTION:DISPLAY',
            'DESCRIPTION:Reminder',
            'END:VALARM',
            'END:VEVENT',
            'END:VCALENDAR',
        ]);

        return implode("\r\n", $lines) . "\r\n";
    }

    private function stamp(string $ymdhis): string
    {
        return (new \DateTimeImmutable($ymdhis, new \DateTimeZone('UTC')))->format('Ymd\THis\Z');
    }

    private function text(string $v): string
    {
        return str_replace(
            ['\\', "\n", ',', ';'],
            ['\\\\', '\\n', '\\,', '\\;'],
            $v
        );
    }
}
```

- [ ] **Step 4: Run to verify it passes** — Run: `vendor/bin/phpunit --filter IcsBuilderTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Ics/IcsBuilder.php tests/Ics/IcsBuilderTest.php
git commit -m "feat(ics): RFC-5545 calendar builder"
```

---

### Task 3: Email DTO + Mailer interface + drivers + factory

**Files:**
- Create: `app/Mail/Email.php`
- Create: `app/Mail/Mailer.php`
- Create: `tests/Support/SpyMailer.php`
- Create: `app/Mail/PhpMailMailer.php`
- Create: `app/Mail/ResendMailer.php`
- Create: `app/Mail/SmtpMailer.php`
- Create: `app/Mail/MailerFactory.php`
- Test: `tests/Mail/MailerFactoryTest.php`
- Modify: `composer.json` (add `phpmailer/phpmailer`)

**Interfaces:**
- Produces:
  - `App\Mail\Email` — `__construct(public string $to, public string $subject, public string $html, public array $attachments = [])`. Each attachment: `['filename' => , 'mime' => , 'content' => ]`.
  - `App\Mail\Mailer` interface: `send(Email $email): void` (throws `\RuntimeException` on failure).
  - `Tests\Support\SpyMailer implements Mailer` — records `$sent` (array of `Email`).
  - `App\Mail\PhpMailMailer`, `ResendMailer`, `SmtpMailer` — thin driver adapters (`__construct` takes the settings it needs).
  - `App\Mail\MailerFactory` with static `make(App\Settings\SettingsRepo $settings): Mailer` — reads `mail_driver` (`resend`|`smtp`|`php`, default `php`) and constructs the driver with `from_email`/`from_name` + driver creds from settings.

- [ ] **Step 1: Add the SMTP dependency** — Run: `composer require phpmailer/phpmailer:^6.9`
Expected: `phpmailer/phpmailer` added to `composer.json`/`composer.lock`, `vendor/` updated.

- [ ] **Step 2: Write the failing test** — `tests/Mail/MailerFactoryTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Mail;

use App\Mail\MailerFactory;
use App\Mail\PhpMailMailer;
use App\Mail\ResendMailer;
use App\Mail\SmtpMailer;
use App\Settings\SettingsRepo;
use Tests\Support\DatabaseTestCase;

final class MailerFactoryTest extends DatabaseTestCase
{
    private function settings(): SettingsRepo
    {
        return new SettingsRepo($this->pdo());
    }

    public function test_defaults_to_php_mailer(): void
    {
        $this->assertInstanceOf(PhpMailMailer::class, MailerFactory::make($this->settings()));
    }

    public function test_selects_resend(): void
    {
        $s = $this->settings();
        $s->set('mail_driver', 'resend');
        $s->set('resend_api_key', 're_123');
        $s->set('from_email', 'love@crush.app');
        $this->assertInstanceOf(ResendMailer::class, MailerFactory::make($s));
    }

    public function test_selects_smtp(): void
    {
        $s = $this->settings();
        $s->set('mail_driver', 'smtp');
        $s->set('smtp_host', 'smtp.test');
        $this->assertInstanceOf(SmtpMailer::class, MailerFactory::make($s));
    }
}
```

- [ ] **Step 3: Run to verify it fails** — Run: `vendor/bin/phpunit --filter MailerFactoryTest`
Expected: FAIL — `Class "App\Mail\MailerFactory" not found`.

- [ ] **Step 4: Write `app/Mail/Email.php`**

```php
<?php
declare(strict_types=1);

namespace App\Mail;

final class Email
{
    /** @param array<int,array{filename:string,mime:string,content:string}> $attachments */
    public function __construct(
        public string $to,
        public string $subject,
        public string $html,
        public array $attachments = [],
    ) {}
}
```

- [ ] **Step 5: Write `app/Mail/Mailer.php`**

```php
<?php
declare(strict_types=1);

namespace App\Mail;

interface Mailer
{
    /** @throws \RuntimeException on send failure. */
    public function send(Email $email): void;
}
```

- [ ] **Step 6: Write `tests/Support/SpyMailer.php`**

```php
<?php
declare(strict_types=1);

namespace Tests\Support;

use App\Mail\Email;
use App\Mail\Mailer;

final class SpyMailer implements Mailer
{
    /** @var Email[] */
    public array $sent = [];

    public function send(Email $email): void
    {
        $this->sent[] = $email;
    }
}
```

- [ ] **Step 7: Write `app/Mail/PhpMailMailer.php`**

```php
<?php
declare(strict_types=1);

namespace App\Mail;

final class PhpMailMailer implements Mailer
{
    public function __construct(private string $fromEmail, private string $fromName) {}

    public function send(Email $email): void
    {
        $boundary = 'crush_' . bin2hex(random_bytes(8));
        $from = sprintf('%s <%s>', $this->fromName, $this->fromEmail);
        $headers = [
            'From: ' . $from,
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
        ];

        $body  = "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=utf-8\r\n\r\n";
        $body .= $email->html . "\r\n";
        foreach ($email->attachments as $att) {
            $body .= "--{$boundary}\r\n";
            $body .= 'Content-Type: ' . $att['mime'] . "; name=\"{$att['filename']}\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= 'Content-Disposition: attachment; filename="' . $att['filename'] . "\"\r\n\r\n";
            $body .= chunk_split(base64_encode($att['content'])) . "\r\n";
        }
        $body .= "--{$boundary}--";

        if (!mail($email->to, $email->subject, $body, implode("\r\n", $headers))) {
            throw new \RuntimeException('mail() failed for ' . $email->to);
        }
    }
}
```

- [ ] **Step 8: Write `app/Mail/ResendMailer.php`**

```php
<?php
declare(strict_types=1);

namespace App\Mail;

final class ResendMailer implements Mailer
{
    public function __construct(
        private string $apiKey,
        private string $fromEmail,
        private string $fromName,
    ) {}

    public function send(Email $email): void
    {
        $payload = [
            'from'    => sprintf('%s <%s>', $this->fromName, $this->fromEmail),
            'to'      => [$email->to],
            'subject' => $email->subject,
            'html'    => $email->html,
        ];
        foreach ($email->attachments as $att) {
            $payload['attachments'][] = [
                'filename' => $att['filename'],
                'content'  => base64_encode($att['content']),
            ];
        }

        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
        ]);
        $res    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($res === false || $status >= 300) {
            throw new \RuntimeException('Resend failed (' . $status . '): ' . $err . ' ' . (string) $res);
        }
    }
}
```

- [ ] **Step 9: Write `app/Mail/SmtpMailer.php`**

```php
<?php
declare(strict_types=1);

namespace App\Mail;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

final class SmtpMailer implements Mailer
{
    public function __construct(
        private string $host,
        private int $port,
        private string $username,
        private string $password,
        private string $fromEmail,
        private string $fromName,
        private string $encryption = 'tls',
    ) {}

    public function send(Email $email): void
    {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $this->host;
            $mail->Port = $this->port;
            if ($this->username !== '') {
                $mail->SMTPAuth = true;
                $mail->Username = $this->username;
                $mail->Password = $this->password;
            }
            if ($this->encryption !== '') {
                $mail->SMTPSecure = $this->encryption;
            }
            $mail->setFrom($this->fromEmail, $this->fromName);
            $mail->addAddress($email->to);
            $mail->isHTML(true);
            $mail->Subject = $email->subject;
            $mail->Body    = $email->html;
            foreach ($email->attachments as $att) {
                $mail->addStringAttachment($att['content'], $att['filename'], PHPMailer::ENCODING_BASE64, $att['mime']);
            }
            $mail->send();
        } catch (PHPMailerException $e) {
            throw new \RuntimeException('SMTP send failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
```

- [ ] **Step 10: Write `app/Mail/MailerFactory.php`**

```php
<?php
declare(strict_types=1);

namespace App\Mail;

use App\Settings\SettingsRepo;

final class MailerFactory
{
    public static function make(SettingsRepo $settings): Mailer
    {
        $fromEmail = $settings->get('from_email', 'noreply@localhost');
        $fromName  = $settings->get('from_name', 'Crush');

        return match ($settings->get('mail_driver', 'php')) {
            'resend' => new ResendMailer(
                (string) $settings->get('resend_api_key', ''),
                (string) $fromEmail,
                (string) $fromName,
            ),
            'smtp' => new SmtpMailer(
                (string) $settings->get('smtp_host', ''),
                (int) ($settings->get('smtp_port', '587')),
                (string) $settings->get('smtp_user', ''),
                (string) $settings->get('smtp_pass', ''),
                (string) $fromEmail,
                (string) $fromName,
                (string) $settings->get('smtp_encryption', 'tls'),
            ),
            default => new PhpMailMailer((string) $fromEmail, (string) $fromName),
        };
    }
}
```

- [ ] **Step 11: Run to verify it passes** — Run: `vendor/bin/phpunit --filter MailerFactoryTest`
Expected: PASS (3 tests).

- [ ] **Step 12: Commit**

```bash
git add composer.json composer.lock app/Mail tests/Support/SpyMailer.php tests/Mail/MailerFactoryTest.php
git commit -m "feat(mail): Email DTO + Mailer drivers (resend/smtp/php) + factory"
```

---

### Task 4: Postman (compose + send) + email templates

**Files:**
- Create: `app/Mail/Postman.php`
- Create: `templates/email/invite.php`
- Create: `templates/email/result.php`
- Test: `tests/Mail/PostmanTest.php`

**Interfaces:**
- Consumes: `App\Mail\Mailer`, `App\Ics\IcsBuilder`, `App\Core\View`, `App\Auth\UserRepo`.
- Produces: `App\Mail\Postman` with:
  - `__construct(Mailer $mailer, IcsBuilder $ics, View $view, string $appUrl)`
  - `sendInvite(array $invite): void` — emails the crush (`$invite['crush_email']`) the themed invite with the secure link `{appUrl}/i/{token}`. Sender identity hidden when anonymous.
  - `sendResult(array $invite, array $response, array $sender): void` — emails the sender (`$sender['email']`) the crush's choices + a `Date.ics` attachment (built from `response.chosen_start/end`, `meal_choice`, `pickup_*`). Validates `pickup_clean_url` scheme (http/https) before using it as an `href`.
  - Both swallow `Mailer` exceptions (log via `error_log`) so a send failure never breaks the caller. Return `bool` success.
- Helper `App\Mail\Postman::safeHref(?string $url): ?string` (public static) — returns the url only if its scheme is http/https, else null.

- [ ] **Step 1: Write the failing test** — `tests/Mail/PostmanTest.php`

```php
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
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter PostmanTest`
Expected: FAIL — `Class "App\Mail\Postman" not found`.

- [ ] **Step 3: Write `app/Mail/Postman.php`**

```php
<?php
declare(strict_types=1);

namespace App\Mail;

use App\Core\View;
use App\Ics\IcsBuilder;

final class Postman
{
    public function __construct(
        private Mailer $mailer,
        private IcsBuilder $ics,
        private View $view,
        private string $appUrl,
    ) {}

    public function sendInvite(array $invite): bool
    {
        $senderLabel = (int) ($invite['is_anonymous'] ?? 0) === 1 ? 'a secret admirer' : 'someone';
        $html = $this->view->render('email/invite', [
            'senderLabel' => $senderLabel,
            'message'     => $invite['message'] ?? null,
            'link'        => rtrim($this->appUrl, '/') . '/i/' . $invite['public_token'],
            'theme'       => $invite['theme_key'] ?? 'bubblegum',
        ]);
        return $this->dispatch(new Email(
            (string) $invite['crush_email'],
            'You have a crush invite',
            $html
        ));
    }

    public function sendResult(array $invite, array $response, array $sender): bool
    {
        $crushName = $invite['crush_name'] ?: 'your crush';
        $descParts = array_filter([
            $response['meal_choice'] ?? null,
            !empty($response['meal_wish']) ? 'wish: ' . $response['meal_wish'] : null,
            !empty($response['crush_contact']) ? 'contact: ' . $response['crush_contact'] : null,
        ]);
        $location = trim(implode(', ', array_filter([
            $response['pickup_name'] ?? null,
            $response['pickup_address'] ?? null,
        ])));

        $ics = $this->ics->build([
            'uid'         => $invite['public_token'] . '@crush',
            'summary'     => 'Date with ' . $crushName,
            'start'       => (string) $response['chosen_start'],
            'end'         => (string) $response['chosen_end'],
            'location'    => $location !== '' ? $location : null,
            'description' => $descParts !== [] ? implode('; ', $descParts) : null,
        ]);

        $html = $this->view->render('email/result', [
            'crushName' => $crushName,
            'response'  => $response,
            'mapHref'   => self::safeHref($response['pickup_clean_url'] ?? null),
            'location'  => $location,
        ]);

        return $this->dispatch(new Email(
            (string) $sender['email'],
            $crushName . ' answered your invite',
            $html,
            [['filename' => 'Date.ics', 'mime' => 'text/calendar', 'content' => $ics]]
        ));
    }

    public static function safeHref(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return ($scheme === 'http' || $scheme === 'https') ? $url : null;
    }

    private function dispatch(Email $email): bool
    {
        try {
            $this->mailer->send($email);
            return true;
        } catch (\Throwable $e) {
            error_log('Crush mail failed: ' . $e->getMessage());
            return false;
        }
    }
}
```

- [ ] **Step 4: Write `templates/email/invite.php`** (icons-only; inline styles for email clients)

```php
<?php $message = $message ?? null; ?>
<div style="font-family:Segoe UI,system-ui,sans-serif;max-width:480px;margin:0 auto;padding:24px;background:#fff;border-radius:16px;">
  <h1 style="color:#ff3d8b;font-size:22px;"><?= $e($senderLabel) ?> has a crush on you</h1>
  <?php if ($message): ?><p style="color:#444;line-height:1.5;"><?= $e($message) ?></p><?php endif; ?>
  <p style="color:#444;">Tap below to pick a date, a meal, and where to meet.</p>
  <p><a href="<?= $e($link) ?>" style="display:inline-block;padding:12px 20px;background:#ff3d8b;color:#fff;border-radius:12px;text-decoration:none;font-weight:700;">Open my invite</a></p>
  <p style="color:#999;font-size:12px;">If the button doesn't work, paste this link: <?= $e($link) ?></p>
</div>
```

- [ ] **Step 5: Write `templates/email/result.php`** (icons-only; href only when safe)

```php
<?php $response = $response ?? []; $mapHref = $mapHref ?? null; $location = $location ?? ''; ?>
<div style="font-family:Segoe UI,system-ui,sans-serif;max-width:480px;margin:0 auto;padding:24px;background:#fff;border-radius:16px;">
  <h1 style="color:#ff3d8b;font-size:22px;"><?= $e($crushName) ?> said yes</h1>
  <p style="color:#444;">Here's what they picked. The calendar invite is attached — add it to your phone and set a reminder.</p>
  <ul style="color:#444;line-height:1.7;list-style:none;padding:0;">
    <li><strong>When:</strong> <?= $e($response['chosen_start'] ?? '') ?></li>
    <?php if (!empty($response['meal_choice'])): ?><li><strong>Craving:</strong> <?= $e($response['meal_choice']) ?></li><?php endif; ?>
    <?php if (!empty($response['meal_wish'])): ?><li><strong>Wish:</strong> <?= $e($response['meal_wish']) ?></li><?php endif; ?>
    <?php if (!empty($response['crush_contact'])): ?><li><strong>Contact:</strong> <?= $e($response['crush_contact']) ?></li><?php endif; ?>
    <?php if ($location !== ''): ?>
      <li><strong>Pickup:</strong>
        <?php if ($mapHref): ?><a href="<?= $e($mapHref) ?>" style="color:#ff3d8b;"><?= $e($location) ?></a>
        <?php else: ?><?= $e($location) ?><?php endif; ?>
      </li>
    <?php endif; ?>
  </ul>
</div>
```

- [ ] **Step 6: Run the Postman test** — Run: `vendor/bin/phpunit --filter PostmanTest`
Expected: PASS (4 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Mail/Postman.php templates/email/ tests/Mail/PostmanTest.php
git commit -m "feat(mail): Postman composes invite + result emails (with .ics, safe href)"
```

---

### Task 5: Wire mail into auth, invite create, and respond submit

**Files:**
- Modify: `app/Auth/AuthController.php` (magic link → email via Mailer)
- Modify: `app/Invite/InviteController.php` (send invite email on create)
- Modify: `app/Respond/RespondController.php` (send result email on submit)
- Modify: `public/index.php` (build `SettingsRepo`, `MailerFactory`, `Postman`; inject)
- Test: `tests/Mail/MailWiringTest.php`

**Interfaces:**
- `AuthController::__construct` replaces the `string $magicLinkSink` parameter with a `Mailer $mailer` (and keeps `appUrl`); `startMagic` sends the link via a small inline email instead of writing a file.
- `InviteController::__construct` gains a trailing `Postman $postman`; `create()` calls `$this->postman->sendInvite($invite)` after creating.
- `RespondController::__construct` gains a trailing `Postman $postman` + `UserRepo` already present; `submit()` calls `$this->postman->sendResult($invite, $response, $sender)` after storing (looking up the sender via `UserRepo`).
- `public/index.php` builds `SettingsRepo`, `$mailer = MailerFactory::make($settings)`, `$postman = new Postman($mailer, new IcsBuilder($clock), $view, $appUrl)`, and injects.

- [ ] **Step 1: Write the failing test** — `tests/Mail/MailWiringTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Mail;

use App\Auth\UserRepo;
use App\Core\ArrayStore;
use App\Core\Csrf;
use App\Core\View;
use App\Ics\IcsBuilder;
use App\Invite\InviteRepo;
use App\Invite\ResponseRepo;
use App\Mail\Postman;
use App\Maps\LinkResolver;
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
        $postman = new Postman($spy, new IcsBuilder($clock), $view, 'https://crush.app');

        $ctrl = new RespondController(
            $view, $csrf, $invites, new ResponseRepo($this->pdo(), $clock), $users,
            new ABAssigner(new ThemeRepo($this->pdo()), $invites, fn(int $m) => 0),
            new AbEventRepo($this->pdo(), $clock), $clock,
            new LinkResolver(new FakeFetcher([])), $postman
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

        $this->assertCount(1, $spy->sent);
        $this->assertSame('sue@x.test', $spy->sent[0]->to);
        $this->assertNotEmpty($spy->sent[0]->attachments);
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter MailWiringTest`
Expected: FAIL — too few constructor arguments (no `Postman`).

- [ ] **Step 3: Add `Postman` to `RespondController`** — add `use App\Mail\Postman;`, add trailing constructor param `private Postman $postman,`, and at the end of `submit()` (after `events->log(... 'completed')` and before building the confirmed response) insert:

```php
        $sender = $this->users->findById((int) $invite['sender_id']);
        if ($sender !== null) {
            $stored = $this->responses->findByInvite((int) $invite['id']);
            if ($stored !== null) {
                $this->postman->sendResult($invite, $stored, $sender);
            }
        }
```

- [ ] **Step 4: Add `Postman` to `InviteController`** — add `use App\Mail\Postman;`, trailing constructor param `private Postman $postman,`, and in `create()` immediately after `$invite = $this->invites->create([...])` (and after adding date options) call:

```php
        $this->postman->sendInvite($invite);
```

- [ ] **Step 5: Switch `AuthController` to email the magic link** — replace the `private string $magicLinkSink` constructor param with `private \App\Mail\Mailer $mailer`. In `startMagic`, replace the `@file_put_contents(...)` block with:

```php
        $html = '<p style="font-family:sans-serif">Tap to sign in to Crush:</p>'
              . '<p><a href="' . htmlspecialchars($link, ENT_QUOTES) . '">Sign in</a></p>'
              . '<p style="color:#999;font-size:12px">Or paste: ' . htmlspecialchars($link, ENT_QUOTES) . '</p>';
        try {
            $this->mailer->send(new \App\Mail\Email($email, 'Your Crush sign-in link', $html));
        } catch (\Throwable $e) {
            error_log('Crush magic-link mail failed: ' . $e->getMessage());
        }
```

(Keep the "Check your email" success render unchanged.)

- [ ] **Step 6: Update `public/index.php`** — build settings + mail and inject. Add imports and, after `$pdo`/`$clock`/`$view` are available:

```php
use App\Ics\IcsBuilder;
use App\Mail\MailerFactory;
use App\Mail\Postman;
use App\Settings\SettingsRepo;

$settings = new SettingsRepo($pdo);
$mailer   = MailerFactory::make($settings);
$postman  = new Postman($mailer, new IcsBuilder($clock), $view, (string) $config->get('app_url', 'http://localhost'));
```

Then:
- Change the `AuthController` construction to pass `$mailer` where `storage/last-magic-link.txt` used to be.
- Add `$postman` as the trailing argument to both `new InviteController(...)` and `new RespondController(...)`.

- [ ] **Step 7: Run the wiring test** — Run: `vendor/bin/phpunit --filter MailWiringTest`
Expected: PASS.

- [ ] **Step 8: Update the older tests that construct these controllers** — `AuthControllerTest`, `InviteControllerTest`, `RespondOpenTest`, `RespondSubmitTest`, `RespondPickupTest` construct the controllers directly. Update each:
  - `AuthControllerTest`: pass a `Tests\Support\SpyMailer` instead of the `$storage` path string.
  - `InviteControllerTest`, `RespondOpenTest`, `RespondSubmitTest`, `RespondPickupTest`: pass a `new Postman(new SpyMailer(), new IcsBuilder($clock), $view, 'http://localhost')` as the trailing argument.
Run `vendor/bin/phpunit` and fix any constructor-arity failures until green.

- [ ] **Step 9: Run the full suite** — Run: `vendor/bin/phpunit`
Expected: all green (~91 tests).

- [ ] **Step 10: Commit**

```bash
git add app/Auth/AuthController.php app/Invite/InviteController.php app/Respond/RespondController.php \
        public/index.php tests/ 
git commit -m "feat(mail): wire emails into auth, invite create, and respond submit"
```

---

## Self-Review

**1. Spec coverage:** Pluggable mailer (Strategy) chosen in admin (spec §10) — Task 3 `MailerFactory` + three drivers from `settings`. Two transactional emails: invite to crush, result to sender (§10) — Task 4 `Postman`. `.ics` RFC-5545 with VALARM, LOCATION, DESCRIPTION (§10) — Task 2. Magic-link now actually emailed (§13) — Task 5 replaces the file bridge. Anonymity preserved in the invite email (§8) — Task 4. `href` scheme safety for `pickup_clean_url` (Plan 5 carry-over) — Task 4 `safeHref`. Icons only, escaped — email templates. Mail failure never breaks the user action (§: robustness) — `Postman::dispatch` + auth try/catch.

**2. Placeholder scan:** No "TBD". The three network drivers are thin, real implementations; the factory + composition (`Postman`) are unit-tested via `SpyMailer`, and the actual SMTP/Resend/`mail()` I/O is exercised manually (admin "send test email" lands in Plan 7). This is a deliberate, documented test boundary.

**3. Type consistency:** `SettingsRepo::get(string,?string):?string`/`set(string,string):void`/`all():array`; `IcsBuilder::build(array):string`; `Email(public to,subject,html,attachments)`; `Mailer::send(Email):void`; `MailerFactory::make(SettingsRepo):Mailer`; `Postman::sendInvite(array):bool`/`sendResult(array,array,array):bool`/`safeHref(?string):?string`. Controllers gain trailing `Postman`/`Mailer` params, matched in `public/index.php`. `ResponseRepo::findByInvite`, `UserRepo::findById`, `InviteRepo::create`/`findByToken` consumed as defined in Plans 2–3. New dep `phpmailer/phpmailer` only.
