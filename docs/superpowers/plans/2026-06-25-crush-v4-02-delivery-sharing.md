# Crush v4 — Plan 2: Delivery Choice + Admin-Configurable Sharing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let the sender choose per-invite how it reaches the crush — email it, or share the link themselves (email optional) — and turn the "invite ready" page into a share screen with admin-configurable social/messaging buttons.

**Architecture:** `invites.crush_email` becomes nullable; `InviteController::create` branches on a `delivery` field. A seeded `share_targets` table + `ShareTargetRepo` drive a share screen (copy + native share + per-target web-intent links) on `invite/created.php`, and an admin screen at `/admin/share`.

**Tech Stack:** PHP 8.1+, PDO MySQL, PHPUnit 10. No new Composer dependencies.

## Global Constraints

- PHP floor 8.1. PSR-4. No new Composer packages. Prepared statements.
- **Icons only — never emojis.** All HTML `$e()`-escaped. POSTs validate CSRF. Admin screens are `is_admin`-gated.
- Run the suite **serially** (concurrent `phpunit` corrupts `crush_test`).
- Share targets are stateless web-intent URL templates (`{url}`/`{text}` placeholders) — no API keys/OAuth. Allowed schemes: `http`, `https`, `sms`, `mailto`.
- Integration tests use MySQL `crush_test`. Production: `https://crush.didudi.com`.

## File Structure

- `migrations/0012_invite_email_nullable.sql`, `migrations/0013_share_targets.sql`.
- `app/Invite/InviteController.php` (modify) — delivery branch, conditional send.
- `templates/invite/new.php` (modify) — delivery toggle.
- `app/Share/ShareTargetRepo.php` (new) — list/get/update/render/scheme-check.
- `templates/partials/icons.php` (modify) — brand glyphs.
- `templates/invite/created.php` (modify) — share screen.
- `app/Admin/AdminController.php` (modify), `config/routes.php`, `public/index.php` (modify) — `/admin/share`.
- `templates/admin/share.php`, `templates/admin/share_edit.php` (new).

---

### Task 1: Delivery choice (email OR link)

**Files:**
- Create: `migrations/0012_invite_email_nullable.sql`
- Modify: `app/Invite/InviteController.php`, `templates/invite/new.php`
- Test: `tests/Invite/InviteDeliveryTest.php`

**Interfaces:**
- Produces: `invites.crush_email` nullable; `InviteController::create` reads `delivery` (`email`|`link`); email required+sent only in `email` mode; in `link` mode email is optional and **no** email is sent; per-email cap + block check run only when an email is present; redirect to the share screen either way.

- [ ] **Step 1: Write the failing test** — `tests/Invite/InviteDeliveryTest.php`

```php
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
use Tests\Support\DatabaseTestCase;
use Tests\Support\FakeFetcher;
use Tests\Support\FrozenClock;
use Tests\Support\SpyMailer;

final class InviteDeliveryTest extends DatabaseTestCase
{
    private FrozenClock $clock;
    private SpyMailer $spy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $this->spy = new SpyMailer();
    }

    private function controller(Csrf $csrf): InviteController
    {
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        return new InviteController(
            $view, $csrf, new InviteRepo($this->pdo(), $this->clock), new UserRepo($this->pdo(), $this->clock),
            $this->clock, 'http://localhost',
            new Postman($this->spy, new IcsBuilder($this->clock), new EmailTemplateRepo($this->pdo()), 'http://localhost'),
            new RateLimiter($this->pdo(), $this->clock), new BlockRepo($this->pdo(), $this->clock),
            new InvitePlaceRepo($this->pdo()), new LinkResolver(new FakeFetcher([]))
        );
    }

    private function uid(string $email = 'u@x.test'): int
    {
        return (new UserRepo($this->pdo(), $this->clock))->create($email, 'U', 'magic')['id'];
    }

    public function test_form_has_delivery_toggle(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $body = $this->controller($csrf)->showNew($this->uid())->body();
        $this->assertStringContainsString('name="delivery"', $body);
        $this->assertStringContainsString('value="link"', $body);
    }

    public function test_email_mode_requires_email_and_sends(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $uid = $this->uid();
        // missing email -> 422
        $bad = $this->controller($csrf)->create($uid, ['delivery' => 'email', 'date_mode' => 'instant'], $csrf->token());
        $this->assertSame(422, $bad->status());
        // valid email -> 302 + email sent
        $ok = $this->controller($csrf)->create($uid, ['delivery' => 'email', 'crush_email' => 'c@x.test', 'date_mode' => 'instant'], $csrf->token());
        $this->assertSame(302, $ok->status());
        $this->assertCount(1, $this->spy->sent);
        $this->assertSame('c@x.test', $this->spy->sent[0]->to);
    }

    public function test_link_mode_allows_blank_email_and_sends_nothing(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $uid = $this->uid('u2@x.test');
        $res = $this->controller($csrf)->create($uid, ['delivery' => 'link', 'date_mode' => 'instant'], $csrf->token());
        $this->assertSame(302, $res->status());
        $this->assertCount(0, $this->spy->sent);                                  // no email
        $invite = (new InviteRepo($this->pdo(), $this->clock))->listBySender($uid)[0];
        $this->assertNull($invite['crush_email']);                                // stored null
    }

    public function test_link_mode_rejects_malformed_email(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf)->create($this->uid('u3@x.test'), ['delivery' => 'link', 'crush_email' => 'nope', 'date_mode' => 'instant'], $csrf->token());
        $this->assertSame(422, $res->status());
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter InviteDeliveryTest`
Expected: FAIL — no delivery handling; email still required.

