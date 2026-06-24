# Crush v3 — Plan 3: Email Templates Core Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move all transactional emails to admin-managed, localized templates (EN/VI/KO) stored in the DB, rendered by language with safe placeholder interpolation, and wire `Postman` to use them.

**Architecture:** A seeded `email_templates(key, lang, subject, body_html)` table, an `EmailTemplateRepo` (get with `en` fallback + `render` that escapes values), and a `Postman` rewrite that resolves `(key, lang, vars)` instead of rendering hardcoded PHP views. A new `Postman::sendMagic` covers the magic-link email.

**Tech Stack:** PHP 8.1+, PDO MySQL, PHPUnit 10. No new Composer dependencies.

## Global Constraints

- PHP floor 8.1. PSR-4. No new Composer packages. Prepared statements.
- **Icons only — never emojis** in template copy. Languages: `en` (default), `vi`, `ko`; `render` falls back to `en` when a `(key, lang)` row is missing.
- `render` HTML-escapes every interpolated value in the **body**; for the **subject** it strips CR/LF from values (header-injection safety) and does not HTML-escape.
- Mail-send failures stay swallowed + logged (existing `Postman::dispatch`).
- Integration tests use MySQL `crush_test`.

## File Structure

- `migrations/0010_email_templates.sql` — `email_templates` table + 12 seed rows (4 keys × 3 langs).
- `app/Mail/EmailTemplateRepo.php` — `get` + `render`.
- `app/Mail/Postman.php` (modify) — template-driven `sendWelcome`/`sendInvite`/`sendResult` + new `sendMagic`.
- `public/index.php` (modify) — inject `EmailTemplateRepo` into `Postman`.

---

### Task 1: email_templates table + seeds

**Files:**
- Create: `migrations/0010_email_templates.sql`
- Test: `tests/Mail/EmailTemplateSchemaTest.php`

**Interfaces:**
- Produces: `email_templates(id, key VARCHAR(32), lang VARCHAR(5), subject VARCHAR(255), body_html TEXT)`, `UNIQUE(key, lang)`; seeded with keys `welcome`/`invite`/`result`/`magic` in `en`/`vi`/`ko`.

- [ ] **Step 1: Write the failing test** — `tests/Mail/EmailTemplateSchemaTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Mail;

use Tests\Support\DatabaseTestCase;

final class EmailTemplateSchemaTest extends DatabaseTestCase
{
    public function test_table_and_seeds(): void
    {
        $cols = array_column($this->pdo()->query('SHOW COLUMNS FROM email_templates')->fetchAll(), 'Field');
        foreach (['id', 'key', 'lang', 'subject', 'body_html'] as $c) {
            $this->assertContains($c, $cols, "email_templates.$c");
        }
        // 4 keys x 3 langs = 12 seeded rows
        $count = (int) $this->pdo()->query('SELECT COUNT(*) AS c FROM email_templates')->fetch()['c'];
        $this->assertSame(12, $count);
        foreach (['welcome', 'invite', 'result', 'magic'] as $key) {
            foreach (['en', 'vi', 'ko'] as $lang) {
                $stmt = $this->pdo()->prepare('SELECT subject, body_html FROM email_templates WHERE `key` = ? AND lang = ?');
                $stmt->execute([$key, $lang]);
                $row = $stmt->fetch();
                $this->assertNotFalse($row, "missing $key/$lang");
                $this->assertNotSame('', trim((string) $row['subject']));
                $this->assertNotSame('', trim((string) $row['body_html']));
            }
        }
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter EmailTemplateSchemaTest`
Expected: FAIL — table `email_templates` not found.

- [ ] **Step 3: Write `migrations/0010_email_templates.sql`** (SQL string literals are single-quoted; HTML uses double-quote attributes; copy avoids apostrophes to keep the SQL clean)

