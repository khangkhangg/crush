# Crush v2 — Plan 4: Crush Accounts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** When a crush submits their response, auto-create an account for them (by email, with their name) and send a welcome email with a magic link + "complete your profile" prompt — the same onboarding the sender gets. Responding stays unauthenticated; existing users are not re-welcomed.

**Architecture:** A small `CrushOnboarder` service (find-or-create user + mint magic link + send welcome) keeps `RespondController::submit` clean. `Postman` gains a `sendWelcome` email. Wired into the existing submit flow after the response is stored.

**Tech Stack:** PHP 8.1+, PDO MySQL, PHPUnit 10.

## Global Constraints

- PHP floor 8.1. PSR-4. No new Composer packages.
- **Icons only — never emojis.** Email HTML escaped via `App\Core\e()`.
- Responding requires **no** auth. Onboarding never blocks/breaks the submit (catch + log).
- **Do not re-welcome an existing user** (no spam): only create + welcome when the email is new.
- Integration tests use MySQL `crush_test`. Local dev serves on **port 8888**.

## File Structure

- `app/Mail/Postman.php` (modify) — `sendWelcome`.
- `templates/email/welcome.php` — welcome email body.
- `app/Respond/CrushOnboarder.php` — find-or-create + welcome.
- `app/Respond/RespondController.php` (modify) — call the onboarder on submit.
- `public/index.php` (modify) — build + inject `CrushOnboarder`.

---

### Task 1: Postman.sendWelcome + CrushOnboarder

**Files:**
- Modify: `app/Mail/Postman.php`
- Create: `templates/email/welcome.php`
- Create: `app/Respond/CrushOnboarder.php`
- Test: `tests/Respond/CrushOnboarderTest.php`

**Interfaces:**
- Consumes: `App\Auth\UserRepo`, `App\Auth\MagicLink`, `App\Mail\Postman`.
- Produces:
  - `Postman::sendWelcome(string $email, ?string $name, string $loginLink): bool` — renders `email/welcome` and sends; swallows mail errors (returns bool), like the other Postman methods.
  - `App\Respond\CrushOnboarder` with `__construct(UserRepo $users, MagicLink $magic, Postman $postman, string $appUrl)` and `onboard(string $email, ?string $name): void`:
    - if `UserRepo::findByEmail($email)` is non-null → return (no create, no email).
    - else → `UserRepo::create($email, $name, 'magic')`, mint a magic link via `MagicLink::start($email)`, and `Postman::sendWelcome($email, $name, $appUrl . '/auth/magic/' . $token)`.

- [ ] **Step 1: Write the failing test** — `tests/Respond/CrushOnboarderTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Respond;

use App\Auth\MagicLink;
use App\Auth\UserRepo;
use App\Core\View;
use App\Ics\IcsBuilder;
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
        $postman = new Postman($spy, new IcsBuilder($this->clock), new View(\dirname(__DIR__, 2) . '/templates'), 'https://crush.app');
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
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter CrushOnboarderTest`
Expected: FAIL — `Class "App\Respond\CrushOnboarder" not found`.

- [ ] **Step 3: Add `sendWelcome` to `app/Mail/Postman.php`** (insert after `sendResult`)

```php
    public function sendWelcome(string $email, ?string $name, string $loginLink): bool
    {
        $html = $this->view->render('email/welcome', [
            'name' => $name,
            'link' => $loginLink,
        ]);
        return $this->dispatch(new Email($email, 'Welcome to Crush', $html));
    }
```

- [ ] **Step 4: Write `templates/email/welcome.php`** (icons only, escaped)

```php
<?php $name = $name ?? null; ?>
<div style="font-family:Segoe UI,system-ui,sans-serif;max-width:480px;margin:0 auto;padding:24px;background:#fff;border-radius:16px;">
  <h1 style="color:#ff3d8b;font-size:22px;">Welcome to Crush<?= $name ? ', ' . $e($name) : '' ?></h1>
  <p style="color:#444;line-height:1.5;">Your account is ready. Sign in and add a few cute details to your profile.</p>
  <p><a href="<?= $e($link) ?>" style="display:inline-block;padding:12px 20px;background:#ff3d8b;color:#fff;border-radius:12px;text-decoration:none;font-weight:700;">Sign in &amp; finish my profile</a></p>
  <p style="color:#999;font-size:12px;">Or paste this link: <?= $e($link) ?></p>
</div>
```

- [ ] **Step 5: Write `app/Respond/CrushOnboarder.php`**

```php
<?php
declare(strict_types=1);

namespace App\Respond;

use App\Auth\MagicLink;
use App\Auth\UserRepo;
use App\Mail\Postman;

final class CrushOnboarder
{
    public function __construct(
        private UserRepo $users,
        private MagicLink $magic,
        private Postman $postman,
        private string $appUrl,
    ) {}

    public function onboard(string $email, ?string $name): void
    {
        if ($this->users->findByEmail($email) !== null) {
            return; // existing account — never re-create or re-welcome
        }
        $this->users->create($email, $name, 'magic');
        $token = $this->magic->start($email);
        $link = rtrim($this->appUrl, '/') . '/auth/magic/' . $token;
        $this->postman->sendWelcome($email, $name, $link);
    }
}
```