- [ ] **Step 3: Write `migrations/0012_invite_email_nullable.sql`**

```sql
ALTER TABLE invites MODIFY crush_email VARCHAR(191) NULL;
```

- [ ] **Step 4: Branch on delivery in `InviteController::create`** — replace the email-validation + rate/block block (current lines ~62–80) with:

```php
        $delivery = ($input['delivery'] ?? 'email') === 'link' ? 'link' : 'email';
        $email = trim((string) ($input['crush_email'] ?? ''));

        if ($delivery === 'email') {
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->renderForm('Please enter a valid email for your crush.', $input, 422);
            }
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->renderForm('That email does not look right — leave it blank to share the link yourself.', $input, 422);
        }

        $dateMode = ($input['date_mode'] ?? 'instant') === 'confirm' ? 'confirm' : 'instant';
        $sender = $this->users->findById($userId);

        if ($email !== '') {
            // Tighter per-email cap first, then per-sender, with short-circuit AND.
            if (!$this->limits->hit('invites_per_email', strtolower($email), 3, 86400)
                || !$this->limits->hit('invites_per_sender', (string) $userId, 20, 86400)) {
                return $this->renderForm('You have sent too many invites for now. Please try again later.', $input, 429);
            }
            if ($this->blocks->isBlocked($userId, $email)) {
                return $this->renderForm('This person has asked not to receive invites.', $input, 403);
            }
        } elseif (!$this->limits->hit('invites_per_sender', (string) $userId, 20, 86400)) {
            return $this->renderForm('You have sent too many invites for now. Please try again later.', $input, 429);
        }
```

Then change the invite-create `crush_email` value to nullable:

```php
            'crush_email'        => $email !== '' ? $email : null,
```

and make the send conditional (replace `$this->postman->sendInvite($invite);`):

```php
        if ($delivery === 'email') {
            $this->postman->sendInvite($invite);
        }
```

- [ ] **Step 5: Add the delivery toggle to `templates/invite/new.php`** — insert before the "Their email" label, and drop `required` from the email input (server enforces it; a small script re-adds it for email mode):

```php
    <fieldset style="border:0;padding:0;margin:0;display:flex;gap:16px;flex-wrap:wrap;">
      <legend style="font-size:13px;font-weight:600;opacity:.7;">How will you send it?</legend>
      <label style="display:flex;align-items:center;gap:6px;"><input type="radio" name="delivery" value="email" checked> Email it to them</label>
      <label style="display:flex;align-items:center;gap:6px;"><input type="radio" name="delivery" value="link"> I will share the link myself</label>
    </fieldset>
```

Change the email input line to drop `required` and add an id:

```php
      <input type="email" id="crush_email" name="crush_email" value="<?= $val('crush_email') ?>"
             style="width:100%;padding:11px;border-radius:12px;border:1px solid #e7d4ff;">
```

Add this script just before `<?php return ob_get_clean(); };` (end of the closure body):

```php
    <script>
    (function(){
      var radios = document.querySelectorAll('input[name="delivery"]');
      var email = document.getElementById('crush_email');
      function sync(){
        var mode = document.querySelector('input[name="delivery"]:checked');
        var isEmail = !mode || mode.value === 'email';
        if (email) { email.required = isEmail; email.placeholder = isEmail ? '' : 'optional'; }
      }
      radios.forEach(function(r){ r.addEventListener('change', sync); });
      sync();
    })();
    </script>
```

- [ ] **Step 6: Run the test** — Run: `vendor/bin/phpunit --filter InviteDeliveryTest`
Expected: PASS.

- [ ] **Step 7: Run the full suite (serially)** — Run: `vendor/bin/phpunit`
Expected: all green (the existing create tests pass `crush_email` without `delivery` → defaults to `email` mode, email still required + sent).

- [ ] **Step 8: Commit**

```bash
git add migrations/0012_invite_email_nullable.sql app/Invite/InviteController.php templates/invite/new.php tests/Invite/InviteDeliveryTest.php
git commit -m "feat(invite): per-invite delivery choice (email or share link)"
```