```sql
CREATE TABLE IF NOT EXISTS email_templates (
  id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `key`     VARCHAR(32)  NOT NULL,
  lang      VARCHAR(5)   NOT NULL,
  subject   VARCHAR(255) NOT NULL,
  body_html TEXT         NOT NULL,
  UNIQUE KEY uq_tpl_key_lang (`key`, lang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO email_templates (`key`, lang, subject, body_html) VALUES
('welcome','en','Welcome to Crush','<div style="font-family:system-ui,sans-serif;max-width:480px;margin:0 auto;padding:24px"><h1 style="color:#ff3d8b">Welcome to Crush, {{name}}</h1><p>Your account is ready. Sign in and add a few cute details to your profile.</p><p><a href="{{link}}" style="display:inline-block;padding:12px 20px;background:#ff3d8b;color:#fff;border-radius:12px;text-decoration:none;font-weight:700">Sign in</a></p><p style="color:#999;font-size:12px">Or paste this link: {{link}}</p></div>'),
('welcome','vi','Chao mung den voi Crush','<div style="font-family:system-ui,sans-serif;max-width:480px;margin:0 auto;padding:24px"><h1 style="color:#ff3d8b">Chao mung den voi Crush, {{name}}</h1><p>Tai khoan cua ban da san sang. Dang nhap va them vai thong tin de hoan thien ho so.</p><p><a href="{{link}}" style="display:inline-block;padding:12px 20px;background:#ff3d8b;color:#fff;border-radius:12px;text-decoration:none;font-weight:700">Dang nhap</a></p><p style="color:#999;font-size:12px">Hoac dan lien ket nay: {{link}}</p></div>'),
('welcome','ko','Crush에 오신 것을 환영합니다','<div style="font-family:system-ui,sans-serif;max-width:480px;margin:0 auto;padding:24px"><h1 style="color:#ff3d8b">{{name}}님, Crush에 오신 것을 환영합니다</h1><p>계정이 준비되었습니다. 로그인하고 프로필을 완성해 보세요.</p><p><a href="{{link}}" style="display:inline-block;padding:12px 20px;background:#ff3d8b;color:#fff;border-radius:12px;text-decoration:none;font-weight:700">로그인</a></p><p style="color:#999;font-size:12px">또는 이 링크를 붙여넣으세요: {{link}}</p></div>'),
('invite','en','You have a crush invite','<div style="font-family:system-ui,sans-serif;max-width:480px;margin:0 auto;padding:24px"><h1 style="color:#ff3d8b">{{senderLabel}} has a crush on you</h1><p>{{message}}</p><p>Tap below to pick a date, a meal, and where to meet.</p><p><a href="{{link}}" style="display:inline-block;padding:12px 20px;background:#ff3d8b;color:#fff;border-radius:12px;text-decoration:none;font-weight:700">Open my invite</a></p><p style="color:#bbb;font-size:11px">Not interested? Block and report: {{unsubscribe}}</p></div>'),
('invite','vi','Ban nhan duoc loi moi hen ho','<div style="font-family:system-ui,sans-serif;max-width:480px;margin:0 auto;padding:24px"><h1 style="color:#ff3d8b">{{senderLabel}} dang thich ban</h1><p>{{message}}</p><p>Nhan vao nut ben duoi de chon ngay, mon an va dia diem gap nhau.</p><p><a href="{{link}}" style="display:inline-block;padding:12px 20px;background:#ff3d8b;color:#fff;border-radius:12px;text-decoration:none;font-weight:700">Mo loi moi</a></p><p style="color:#bbb;font-size:11px">Khong quan tam? Chan va bao cao: {{unsubscribe}}</p></div>'),
('invite','ko','초대장이 도착했어요','<div style="font-family:system-ui,sans-serif;max-width:480px;margin:0 auto;padding:24px"><h1 style="color:#ff3d8b">{{senderLabel}}님이 당신을 좋아합니다</h1><p>{{message}}</p><p>아래를 눌러 날짜와 식사, 만날 장소를 골라주세요.</p><p><a href="{{link}}" style="display:inline-block;padding:12px 20px;background:#ff3d8b;color:#fff;border-radius:12px;text-decoration:none;font-weight:700">초대장 열기</a></p><p style="color:#bbb;font-size:11px">관심이 없으신가요? 차단 및 신고: {{unsubscribe}}</p></div>'),
('result','en','{{crushName}} answered your invite','<div style="font-family:system-ui,sans-serif;max-width:480px;margin:0 auto;padding:24px"><h1 style="color:#ff3d8b">{{crushName}} said yes</h1><p>Here is what they picked. The calendar invite is attached.</p><ul style="line-height:1.7"><li><strong>When:</strong> {{when}}</li><li><strong>Craving:</strong> {{meal}}</li><li><strong>Pickup:</strong> {{place}}</li><li><strong>Map:</strong> {{mapHref}}</li></ul></div>'),
('result','vi','{{crushName}} da tra loi loi moi','<div style="font-family:system-ui,sans-serif;max-width:480px;margin:0 auto;padding:24px"><h1 style="color:#ff3d8b">{{crushName}} dong y roi</h1><p>Day la lua chon cua ho. Loi moi lich da duoc dinh kem.</p><ul style="line-height:1.7"><li><strong>Khi nao:</strong> {{when}}</li><li><strong>Mon:</strong> {{meal}}</li><li><strong>Don o:</strong> {{place}}</li><li><strong>Ban do:</strong> {{mapHref}}</li></ul></div>'),
('result','ko','{{crushName}}님이 초대에 응답했어요','<div style="font-family:system-ui,sans-serif;max-width:480px;margin:0 auto;padding:24px"><h1 style="color:#ff3d8b">{{crushName}}님이 좋다고 했어요</h1><p>고른 내용은 다음과 같아요. 캘린더 초대가 첨부되어 있습니다.</p><ul style="line-height:1.7"><li><strong>언제:</strong> {{when}}</li><li><strong>메뉴:</strong> {{meal}}</li><li><strong>픽업:</strong> {{place}}</li><li><strong>지도:</strong> {{mapHref}}</li></ul></div>'),
('magic','en','Your Crush sign-in link','<div style="font-family:system-ui,sans-serif;max-width:480px;margin:0 auto;padding:24px"><p>Tap to sign in to Crush:</p><p><a href="{{link}}" style="display:inline-block;padding:12px 20px;background:#ff3d8b;color:#fff;border-radius:12px;text-decoration:none;font-weight:700">Sign in</a></p><p style="color:#999;font-size:12px">Or paste: {{link}}</p></div>'),
('magic','vi','Lien ket dang nhap Crush','<div style="font-family:system-ui,sans-serif;max-width:480px;margin:0 auto;padding:24px"><p>Nhan de dang nhap vao Crush:</p><p><a href="{{link}}" style="display:inline-block;padding:12px 20px;background:#ff3d8b;color:#fff;border-radius:12px;text-decoration:none;font-weight:700">Dang nhap</a></p><p style="color:#999;font-size:12px">Hoac dan: {{link}}</p></div>'),
('magic','ko','Crush 로그인 링크','<div style="font-family:system-ui,sans-serif;max-width:480px;margin:0 auto;padding:24px"><p>탭하여 Crush에 로그인하세요:</p><p><a href="{{link}}" style="display:inline-block;padding:12px 20px;background:#ff3d8b;color:#fff;border-radius:12px;text-decoration:none;font-weight:700">로그인</a></p><p style="color:#999;font-size:12px">또는 붙여넣기: {{link}}</p></div>')
ON DUPLICATE KEY UPDATE subject = VALUES(subject), body_html = VALUES(body_html);
```

