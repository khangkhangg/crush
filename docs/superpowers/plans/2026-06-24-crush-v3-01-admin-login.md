# Crush v3 — Plan 1: Admin Password Login Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A dedicated `/admin/login` page where an admin signs in with email + password (bcrypt), separate from the magic-link flow, rate-limited, with generic errors and an `is_admin` requirement.

**Architecture:** Adds a nullable `users.password_hash`, a `UserRepo::setPasswordHash` method, an `AdminAuthController` owning `GET/POST /admin/login`, and a `bin/set-password.php` CLI to provision credentials. Verification is `password_verify` + `is_admin`, throttled by the existing `RateLimiter`.

**Tech Stack:** PHP 8.1+, PDO MySQL, PHPUnit 10. No new Composer dependencies (`password_hash`/`password_verify` are built-in).

## Global Constraints

- PHP floor 8.1. PSR-4: `App\` → `app/`, `Tests\` → `tests/`. No new Composer packages.
- **Icons only — never emojis.** All HTML output escaped via `App\Core\e()`. `POST /admin/login` validates CSRF.
- Passwords are bcrypt (`password_hash` default algo). Login errors are **generic** (no user-enumeration). `/admin/login` is rate-limited (per-IP and per-email). Only `is_admin` users may log in here.
- Integration tests use MySQL `crush_test`. Production: `https://crush.didudi.com`.

## File Structure

- `migrations/0008_user_password.sql` — adds `users.password_hash`.
- `app/Auth/UserRepo.php` (modify) — `setPasswordHash`.
- `app/Admin/AdminAuthController.php` — login page + verify.
- `templates/admin/login.php` — the password login form.
- `bin/set-password.php` — CLI to set a user's password.
- `config/routes.php`, `public/index.php` (modify) — wire the routes.

---

### Task 1: users.password_hash + UserRepo::setPasswordHash

**Files:**
- Create: `migrations/0008_user_password.sql`
- Modify: `app/Auth/UserRepo.php`
- Test: `tests/Auth/UserPasswordTest.php`

**Interfaces:**
- Consumes: `Tests\Support\DatabaseTestCase`, `App\Core\Clock`.
- Produces:
  - `users.password_hash VARCHAR(255) NULL`.
  - `UserRepo::setPasswordHash(int $id, string $hash): void` — sets `password_hash`.
  - `findById`/`findByEmail` rows include `password_hash` (already `SELECT *`).

- [ ] **Step 1: Write the failing test** — `tests/Auth/UserPasswordTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Auth;

use App\Auth\UserRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class UserPasswordTest extends DatabaseTestCase
{
    public function test_set_and_verify_password_hash(): void
    {
        $repo = new UserRepo($this->pdo(), new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z')));
        $user = $repo->create('a@x.test', 'Ann', 'magic');
        $this->assertArrayHasKey('password_hash', $user);
        $this->assertNull($user['password_hash']);

        $repo->setPasswordHash($user['id'], password_hash('Sushi08!', PASSWORD_DEFAULT));
        $reloaded = $repo->findByEmail('a@x.test');

        $this->assertNotNull($reloaded['password_hash']);
        $this->assertTrue(password_verify('Sushi08!', $reloaded['password_hash']));
        $this->assertFalse(password_verify('wrong', $reloaded['password_hash']));
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter UserPasswordTest`
Expected: FAIL — unknown column `password_hash` / undefined method `setPasswordHash`.

- [ ] **Step 3: Write `migrations/0008_user_password.sql`**

```sql
ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NULL;
```

- [ ] **Step 4: Add `setPasswordHash` to `app/Auth/UserRepo.php`** (after `saveProfile`)

```php
    public function setPasswordHash(int $id, string $hash): void
    {
        $this->pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $id]);
    }
```

- [ ] **Step 5: Run to verify it passes** — Run: `vendor/bin/phpunit --filter UserPasswordTest`
Expected: PASS.

- [ ] **Step 6: Run the full suite** — Run: `vendor/bin/phpunit`
Expected: all green (existing user tests unaffected by the new nullable column).

- [ ] **Step 7: Commit**

```bash
git add migrations/0008_user_password.sql app/Auth/UserRepo.php tests/Auth/UserPasswordTest.php
git commit -m "feat(admin-auth): users.password_hash + UserRepo.setPasswordHash"
```

