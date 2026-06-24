# Crush v3 — Plan 5: Landing Flow + Language Wiring Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The landing never dead-ends — a new email creates an account (detected language) + logs in + sends a localized welcome; an existing email just logs in and continues (no welcome) with an identity banner on the invite form. Language detection is wired through landing, invite creation, and the crush submit.

**Architecture:** `LandingController::start` is reworked (new vs existing) and now sends the **welcome template** via `Postman` (no more inline mail); a `/switch` route logs out for "not you". `InviteController` shows the identity banner and stamps the invite's `lang` from the sender. `RespondController::submit` detects the crush's language and passes it to `CrushOnboarder`.

**Tech Stack:** PHP 8.1+, PDO MySQL, PHPUnit 10. No new Composer dependencies.

## Global Constraints

- PHP floor 8.1. PSR-4. No new Composer packages.
- **Icons only — never emojis.** All HTML `$e()`-escaped. POSTs validate CSRF.
- New email → create + `setLang(detected)` + log in + welcome email + `/invites/new`. Existing email → log in, **no** welcome email, `/invites/new`. (Accepted tradeoff: existing-email auto-login; the identity banner is the typo guard.)
- Mail failures stay swallowed. Integration tests use MySQL `crush_test`. Production: `https://crush.didudi.com`.

## File Structure

- `app/Landing/LandingController.php` (modify) — new/existing split, lang, welcome via `Postman`, `switchAccount`.
- `app/Invite/InviteController.php` (modify) — identity banner data + invite `lang` from sender.
- `app/Respond/RespondController.php` (modify) — detect crush lang, pass to onboarder.
- `app/Respond/CrushOnboarder.php` (modify) — `lang` param, `setLang`, localized welcome.
- `templates/invite/new.php` (modify) — identity banner.
- `config/routes.php`, `public/index.php` (modify) — `/switch` route, `Accept-Language` into landing/submit, `LandingController` ctor change.

---

### Task 1: LandingController rework (new vs existing + lang + welcome via Postman)

**Files:**
- Modify: `app/Landing/LandingController.php`
- Modify: `config/routes.php`, `public/index.php`
- Test: `tests/Landing/LandingControllerTest.php` (rewrite)

**Interfaces:**
- `LandingController::__construct(View $view, Csrf $csrf, UserRepo $users, MagicLink $magic, Session $session, Postman $postman, string $appUrl)` — **`Mailer` is replaced by `Postman`**.
- `home(?int $userId): Response` — unchanged (logged in → `302 /invites`; else render `landing/home`).
- `start(array $input, string $csrf, string $acceptLanguage = ''): Response`:
  - `400` bad CSRF; `422` empty name or invalid email.
  - **existing email:** `Session::login(existing.id)` → `302 /invites/new` (no email).
  - **new email:** `UserRepo::create(email, name, 'magic')` → `setLang(Locale::detect($acceptLanguage))` → `Session::login` → `Postman::sendWelcome(email, name, {appUrl}/auth/magic/{token}, lang)` → `302 /invites/new`.
- `switchAccount(): Response` — `Session::logout()` → `302 /`.

- [ ] **Step 1: Rewrite `tests/Landing/LandingControllerTest.php`**