---

### Task 2: share_targets table + ShareTargetRepo + brand icons

**Files:**
- Create: `migrations/0013_share_targets.sql`, `app/Share/ShareTargetRepo.php`
- Modify: `templates/partials/icons.php`
- Test: `tests/Share/ShareTargetRepoTest.php`

**Interfaces:**
- Produces:
  - `share_targets(id, `key` UNIQUE, label, icon, url_template, sort INT, enabled TINYINT)` seeded with whatsapp/telegram/messenger/sms/x/line (all enabled).
  - `App\Share\ShareTargetRepo`: `listEnabled(): array` (ordered by sort), `all(): array`, `getExact(string $key): ?array`, `update(string $key, string $label, string $urlTemplate, bool $enabled): void` (upsert), `setEnabled(string $key, bool): void`, `render(string $template, string $url): string` (interpolate `{url}`→`rawurlencode`, `{text}`→`rawurlencode`), `static isAllowed(string $urlTemplate): bool` (scheme in http/https/sms/mailto).
  - Icon sprite gains `ic-whatsapp`, `ic-telegram`, `ic-messenger`, `ic-sms`, `ic-x`, `ic-line`, `ic-copy`, `ic-share`.

- [ ] **Step 1: Write the failing test** — `tests/Share/ShareTargetRepoTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Share;

use App\Share\ShareTargetRepo;
use Tests\Support\DatabaseTestCase;

final class ShareTargetRepoTest extends DatabaseTestCase
{
    public function test_seeded_targets_enabled(): void
    {
        $repo = new ShareTargetRepo($this->pdo());
        $keys = array_column($repo->listEnabled(), 'key');
        foreach (['whatsapp', 'telegram', 'messenger', 'sms', 'x', 'line'] as $k) {
            $this->assertContains($k, $keys);
        }
    }

    public function test_render_encodes_url(): void
    {
        $repo = new ShareTargetRepo($this->pdo());
        $out = $repo->render('https://wa.me/?text={url}', 'https://crush.app/i/AB?x=1');
        $this->assertStringContainsString('https%3A%2F%2Fcrush.app%2Fi%2FAB%3Fx%3D1', $out);
        $this->assertStringNotContainsString('{url}', $out);
    }

    public function test_scheme_allowlist(): void
    {
        $this->assertTrue(ShareTargetRepo::isAllowed('https://wa.me/?text={url}'));
        $this->assertTrue(ShareTargetRepo::isAllowed('sms:?body={url}'));
        $this->assertFalse(ShareTargetRepo::isAllowed('javascript:alert(1)'));
    }

    public function test_set_enabled_and_update(): void
    {
        $repo = new ShareTargetRepo($this->pdo());
        $repo->setEnabled('telegram', false);
        $this->assertNotContains('telegram', array_column($repo->listEnabled(), 'key'));
        $repo->update('telegram', 'Telegram', 'https://t.me/share/url?url={url}', true);
        $this->assertContains('telegram', array_column($repo->listEnabled(), 'key'));
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter ShareTargetRepoTest`
Expected: FAIL — table/class absent.

- [ ] **Step 3: Write `migrations/0013_share_targets.sql`**

```sql
CREATE TABLE IF NOT EXISTS share_targets (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `key`        VARCHAR(32)   NOT NULL,
  label        VARCHAR(40)   NOT NULL,
  icon         VARCHAR(32)   NOT NULL,
  url_template VARCHAR(512)  NOT NULL,
  sort         INT           NOT NULL DEFAULT 0,
  enabled      TINYINT(1)    NOT NULL DEFAULT 1,
  UNIQUE KEY uq_share_key (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO share_targets (`key`, label, icon, url_template, sort, enabled) VALUES
('whatsapp','WhatsApp','ic-whatsapp','https://wa.me/?text={url}',10,1),
('telegram','Telegram','ic-telegram','https://t.me/share/url?url={url}',20,1),
('messenger','Messenger','ic-messenger','https://www.facebook.com/dialog/send?link={url}&redirect_uri={url}',30,1),
('line','Line','ic-line','https://social-plugins.line.me/lineit/share?url={url}',40,1),
('sms','SMS','ic-sms','sms:?body={url}',50,1),
('x','X','ic-x','https://twitter.com/intent/tweet?text={url}',60,1)
ON DUPLICATE KEY UPDATE label=VALUES(label), icon=VALUES(icon), url_template=VALUES(url_template), sort=VALUES(sort);
```

- [ ] **Step 4: Write `app/Share/ShareTargetRepo.php`**

