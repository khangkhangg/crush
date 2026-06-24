# Crush v5 — Password Sign-In (no email dependency) + Polish Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove the magic-link dead-end by giving regular users a password they pick at signup and an email+password login at `/login` — so sign-in never depends on outbound email. Plus three approved polish items: an "add share button" admin form, and improved VI/KO email copy.

**Architecture:** The landing form gains a password field; `LandingController::start` picks the password for new users and verifies it for returning ones. `/login` gains an email+password form (magic link demoted to a secondary option), backed by a new `AuthController::loginPassword` using `UserRepo`. The admin share screen gains a create form; VI/KO welcome/invite templates get accented copy.

**Tech Stack:** PHP 8.1+, PDO MySQL, PHPUnit 10. No new Composer dependencies. `users.password_hash` already exists (migration 0008).

## Global Constraints

- PHP floor 8.1. PSR-4. No new Composer packages. Prepared statements. `password_hash`/`password_verify` (PASSWORD_DEFAULT).
- **Icons only — never emojis.** All HTML `$e()`-escaped. POSTs validate CSRF. Generic auth errors ("Invalid email or password").
- Password minimum length: **6**.
- Run the suite **serially** (concurrent `phpunit` corrupts `crush_test`).
- Integration tests use MySQL `crush_test`. Production: `https://crush.didudi.com`.

## File Structure

- `templates/landing/home.php` (modify) — password field.
- `app/Landing/LandingController.php` (modify) — pick/verify password.
- `app/Auth/AuthController.php` (modify) — `UserRepo` dep + `loginPassword`.
- `templates/auth/login.php` (modify) — email+password form + secondary magic link.
- `config/routes.php`, `public/index.php` (modify) — `POST /login` → password; `POST /login/magic` → magic.
- `app/Share/ShareTargetRepo.php`, `app/Admin/AdminController.php`, `templates/admin/share.php` (modify) — add-target.
- `migrations/0014_email_copy_intl.sql` — accented VI/KO copy.

---

### Task 1: Landing — pick a password (new) / verify (returning)

**Files:**
- Modify: `templates/landing/home.php`, `app/Landing/LandingController.php`
- Test: `tests/Landing/LandingPasswordTest.php`

**Interfaces:**
- `LandingController::start` now requires `password` (min 6, else 422). New email → create + `setPasswordHash` + login + welcome + `302 /invites/new`. Existing email **with** a password_hash → `password_verify`; match → login + `302 /invites/new`; mismatch → 401 re-render. Existing email **without** a password_hash (legacy) → set it (claim) + login + `302 /invites/new`.

- [ ] **Step 1: Write the failing test** — `tests/Landing/LandingPasswordTest.php`

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