```php
<?php
declare(strict_types=1);

namespace Tests\Landing;

use App\Auth\MagicLink;
use App\Auth\Session;
use App\Auth\UserRepo;
use App\Core\ArrayStore;
use App\Core\Csrf;
use App\Core\View;
use App\Ics\IcsBuilder;
use App\Landing\LandingController;
use App\Mail\EmailTemplateRepo;
use App\Mail\Postman;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;
use Tests\Support\SpyMailer;

final class LandingControllerTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    private function controller(Csrf $csrf, Session $session, SpyMailer $spy): LandingController
    {
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $users = new UserRepo($this->pdo(), $this->clock);
        $magic = new MagicLink($this->pdo(), $users, $this->clock, 900);
        $postman = new Postman($spy, new IcsBuilder($this->clock), new EmailTemplateRepo($this->pdo()), 'https://crush.app');
        return new LandingController($view, $csrf, $users, $magic, $session, $postman, 'https://crush.app');
    }

    public function test_home_renders_for_logged_out(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf, new Session(new ArrayStore()), new SpyMailer())->home(null);
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('name="email"', $res->body());
    }

    public function test_bad_csrf_is_400(): void
    {
        $res = $this->controller(new Csrf(new ArrayStore()), new Session(new ArrayStore()), new SpyMailer())
            ->start(['name' => 'A', 'email' => 'a@x.test'], 'wrong', '');
        $this->assertSame(400, $res->status());
    }

    public function test_invalid_email_is_422(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf, new Session(new ArrayStore()), new SpyMailer())
            ->start(['name' => 'A', 'email' => 'nope'], $csrf->token(), '');
        $this->assertSame(422, $res->status());
    }

    public function test_new_email_creates_logs_in_sets_lang_and_welcomes(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $session = new Session(new ArrayStore());
        $spy = new SpyMailer();
        $res = $this->controller($csrf, $session, $spy)
            ->start(['name' => 'New', 'email' => 'new@x.test'], $csrf->token(), 'vi-VN,vi;q=0.9');

        $this->assertSame(302, $res->status());
        $this->assertSame('/invites/new', $res->headers()['Location']);
        $this->assertTrue($session->check());
        $user = (new UserRepo($this->pdo(), $this->clock))->findByEmail('new@x.test');
        $this->assertSame('vi', $user['lang']);                    // detected + stored
        $this->assertCount(1, $spy->sent);                          // welcome email
        $this->assertSame('new@x.test', $spy->sent[0]->to);
        $this->assertStringContainsString('Chao mung', $spy->sent[0]->subject); // vi welcome subject
    }

    public function test_existing_email_logs_in_without_welcome(): void
    {
        (new UserRepo($this->pdo(), $this->clock))->create('dupe@x.test', 'Dee', 'magic');
        $csrf = new Csrf(new ArrayStore());
        $session = new Session(new ArrayStore());
        $spy = new SpyMailer();
        $res = $this->controller($csrf, $session, $spy)
            ->start(['name' => 'Dee', 'email' => 'dupe@x.test'], $csrf->token(), '');

        $this->assertSame(302, $res->status());
        $this->assertSame('/invites/new', $res->headers()['Location']);
        $this->assertTrue($session->check());                       // logged in
        $this->assertCount(0, $spy->sent);                          // NO welcome email
    }

    public function test_switch_account_logs_out(): void
    {
        $session = new Session(new ArrayStore());
        $session->login(7);
        $res = $this->controller(new Csrf(new ArrayStore()), $session, new SpyMailer())->switchAccount();
        $this->assertSame(302, $res->status());
        $this->assertSame('/', $res->headers()['Location']);
        $this->assertFalse($session->check());
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter LandingControllerTest`
Expected: FAIL — `LandingController::__construct` wants a `Mailer`; `switchAccount` undefined.

- [ ] **Step 3: Rewrite `app/Landing/LandingController.php`**

```php
<?php
declare(strict_types=1);

namespace App\Landing;

use App\Auth\MagicLink;
use App\Auth\Session;
use App\Auth\UserRepo;
use App\Core\Csrf;
use App\Core\Locale;
use App\Core\Response;
use App\Core\View;
use App\Mail\Postman;

final class LandingController
{
    public function __construct(
        private View $view,
        private Csrf $csrf,
        private UserRepo $users,
        private MagicLink $magic,
        private Session $session,
        private Postman $postman,
        private string $appUrl,
    ) {}

    public function home(?int $userId): Response
    {
        if ($userId !== null) {
            return (new Response('', 302))->withHeader('Location', '/invites');
        }
        return Response::html($this->view->render('landing/home', [
            'title' => 'Crush',
            'csrf'  => $this->csrf->token(),
        ]));
    }

    public function start(array $input, string $csrf, string $acceptLanguage = ''): Response
    {
        if (!$this->csrf->validate($csrf)) {
            return $this->render('Your session expired. Please try again.', 400);
        }
        $name = trim((string) ($input['name'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->render('Please enter your name and a valid email.', 422, $name, $email);
        }

        $existing = $this->users->findByEmail($email);
        if ($existing !== null) {
            $this->session->login((int) $existing['id']);   // no welcome email for returning accounts
            return (new Response('', 302))->withHeader('Location', '/invites/new');
        }

        $lang = Locale::detect($acceptLanguage);
        $user = $this->users->create($email, $name, 'magic');
        $this->users->setLang((int) $user['id'], $lang);
        $this->session->login((int) $user['id']);
        $token = $this->magic->start($email);
        $this->postman->sendWelcome($email, $name, rtrim($this->appUrl, '/') . '/auth/magic/' . $token, $lang);

        return (new Response('', 302))->withHeader('Location', '/invites/new');
    }

    public function switchAccount(): Response
    {
        $this->session->logout();
        return (new Response('', 302))->withHeader('Location', '/');
    }

    private function render(?string $error, int $status, string $name = '', string $email = ''): Response
    {
        return Response::html($this->view->render('landing/home', [
            'title' => 'Crush',
            'csrf'  => $this->csrf->token(),
            'error' => $error,
            'name'  => $name,
            'email' => $email,
        ]), $status);
    }
}
```

