# Crush v2 — Plan 2: Landing Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A cute one-screen landing page that is the front door: a logged-out visitor enters name + email; a new email creates the account, logs them in immediately, and sends a magic link for later; an existing email gets a login-link email ("check your email") without logging in.

**Architecture:** A `LandingController` owns `GET /` (landing for logged-out, redirect to the dashboard for logged-in) and `POST /` (new-vs-returning entry). It reuses `UserRepo`, `MagicLink`, `Session`, and `Mailer`. One full-viewport template.

**Tech Stack:** PHP 8.1+, PDO MySQL, PHPUnit 10.

## Global Constraints

- PHP floor 8.1. PSR-4. No new Composer packages.
- **Icons only — never emojis.** All HTML output escaped via `App\Core\e()`. `POST /` validates CSRF.
- New email → create user (with name) + log in immediately + email a magic link for later. Existing email → email a login link, render "check your email", do **not** log in.
- A mail-send failure must not break the action (caught + logged).
- Local dev serves on **port 8888**. Integration tests use MySQL `crush_test`.

## File Structure

- `app/Landing/LandingController.php` — home + start.
- `templates/landing/home.php` — the one-screen landing.
- `config/routes.php`, `public/index.php` (modify) — point `GET /` + `POST /` at the landing; keep `/invites` as the dashboard.

---

### Task 1: LandingController

**Files:**
- Create: `app/Landing/LandingController.php`
- Test: `tests/Landing/LandingControllerTest.php`

**Interfaces:**
- Consumes: `App\Auth\UserRepo`, `App\Auth\MagicLink`, `App\Auth\Session`, `App\Mail\Mailer`, `App\Mail\Email`, `App\Core\View`, `App\Core\Csrf`, `App\Core\Response`.
- Produces: `App\Landing\LandingController` with `__construct(View $view, Csrf $csrf, UserRepo $users, MagicLink $magic, Session $session, Mailer $mailer, string $appUrl)`:
  - `home(?int $userId): Response` — if logged in, `302 → /invites`; else render `landing/home` (200) with a CSRF token.
  - `start(array $input, string $csrf): Response` — `400` on bad CSRF; `422` on empty name or invalid email (re-render landing with an error). Otherwise:
    - **existing email** (`UserRepo::findByEmail`): `MagicLink::start`, email the login link, render `landing/home` with a `sent` flag (200), **no** session.
    - **new email**: `UserRepo::create(email, name, 'magic')`, `MagicLink::start`, email the link, `Session::login(id)`, `302 → /invites/new`.
  - Email sending goes through a private `sendLink(string $email, string $token): void` that catches `\Throwable` and `error_log`s.

- [ ] **Step 1: Write the failing test** — `tests/Landing/LandingControllerTest.php`

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
use App\Landing\LandingController;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;
use Tests\Support\SpyMailer;