- [ ] **Step 4: Run to verify it passes** — Run: `vendor/bin/phpunit --filter EmailTemplateSchemaTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add migrations/0010_email_templates.sql tests/Mail/EmailTemplateSchemaTest.php
git commit -m "feat(email): email_templates table + EN/VI/KO seeds"
```

---

### Task 2: EmailTemplateRepo (get + render)

**Files:**
- Create: `app/Mail/EmailTemplateRepo.php`
- Test: `tests/Mail/EmailTemplateRepoTest.php`

**Interfaces:**
- Consumes: `\PDO`.
- Produces: `App\Mail\EmailTemplateRepo` with `__construct(\PDO $pdo)`:
  - `get(string $key, string $lang): ?array` — exact `(key, lang)`; else `(key, 'en')`; else `null`.
  - `render(string $key, string $lang, array $vars): array` — returns `['subject' => string, 'html' => string]`. Replaces each `{{name}}` token: in `html` with the **HTML-escaped** value, in `subject` with the value **stripped of CR/LF** (not HTML-escaped). Throws `\RuntimeException` if no template (not even `en`) exists.
  - `all(): array` — all rows ordered by `key`,`lang` (for the admin UI in Plan v3-4).

- [ ] **Step 1: Write the failing test** — `tests/Mail/EmailTemplateRepoTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Mail;

use App\Mail\EmailTemplateRepo;
use Tests\Support\DatabaseTestCase;

final class EmailTemplateRepoTest extends DatabaseTestCase
{
    public function test_get_exact_then_en_fallback(): void
    {
        $repo = new EmailTemplateRepo($this->pdo());
        $this->assertSame('vi', $this->langOf($repo->get('welcome', 'vi')));
        // Korean exists too
        $this->assertNotNull($repo->get('welcome', 'ko'));
        // unsupported lang -> en fallback row
        $fb = $repo->get('welcome', 'fr');
        $this->assertNotNull($fb);
        $this->assertSame('Welcome to Crush', $fb['subject']);
    }

    public function test_render_interpolates_and_escapes_body(): void
    {
        $repo = new EmailTemplateRepo($this->pdo());
        $out = $repo->render('welcome', 'en', ['name' => '<b>Ann</b>', 'link' => 'https://crush.app/x']);
        $this->assertStringContainsString('&lt;b&gt;Ann&lt;/b&gt;', $out['html']); // escaped in body
        $this->assertStringContainsString('https://crush.app/x', $out['html']);
        $this->assertStringNotContainsString('{{name}}', $out['html']);
    }

    public function test_render_subject_strips_newlines_not_escaped(): void
    {
        $repo = new EmailTemplateRepo($this->pdo());
        $out = $repo->render('result', 'en', ['crushName' => "Cee\r\nBcc: evil@x", 'when' => '', 'meal' => '', 'place' => '', 'mapHref' => '']);
        $this->assertStringNotContainsString("\n", $out['subject']);   // CR/LF stripped (header-injection safe)
        $this->assertStringContainsString('Cee', $out['subject']);
    }

    public function test_render_throws_when_missing(): void
    {
        $repo = new EmailTemplateRepo($this->pdo());
        $this->expectException(\RuntimeException::class);
        $repo->render('nope', 'en', []);
    }

    private function langOf(?array $row): ?string
    {
        return $row['lang'] ?? null;
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter EmailTemplateRepoTest`
Expected: FAIL — `Class "App\Mail\EmailTemplateRepo" not found`.