- [ ] **Step 4: Run to verify it passes** — Run: `vendor/bin/phpunit --filter LandingControllerTest`
Expected: PASS (6 tests).

- [ ] **Step 5: Update routes + wiring** — in `config/routes.php`: pass `$_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''` into `start`, and add `/switch`:

```php
    $router->add('POST', '/', static fn(): Response => $landing->start(
        $_POST,
        (static fn($v) => is_string($v) ? $v : '')($_POST['csrf'] ?? ''),
        (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')
    ));
    $router->add('GET', '/switch', static fn(): Response => $landing->switchAccount());
```

In `public/index.php`, change the `LandingController` construction to pass `$postman` instead of `$mailer`: `new LandingController($view, $csrf, $users, $magic, $session, $postman, (string) $config->get('app_url', 'http://localhost'))`. (`$postman` already exists.)

- [ ] **Step 6: Run the full suite (serially, once)** — Run: `vendor/bin/phpunit`
Expected: all green.

- [ ] **Step 7: Commit**

```bash
git add app/Landing/LandingController.php config/routes.php public/index.php tests/Landing/LandingControllerTest.php
git commit -m "feat(landing): new-vs-existing flow (welcome via templates, lang, switch)"
```

---

### Task 2: Identity banner on /invites/new

**Files:**
- Modify: `app/Invite/InviteController.php`
- Modify: `templates/invite/new.php`
- Test: `tests/Invite/InviteBannerTest.php`

**Interfaces:**
- `InviteController::showNew(?int $userId)` loads the current user (`UserRepo::findById`) and passes `me` (the user row) to the form. `renderForm` accepts/forwards `me`. `new.php` renders an identity banner when `me` has a name: "Creating as {avatar} {name} — not you? **use a different email** (`/switch`) · **log in** (`/login`)".

- [ ] **Step 1: Write the failing test** — `tests/Invite/InviteBannerTest.php`

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