---

### Task 2: AdminAuthController + /admin/login + set-password CLI

**Files:**
- Create: `app/Admin/AdminAuthController.php`
- Create: `templates/admin/login.php`
- Create: `bin/set-password.php`
- Modify: `config/routes.php`, `public/index.php`
- Test: `tests/Admin/AdminAuthControllerTest.php`

**Interfaces:**
- Consumes: `App\Auth\UserRepo`, `App\Auth\Session`, `App\Core\View`, `App\Core\Csrf`, `App\Core\Response`, `App\Security\RateLimiter`.
- Produces:
  - `App\Admin\AdminAuthController` with `__construct(View $view, Csrf $csrf, UserRepo $users, Session $session, RateLimiter $limits)`:
    - `showLogin(?string $error = null, int $status = 200): Response` — renders `admin/login` with a CSRF token.
    - `login(array $input, string $csrf, string $ip): Response` — `400` on bad CSRF; `429` when rate-limited; otherwise verify `email`+`password`: on success (`password_verify` AND `is_admin === 1`) `Session::login` + `302 /admin`; on any failure a **generic** `401` re-render ("Invalid email or password."). Rate-limit scopes: `admin_login_ip` (10 / 3600s) and `admin_login_email` (5 / 900s), checked before verification.
  - `bin/set-password.php <email> <password>` — sets the user's `password_hash` (exits non-zero if the user/args are missing).

- [ ] **Step 1: Write the failing test** — `tests/Admin/AdminAuthControllerTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Admin;

use App\Admin\AdminAuthController;
use App\Auth\Session;
use App\Auth\UserRepo;
use App\Core\ArrayStore;
use App\Core\Csrf;
use App\Core\View;
use App\Security\RateLimiter;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class AdminAuthControllerTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    private function controller(Csrf $csrf, Session $session): AdminAuthController
    {
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        return new AdminAuthController(
            $view, $csrf, new UserRepo($this->pdo(), $this->clock), $session,
            new RateLimiter($this->pdo(), $this->clock)
        );
    }

    private function admin(string $pw): void
    {
        $repo = new UserRepo($this->pdo(), $this->clock);
        $u = $repo->create('admin@x.test', 'Boss', 'magic');
        $repo->setPasswordHash($u['id'], password_hash($pw, PASSWORD_DEFAULT));
        $this->pdo()->prepare('UPDATE users SET is_admin = 1 WHERE id = ?')->execute([$u['id']]);
    }

    public function test_show_login_renders_form(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf, new Session(new ArrayStore()))->showLogin();
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString($csrf->token(), $res->body());
        $this->assertStringContainsString('name="password"', $res->body());
    }

    public function test_bad_csrf_is_400(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf, new Session(new ArrayStore()))
            ->login(['email' => 'admin@x.test', 'password' => 'Sushi08!'], 'wrong', '1.1.1.1');
        $this->assertSame(400, $res->status());
    }

    public function test_correct_admin_logs_in(): void
    {
        $this->admin('Sushi08!');
        $csrf = new Csrf(new ArrayStore());
        $session = new Session(new ArrayStore());
        $res = $this->controller($csrf, $session)
            ->login(['email' => 'admin@x.test', 'password' => 'Sushi08!'], $csrf->token(), '1.1.1.1');
        $this->assertSame(302, $res->status());
        $this->assertSame('/admin', $res->headers()['Location']);
        $this->assertTrue($session->check());
    }

    public function test_wrong_password_is_generic_401(): void
    {
        $this->admin('Sushi08!');
        $csrf = new Csrf(new ArrayStore());
        $session = new Session(new ArrayStore());
        $res = $this->controller($csrf, $session)
            ->login(['email' => 'admin@x.test', 'password' => 'nope'], $csrf->token(), '1.1.1.1');
        $this->assertSame(401, $res->status());
        $this->assertFalse($session->check());
        $this->assertStringContainsString('Invalid email or password', $res->body());
    }

    public function test_non_admin_with_correct_password_is_rejected(): void
    {
        $repo = new UserRepo($this->pdo(), $this->clock);
        $u = $repo->create('plain@x.test', 'Plain', 'magic');
        $repo->setPasswordHash($u['id'], password_hash('Sushi08!', PASSWORD_DEFAULT)); // NOT admin
        $csrf = new Csrf(new ArrayStore());
        $session = new Session(new ArrayStore());
        $res = $this->controller($csrf, $session)
            ->login(['email' => 'plain@x.test', 'password' => 'Sushi08!'], $csrf->token(), '1.1.1.1');
        $this->assertSame(401, $res->status());
        $this->assertFalse($session->check());
    }

    public function test_rate_limited_after_repeated_attempts(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $ctrl = $this->controller($csrf, new Session(new ArrayStore()));
        // per-email cap is 5/900s; the 6th attempt to the same email is 429
        for ($i = 0; $i < 5; $i++) {
            $ctrl->login(['email' => 'admin@x.test', 'password' => 'x'], $csrf->token(), '9.9.9.9');
        }
        $res = $ctrl->login(['email' => 'admin@x.test', 'password' => 'x'], $csrf->token(), '9.9.9.9');
        $this->assertSame(429, $res->status());
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter AdminAuthControllerTest`
Expected: FAIL — `Class "App\Admin\AdminAuthController" not found`.