final class LandingPasswordTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    private function controller(Csrf $csrf, Session $session): LandingController
    {
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $users = new UserRepo($this->pdo(), $this->clock);
        $magic = new MagicLink($this->pdo(), $users, $this->clock, 900);
        $postman = new Postman(new SpyMailer(), new IcsBuilder($this->clock), new EmailTemplateRepo($this->pdo()), 'https://crush.app');
        return new LandingController($view, $csrf, $users, $magic, $session, $postman, 'https://crush.app');
    }

    public function test_short_password_rejected(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf, new Session(new ArrayStore()))
            ->start(['name' => 'A', 'email' => 'a@x.test', 'password' => '123'], $csrf->token(), '');
        $this->assertSame(422, $res->status());
    }

    public function test_new_user_sets_password_and_continues(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $session = new Session(new ArrayStore());
        $res = $this->controller($csrf, $session)
            ->start(['name' => 'New', 'email' => 'new@x.test', 'password' => 'secret1'], $csrf->token(), '');
        $this->assertSame(302, $res->status());
        $this->assertSame('/invites/new', $res->headers()['Location']);
        $this->assertTrue($session->check());
        $user = (new UserRepo($this->pdo(), $this->clock))->findByEmail('new@x.test');
        $this->assertTrue(password_verify('secret1', $user['password_hash']));
    }

    public function test_returning_user_correct_password_logs_in(): void
    {
        $users = new UserRepo($this->pdo(), $this->clock);
        $u = $users->create('back@x.test', 'Back', 'magic');
        $users->setPasswordHash($u['id'], password_hash('rightpass', PASSWORD_DEFAULT));
        $csrf = new Csrf(new ArrayStore());
        $session = new Session(new ArrayStore());
        $res = $this->controller($csrf, $session)
            ->start(['name' => 'Back', 'email' => 'back@x.test', 'password' => 'rightpass'], $csrf->token(), '');
        $this->assertSame(302, $res->status());
        $this->assertTrue($session->check());
    }

    public function test_returning_user_wrong_password_rejected(): void
    {
        $users = new UserRepo($this->pdo(), $this->clock);
        $u = $users->create('back2@x.test', 'Back', 'magic');
        $users->setPasswordHash($u['id'], password_hash('rightpass', PASSWORD_DEFAULT));
        $csrf = new Csrf(new ArrayStore());
        $session = new Session(new ArrayStore());
        $res = $this->controller($csrf, $session)
            ->start(['name' => 'Back', 'email' => 'back2@x.test', 'password' => 'wrongpass'], $csrf->token(), '');
        $this->assertSame(401, $res->status());
        $this->assertFalse($session->check());
    }

    public function test_legacy_passwordless_user_claims_password(): void
    {
        $users = new UserRepo($this->pdo(), $this->clock);
        $users->create('legacy@x.test', 'Leg', 'magic');           // no password_hash
        $csrf = new Csrf(new ArrayStore());
        $session = new Session(new ArrayStore());
        $res = $this->controller($csrf, $session)
            ->start(['name' => 'Leg', 'email' => 'legacy@x.test', 'password' => 'newpass1'], $csrf->token(), '');
        $this->assertSame(302, $res->status());
        $this->assertTrue($session->check());
        $this->assertTrue(password_verify('newpass1', $users->findByEmail('legacy@x.test')['password_hash']));
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter LandingPasswordTest`
Expected: FAIL — password ignored.

- [ ] **Step 3: Rewrite `LandingController::start`** — replace the method body with the password-aware flow:

```php
    public function start(array $input, string $csrf, string $acceptLanguage = ''): Response
    {
        if (!$this->csrf->validate($csrf)) {
            return $this->render('Your session expired. Please try again.', 400);
        }
        $name = trim((string) ($input['name'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->render('Please enter your name and a valid email.', 422, $name, $email);
        }
        if (strlen($password) < 6) {
            return $this->render('Pick a password with at least 6 characters.', 422, $name, $email);
        }

        $existing = $this->users->findByEmail($email);
        if ($existing !== null) {
            $hash = (string) ($existing['password_hash'] ?? '');
            if ($hash !== '' && !password_verify($password, $hash)) {
                return $this->render('That email is taken. If it is yours, enter the right password — or use a different email.', 401, $name, $email);
            }
            if ($hash === '') {
                $this->users->setPasswordHash((int) $existing['id'], password_hash($password, PASSWORD_DEFAULT));
            }
            $this->session->login((int) $existing['id']);
            return (new Response('', 302))->withHeader('Location', '/invites/new');
        }

        $lang = Locale::detect($acceptLanguage);
        $user = $this->users->create($email, $name, 'password');
        $this->users->setPasswordHash((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        $this->users->setLang((int) $user['id'], $lang);
        $this->session->login((int) $user['id']);
        $token = $this->magic->start($email);
        $this->postman->sendWelcome($email, $name, rtrim($this->appUrl, '/') . '/auth/magic/' . $token, $lang);

        return (new Response('', 302))->withHeader('Location', '/invites/new');
    }
```

- [ ] **Step 4: Add the password field to `templates/landing/home.php`** — inside the `<form>` (after the email input, before the button), add:

```php
          <input type="password" name="password" placeholder="pick a password" required minlength="6" autocomplete="new-password"
                 style="padding:13px;border-radius:14px;border:1px solid #f0d9ea;font-size:16px;font-family:inherit;">
```

and change the fine-print line from "No password needed — we'll email you a magic link." to:

```php
        <p class="fine">Pick a password — you'll use it to sign back in.</p>
```

- [ ] **Step 5: Run the tests** — Run: `vendor/bin/phpunit --filter LandingPasswordTest`
Expected: PASS (5 tests).

- [ ] **Step 6: Run the full suite (serially)** — Run: `vendor/bin/phpunit`
Expected: green except the older `LandingControllerTest` cases that call `start` without a password (they now 422). **Update those** in `tests/Landing/LandingControllerTest.php`: add `'password' => 'secret1'` to each `start([...])` input array that expects success (the new-email + existing-email cases); the CSRF-400 and invalid-email-422 cases need no password. Re-run until green.

- [ ] **Step 7: Commit**

```bash
git add templates/landing/home.php app/Landing/LandingController.php tests/Landing/
git commit -m "feat(auth): pick a password at signup; verify returning users on landing"
```

---

### Task 2: /login — email + password (magic link demoted to secondary)

**Files:**
- Modify: `app/Auth/AuthController.php`, `templates/auth/login.php`, `config/routes.php`, `public/index.php`
- Test: `tests/Auth/PasswordLoginTest.php`

**Interfaces:**
- `AuthController::__construct` gains a `UserRepo $users` param (after `Session`). New `loginPassword(string $email, string $password, string $csrf): Response` — 400 bad CSRF; on `password_verify` success → `session->login` + `302 /invites`; else re-render `auth/login` with a generic 401 "Invalid email or password." `startMagic` unchanged but moves to `POST /login/magic`.

- [ ] **Step 1: Write the failing test** — `tests/Auth/PasswordLoginTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Auth;

use App\Auth\AuthController;
use App\Auth\MagicLink;
use App\Auth\Session;
use App\Auth\UserRepo;
use App\Core\ArrayStore;
use App\Core\Csrf;
use App\Core\View;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;
use Tests\Support\SpyMailer;

final class PasswordLoginTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    private function controller(Csrf $csrf, Session $session): AuthController
    {
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $users = new UserRepo($this->pdo(), $this->clock);
        $magic = new MagicLink($this->pdo(), $users, $this->clock, 900);
        return new AuthController($view, $session, $csrf, $magic, new SpyMailer(), 'https://crush.app', $users);
    }

    private function makeUser(string $email, string $password): void
    {
        $users = new UserRepo($this->pdo(), $this->clock);
        $u = $users->create($email, 'U', 'password');
        $users->setPasswordHash($u['id'], password_hash($password, PASSWORD_DEFAULT));
    }

    public function test_correct_password_logs_in(): void
    {
        $this->makeUser('p@x.test', 'goodpass');
        $csrf = new Csrf(new ArrayStore());
        $session = new Session(new ArrayStore());
        $res = $this->controller($csrf, $session)->loginPassword('p@x.test', 'goodpass', $csrf->token());
        $this->assertSame(302, $res->status());
        $this->assertSame('/invites', $res->headers()['Location']);
        $this->assertTrue($session->check());
    }

    public function test_wrong_password_401(): void
    {
        $this->makeUser('p2@x.test', 'goodpass');
        $csrf = new Csrf(new ArrayStore());
        $session = new Session(new ArrayStore());
        $res = $this->controller($csrf, $session)->loginPassword('p2@x.test', 'nope', $csrf->token());
        $this->assertSame(401, $res->status());
        $this->assertFalse($session->check());
        $this->assertStringContainsString('Invalid email or password', $res->body());
    }

    public function test_unknown_email_401(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf, new Session(new ArrayStore()))->loginPassword('ghost@x.test', 'whatever', $csrf->token());
        $this->assertSame(401, $res->status());
    }

    public function test_bad_csrf_400(): void
    {
        $res = $this->controller(new Csrf(new ArrayStore()), new Session(new ArrayStore()))->loginPassword('p@x.test', 'x', 'wrong');
        $this->assertSame(400, $res->status());
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter PasswordLoginTest`
Expected: FAIL — ctor arity / `loginPassword` undefined.

- [ ] **Step 3: Add `UserRepo` + `loginPassword` to `AuthController`** — add `use App\Auth\UserRepo;` (same namespace — already `App\Auth`, so just reference `UserRepo`), a trailing constructor param `private UserRepo $users,`, and:

```php
    public function loginPassword(string $email, string $password, string $csrf): Response
    {
        if (!$this->csrf->validate($csrf)) {
            return Response::html($this->view->render('auth/login', [
                'csrf' => $this->csrf->token(), 'title' => 'Sign in',
                'error' => 'Your session expired. Please try again.',
            ]), 400);
        }
        $email = trim($email);
        $user = $this->users->findByEmail($email);
        $hash = $user !== null ? (string) ($user['password_hash'] ?? '') : '';
        if ($user === null || $hash === '' || !password_verify($password, $hash)) {
            return Response::html($this->view->render('auth/login', [
                'csrf' => $this->csrf->token(), 'title' => 'Sign in',
                'error' => 'Invalid email or password.',
            ]), 401);
        }
        $this->session->login((int) $user['id']);
        return (new Response('', 302))->withHeader('Location', '/invites');
    }
```

- [ ] **Step 4: Rework `templates/auth/login.php`** — make email+password the primary form and demote magic link. Replace the `else` branch's `<form>`…Google block with:

```php
    <p style="opacity:.8;margin-top:0;">Sign in to send someone a date invite.</p>
    <?php if (!empty($error)): ?>
      <p role="alert" style="color:#b3243b;"><?= $e($error) ?></p>
    <?php endif; ?>
    <form method="post" action="/login" style="display:flex;flex-direction:column;gap:12px;">
      <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
      <input class="i" type="email" name="email" placeholder="you@email.com" required
             style="padding:12px;border-radius:14px;border:1px solid #e7d4ff;font-size:16px;">
      <input class="i" type="password" name="password" placeholder="password" required autocomplete="current-password"
             style="padding:12px;border-radius:14px;border:1px solid #e7d4ff;font-size:16px;">
      <button type="submit"
              style="padding:12px;border:0;border-radius:14px;background:#ff3d8b;color:#fff;font-weight:700;font-size:16px;cursor:pointer;">
        Sign in
      </button>
    </form>
    <p style="text-align:center;margin:14px 0 6px;opacity:.6;">No account yet? <a href="/" style="color:#ff3d8b;font-weight:600;">Start here</a></p>
    <details style="margin-top:6px;">
      <summary style="cursor:pointer;opacity:.7;font-size:14px;">Other ways to sign in</summary>
      <form method="post" action="/login/magic" style="display:flex;flex-direction:column;gap:8px;margin-top:8px;">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
        <input class="i" type="email" name="email" placeholder="you@email.com" required
               style="padding:10px;border-radius:12px;border:1px solid #e7d4ff;font-size:15px;">
        <button type="submit" style="padding:10px;border:0;border-radius:12px;border:1px solid #e7d4ff;background:#fff;color:#5a2a52;font-weight:600;cursor:pointer;">Email me a magic link</button>
      </form>
      <a href="/auth/google" style="display:block;text-align:center;margin-top:8px;padding:10px;border-radius:12px;border:1px solid #e7d4ff;color:#5a2a52;text-decoration:none;font-weight:600;">Continue with Google</a>
    </details>
```

(The `$sent` branch is unchanged.)

- [ ] **Step 5: Update routes + wiring** — in `config/routes.php` change `POST /login` to call `loginPassword` and add `POST /login/magic`:

```php
    $router->add('POST', '/login', static fn(): Response => $auth->loginPassword(
        is_string($_POST['email'] ?? null) ? $_POST['email'] : '',
        is_string($_POST['password'] ?? null) ? $_POST['password'] : '',
        is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : ''
    ));
    $router->add('POST', '/login/magic', static fn(): Response => $auth->startMagic(
        is_string($_POST['email'] ?? null) ? $_POST['email'] : '',
        is_string($_POST['csrf']  ?? null) ? $_POST['csrf']  : ''
    ));
```

In `public/index.php`, add `$users` as the trailing `AuthController` argument (it already exists in scope as `$users`).

- [ ] **Step 6: Update the existing AuthController test ctor** — `tests/Auth/AuthControllerTest.php` builds `new AuthController(...)`; add the trailing `new UserRepo($this->pdo(), $clock)` (or the test's existing user repo / clock). Run `vendor/bin/phpunit` until green.

- [ ] **Step 7: Run the full suite (serially)** — Run: `vendor/bin/phpunit`
Expected: all green.

- [ ] **Step 8: Commit**

```bash
git add app/Auth/AuthController.php templates/auth/login.php config/routes.php public/index.php tests/Auth/
git commit -m "feat(auth): email+password sign-in at /login (magic link secondary)"
```

---

### Task 3: Admin — add a new share button

**Files:**
- Modify: `app/Share/ShareTargetRepo.php`, `app/Admin/AdminController.php`, `templates/admin/share.php`, `config/routes.php`
- Test: `tests/Admin/AdminShareCreateTest.php`

**Interfaces:**
- `ShareTargetRepo::create(string $key, string $label, string $icon, string $urlTemplate, int $sort, bool $enabled): void` (INSERT IGNORE-style upsert keeping a real icon).
- `AdminController::createShare(?int $userId, array $input, string $csrf): Response` — admin-gated; 400 bad CSRF; 422 if key/label empty, `!isAllowed`, or key already exists; else `create` + `302 /admin/share`. Route `POST /admin/share/new`. `admin/share.php` gains a create form.

- [ ] **Step 1: Write the failing test** — `tests/Admin/AdminShareCreateTest.php`

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

final class AdminShareCreateTest extends DatabaseTestCase
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

    public function test_create_adds_target(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $ctrl = $this->controller($csrf);
        $res = $ctrl->createShare($this->adminId(), [
            'key' => 'reddit', 'label' => 'Reddit', 'icon' => 'ic-share',
            'url_template' => 'https://www.reddit.com/submit?url={url}', 'enabled' => '1',
        ], $csrf->token());
        $this->assertSame(302, $res->status());
        $this->assertNotNull((new ShareTargetRepo($this->pdo()))->getExact('reddit'));
    }

    public function test_create_rejects_unsafe(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf)->createShare($this->adminId(), [
            'key' => 'evil', 'label' => 'Evil', 'icon' => 'ic-share', 'url_template' => 'javascript:alert(1)',
        ], $csrf->token());
        $this->assertSame(422, $res->status());
    }

    public function test_create_rejects_duplicate_key(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf)->createShare($this->adminId(), [
            'key' => 'whatsapp', 'label' => 'Dup', 'icon' => 'ic-whatsapp', 'url_template' => 'https://wa.me/?text={url}',
        ], $csrf->token());
        $this->assertSame(422, $res->status());
    }

    public function test_create_requires_admin(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $this->assertSame(403, $this->controller($csrf)->createShare(null, [], $csrf->token())->status());
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter AdminShareCreateTest`
Expected: FAIL — `createShare`/`create` undefined.

- [ ] **Step 3: Add `ShareTargetRepo::create`**

```php
    public function create(string $key, string $label, string $icon, string $urlTemplate, int $sort, bool $enabled): void
    {
        $this->pdo->prepare(
            'INSERT INTO share_targets (`key`, label, icon, url_template, sort, enabled) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$key, $label, $icon, $urlTemplate, $sort, $enabled ? 1 : 0]);
    }
```

- [ ] **Step 4: Add `AdminController::createShare`** (next to `saveShare`)

```php
    public function createShare(?int $userId, array $input, string $csrf): Response
    {
        if ($this->requireAdmin($userId) === null) {
            return $this->forbidden();
        }
        if (!$this->csrf->validate($csrf)) {
            return $this->render('admin/share', ['title' => 'Share buttons', 'targets' => $this->shareTargets->all(), 'flash' => 'Session expired, please retry.'])->withStatus(400);
        }
        $key = strtolower(trim((string) ($input['key'] ?? '')));
        $label = (string) ($input['label'] ?? '');
        $icon = (string) ($input['icon'] ?? '') ?: 'ic-share';
        $template = (string) ($input['url_template'] ?? '');
        $enabled = !empty($input['enabled']);
        if ($key === '' || $label === '' || !ShareTargetRepo::isAllowed($template) || $this->shareTargets->getExact($key) !== null) {
            return $this->render('admin/share', [
                'title' => 'Share buttons', 'targets' => $this->shareTargets->all(),
                'flash' => 'New button needs a unique key, a label, and an http(s)/sms/mailto link.',
            ])->withStatus(422);
        }
        $this->shareTargets->create($key, $label, $icon, $template, 100, $enabled);
        return (new Response('', 302))->withHeader('Location', '/admin/share');
    }
```

- [ ] **Step 5: Add the create form to `templates/admin/share.php`** — after the table:

```php
  <h2 style="margin-top:18px;">Add a button</h2>
  <form method="post" action="/admin/share/new" style="display:flex;flex-direction:column;gap:6px;max-width:420px;">
    <input type="hidden" name="csrf" value="<?= $e($csrf ?? '') ?>">
    <input type="text" name="key" placeholder="key (e.g. reddit)">
    <input type="text" name="label" placeholder="Label">
    <input type="text" name="icon" placeholder="icon id (e.g. ic-share)" value="ic-share">
    <input type="text" name="url_template" placeholder="https://… with {url}">
    <label><input type="checkbox" name="enabled" value="1" checked> Enabled</label>
    <button type="submit">Add button</button>
  </form>
```

and pass a CSRF token to the list view — in `AdminController::shareList`, add `'csrf' => $this->csrf->token()` to the render data.

- [ ] **Step 6: Register the route** — in `config/routes.php`:

```php
    $router->add('POST', '/admin/share/new', static fn(): Response => $admin->createShare(
        $currentUserId(), $_POST, (static fn($v) => is_string($v) ? $v : '')($_POST['csrf'] ?? '')
    ));
```

- [ ] **Step 7: Run the test, then the full suite (serially)** — Run: `vendor/bin/phpunit --filter AdminShareCreateTest` then `vendor/bin/phpunit`
Expected: all green.

- [ ] **Step 8: Commit**

```bash
git add app/Share/ShareTargetRepo.php app/Admin/AdminController.php templates/admin/share.php config/routes.php tests/Admin/AdminShareCreateTest.php
git commit -m "feat(admin): add new share buttons from /admin/share"
```

---

### Task 4: Accented VI/KO welcome + invite email copy

**Files:**
- Create: `migrations/0014_email_copy_intl.sql`
- Test: `tests/Mail/EmailCopyIntlTest.php`

**Interfaces:**
- Produces: updated `email_templates` rows for `welcome`/`invite` in `vi` and `ko` with properly accented/natural copy (same `{{placeholders}}`).

- [ ] **Step 1: Write the failing test** — `tests/Mail/EmailCopyIntlTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Mail;

use App\Mail\EmailTemplateRepo;
use Tests\Support\DatabaseTestCase;

final class EmailCopyIntlTest extends DatabaseTestCase
{
    public function test_vietnamese_welcome_is_accented(): void
    {
        $repo = new EmailTemplateRepo($this->pdo());
        $vi = $repo->getExact('welcome', 'vi');
        $this->assertStringContainsString('Chào mừng', $vi['subject']);   // accented
        // placeholders preserved
        $this->assertStringContainsString('{{name}}', $vi['body_html']);
        $this->assertStringContainsString('{{link}}', $vi['body_html']);
    }

    public function test_korean_invite_preserves_placeholders(): void
    {
        $repo = new EmailTemplateRepo($this->pdo());
        $ko = $repo->getExact('invite', 'ko');
        foreach (['{{senderLabel}}', '{{link}}', '{{unsubscribe}}'] as $p) {
            $this->assertStringContainsString($p, $ko['body_html']);
        }
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter EmailCopyIntlTest`
Expected: FAIL — VI welcome subject is the unaccented `Chao mung`.

- [ ] **Step 3: Write `migrations/0014_email_copy_intl.sql`** (UPDATE the four VI/KO rows with accented/natural copy; keep every `{{placeholder}}`; single-quoted SQL with the apostrophe-free copy, UTF-8)

```sql
UPDATE email_templates SET subject = 'Chào mừng đến với Crush',
  body_html = '<div style="font-family:system-ui,sans-serif;max-width:480px;margin:0 auto;padding:24px"><h1 style="color:#ff3d8b">Chào mừng đến với Crush, {{name}}</h1><p>Tài khoản của bạn đã sẵn sàng. Đăng nhập và thêm vài thông tin dễ thương cho hồ sơ.</p><p><a href="{{link}}" style="display:inline-block;padding:12px 20px;background:#ff3d8b;color:#fff;border-radius:12px;text-decoration:none;font-weight:700">Đăng nhập</a></p><p style="color:#999;font-size:12px">Hoặc dán liên kết này: {{link}}</p></div>'
  WHERE `key` = 'welcome' AND lang = 'vi';

UPDATE email_templates SET subject = 'Bạn nhận được một lời mời hẹn hò',
  body_html = '<div style="font-family:system-ui,sans-serif;max-width:480px;margin:0 auto;padding:24px"><h1 style="color:#ff3d8b">{{senderLabel}} đang thích bạn</h1><p>{{message}}</p><p>Nhấn vào nút bên dưới để chọn ngày, món ăn và nơi gặp nhau.</p><p><a href="{{link}}" style="display:inline-block;padding:12px 20px;background:#ff3d8b;color:#fff;border-radius:12px;text-decoration:none;font-weight:700">Mở lời mời</a></p><p style="color:#bbb;font-size:11px">Không quan tâm? Chặn và báo cáo: {{unsubscribe}}</p></div>'
  WHERE `key` = 'invite' AND lang = 'vi';

UPDATE email_templates SET subject = 'Crush에 오신 것을 환영합니다',
  body_html = '<div style="font-family:system-ui,sans-serif;max-width:480px;margin:0 auto;padding:24px"><h1 style="color:#ff3d8b">{{name}}님, Crush에 오신 것을 환영합니다</h1><p>계정이 준비되었어요. 로그인하고 프로필을 예쁘게 채워 보세요.</p><p><a href="{{link}}" style="display:inline-block;padding:12px 20px;background:#ff3d8b;color:#fff;border-radius:12px;text-decoration:none;font-weight:700">로그인</a></p><p style="color:#999;font-size:12px">또는 이 링크를 붙여넣으세요: {{link}}</p></div>'
  WHERE `key` = 'welcome' AND lang = 'ko';

UPDATE email_templates SET subject = '초대장이 도착했어요',
  body_html = '<div style="font-family:system-ui,sans-serif;max-width:480px;margin:0 auto;padding:24px"><h1 style="color:#ff3d8b">{{senderLabel}}님이 당신을 좋아해요</h1><p>{{message}}</p><p>아래 버튼을 눌러 날짜와 메뉴, 만날 장소를 골라 주세요.</p><p><a href="{{link}}" style="display:inline-block;padding:12px 20px;background:#ff3d8b;color:#fff;border-radius:12px;text-decoration:none;font-weight:700">초대장 열기</a></p><p style="color:#bbb;font-size:11px">관심이 없으신가요? 차단 및 신고: {{unsubscribe}}</p></div>'
  WHERE `key` = 'invite' AND lang = 'ko';
```

- [ ] **Step 4: Run the test, then the full suite (serially)** — Run: `vendor/bin/phpunit --filter EmailCopyIntlTest` then `vendor/bin/phpunit`
Expected: all green (the v3 `EmailTemplateSchemaTest` still passes — rows still present and non-empty).

- [ ] **Step 5: Commit**

```bash
git add migrations/0014_email_copy_intl.sql tests/Mail/EmailCopyIntlTest.php
git commit -m "feat(email): accented Vietnamese + natural Korean welcome/invite copy"
```

---

## Self-Review

**1. Spec coverage:** Password picked at signup + returning-user verify on landing, no email dependency (the dead-end fix) — Task 1. Email+password `/login` with magic link demoted (the actual page the user hit) — Task 2. Admin "add share button" form — Task 3. Accented VI/KO email copy — Task 4. Icons-only, escaped, CSRF, generic auth errors — throughout.

**2. Placeholder scan:** No "TBD". Legacy passwordless accounts claim-on-first-password (only pre-existing/test rows; documented). Full code for every change. VI/KO copy keeps every `{{placeholder}}`.

**3. Type consistency:** `LandingController::start` unchanged signature (reads `password` from `$input`); uses `UserRepo::setPasswordHash`/`findByEmail`/`create`/`setLang`. `AuthController::__construct(View,Session,Csrf,MagicLink,Mailer,string,UserRepo)`; `loginPassword(string,string,string): Response`; matched in `public/index.php` + `tests/Auth/AuthControllerTest.php`. `ShareTargetRepo::create(string,string,string,string,int,bool): void`; `AdminController::createShare(?int,array,string): Response`. Routes: `POST /login`→`loginPassword`, `POST /login/magic`→`startMagic`, `POST /admin/share/new`→`createShare`.