```php
<?php
declare(strict_types=1);

namespace App\Share;

final class ShareTargetRepo
{
    private const ALLOWED_SCHEMES = ['http', 'https', 'sms', 'mailto'];

    public function __construct(private \PDO $pdo) {}

    /** @return array<int,array> */
    public function listEnabled(): array
    {
        return $this->pdo->query('SELECT * FROM share_targets WHERE enabled = 1 ORDER BY sort, id')->fetchAll();
    }

    /** @return array<int,array> */
    public function all(): array
    {
        return $this->pdo->query('SELECT * FROM share_targets ORDER BY sort, id')->fetchAll();
    }

    public function getExact(string $key): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM share_targets WHERE `key` = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function update(string $key, string $label, string $urlTemplate, bool $enabled): void
    {
        $this->pdo->prepare(
            'INSERT INTO share_targets (`key`, label, icon, url_template, sort, enabled)
             VALUES (?, ?, ?, ?, 0, ?)
             ON DUPLICATE KEY UPDATE label = VALUES(label), url_template = VALUES(url_template), enabled = VALUES(enabled)'
        )->execute([$key, $label, $key, $urlTemplate, $enabled ? 1 : 0]);
    }

    public function setEnabled(string $key, bool $enabled): void
    {
        $this->pdo->prepare('UPDATE share_targets SET enabled = ? WHERE `key` = ?')->execute([$enabled ? 1 : 0, $key]);
    }

    public function render(string $template, string $url): string
    {
        $enc = rawurlencode($url);
        return str_replace(['{url}', '{text}'], [$enc, $enc], $template);
    }

    public static function isAllowed(string $urlTemplate): bool
    {
        $scheme = strtolower((string) parse_url($urlTemplate, PHP_URL_SCHEME));
        return in_array($scheme, self::ALLOWED_SCHEMES, true);
    }
}
```

- [ ] **Step 5: Add brand glyphs to `templates/partials/icons.php`** — insert these `<symbol>`s before the closing `</svg>`:

```php
  <symbol id="ic-copy" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></symbol>
  <symbol id="ic-share" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/></symbol>
  <symbol id="ic-whatsapp" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15L2 22l5.1-1.3A10 10 0 1 0 12 2Zm0 18a8 8 0 0 1-4.1-1.1l-.3-.2-2.8.8.8-2.7-.2-.3A8 8 0 1 1 12 20Zm4.6-6c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.1-.3.2-.5 0a6.5 6.5 0 0 1-3.2-2.8c-.2-.4.2-.4.6-1.2.1-.1 0-.3 0-.4l-.8-1.9c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2c0 1.3 1 2.6 1.1 2.8.1.2 1.9 3 4.7 4.1 1.7.7 2.3.8 3.1.7.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.1-1.2-.1-.1-.2-.1-.4-.2Z"/></symbol>
  <symbol id="ic-telegram" viewBox="0 0 24 24"><path fill="currentColor" d="M21.9 4.3 2.9 11.6c-.9.4-.9 1.6.1 1.9l4.6 1.4 1.8 5.6c.2.6 1 .8 1.5.3l2.5-2.4 4.7 3.5c.6.4 1.4.1 1.6-.6l3.5-15.6c.2-.9-.6-1.6-1.4-1.3Zm-3.6 4-7.9 7c-.2.2-.3.4-.3.7l-.3 2.4-1.4-4.3 9.5-5.9c.4-.3.8.2.4.5Z"/></symbol>
  <symbol id="ic-messenger" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2C6.4 2 2 6.2 2 11.8c0 3 1.4 5.6 3.7 7.3V23l3.4-1.9c.9.3 1.9.4 2.9.4 5.6 0 10-4.2 10-9.8S17.6 2 12 2Zm1 13.2-2.5-2.7-4.9 2.7 5.4-5.7 2.6 2.7 4.8-2.7-5.4 5.7Z"/></symbol>
  <symbol id="ic-line" viewBox="0 0 24 24"><path fill="currentColor" d="M12 3C6.5 3 2 6.6 2 11c0 4 3.6 7.3 8.5 7.9.3.1.7.2.8.5.1.2 0 .6 0 .9l-.1.8c0 .3-.2 1 .9.6 1.1-.5 6-3.5 8.2-6 1.5-1.6 1.7-3.3 1.7-4.7 0-4.4-4.5-8-10-8Zm-3.9 9.6h-2c-.3 0-.5-.2-.5-.4V9.3c0-.3.2-.5.5-.5s.5.2.5.5v2.4h1.5c.3 0 .5.2.5.5s-.3.4-.5.4Zm2-.4c0 .2-.2.4-.5.4s-.5-.2-.5-.4V9.3c0-.3.2-.5.5-.5s.5.2.5.5v2.9Zm4.7 0c0 .2-.1.4-.4.4-.1 0-.3 0-.4-.2l-1.6-2.2v2c0 .2-.2.4-.5.4s-.5-.2-.5-.4V9.3c0-.2.1-.4.3-.5.3-.1.5 0 .6.2l1.7 2.2v-2c0-.3.2-.5.5-.5s.4.2.4.5v2.9Zm3-1.9c.3 0 .5.2.5.4 0 .3-.2.5-.5.5h-1.4v.6h1.4c.3 0 .5.2.5.4 0 .3-.2.5-.5.5h-2c-.2 0-.4-.2-.4-.5V9.3c0-.2.2-.5.4-.5h2c.3 0 .5.2.5.5 0 .2-.2.4-.5.4h-1.4v.6h1.4Z"/></symbol>
  <symbol id="ic-sms" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 9 9 0 0 1-3.9-.9L3 20l1-3.1A8.4 8.4 0 1 1 21 11.5Z"/></symbol>
  <symbol id="ic-x" viewBox="0 0 24 24"><path fill="currentColor" d="M17.5 3h3l-6.6 7.5L21.7 21h-5.9l-4.6-6-5.3 6H3l7.1-8L2.6 3h6l4.1 5.5L17.5 3Zm-1 16h1.6L7.6 4.7H5.9L16.5 19Z"/></symbol>
```