- [ ] **Step 3: Write `app/Admin/AdminAuthController.php`**

```php
<?php
declare(strict_types=1);

namespace App\Admin;

use App\Auth\Session;
use App\Auth\UserRepo;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\View;
use App\Security\RateLimiter;

final class AdminAuthController
{
    public function __construct(
        private View $view,
        private Csrf $csrf,
        private UserRepo $users,
        private Session $session,
        private RateLimiter $limits,
    ) {}

    public function showLogin(?string $error = null, int $status = 200): Response
    {
        return Response::html($this->view->render('admin/login', [
            'title' => 'Admin sign in',
            'csrf'  => $this->csrf->token(),
            'error' => $error,
        ]), $status);
    }

    public function login(array $input, string $csrf, string $ip): Response
    {
        if (!$this->csrf->validate($csrf)) {
            return $this->showLogin('Your session expired. Please try again.', 400);
        }

        $email = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        $okIp    = $this->limits->hit('admin_login_ip', $ip, 10, 3600);
        $okEmail = $this->limits->hit('admin_login_email', strtolower($email), 5, 900);
        if (!$okIp || !$okEmail) {
            return $this->showLogin('Too many attempts. Please wait and try again.', 429);
        }

        $user = $email !== '' ? $this->users->findByEmail($email) : null;
        $hash = $user['password_hash'] ?? null;

        if ($user !== null && is_string($hash) && (int) $user['is_admin'] === 1 && password_verify($password, $hash)) {
            $this->session->login((int) $user['id']);
            return (new Response('', 302))->withHeader('Location', '/admin');
        }

        return $this->showLogin('Invalid email or password.', 401);
    }
}
```

- [ ] **Step 4: Write `templates/admin/login.php`** (icons only; standalone simple page)

```php
<?php $error = $error ?? null; ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($title ?? 'Admin sign in') ?></title>
<style>
  body{font-family:system-ui,sans-serif;background:#f6f6fb;color:#222;margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center}
  .box{background:#fff;border-radius:14px;padding:28px;width:min(92vw,360px);box-shadow:0 1px 2px rgba(0,0,0,.05),0 10px 26px rgba(0,0,0,.08)}
  h1{font-size:20px;margin:0 0 16px} label{display:block;font-size:13px;font-weight:600;color:#555;margin-top:10px}
  input{width:100%;padding:11px;border:1px solid #e3e3ef;border-radius:10px;font-size:16px;box-sizing:border-box}
  button{margin-top:16px;width:100%;padding:12px;border:0;border-radius:10px;background:#7a3cff;color:#fff;font-weight:700;font-size:16px;cursor:pointer}
  .err{color:#b3243b;font-size:14px;margin:0 0 8px}
</style></head>
<body>
  <form class="box" method="post" action="/admin/login">
    <h1>Admin sign in</h1>
    <?php if ($error): ?><p class="err" role="alert"><?= $e($error) ?></p><?php endif; ?>
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <label>Email <input type="email" name="email" required autocomplete="username"></label>
    <label>Password <input type="password" name="password" required autocomplete="current-password"></label>
    <button type="submit">Sign in</button>
  </form>
</body></html>
```