- [ ] **Step 3: Write `app/Mail/EmailTemplateRepo.php`**

```php
<?php
declare(strict_types=1);

namespace App\Mail;

final class EmailTemplateRepo
{
    public function __construct(private \PDO $pdo) {}

    public function get(string $key, string $lang): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM email_templates WHERE `key` = ? AND lang = ?');
        $stmt->execute([$key, $lang]);
        $row = $stmt->fetch();
        if ($row !== false) {
            return $row;
        }
        if ($lang !== 'en') {
            $stmt->execute([$key, 'en']);
            $row = $stmt->fetch();
            if ($row !== false) {
                return $row;
            }
        }
        return null;
    }

    /** @return array{subject:string,html:string} */
    public function render(string $key, string $lang, array $vars): array
    {
        $tpl = $this->get($key, $lang);
        if ($tpl === null) {
            throw new \RuntimeException("Email template not found: {$key}");
        }
        $subject = (string) $tpl['subject'];
        $html = (string) $tpl['body_html'];
        foreach ($vars as $name => $value) {
            $value = (string) $value;
            $html = str_replace('{{' . $name . '}}', htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $html);
            $subject = str_replace('{{' . $name . '}}', str_replace(["\r", "\n"], ' ', $value), $subject);
        }
        return ['subject' => $subject, 'html' => $html];
    }

    /** @return array<int,array> */
    public function all(): array
    {
        return $this->pdo->query('SELECT * FROM email_templates ORDER BY `key`, lang')->fetchAll();
    }
}
```

- [ ] **Step 4: Run to verify it passes** — Run: `vendor/bin/phpunit --filter EmailTemplateRepoTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Mail/EmailTemplateRepo.php tests/Mail/EmailTemplateRepoTest.php
git commit -m "feat(email): EmailTemplateRepo get + render (escape + en fallback)"
```

---

### Task 3: Postman rewrite to template-driven, language-aware