- [ ] **Step 6: Run the test** — Run: `vendor/bin/phpunit --filter CrushOnboarderTest`
Expected: PASS (2 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Mail/Postman.php templates/email/welcome.php app/Respond/CrushOnboarder.php tests/Respond/CrushOnboarderTest.php
git commit -m "feat(crush): CrushOnboarder + welcome email"
```

---

### Task 2: Wire CrushOnboarder into the submit flow

**Files:**
- Modify: `app/Respond/RespondController.php`
- Modify: `public/index.php`
- Test: `tests/Respond/RespondOnboardTest.php`

**Interfaces:**
- `RespondController::__construct` gains a trailing `CrushOnboarder $onboarder` parameter.
- In `submit()`, after the response is stored, the state transitioned, the `completed` event logged, and the sender result emailed, call `$this->onboarder->onboard($invite['crush_email'], $invite['crush_name'])` inside a `try/catch (\Throwable)` (onboarding must never break the crush's submit).

- [ ] **Step 1: Write the failing test** — `tests/Respond/RespondOnboardTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Respond;

use App\Auth\MagicLink;
use App\Auth\UserRepo;
use App\Core\ArrayStore;
use App\Core\Csrf;
use App\Core\View;
use App\Ics\IcsBuilder;
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

final class RespondOnboardTest extends DatabaseTestCase
{
    public function test_submit_creates_crush_account_and_welcomes(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $csrf  = new Csrf(new ArrayStore());
        $view  = new View(\dirname(__DIR__, 2) . '/templates');
        $invites = new InviteRepo($this->pdo(), $clock);
        $users   = new UserRepo($this->pdo(), $clock);
        $spy     = new SpyMailer();
        $postman = new Postman($spy, new IcsBuilder($clock), $view, 'https://crush.app');
        $magic   = new MagicLink($this->pdo(), $users, $clock, 900);
        $onboarder = new CrushOnboarder($users, $magic, $postman, 'https://crush.app');

        $ctrl = new RespondController(
            $view, $csrf, $invites, new ResponseRepo($this->pdo(), $clock), $users,
            new ABAssigner(new ThemeRepo($this->pdo()), $invites, fn(int $m) => 0),
            new AbEventRepo($this->pdo(), $clock), $clock,
            new LinkResolver(new FakeFetcher([])), $postman, $onboarder
        );

        $sender = $users->create('sue@x.test', 'Sue', 'magic');
        $invite = $invites->create([
            'sender_id' => $sender['id'], 'crush_email' => 'crush@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => false, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'expires_at' => '2026-02-01 00:00:00',
        ]);

        $ctrl->submit($invite['public_token'], [
            'chosen_start' => '2026-02-10T19:00', 'meal_choice' => 'dinner',
        ], $csrf->token());

        // crush now has an account
        $crush = $users->findByEmail('crush@x.test');
        $this->assertNotNull($crush);
        $this->assertSame('Cee', $crush['name']);

        // both emails sent: result to sender + welcome to crush
        $recipients = array_map(fn($e) => $e->to, $spy->sent);
        $this->assertContains('sue@x.test', $recipients);
        $this->assertContains('crush@x.test', $recipients);
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter RespondOnboardTest`
Expected: FAIL — `RespondController::__construct()` too few arguments.

- [ ] **Step 3: Add the onboarder to `RespondController`** — add `use App\Respond\CrushOnboarder;`, a trailing constructor param `private CrushOnboarder $onboarder,`, and in `submit()` immediately after the existing sender-result block (the `if ($sender !== null) { ... sendResult ... }`), add:

```php
        try {
            $this->onboarder->onboard((string) $invite['crush_email'], $invite['crush_name']);
        } catch (\Throwable $e) {
            error_log('Crush onboarding failed: ' . $e->getMessage());
        }
```

- [ ] **Step 4: Wire in `public/index.php`** — after `$postman` and `$magic` are built (both already exist from v1), add:

```php
use App\Respond\CrushOnboarder;

$crushOnboarder = new CrushOnboarder($users, $magic, $postman, (string) $config->get('app_url', 'http://localhost'));
```

and pass `$crushOnboarder` as the trailing argument to `new RespondController(...)`.

- [ ] **Step 5: Update existing RespondController test constructions** — the controller's arity grew. In `tests/Respond/RespondOpenTest.php`, `tests/Respond/RespondSubmitTest.php`, `tests/Respond/RespondPickupTest.php`, and `tests/Mail/MailWiringTest.php`, every `new RespondController(...)` must pass a trailing `CrushOnboarder`. Construct it in each helper as:

```php
new CrushOnboarder($users, new MagicLink($this->pdo(), $users, $clock, 900), $postman, 'http://localhost')
```

(use the test's existing `$users`/`$postman`/`$clock`; add the needed `use` imports). Run `vendor/bin/phpunit` and fix every arity failure until green.

- [ ] **Step 6: Run the full suite** — Run: `vendor/bin/phpunit`
Expected: all green (~135 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Respond/RespondController.php public/index.php tests/
git commit -m "feat(crush): onboard crush account on submit"
```

---

## Self-Review

**1. Spec coverage:** Crush account auto-created on submit + welcome email + complete-profile flow (spec §4) — Tasks 1,2. Responding stays unauthenticated (submit unchanged except the post-store onboard call) (§4) — Task 2. Existing users not re-welcomed (§: no spam) — Task 1 `onboard` early return. Onboarding never breaks submit (§: robustness) — Task 2 try/catch. Icons only, escaped welcome email — Task 1. The crush's magic link routes them into `/auth/magic/{token}` → login → they can visit `/profile` (built in v2-1). Port 8888 dev (no new manual step needed).

**2. Placeholder scan:** No "TBD". The welcome template is complete; the onboarder logic is fully shown. Test-construction updates name the exact files and the exact constructor expression.

**3. Type consistency:** `Postman::sendWelcome(string,?string,string): bool`; `CrushOnboarder::onboard(string,?string): void`; consumes `UserRepo::findByEmail/create`, `MagicLink::start(string): string`, `Postman` as defined in v1/v2. `RespondController` gains a trailing `CrushOnboarder`, matched in `public/index.php` and all four test helpers. `invite['crush_email']`/`['crush_name']` are the existing invite row fields.