- [ ] **Step 5: Write `bin/set-password.php`**

```php
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Auth\UserRepo;
use App\Core\DB;
use App\Core\SystemClock;

$email = $argv[1] ?? null;
$password = $argv[2] ?? null;
if ($email === null || $password === null || $password === '') {
    fwrite(STDERR, "Usage: php bin/set-password.php <email> <password>\n");
    exit(1);
}

/** @var App\Core\Config $config */
$config = require dirname(__DIR__) . '/config/config.php';
$pdo = DB::connect($config);
$users = new UserRepo($pdo, new SystemClock());

$user = $users->findByEmail($email);
if ($user === null) {
    fwrite(STDERR, "No user with email {$email}\n");
    exit(1);
}
$users->setPasswordHash((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
echo "Password set for {$email}\n";
```

- [ ] **Step 6: Run the controller test** — Run: `vendor/bin/phpunit --filter AdminAuthControllerTest`
Expected: PASS (6 tests).

- [ ] **Step 7: Register routes** — in `config/routes.php`, add an `AdminAuthController $adminAuth` parameter to the factory and:

```php
    $router->add('GET',  '/admin/login', static fn(): Response => $adminAuth->showLogin());
    $router->add('POST', '/admin/login', static fn(): Response => $adminAuth->login(
        $_POST,
        (static fn($v) => is_string($v) ? $v : '')($_POST['csrf'] ?? ''),
        (string) ($_SERVER['REMOTE_ADDR'] ?? '')
    ));
```

(Place these BEFORE the existing `GET /admin` route so the literal `/admin/login` is registered alongside it — both are exact matches so order is not strictly required, but keep them together.)

- [ ] **Step 8: Wire in `public/index.php`** — add `use App\Admin\AdminAuthController;`, build `$adminAuthCtrl = new AdminAuthController($view, $csrf, $users, $session, new RateLimiter($pdo, $clock));` (a `RateLimiter` is already constructed elsewhere for invites; constructing another is fine — it's stateless over the same table), and pass `$adminAuthCtrl` as the trailing routes-factory argument.

- [ ] **Step 9: Run the full suite** — Run: `vendor/bin/phpunit`
Expected: all green.

- [ ] **Step 10: Manual check on port 8888** — confirm the login page renders and bad creds are generic:

```bash
DB_DSN="mysql:host=127.0.0.1;dbname=crush_dev;charset=utf8mb4" DB_USER=root DB_PASS= APP_URL="http://127.0.0.1:8888" \
  php -S 127.0.0.1:8888 -t public >/dev/null 2>&1 &
sleep 1
curl -s 127.0.0.1:8888/admin/login | grep -c 'name="password"'
curl -s -X POST -d 'email=x@x.test&password=nope&csrf=bad' 127.0.0.1:8888/admin/login -o /dev/null -w 'bad csrf -> %{http_code}\n'
kill %1
```
Expected: first prints `1` (form), second prints `400`.

- [ ] **Step 11: Commit**

```bash
git add app/Admin/AdminAuthController.php templates/admin/login.php bin/set-password.php config/routes.php public/index.php tests/Admin/AdminAuthControllerTest.php
git commit -m "feat(admin-auth): /admin/login password page + set-password CLI"
```

---

## Self-Review

**1. Spec coverage:** Dedicated `/admin/login` email+password, admin-only, generic errors, rate-limited (spec §4) — Task 2. `users.password_hash` bcrypt (§4,§7) — Task 1. `bin/set-password.php` to provision `dkhang@gmail.com`/`Sushi08!` (§4) — Task 2 (run at deploy). CSRF on POST, icons only, escaped — Task 2. Regular users unaffected (magic-link untouched) — no changes to `AuthController`.

**2. Placeholder scan:** No "TBD". Full code in every step; the login template + CLI are complete.

**3. Type consistency:** `UserRepo::setPasswordHash(int,string): void`; `AdminAuthController::showLogin(?string,int): Response`, `login(array,string,string): Response`; consumes `UserRepo::findByEmail` (returns row incl `password_hash` after migration), `Session::login(int)`, `RateLimiter::hit(string,string,int,int): bool`, `Csrf`, `View`, `Response` as defined in v1/v2. Routes factory gains a trailing `AdminAuthController`, matched in `public/index.php`.