**Files:**
- Modify: `app/Mail/Postman.php`
- Modify: `public/index.php`
- Test: `tests/Mail/PostmanTest.php` (rewrite assertions to template output)

**Interfaces:**
- `Postman::__construct(Mailer $mailer, IcsBuilder $ics, EmailTemplateRepo $templates, string $appUrl)` — **`View` is replaced by `EmailTemplateRepo`**.
- Methods (all resolve via `EmailTemplateRepo::render(key, lang, vars)`):
  - `sendWelcome(string $email, ?string $name, string $loginLink, string $lang = 'en'): bool` — key `welcome`, vars `{name, link}`.
  - `sendInvite(array $invite): bool` — key `invite`, lang `= $invite['lang'] ?? 'en'`, vars `{senderLabel, message, link, unsubscribe}` (senderLabel = "a secret admirer" when anonymous else "someone"; link = `{appUrl}/i/{token}`; unsubscribe = `{appUrl}/unsubscribe/{token}`).
  - `sendResult(array $invite, array $response, array $sender): bool` — key `result`, lang `= $sender['lang'] ?? 'en'`, vars `{crushName, when, meal, place, mapHref}` (mapHref via `safeHref`, else ''; .ics still attached).
  - `sendMagic(string $email, string $loginLink, string $lang = 'en'): bool` — key `magic`, vars `{link}`.
  - `safeHref` + `dispatch` unchanged.

- [ ] **Step 1: Rewrite `tests/Mail/PostmanTest.php`** to assert template-driven output (uses the seeded DB, so extend `DatabaseTestCase`)

```php
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
        $this->assertStringContainsString('Chao mung', $spy->sent[0]->subject); // vi subject
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
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter PostmanTest`
Expected: FAIL — `Postman::__construct` still wants a `View` / `sendMagic` undefined.

- [ ] **Step 3: Rewrite `app/Mail/Postman.php`**

```php
<?php
declare(strict_types=1);

namespace App\Mail;

use App\Ics\IcsBuilder;

final class Postman
{
    public function __construct(
        private Mailer $mailer,
        private IcsBuilder $ics,
        private EmailTemplateRepo $templates,
        private string $appUrl,
    ) {}

    public function sendWelcome(string $email, ?string $name, string $loginLink, string $lang = 'en'): bool
    {
        return $this->dispatchTemplate($email, 'welcome', $lang, [
            'name' => $name ?? '',
            'link' => $loginLink,
        ]);
    }

    public function sendMagic(string $email, string $loginLink, string $lang = 'en'): bool
    {
        return $this->dispatchTemplate($email, 'magic', $lang, ['link' => $loginLink]);
    }

    public function sendInvite(array $invite): bool
    {
        $base = rtrim($this->appUrl, '/');
        return $this->dispatchTemplate((string) $invite['crush_email'], 'invite', (string) ($invite['lang'] ?? 'en'), [
            'senderLabel' => (int) ($invite['is_anonymous'] ?? 0) === 1 ? 'a secret admirer' : 'someone',
            'message'     => (string) ($invite['message'] ?? ''),
            'link'        => $base . '/i/' . $invite['public_token'],
            'unsubscribe' => $base . '/unsubscribe/' . $invite['public_token'],
        ]);
    }

    public function sendResult(array $invite, array $response, array $sender): bool
    {
        $crush = $invite['crush_name'] ?: 'your crush';
        $place = trim((string) (($response['pickup_name'] ?? '') . ' ' . ($response['pickup_address'] ?? '')));
        $descParts = array_filter([
            $response['meal_choice'] ?? null,
            !empty($response['meal_wish']) ? 'wish: ' . $response['meal_wish'] : null,
            !empty($response['crush_contact']) ? 'contact: ' . $response['crush_contact'] : null,
        ]);

        $ics = $this->ics->build([
            'uid'         => $invite['public_token'] . '@crush',
            'summary'     => 'Date with ' . $crush,
            'start'       => (string) $response['chosen_start'],
            'end'         => (string) $response['chosen_end'],
            'location'    => $place !== '' ? $place : null,
            'description' => $descParts !== [] ? implode('; ', $descParts) : null,
        ]);

        $rendered = $this->templates->render('result', (string) ($sender['lang'] ?? 'en'), [
            'crushName' => $crush,
            'when'      => (string) ($response['chosen_start'] ?? ''),
            'meal'      => (string) ($response['meal_choice'] ?? ''),
            'place'     => $place,
            'mapHref'   => self::safeHref($response['pickup_clean_url'] ?? null) ?? '',
        ]);

        return $this->dispatch(new Email(
            (string) $sender['email'],
            $rendered['subject'],
            $rendered['html'],
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

    private function dispatchTemplate(string $to, string $key, string $lang, array $vars): bool
    {
        try {
            $rendered = $this->templates->render($key, $lang, $vars);
        } catch (\Throwable $e) {
            error_log('Crush template render failed: ' . $e->getMessage());
            return false;
        }
        return $this->dispatch(new Email($to, $rendered['subject'], $rendered['html']));
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

- [ ] **Step 4: Run the Postman test** — Run: `vendor/bin/phpunit --filter PostmanTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Update `public/index.php` Postman construction** — build `$emailTemplates = new EmailTemplateRepo($pdo);` and change `new Postman($mailer, new IcsBuilder($clock), $view, $appUrl)` to `new Postman($mailer, new IcsBuilder($clock), $emailTemplates, (string) $config->get('app_url', 'http://localhost'))`. Add `use App\Mail\EmailTemplateRepo;`.