final class LandingControllerTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    private function controller(Csrf $csrf, Session $session, SpyMailer $spy): LandingController
    {
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $users = new UserRepo($this->pdo(), $this->clock);
        $magic = new MagicLink($this->pdo(), $users, $this->clock, 900);
        return new LandingController($view, $csrf, $users, $magic, $session, $spy, 'https://crush.app');
    }

    public function test_home_renders_for_logged_out(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf, new Session(new ArrayStore()), new SpyMailer())->home(null);
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString($csrf->token(), $res->body());
        $this->assertStringContainsString('name="email"', $res->body());
    }

    public function test_home_redirects_logged_in_to_dashboard(): void
    {
        $res = $this->controller(new Csrf(new ArrayStore()), new Session(new ArrayStore()), new SpyMailer())->home(42);
        $this->assertSame(302, $res->status());
        $this->assertSame('/invites', $res->headers()['Location']);
    }

    public function test_start_bad_csrf_is_400(): void
    {
        $res = $this->controller(new Csrf(new ArrayStore()), new Session(new ArrayStore()), new SpyMailer())
            ->start(['name' => 'Ann', 'email' => 'a@x.test'], 'wrong');
        $this->assertSame(400, $res->status());
    }

    public function test_start_invalid_email_is_422(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf, new Session(new ArrayStore()), new SpyMailer())
            ->start(['name' => 'Ann', 'email' => 'nope'], $csrf->token());
        $this->assertSame(422, $res->status());
    }

    public function test_new_email_creates_logs_in_and_redirects(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $session = new Session(new ArrayStore());
        $spy = new SpyMailer();
        $ctrl = $this->controller($csrf, $session, $spy);

        $res = $ctrl->start(['name' => 'New', 'email' => 'new@x.test'], $csrf->token());

        $this->assertSame(302, $res->status());
        $this->assertSame('/invites/new', $res->headers()['Location']);
        $this->assertTrue($session->check());
        $this->assertCount(1, $spy->sent);                       // magic link emailed
        $this->assertSame('new@x.test', $spy->sent[0]->to);
        $user = (new UserRepo($this->pdo(), $this->clock))->findByEmail('new@x.test');
        $this->assertSame('New', $user['name']);
    }

    public function test_existing_email_emails_link_without_login(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $session = new Session(new ArrayStore());
        $spy = new SpyMailer();
        (new UserRepo($this->pdo(), new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'))))
            ->create('dupe@x.test', 'Dee', 'magic');

        $res = $this->controller($csrf, $session, $spy)
            ->start(['name' => 'Dee', 'email' => 'dupe@x.test'], $csrf->token());

        $this->assertSame(200, $res->status());
        $this->assertFalse($session->check());                    // NOT logged in
        $this->assertCount(1, $spy->sent);
        $this->assertStringContainsString('check your email', strtolower($res->body()));
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter LandingControllerTest`
Expected: FAIL — `Class "App\Landing\LandingController" not found`.

- [ ] **Step 3: Write `app/Landing/LandingController.php`**

```php
<?php
declare(strict_types=1);

namespace App\Landing;

use App\Auth\MagicLink;
use App\Auth\Session;
use App\Auth\UserRepo;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\View;
use App\Mail\Email;
use App\Mail\Mailer;

final class LandingController
{
    public function __construct(
        private View $view,
        private Csrf $csrf,
        private UserRepo $users,
        private MagicLink $magic,
        private Session $session,
        private Mailer $mailer,
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

    public function start(array $input, string $csrf): Response
    {
        if (!$this->csrf->validate($csrf)) {
            return $this->render('Your session expired. Please try again.', 400);
        }

        $name  = trim((string) ($input['name'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->render('Please enter your name and a valid email.', 422, $name, $email);
        }

        $existing = $this->users->findByEmail($email);
        if ($existing !== null) {
            $this->sendLink($email, $this->magic->start($email));
            return $this->render(null, 200, $name, $email, sent: $email);
        }

        $user = $this->users->create($email, $name, 'magic');
        $this->sendLink($email, $this->magic->start($email));
        $this->session->login((int) $user['id']);
        return (new Response('', 302))->withHeader('Location', '/invites/new');
    }

    private function sendLink(string $email, string $token): void
    {
        $link = rtrim($this->appUrl, '/') . '/auth/magic/' . $token;
        $safe = htmlspecialchars($link, ENT_QUOTES);
        $html = '<p style="font-family:sans-serif">Tap to sign in to Crush:</p>'
              . '<p><a href="' . $safe . '">Sign in</a></p>'
              . '<p style="color:#999;font-size:12px">Or paste: ' . $safe . '</p>';
        try {
            $this->mailer->send(new Email($email, 'Your Crush sign-in link', $html));
        } catch (\Throwable $e) {
            error_log('Crush landing mail failed: ' . $e->getMessage());
        }
    }

    private function render(?string $error, int $status, string $name = '', string $email = '', ?string $sent = null): Response
    {
        return Response::html($this->view->render('landing/home', [
            'title' => 'Crush',
            'csrf'  => $this->csrf->token(),
            'error' => $error,
            'name'  => $name,
            'email' => $email,
            'sent'  => $sent,
        ]), $status);
    }
}
```

- [ ] **Step 4: Run to verify it passes** — Run: `vendor/bin/phpunit --filter LandingControllerTest`
Expected: FAIL still — the `landing/home` template does not exist yet. Create a **minimal** placeholder so the controller tests pass, to be replaced by the real design in Task 2:

`templates/landing/home.php` (minimal placeholder):
```php
<?php $error = $error ?? null; $sent = $sent ?? null; $name = $name ?? ''; $email = $email ?? ''; ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><title><?= $e($title ?? 'Crush') ?></title></head>
<body>
<?php if ($sent): ?>
  <p>Check your email — we sent a sign-in link to <?= $e($sent) ?>.</p>
<?php else: ?>
  <?php if ($error): ?><p role="alert"><?= $e($error) ?></p><?php endif; ?>
  <form method="post" action="/">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <input name="name" value="<?= $e($name) ?>" placeholder="your name">
    <input name="email" value="<?= $e($email) ?>" placeholder="you@email.com">
    <button type="submit">Start</button>
  </form>
<?php endif; ?>
</body></html>
```

- [ ] **Step 5: Run to verify it passes** — Run: `vendor/bin/phpunit --filter LandingControllerTest`
Expected: PASS (6 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Landing/LandingController.php templates/landing/home.php tests/Landing/LandingControllerTest.php
git commit -m "feat(landing): LandingController new-vs-returning entry"
```

---

### Task 2: The one-screen landing design + routes + wiring

**Files:**
- Modify: `templates/landing/home.php` (replace placeholder with the real one-screen design)
- Modify: `config/routes.php`, `public/index.php`
- Test: `tests/Landing/LandingRoutingTest.php`

**Interfaces:**
- Consumes: `App\Landing\LandingController` (Task 1).
- Produces: `GET /` → `LandingController::home`; `POST /` → `LandingController::start`. `/invites` remains the authenticated dashboard.

- [ ] **Step 1: Replace `templates/landing/home.php` with the full one-screen design** (full-viewport, no scroll, cute, icons only — NO emojis)

```php
<?php $error = $error ?? null; $sent = $sent ?? null; $name = $name ?? ''; $email = $email ?? ''; ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $e($title ?? 'Crush') ?></title>
  <style>
    :root{ --pink:#ff3d8b; --ink:#5a2a52; }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{margin:0;font-family:ui-rounded,"Segoe UI",system-ui,sans-serif;color:var(--ink);
      background:linear-gradient(160deg,#ffd9ec 0%,#e7d4ff 55%,#d4f0ff 100%);
      min-height:100svh;display:flex;align-items:center;justify-content:center;overflow:hidden;
      -webkit-font-smoothing:antialiased;}
    .float{position:fixed;color:#fff6;animation:drift 9s ease-in-out infinite;}
    .float svg{width:100%;height:100%}
    @keyframes drift{0%,100%{transform:translateY(0) rotate(-6deg)}50%{transform:translateY(-22px) rotate(8deg)}}
    .stage{width:min(94vw,420px);text-align:center;padding:24px;position:relative;z-index:1}
    .mascot{width:84px;height:84px;margin:0 auto 6px;color:var(--pink);
      animation:bob 3s ease-in-out infinite;filter:drop-shadow(0 8px 14px rgba(255,61,139,.3))}
    @keyframes bob{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
    .word{font-size:46px;font-weight:900;letter-spacing:-1px;margin:2px 0;line-height:1;
      display:inline-flex;align-items:center;gap:8px;}
    .word .hb{width:38px;height:38px;color:var(--pink);animation:beat 1.2s ease-in-out infinite}
    @keyframes beat{0%,100%{transform:scale(1)}15%{transform:scale(1.25)}30%{transform:scale(1)}}
    .tag{opacity:.8;margin:6px 0 20px;text-wrap:balance;font-size:15px}
    .card{background:#fff;border-radius:24px;padding:18px;
      box-shadow:0 1px 2px rgba(90,42,82,.08),0 16px 34px rgba(157,123,255,.28)}
    .row{display:flex;flex-direction:column;gap:10px}
    .row input{padding:13px;border-radius:14px;border:1px solid #f0d9ea;font-size:16px;font-family:inherit}
    .go{padding:14px;border:0;border-radius:16px;background:var(--pink);color:#fff;font-weight:800;font-size:16px;
      cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;min-height:48px;
      transition:scale .12s cubic-bezier(.2,0,0,1),box-shadow .2s;box-shadow:0 6px 0 #c81e68}
    .go:active{scale:.97;box-shadow:0 3px 0 #c81e68}
    .go svg{width:18px;height:18px}
    .err{color:#b3243b;font-size:13px;margin:0 0 8px}
    .fine{font-size:12px;opacity:.6;margin-top:12px}
  </style>
</head>
<body>
  <svg width="0" height="0" style="position:absolute" aria-hidden="true">
    <symbol id="l-heart" viewBox="0 0 24 24"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z" fill="currentColor"/></symbol>
    <symbol id="l-spark" viewBox="0 0 24 24"><path d="M12 2l1.8 6.2L20 10l-6.2 1.8L12 18l-1.8-6.2L4 10l6.2-1.8z" fill="currentColor"/></symbol>
    <symbol id="l-mail" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="3"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></symbol>
  </svg>

  <span class="float" style="top:12%;left:10%;width:30px;height:30px;animation-delay:-1s"><svg><use href="#l-heart"/></svg></span>
  <span class="float" style="top:22%;right:12%;width:22px;height:22px;animation-delay:-3s"><svg><use href="#l-spark"/></svg></span>
  <span class="float" style="bottom:16%;left:16%;width:26px;height:26px;animation-delay:-5s"><svg><use href="#l-spark"/></svg></span>
  <span class="float" style="bottom:20%;right:14%;width:34px;height:34px;animation-delay:-2s"><svg><use href="#l-heart"/></svg></span>

  <main class="stage">
    <div class="mascot"><svg width="84" height="84"><use href="#l-mail"/></svg></div>
    <div class="word">Crush <span class="hb"><svg width="38" height="38"><use href="#l-heart"/></svg></span></div>
    <p class="tag">Send your crush a date — anonymously, adorably.</p>

    <div class="card">
      <?php if ($sent): ?>
        <p style="margin:6px 0;">We sent a sign-in link to <strong><?= $e($sent) ?></strong>. Open it to keep going.</p>
      <?php else: ?>
        <?php if ($error): ?><p class="err" role="alert"><?= $e($error) ?></p><?php endif; ?>
        <form method="post" action="/" class="row">
          <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
          <input name="name" value="<?= $e($name) ?>" placeholder="your name" required autocomplete="name">
          <input type="email" name="email" value="<?= $e($email) ?>" placeholder="you@email.com" required autocomplete="email">
          <button type="submit" class="go">Start <svg><use href="#l-mail"/></svg></button>
        </form>
        <p class="fine">No password needed — we'll email you a magic link.</p>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>
```

- [ ] **Step 2: Write the routing test** — `tests/Landing/LandingRoutingTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Landing;

use App\Core\Router;
use PHPUnit\Framework\TestCase;

final class LandingRoutingTest extends TestCase
{
    public function test_root_routes_exist_for_get_and_post(): void
    {
        $router = new Router();
        $router->add('GET', '/', static fn() => 'home');
        $router->add('POST', '/', static fn() => 'start');
        $this->assertNotNull($router->match('GET', '/'));
        $this->assertNotNull($router->match('POST', '/'));
    }
}
```

- [ ] **Step 3: Run it** — Run: `vendor/bin/phpunit --filter LandingRoutingTest`
Expected: PASS.

- [ ] **Step 4: Repoint the routes** — in `config/routes.php`, add a trailing `LandingController $landing` factory param. Change the existing `GET /` route (currently the dashboard) to the landing, add `POST /`, and keep `/invites` as the dashboard:

```php
    // Front door
    $router->add('GET',  '/', static fn(): Response => $landing->home($currentUserId()));
    $router->add('POST', '/', static fn(): Response => $landing->start($_POST, (static fn($v) => is_string($v) ? $v : '')($_POST['csrf'] ?? '')));
    // Dashboard moves to /invites only (the old `GET /` -> dashboard line is removed)
```

Ensure the old `$router->add('GET', '/', ... dashboard ...)` line is **removed** (keep `GET /invites` → dashboard).

- [ ] **Step 5: Wire in `public/index.php`** — add `use App\Landing\LandingController;`, build:

```php
$landingCtrl = new LandingController($view, $csrf, $users, $magic, $session, $mailer, (string) $config->get('app_url', 'http://localhost'));
```

(`$magic`, `$mailer`, `$session`, `$users` already exist.) Add `$landingCtrl` as the trailing argument to the routes-factory invocation, matching the new factory signature.

- [ ] **Step 6: Run the full suite** — Run: `vendor/bin/phpunit`
Expected: all green.

- [ ] **Step 7: Manual check on port 8888** — confirm the landing renders for logged-out and the form posts:

```bash
DB_DSN="mysql:host=127.0.0.1;dbname=crush_dev;charset=utf8mb4" DB_USER=root DB_PASS= APP_URL="http://127.0.0.1:8888" \
  php -S 127.0.0.1:8888 -t public >/dev/null 2>&1 &
sleep 1
curl -s 127.0.0.1:8888/ | grep -c 'Send your crush a date'
curl -s -X POST -d 'name=Test&email=bad&csrf=nope' 127.0.0.1:8888/ -o /dev/null -w 'POST bad csrf: %{http_code}\n'
kill %1
```
Expected: first prints `1` (landing rendered), second prints `400`.

- [ ] **Step 8: Commit**

```bash
git add templates/landing/home.php config/routes.php public/index.php tests/Landing/LandingRoutingTest.php
git commit -m "feat(landing): one-screen landing design + front-door routes"
```

---

## Self-Review

**1. Spec coverage:** One-screen cute landing with name+email (spec §5) — Task 2. New email → create + immediate login + magic link emailed (§4) — Task 1. Existing email → login-link email + "check your email", no login (§4) — Task 1. Landing replaces the bare front door, `/login` + `/invites` remain (§4,§9) — Task 2 routes. Mail failure non-fatal (§: robustness) — Task 1 `sendLink` try/catch. Icons only, CSRF on POST, escaped — Tasks 1,2. Port 8888 dev — Task 2 manual check.

**2. Placeholder scan:** No "TBD". Task 1 ships a minimal `landing/home.php` only so the controller tests pass; Task 2 replaces it with the full design — this is an explicit, sequenced handoff, not a placeholder.

**3. Type consistency:** `LandingController::home(?int): Response`, `start(array,string): Response`; consumes `UserRepo::findByEmail/create`, `MagicLink::start(string): string`, `Session::login(int)/check`, `Mailer::send(Email)`, `View`, `Csrf`, `Response` as defined in v1 Plans 1–2/6. Routes factory gains a trailing `LandingController`, matched in `public/index.php`. The old `GET /`→dashboard route is removed; `GET /invites`→dashboard stays.