- [ ] **Step 6: Run the test** — Run: `vendor/bin/phpunit --filter ShareTargetRepoTest`
Expected: PASS (4 tests).

- [ ] **Step 7: Run the full suite (serially)** — Run: `vendor/bin/phpunit`
Expected: all green.

- [ ] **Step 8: Commit**

```bash
git add migrations/0013_share_targets.sql app/Share/ShareTargetRepo.php templates/partials/icons.php tests/Share/ShareTargetRepoTest.php
git commit -m "feat(share): share_targets table + ShareTargetRepo + brand icons"
```

---

### Task 3: Share screen on the "invite ready" page

**Files:**
- Modify: `app/Invite/InviteController.php`, `templates/invite/created.php`
- Test: `tests/Invite/ShareScreenTest.php`

**Interfaces:**
- Consumes: `ShareTargetRepo::listEnabled` + `render` (Task 2).
- Produces: `InviteController` gains a trailing `ShareTargetRepo $share` constructor param; `showCreated` passes `shareLinks` = `[['label','icon','href'] …]` (each target's `url_template` rendered with the invite link) to `invite/created.php`, which renders the link, a Copy button, a native-share button (`navigator.share`, progressively shown), and one `<a>` per target.

- [ ] **Step 1: Write the failing test** — `tests/Invite/ShareScreenTest.php`

```php
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

final class ShareScreenTest extends DatabaseTestCase
{
    public function test_share_screen_lists_targets_with_invite_link(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $csrf = new Csrf(new ArrayStore());
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $invites = new InviteRepo($this->pdo(), $clock);
        $users = new UserRepo($this->pdo(), $clock);
        $ctrl = new InviteController(
            $view, $csrf, $invites, $users, $clock, 'https://crush.app',
            new Postman(new SpyMailer(), new IcsBuilder($clock), new EmailTemplateRepo($this->pdo()), 'https://crush.app'),
            new RateLimiter($this->pdo(), $clock), new BlockRepo($this->pdo(), $clock),
            new InvitePlaceRepo($this->pdo()), new LinkResolver(new FakeFetcher([])),
            new ShareTargetRepo($this->pdo())
        );
        $uid = $users->create('u@x.test', 'U', 'magic')['id'];
        $invite = $invites->create([
            'sender_id' => $uid, 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => false, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'expires_at' => '2026-12-01 00:00:00',
        ]);

        $res = $ctrl->showCreated($uid, $invite['public_token']);
        $this->assertSame(200, $res->status());
        $body = $res->body();
        $encoded = rawurlencode('https://crush.app/i/' . $invite['public_token']);
        $this->assertStringContainsString('wa.me', $body);                  // a target rendered
        $this->assertStringContainsString($encoded, $body);                 // with the encoded link
        $this->assertStringContainsString('navigator.share', $body);        // native share hook
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter ShareScreenTest`
Expected: FAIL — `InviteController::__construct` arity / no targets in body.

- [ ] **Step 3: Add the `ShareTargetRepo` dependency + share links in `InviteController`** — add `use App\Share\ShareTargetRepo;`, a trailing constructor param `private ShareTargetRepo $share,`, and in `showCreated` build + pass `shareLinks`:

```php
        $link = rtrim($this->appUrl, '/') . '/i/' . $invite['public_token'];
        $shareLinks = array_map(fn(array $t): array => [
            'label' => $t['label'],
            'icon'  => $t['icon'],
            'href'  => $this->share->render((string) $t['url_template'], $link),
        ], $this->share->listEnabled());

        return Response::html($this->view->render('invite/created', [
            'title'      => 'Invite ready',
            'link'       => $link,
            'invite'     => $invite,
            'shareLinks' => $shareLinks,
        ]));
```

- [ ] **Step 4: Rewrite `templates/invite/created.php`** as the share screen:

```php
<?php $link = $link ?? ''; $invite = $invite ?? []; $shareLinks = $shareLinks ?? []; ?>
<?php $content = function () use ($e, $link, $invite, $shareLinks) {
  $who = $invite['crush_name'] ?: ($invite['crush_email'] ?: '');
  ob_start(); ?>
  <?php include __DIR__ . '/../partials/icons.php'; ?>
  <h1 style="text-wrap:balance;">Your invite is ready</h1>
  <p style="opacity:.8;"><?= $who !== '' ? 'Share this private link with <strong>' . $e($who) . '</strong>:' : 'Share your private invite link:' ?></p>
  <div style="display:flex;gap:8px;">
    <input id="lnk" readonly value="<?= $e($link) ?>"
           style="flex:1;min-width:0;padding:11px;border-radius:12px;border:1px solid #e7d4ff;font-size:13px;" onclick="this.select()">
    <button type="button" id="copyBtn" aria-label="Copy link"
            style="padding:0 14px;border:0;border-radius:12px;background:#ff3d8b;color:#fff;font-weight:700;cursor:pointer;">
      <svg width="18" height="18"><use href="#ic-copy"/></svg>
    </button>
  </div>
  <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:16px;">
    <button type="button" id="nativeShare" hidden
            style="display:flex;align-items:center;gap:6px;padding:9px 12px;border:1px solid #e7d4ff;border-radius:12px;background:#fff;color:#5a2a52;font-weight:600;cursor:pointer;">
      <svg width="18" height="18"><use href="#ic-share"/></svg> Share
    </button>
    <?php foreach ($shareLinks as $s): ?>
      <a href="<?= $e($s['href']) ?>" target="_blank" rel="noopener"
         style="display:flex;align-items:center;gap:6px;padding:9px 12px;border:1px solid #e7d4ff;border-radius:12px;color:#5a2a52;text-decoration:none;font-weight:600;">
        <svg width="18" height="18"><use href="#<?= $e($s['icon']) ?>"/></svg> <?= $e($s['label']) ?>
      </a>
    <?php endforeach; ?>
  </div>
  <p style="margin-top:18px;"><a href="/invites" style="color:#ff3d8b;font-weight:600;">Back to your invites</a></p>
  <script>
  (function(){
    var url = <?= json_encode($link, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var copy = document.getElementById('copyBtn');
    if (copy) copy.addEventListener('click', function(){
      navigator.clipboard && navigator.clipboard.writeText(url).then(function(){ copy.setAttribute('aria-label','Copied'); });
    });
    var ns = document.getElementById('nativeShare');
    if (ns && navigator.share) {
      ns.hidden = false;
      ns.addEventListener('click', function(){ navigator.share({ url: url }).catch(function(){}); });
    }
  })();
  </script>
  <?php return ob_get_clean(); };
$cardClass = 'card--wide';
$body = $content();
include __DIR__ . '/../layout.php';
```

- [ ] **Step 5: Update existing `InviteController` constructions** — every test that builds `InviteController` now passes a trailing `new ShareTargetRepo($this->pdo())` (import it). Affected: `tests/Invite/InviteCuisineCreateTest.php`, `InviteDeliveryTest.php`, `InvitePlacesCreateTest.php`, `InviteBannerTest.php`, `InviteSenderLangTest.php`, and any `InviteControllerTest`/`InviteRateLimitTest`. Update `public/index.php` to pass `$shareTargets = new ShareTargetRepo($pdo);`. Run `vendor/bin/phpunit` until green.

- [ ] **Step 6: Run the full suite (serially)** — Run: `vendor/bin/phpunit`
Expected: all green.

- [ ] **Step 7: Commit**

```bash
git add app/Invite/InviteController.php templates/invite/created.php public/index.php tests/
git commit -m "feat(share): share screen with copy, native share, and targets"
```

---

### Task 4: Admin — manage share targets

**Files:**
- Modify: `app/Admin/AdminController.php`, `config/routes.php`, `public/index.php`
- Create: `templates/admin/share.php`, `templates/admin/share_edit.php`
- Test: `tests/Admin/AdminShareTest.php`

**Interfaces:**
- Consumes: `ShareTargetRepo` (Task 2).
- Produces: `AdminController` gains a trailing `ShareTargetRepo $shareTargets` constructor param and `shareList(?int)`, `editShare(?int,string $key)`, `saveShare(?int,array,string)` — all `is_admin`-gated, CSRF on save; `saveShare` rejects a `url_template` failing `ShareTargetRepo::isAllowed` (422 re-render). Routes `GET /admin/share`, `GET /admin/share/edit`, `POST /admin/share`.

- [ ] **Step 1: Write the failing test** — `tests/Admin/AdminShareTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Admin;

use App\Admin\AdminController;
use App\Auth\UserRepo;
use App\Core\ArrayStore;
use App\Core\Csrf;
use App\Core\View;
use App\Invite\InviteRepo;
use App\Mail\EmailTemplateRepo;
use App\Security\BlockRepo;
use App\Settings\SettingsRepo;
use App\Share\ShareTargetRepo;
use App\Theme\AbEventRepo;
use App\Theme\ThemeRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class AdminShareTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    private function controller(Csrf $csrf): AdminController
    {
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        return new AdminController(
            $view, $csrf, new UserRepo($this->pdo(), $this->clock), new SettingsRepo($this->pdo()),
            new ThemeRepo($this->pdo()), new AbEventRepo($this->pdo(), $this->clock),
            new InviteRepo($this->pdo(), $this->clock), new BlockRepo($this->pdo(), $this->clock),
            'http://localhost', new EmailTemplateRepo($this->pdo()), new ShareTargetRepo($this->pdo())
        );
    }

    private function adminId(): int
    {
        $u = (new UserRepo($this->pdo(), $this->clock))->create('admin@x.test', 'Boss', 'magic');
        $this->pdo()->prepare('UPDATE users SET is_admin = 1 WHERE id = ?')->execute([$u['id']]);
        return $u['id'];
    }

    public function test_list_requires_admin(): void
    {
        $this->assertSame(403, $this->controller(new Csrf(new ArrayStore()))->shareList(null)->status());
    }

    public function test_list_renders_targets(): void
    {
        $res = $this->controller(new Csrf(new ArrayStore()))->shareList($this->adminId());
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('whatsapp', $res->body());
    }

    public function test_save_updates_and_redirects(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $ctrl = $this->controller($csrf);
        $res = $ctrl->saveShare($this->adminId(), [
            'key' => 'whatsapp', 'label' => 'WhatsApp', 'url_template' => 'https://wa.me/?text={url}', 'enabled' => '1',
        ], $csrf->token());
        $this->assertSame(302, $res->status());
    }

    public function test_save_rejects_unsafe_template(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf)->saveShare($this->adminId(), [
            'key' => 'whatsapp', 'label' => 'X', 'url_template' => 'javascript:alert(1)', 'enabled' => '1',
        ], $csrf->token());
        $this->assertSame(422, $res->status());
    }

    public function test_save_rejects_bad_csrf(): void
    {
        $res = $this->controller(new Csrf(new ArrayStore()))->saveShare($this->adminId(), ['key' => 'whatsapp'], 'wrong');
        $this->assertSame(400, $res->status());
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter AdminShareTest`
Expected: FAIL — arity / methods undefined.

- [ ] **Step 3: Add the methods to `AdminController`** — `use App\Share\ShareTargetRepo;`, a trailing constructor param `private ShareTargetRepo $shareTargets,`, and:

```php
    public function shareList(?int $userId): Response
    {
        if ($this->requireAdmin($userId) === null) {
            return $this->forbidden();
        }
        return $this->render('admin/share', ['title' => 'Share buttons', 'targets' => $this->shareTargets->all()]);
    }

    public function editShare(?int $userId, string $key): Response
    {
        if ($this->requireAdmin($userId) === null) {
            return $this->forbidden();
        }
        $row = $this->shareTargets->getExact($key);
        if ($row === null) {
            return $this->render('admin/share', ['title' => 'Share buttons', 'targets' => $this->shareTargets->all(), 'flash' => 'Unknown target.'])->withStatus(404);
        }
        return $this->render('admin/share_edit', ['title' => 'Edit share button', 'csrf' => $this->csrf->token(), 'target' => $row]);
    }

    public function saveShare(?int $userId, array $input, string $csrf): Response
    {
        if ($this->requireAdmin($userId) === null) {
            return $this->forbidden();
        }
        if (!$this->csrf->validate($csrf)) {
            return $this->render('admin/share', ['title' => 'Share buttons', 'targets' => $this->shareTargets->all(), 'flash' => 'Session expired, please retry.'])->withStatus(400);
        }
        $key = (string) ($input['key'] ?? '');
        $label = (string) ($input['label'] ?? '');
        $template = (string) ($input['url_template'] ?? '');
        $enabled = !empty($input['enabled']);
        if ($key === '' || $label === '' || !ShareTargetRepo::isAllowed($template)) {
            return $this->render('admin/share', [
                'title' => 'Share buttons', 'targets' => $this->shareTargets->all(),
                'flash' => 'A share link must use http(s), sms, or mailto and have a label.',
            ])->withStatus(422);
        }
        $this->shareTargets->update($key, $label, $template, $enabled);
        return (new Response('', 302))->withHeader('Location', '/admin/share');
    }
```

- [ ] **Step 4: Write `templates/admin/share.php`** (list with edit links + enabled state)

```php
<?php $targets = $targets ?? []; $flash = $flash ?? null; ?>
<?php $content = function () use ($e, $targets, $flash) { ob_start(); ?>
  <div class="panel"><h1>Share buttons</h1>
  <?php if ($flash): ?><div class="flash"><?= $e($flash) ?></div><?php endif; ?>
  <table><tr><th>Key</th><th>Label</th><th>Enabled</th><th></th></tr>
  <?php foreach ($targets as $t): ?>
    <tr><td><?= $e($t['key']) ?></td><td><?= $e($t['label']) ?></td>
      <td><?= ((int) $t['enabled'] === 1) ? 'yes' : 'no' ?></td>
      <td><a href="/admin/share/edit?key=<?= $e($t['key']) ?>">Edit</a></td></tr>
  <?php endforeach; ?>
  </table></div>
<?php return ob_get_clean(); };
$body = $content();
include __DIR__ . '/layout.php';
```

- [ ] **Step 5: Write `templates/admin/share_edit.php`**

```php
<?php $target = $target ?? []; $csrf = $csrf ?? ''; ?>
<?php $content = function () use ($e, $target, $csrf) { ob_start(); ?>
  <div class="panel"><h1>Edit <?= $e($target['key']) ?></h1>
  <p style="font-size:12px;opacity:.7">Use {url} where the invite link goes. Allowed: http(s), sms, mailto.</p>
  <form method="post" action="/admin/share">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <input type="hidden" name="key" value="<?= $e($target['key']) ?>">
    <label>Label <input type="text" name="label" value="<?= $e($target['label']) ?>"></label>
    <label>URL template <input type="text" name="url_template" value="<?= $e($target['url_template']) ?>" style="width:100%"></label>
    <label><input type="checkbox" name="enabled" value="1" <?= ((int) $target['enabled'] === 1) ? 'checked' : '' ?>> Enabled</label>
    <button type="submit">Save</button>
  </form></div>
<?php return ob_get_clean(); };
$body = $content();
include __DIR__ . '/layout.php';
```

- [ ] **Step 6: Register routes + nav + wiring** — in `config/routes.php`:

```php
    $router->add('GET',  '/admin/share',      static fn(): Response => $admin->shareList($currentUserId()));
    $router->add('GET',  '/admin/share/edit', static fn(): Response => $admin->editShare(
        $currentUserId(), (static fn($v) => is_string($v) ? $v : '')($_GET['key'] ?? '')
    ));
    $router->add('POST', '/admin/share',      static fn(): Response => $admin->saveShare(
        $currentUserId(), $_POST, (static fn($v) => is_string($v) ? $v : '')($_POST['csrf'] ?? '')
    ));
```

Add a nav link to `templates/admin/layout.php`: `<a href="/admin/share">Share</a>`. In `public/index.php`, pass the existing `$shareTargets` as the trailing `AdminController` arg.

- [ ] **Step 7: Update existing AdminController test constructions** — add the trailing `new ShareTargetRepo($this->pdo())` to `AdminAuthTest`, `AdminSettingsTest`, `AdminThemesTest`, `AdminModerationTest`, `AdminTemplatesTest` (import it). Run `vendor/bin/phpunit` until green.

- [ ] **Step 8: Run the full suite (serially)** — Run: `vendor/bin/phpunit`
Expected: all green.

- [ ] **Step 9: Commit**

```bash
git add app/Admin/AdminController.php config/routes.php public/index.php templates/admin/ tests/
git commit -m "feat(admin): manage share buttons at /admin/share"
```

---

## Self-Review

**1. Spec coverage:** Delivery choice (email required+sent vs link-optional+no-send), email nullable (spec §C) — Task 1. `share_targets` table + repo + brand icons, web-intent templates, scheme allowlist (§D) — Task 2. Share screen with copy + native share + targets (§D) — Task 3. Admin `/admin/share` list/edit/toggle, unsafe-template rejection (§D) — Task 4. Icons only; escaped; CSRF; admin-gated — throughout.

**2. Placeholder scan:** No "TBD". The created.php link is injected into JS via `json_encode` with HEX flags (XSS-safe); target hrefs are `render`-encoded then `$e()`-escaped in the attribute. Full code for every change.

**3. Type consistency:** `ShareTargetRepo::listEnabled/all(): array`, `getExact(string): ?array`, `update(string,string,string,bool): void`, `setEnabled(string,bool): void`, `render(string,string): string`, `static isAllowed(string): bool`. `InviteController` + `AdminController` each gain a trailing `ShareTargetRepo` param, matched in `public/index.php` and every test construction. `showCreated` passes `shareLinks`; `created.php` consumes `shareLinks`/`link`/`invite`. `delivery` read in `create`; `crush_email` stored null when blank (column nullable via 0012).