- [ ] **Step 6: Update other Postman test constructions** — every test that does `new Postman(...)` now passes an `EmailTemplateRepo($this->pdo())` instead of the `View`. Affected: `tests/Mail/MailWiringTest.php`, `tests/Respond/RespondOnboardTest.php`, `tests/Respond/RespondPlaceTest.php`, `tests/Invite/InvitePlacesCreateTest.php`, `tests/Respond/RespondOpenTest.php`/`RespondSubmitTest.php` (if they build Postman), `tests/Invite/InviteControllerTest.php`/`InviteRateLimitTest.php` (if they build Postman), `tests/Respond/CrushOnboarderTest.php`. Replace the `$view`/`new View(...)` argument to `Postman` with `new EmailTemplateRepo($this->pdo())` (add the import). These are DatabaseTestCase-based so the seeded templates exist. Run `vendor/bin/phpunit` and fix every constructor-arity/type failure until green.

- [ ] **Step 7: Run the full suite** — Run: `vendor/bin/phpunit`
Expected: all green.

- [ ] **Step 8: Commit**

```bash
git add app/Mail/Postman.php public/index.php tests/
git commit -m "feat(email): Postman rewrite to template-driven, language-aware sends"
```

---

## Self-Review

**1. Spec coverage:** `email_templates` table + EN/VI/KO seeds for welcome/invite/result/magic (spec §6,§7) — Task 1. `EmailTemplateRepo::get`(en fallback)/`render`(escape body, strip-CRLF subject)/`all` (§6) — Task 2. `Postman` template-driven, language-aware, `sendMagic` added, anonymity + `.ics` + `safeHref` preserved (§6) — Task 3. Mail failures swallowed (§6) — `dispatch`/`dispatchTemplate` try/catch. Wiring of recipient/invite `lang` into the call sites (landing welcome, crush welcome, magic) lands in Plan v3-5; here the lang params default to `en` and `sendInvite` reads `invite['lang']`.

**2. Placeholder scan:** No "TBD". Full seeds (12 rows) and complete `Postman`/`EmailTemplateRepo` code. Copy avoids apostrophes to keep single-quoted SQL clean; VI uses unaccented-safe ASCII-ish text acceptable for v1 seed (admin can refine in Plan v3-4).

**3. Type consistency:** `EmailTemplateRepo::get(string,string): ?array`, `render(string,string,array): array{subject,html}`, `all(): array`; `Postman::__construct(Mailer,IcsBuilder,EmailTemplateRepo,string)`, `sendWelcome(string,?string,string,string): bool`, `sendInvite(array): bool`, `sendResult(array,array,array): bool`, `sendMagic(string,string,string): bool`, `safeHref(?string): ?string`. The `View` dependency is removed from `Postman` and replaced by `EmailTemplateRepo` everywhere it is constructed (index.php + all test helpers). `Email` DTO + `IcsBuilder` + `SpyMailer` consumed as defined.