final class InviteBannerTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    private function controller(): InviteController
    {
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $invites = new InviteRepo($this->pdo(), $this->clock);
        $users = new UserRepo($this->pdo(), $this->clock);
        $postman = new Postman(new SpyMailer(), new IcsBuilder($this->clock), new EmailTemplateRepo($this->pdo()), 'http://localhost');
        return new InviteController(
            $view, new Csrf(new ArrayStore()), $invites, $users, $this->clock, 'http://localhost', $postman,
            new RateLimiter($this->pdo(), $this->clock), new BlockRepo($this->pdo(), $this->clock),
            new InvitePlaceRepo($this->pdo()), new LinkResolver(new FakeFetcher([]))
        );
    }

    public function test_banner_shows_current_user_name(): void
    {
        $repo = new UserRepo($this->pdo(), $this->clock);
        $u = $repo->create('khang@x.test', 'Khang', 'magic');
        $repo->saveProfile($u['id'], 'fox', null, 'hi', null);

        $res = $this->controller()->showNew($u['id']);
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('Khang', $res->body());     // identity shown
        $this->assertStringContainsString('/switch', $res->body());   // "use a different email"
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter InviteBannerTest`
Expected: FAIL — banner / `me` not rendered.

- [ ] **Step 3: Modify `InviteController::showNew` + `renderForm`** — `showNew` loads the user and passes it:

```php
    public function showNew(?int $userId): Response
    {
        if ($userId === null) {
            return $this->redirect('/login');
        }
        return $this->renderForm(null, [], 200, $this->users->findById($userId));
    }
```

and update `renderForm` to accept + forward `me`:

```php
    private function renderForm(?string $error = null, array $old = [], int $status = 200, ?array $me = null): Response
    {
        return Response::html($this->view->render('invite/new', [
            'title' => 'New invite',
            'csrf'  => $this->csrf->token(),
            'error' => $error,
            'old'   => $old,
            'meals' => MealOptions::CHOICES,
            'me'    => $me,
        ]), $status);
    }
```

(The other `renderForm` callers in `create()` pass no `me` — that's fine; the banner only shows on `showNew`.)

- [ ] **Step 4: Add the banner to `templates/invite/new.php`** — add `$me = $me ?? null;` to the top defaults, capture `$me` in the closure `use (...)` list, and at the very top of the rendered content (before the `<h1>`), add:

```php
  <?php if ($me && !empty($me['name'])): ?>
    <div style="display:flex;align-items:center;gap:8px;font-size:13px;background:#faf2ff;border:1px solid #eadcff;border-radius:12px;padding:8px 12px;margin-bottom:12px;">
      <?php if (!empty($me['avatar_key'])): ?>
        <?php include __DIR__ . '/../partials/avatars.php'; ?>
        <svg width="22" height="22"><use href="#av-<?= $e($me['avatar_key']) ?>"/></svg>
      <?php endif; ?>
      <span>Creating as <strong><?= $e($me['name']) ?></strong></span>
      <span style="margin-left:auto;opacity:.8;">not you? <a href="/switch" style="color:#ff3d8b;">use another email</a> · <a href="/login" style="color:#ff3d8b;">log in</a></span>
    </div>
  <?php endif; ?>
```

- [ ] **Step 5: Run the test** — Run: `vendor/bin/phpunit --filter InviteBannerTest`
Expected: PASS.

- [ ] **Step 6: Run the full suite (serially)** — Run: `vendor/bin/phpunit`
Expected: all green (the existing `test_new_form_renders_with_csrf` still passes — `me` is null there, banner hidden).

- [ ] **Step 7: Commit**

```bash
git add app/Invite/InviteController.php templates/invite/new.php tests/Invite/InviteBannerTest.php
git commit -m "feat(landing): identity banner on /invites/new"
```

---

### Task 3: Language wiring — invite lang from sender, crush lang on submit

**Files:**
- Modify: `app/Invite/InviteController.php` (invite `lang` from sender)
- Modify: `app/Respond/CrushOnboarder.php` (`lang` param)
- Modify: `app/Respond/RespondController.php` (detect crush lang → onboarder)
- Modify: `config/routes.php`, `public/index.php`
- Test: `tests/Invite/InviteSenderLangTest.php`, `tests/Respond/CrushLangTest.php`

**Interfaces:**
- `InviteController::create` sets `data['lang'] = (current sender)['lang']` (so the crush invite email uses the sender's language).
- `CrushOnboarder::onboard(string $email, ?string $name, string $lang = 'en'): void` — on create, also `UserRepo::setLang` and pass `$lang` to `Postman::sendWelcome`.
- `RespondController::submit(string $token, array $input, string $csrf, string $acceptLanguage = '')` — detect the crush's lang via `Locale::detect` and pass it to `onboarder->onboard(...)`.

- [ ] **Step 1: Write the failing tests** — `tests/Invite/InviteSenderLangTest.php`

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
            new InvitePlaceRepo($this->pdo()), new LinkResolver(new FakeFetcher([]))
        );

        $sender = $users->create('sue@x.test', 'Sue', 'magic');
        $users->setLang($sender['id'], 'ko');
        $ctrl->create($sender['id'], ['crush_email' => 'c@x.test', 'date_mode' => 'instant'], $csrf->token());

        $invite = $invites->listBySender($sender['id'])[0];
        $this->assertSame('ko', $invite['lang']); // invite carries the sender's language
    }
}
```

And `tests/Respond/CrushLangTest.php`:

```php
<?php
declare(strict_types=1);

namespace Tests\Respond;

use App\Auth\MagicLink;
use App\Auth\UserRepo;
use App\Mail\EmailTemplateRepo;
use App\Mail\Postman;
use App\Respond\CrushOnboarder;
use App\Core\View;
use App\Ics\IcsBuilder;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;
use Tests\Support\SpyMailer;

final class CrushLangTest extends DatabaseTestCase
{
    public function test_onboard_sets_crush_lang_and_localized_welcome(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $users = new UserRepo($this->pdo(), $clock);
        $spy = new SpyMailer();
        $postman = new Postman($spy, new IcsBuilder($clock), new EmailTemplateRepo($this->pdo()), 'http://localhost');
        $onboarder = new CrushOnboarder($users, new MagicLink($this->pdo(), $users, $clock, 900), $postman, 'http://localhost');

        $onboarder->onboard('crush@x.test', 'Cee', 'vi');

        $this->assertSame('vi', $users->findByEmail('crush@x.test')['lang']);
        $this->assertStringContainsString('Chao mung', $spy->sent[0]->subject); // vi welcome
    }
}
```

- [ ] **Step 2: Run to verify they fail** — Run: `vendor/bin/phpunit --filter "InviteSenderLangTest|CrushLangTest"`
Expected: FAIL — invite `lang` null; `onboard` ignores lang.

- [ ] **Step 3: Set invite `lang` from sender in `InviteController::create`** — after `$dateMode` is computed and before `$this->invites->create([...])`, load the sender and add `lang` to the create data:

```php
        $sender = $this->users->findById($userId);
```

and add to the `$this->invites->create([...])` data array:

```php
            'lang'               => $sender['lang'] ?? null,
```

- [ ] **Step 4: Add `lang` to `CrushOnboarder::onboard`** — change the signature + body:

```php
    public function onboard(string $email, ?string $name, string $lang = 'en'): void
    {
        if ($this->users->findByEmail($email) !== null) {
            return;
        }
        $user = $this->users->create($email, $name, 'magic');
        $this->users->setLang((int) $user['id'], $lang);
        $token = $this->magic->start($email);
        $link = rtrim($this->appUrl, '/') . '/auth/magic/' . $token;
        $this->postman->sendWelcome($email, $name, $link, $lang);
    }
```

(`CrushOnboarder` already has `UserRepo $users` — `setLang` is available.)

- [ ] **Step 5: Detect crush lang in `RespondController::submit`** — change the signature to `submit(string $token, array $input, string $csrf, string $acceptLanguage = '')`, add `use App\Core\Locale;`, and in the onboarder call pass the detected lang:

```php
        try {
            $this->onboarder->onboard((string) $invite['crush_email'], $invite['crush_name'], \App\Core\Locale::detect($acceptLanguage));
        } catch (\Throwable $e) {
            error_log('Crush onboarding failed: ' . $e->getMessage());
        }
```

- [ ] **Step 6: Pass `Accept-Language` into submit** — in `config/routes.php`, the `POST /i/{token}` route adds the header:

```php
    $router->add('POST', '/i/{token}', static fn(string $token): Response => $respond->submit(
        $token, $_POST,
        (static fn($v) => is_string($v) ? $v : '')($_POST['csrf'] ?? ''),
        (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')
    ));
```

- [ ] **Step 7: Run the tests** — Run: `vendor/bin/phpunit --filter "InviteSenderLangTest|CrushLangTest"`
Expected: PASS.

- [ ] **Step 8: Run the full suite (serially)** — Run: `vendor/bin/phpunit`
Expected: all green. (Existing `RespondOnboardTest` still passes — `onboard`'s `lang` defaults to `en`; `submit`'s `acceptLanguage` defaults to `''`. The `InviteController::create` sender lookup is harmless — sender always exists when `$userId` is set.)

- [ ] **Step 9: Commit**

```bash
git add app/Invite/InviteController.php app/Respond/CrushOnboarder.php app/Respond/RespondController.php config/routes.php tests/
git commit -m "feat(i18n): invite lang from sender + crush lang on submit"
```

---

## Self-Review

**1. Spec coverage:** Landing new-vs-existing, no dead-end, welcome only for new, existing logs in (spec §3) — Task 1. Welcome via localized template + detected lang (§3,§5) — Task 1. Identity banner with switch/login (§3) — Task 2. Invite email uses sender's lang via `invites.lang` (§5) — Task 3. Crush welcome localized + `users.lang` set on submit (§5) — Task 3. Mail failures swallowed; icons only; CSRF — throughout.

**2. Placeholder scan:** No "TBD". The `landing/home` "sent" branch is now unreachable from `start` (both paths redirect) but left in place harmlessly. Full code shown for every change.

**3. Type consistency:** `LandingController::start(array,string,string): Response`, `switchAccount(): Response`, ctor takes `Postman` (not `Mailer`); `CrushOnboarder::onboard(string,?string,string): void`; `RespondController::submit(string,array,string,string): Response`; `InviteController::renderForm(?string,array,int,?array): Response`. Consumes `Locale::detect`, `UserRepo::setLang/findById/create`, `Postman::sendWelcome(...,lang)`, `MagicLink::start`, `Session` as defined in v3-1..v3-3. `LandingController` ctor change matched in `public/index.php` (passing `$postman`); `submit`/`start` new params default-valued so route wiring + existing tests stay green.
