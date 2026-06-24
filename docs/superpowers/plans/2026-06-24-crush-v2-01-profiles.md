# Crush v2 — Plan 1: Profiles Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a signed-in user complete a short profile (display name, cute SVG avatar, pronouns, one-line bio, contact) which stamps `profile_completed_at` — the foundation for the reveal gate and both account flows.

**Architecture:** Adds profile columns to `users`, a fixed `Avatars` catalog (SVG sprite), `UserRepo` profile methods, and a `ProfileController` + pages gated by login. No new dependencies; follows the existing controller/repo/template patterns.

**Tech Stack:** PHP 8.1+, PDO MySQL, PHPUnit 10.

## Global Constraints

- PHP floor 8.1. PSR-4: `App\` → `app/`, `Tests\` → `tests/`. No new Composer packages.
- **Icons only — never emojis.** All HTML output escaped via `App\Core\e()`. POST routes validate CSRF (`App\Core\Csrf`).
- Prepared statements only. Integration tests use MySQL `crush_test`.
- Local dev serves on **port 8888**.

## File Structure

- `migrations/0006_profiles.sql` — adds profile columns to `users`.
- `app/Profile/Avatars.php` — fixed avatar-key catalog + validation.
- `templates/partials/avatars.php` — SVG sprite of the avatar set (icons, no emojis).
- `app/Auth/UserRepo.php` (modify) — `saveProfile`, `isProfileComplete`.
- `app/Profile/ProfileController.php` — edit + save.
- `templates/profile/edit.php` — the profile form.
- `config/routes.php`, `public/index.php` (modify) — wire profile routes.

---

### Task 1: users profile columns + UserRepo profile methods

**Files:**
- Create: `migrations/0006_profiles.sql`
- Modify: `app/Auth/UserRepo.php`
- Test: `tests/Auth/UserProfileTest.php`

**Interfaces:**
- Consumes: `Tests\Support\DatabaseTestCase`, `App\Core\Clock`.
- Produces:
  - `users` columns `avatar_key VARCHAR(32) NULL`, `pronouns VARCHAR(32) NULL`, `bio VARCHAR(280) NULL`, `contact VARCHAR(191) NULL`, `profile_completed_at DATETIME NULL`.
  - `UserRepo::saveProfile(int $id, string $avatarKey, ?string $pronouns, string $bio, ?string $contact): void` — writes the four fields and stamps `profile_completed_at = clock->now()`.
  - `UserRepo::isProfileComplete(array $user): bool` (static) — true when `$user['profile_completed_at']` is a non-empty string.

- [ ] **Step 1: Write the failing test** — `tests/Auth/UserProfileTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Auth;

use App\Auth\UserRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class UserProfileTest extends DatabaseTestCase
{
    public function test_save_profile_sets_fields_and_stamps_completion(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $repo = new UserRepo($this->pdo(), $clock);
        $user = $repo->create('a@x.test', 'Ann', 'magic');

        $this->assertFalse(UserRepo::isProfileComplete($user));

        $repo->saveProfile($user['id'], 'fox', 'she/her', 'i like long walks to the fridge', '@ann');
        $reloaded = $repo->findById($user['id']);

        $this->assertSame('fox', $reloaded['avatar_key']);
        $this->assertSame('she/her', $reloaded['pronouns']);
        $this->assertSame('i like long walks to the fridge', $reloaded['bio']);
        $this->assertSame('@ann', $reloaded['contact']);
        $this->assertNotNull($reloaded['profile_completed_at']);
        $this->assertTrue(UserRepo::isProfileComplete($reloaded));
    }

    public function test_is_profile_complete_false_when_unset(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $repo = new UserRepo($this->pdo(), $clock);
        $user = $repo->create('b@x.test', 'Bo', 'magic');
        $this->assertFalse(UserRepo::isProfileComplete($repo->findById($user['id'])));
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter UserProfileTest`
Expected: FAIL — unknown column `avatar_key` / `Call to undefined method ...saveProfile`.

- [ ] **Step 3: Write `migrations/0006_profiles.sql`**

```sql
ALTER TABLE users
  ADD COLUMN avatar_key           VARCHAR(32)  NULL,
  ADD COLUMN pronouns             VARCHAR(32)  NULL,
  ADD COLUMN bio                  VARCHAR(280) NULL,
  ADD COLUMN contact              VARCHAR(191) NULL,
  ADD COLUMN profile_completed_at DATETIME     NULL;
```

> Note: the test harness drops all tables and re-runs every migration per test, so this `ALTER` always applies to a freshly-created `users` table. `0002_users.sql` runs first, then this.

- [ ] **Step 4: Add the methods to `app/Auth/UserRepo.php`** — add after `linkGoogle`:

```php
    public function saveProfile(
        int $id,
        string $avatarKey,
        ?string $pronouns,
        string $bio,
        ?string $contact
    ): void {
        $stmt = $this->pdo->prepare(
            'UPDATE users
                SET avatar_key = ?, pronouns = ?, bio = ?, contact = ?, profile_completed_at = ?
              WHERE id = ?'
        );
        $stmt->execute([
            $avatarKey, $pronouns, $bio, $contact,
            $this->clock->now()->format('Y-m-d H:i:s'),
            $id,
        ]);
    }

    public static function isProfileComplete(array $user): bool
    {
        return isset($user['profile_completed_at']) && $user['profile_completed_at'] !== '';
    }
```

- [ ] **Step 5: Run to verify it passes** — Run: `vendor/bin/phpunit --filter UserProfileTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Run the full suite** — Run: `vendor/bin/phpunit`
Expected: all green (existing user tests unaffected — `SELECT *` now returns the new nullable columns).

- [ ] **Step 7: Commit**

```bash
git add migrations/0006_profiles.sql app/Auth/UserRepo.php tests/Auth/UserProfileTest.php
git commit -m "feat(profile): users profile columns + UserRepo.saveProfile"
```

---

### Task 2: Avatars catalog + ProfileController + page + routes

**Files:**
- Create: `app/Profile/Avatars.php`
- Create: `templates/partials/avatars.php`
- Create: `app/Profile/ProfileController.php`
- Create: `templates/profile/edit.php`
- Modify: `config/routes.php`, `public/index.php`
- Test: `tests/Profile/AvatarsTest.php`, `tests/Profile/ProfileControllerTest.php`

**Interfaces:**
- Consumes: `App\Auth\UserRepo`, `App\Auth\Session`, `App\Core\View`, `App\Core\Csrf`, `App\Core\Response`.
- Produces:
  - `App\Profile\Avatars` — `const KEYS` (string[]) of avatar ids; `static keys(): array`; `static isValid(string $key): bool`; `static default(): string` (first key).
  - `App\Profile\ProfileController` with `__construct(View $view, Csrf $csrf, UserRepo $users)`:
    - `edit(?int $userId): Response` — redirect `/login` if null; render `profile/edit` with the current user + avatar keys + csrf.
    - `save(?int $userId, array $input, string $csrf): Response` — redirect `/login` if null; 400 on bad CSRF; coerce avatar to a valid key (`Avatars::default()` if invalid); trim bio to 280 chars; persist via `UserRepo::saveProfile`; redirect to `/`.

- [ ] **Step 1: Write `tests/Profile/AvatarsTest.php`**

```php
<?php
declare(strict_types=1);

namespace Tests\Profile;

use App\Profile\Avatars;
use PHPUnit\Framework\TestCase;

final class AvatarsTest extends TestCase
{
    public function test_catalog_and_validation(): void
    {
        $this->assertNotEmpty(Avatars::keys());
        $this->assertTrue(Avatars::isValid(Avatars::keys()[0]));
        $this->assertFalse(Avatars::isValid('not-a-real-avatar'));
        $this->assertSame(Avatars::keys()[0], Avatars::default());
    }
}
```

- [ ] **Step 2: Write `tests/Profile/ProfileControllerTest.php`**

```php
<?php
declare(strict_types=1);

namespace Tests\Profile;

use App\Auth\UserRepo;
use App\Core\ArrayStore;
use App\Core\Csrf;
use App\Core\View;
use App\Profile\Avatars;
use App\Profile\ProfileController;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class ProfileControllerTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    private function controller(Csrf $csrf): ProfileController
    {
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        return new ProfileController($view, $csrf, new UserRepo($this->pdo(), $this->clock));
    }

    private function user(): int
    {
        $c = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        return (new UserRepo($this->pdo(), $c))->create('me@x.test', 'Me', 'magic')['id'];
    }

    public function test_edit_redirects_when_logged_out(): void
    {
        $res = $this->controller(new Csrf(new ArrayStore()))->edit(null);
        $this->assertSame(302, $res->status());
        $this->assertSame('/login', $res->headers()['Location']);
    }

    public function test_edit_renders_form_with_csrf_and_avatars(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf)->edit($this->user());
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString($csrf->token(), $res->body());
        $this->assertStringContainsString('name="avatar_key"', $res->body());
    }

    public function test_save_rejects_bad_csrf(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf)->save($this->user(), ['avatar_key' => Avatars::default()], 'wrong');
        $this->assertSame(400, $res->status());
    }

    public function test_save_persists_profile_and_redirects(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $ctrl = $this->controller($csrf);
        $id = $this->user();
        $res = $ctrl->save($id, [
            'avatar_key' => Avatars::keys()[1] ?? Avatars::default(),
            'pronouns' => 'they/them', 'bio' => 'hi', 'contact' => '@me',
        ], $csrf->token());

        $this->assertSame(302, $res->status());
        $reloaded = (new UserRepo($this->pdo(), $this->clock))->findById($id);
        $this->assertTrue(UserRepo::isProfileComplete($reloaded));
        $this->assertSame('they/them', $reloaded['pronouns']);
    }

    public function test_save_coerces_invalid_avatar_to_default(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $ctrl = $this->controller($csrf);
        $id = $this->user();
        $ctrl->save($id, ['avatar_key' => 'bogus', 'bio' => ''], $csrf->token());
        $reloaded = (new UserRepo($this->pdo(), $this->clock))->findById($id);
        $this->assertSame(Avatars::default(), $reloaded['avatar_key']);
    }
}
```

- [ ] **Step 3: Run to verify they fail** — Run: `vendor/bin/phpunit --filter "AvatarsTest|ProfileControllerTest"`
Expected: FAIL — `Class "App\Profile\Avatars" not found`.

- [ ] **Step 4: Write `app/Profile/Avatars.php`**

```php
<?php
declare(strict_types=1);

namespace App\Profile;

final class Avatars
{
    public const KEYS = ['fox', 'bunny', 'cat', 'bear', 'frog', 'duck', 'ghost', 'star'];

    /** @return string[] */
    public static function keys(): array
    {
        return self::KEYS;
    }

    public static function isValid(string $key): bool
    {
        return in_array($key, self::KEYS, true);
    }

    public static function default(): string
    {
        return self::KEYS[0];
    }
}
```

- [ ] **Step 5: Write `templates/partials/avatars.php`** (SVG sprite — one `<symbol>` per avatar key, simple cute line/fill icons, NO emojis)

```php
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <symbol id="av-fox" viewBox="0 0 48 48"><path d="M8 10l8 6 8-2 8 2 8-6-2 14a14 14 0 0 1-28 0z" fill="#ff8a4c"/><circle cx="19" cy="24" r="2" fill="#3a2a2a"/><circle cx="29" cy="24" r="2" fill="#3a2a2a"/><path d="M24 28l-2 3h4z" fill="#3a2a2a"/></symbol>
  <symbol id="av-bunny" viewBox="0 0 48 48"><ellipse cx="18" cy="12" rx="3" ry="9" fill="#f7c9e0"/><ellipse cx="30" cy="12" rx="3" ry="9" fill="#f7c9e0"/><circle cx="24" cy="28" r="12" fill="#fff0f7"/><circle cx="20" cy="27" r="2" fill="#3a2a2a"/><circle cx="28" cy="27" r="2" fill="#3a2a2a"/><circle cx="24" cy="31" r="2" fill="#ff6fae"/></symbol>
  <symbol id="av-cat" viewBox="0 0 48 48"><path d="M12 12l4 8M36 12l-4 8" stroke="#a98bff" stroke-width="3" fill="none"/><circle cx="24" cy="26" r="13" fill="#cdbcff"/><circle cx="20" cy="25" r="2" fill="#2a2440"/><circle cx="28" cy="25" r="2" fill="#2a2440"/><path d="M24 29l-3 2M24 29l3 2" stroke="#2a2440" stroke-width="2"/></symbol>
  <symbol id="av-bear" viewBox="0 0 48 48"><circle cx="15" cy="15" r="5" fill="#c79a6b"/><circle cx="33" cy="15" r="5" fill="#c79a6b"/><circle cx="24" cy="27" r="13" fill="#e3c39a"/><circle cx="20" cy="26" r="2" fill="#3a2a2a"/><circle cx="28" cy="26" r="2" fill="#3a2a2a"/><circle cx="24" cy="30" r="2" fill="#7a5a3a"/></symbol>
  <symbol id="av-frog" viewBox="0 0 48 48"><circle cx="16" cy="14" r="5" fill="#9be36b"/><circle cx="32" cy="14" r="5" fill="#9be36b"/><circle cx="16" cy="14" r="2" fill="#1f3a1f"/><circle cx="32" cy="14" r="2" fill="#1f3a1f"/><path d="M10 22h28a14 12 0 0 1-28 0z" fill="#7fd34f"/><path d="M18 30q6 4 12 0" stroke="#1f3a1f" stroke-width="2" fill="none"/></symbol>
  <symbol id="av-duck" viewBox="0 0 48 48"><circle cx="24" cy="24" r="14" fill="#ffe066"/><circle cx="20" cy="22" r="2" fill="#3a2a2a"/><circle cx="28" cy="22" r="2" fill="#3a2a2a"/><path d="M22 28h8l-4 4z" fill="#ff9d3c"/></symbol>
  <symbol id="av-ghost" viewBox="0 0 48 48"><path d="M12 22a12 12 0 0 1 24 0v16l-4-3-4 3-4-3-4 3-4-3z" fill="#e9e6ff"/><circle cx="19" cy="22" r="2" fill="#5a4aa0"/><circle cx="29" cy="22" r="2" fill="#5a4aa0"/></symbol>
  <symbol id="av-star" viewBox="0 0 48 48"><path d="M24 6l5 12 13 1-10 8 3 13-11-7-11 7 3-13-10-8 13-1z" fill="#ffd24c"/><circle cx="20" cy="22" r="1.6" fill="#3a2a2a"/><circle cx="28" cy="22" r="1.6" fill="#3a2a2a"/></symbol>
</svg>
```

- [ ] **Step 6: Write `app/Profile/ProfileController.php`**

```php
<?php
declare(strict_types=1);

namespace App\Profile;

use App\Auth\UserRepo;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\View;

final class ProfileController
{
    public function __construct(
        private View $view,
        private Csrf $csrf,
        private UserRepo $users,
    ) {}

    public function edit(?int $userId): Response
    {
        if ($userId === null) {
            return (new Response('', 302))->withHeader('Location', '/login');
        }
        $user = $this->users->findById($userId);
        return Response::html($this->view->render('profile/edit', [
            'title'   => 'Your profile',
            'csrf'    => $this->csrf->token(),
            'user'    => $user,
            'avatars' => Avatars::keys(),
        ]));
    }

    public function save(?int $userId, array $input, string $csrf): Response
    {
        if ($userId === null) {
            return (new Response('', 302))->withHeader('Location', '/login');
        }
        if (!$this->csrf->validate($csrf)) {
            $user = $this->users->findById($userId);
            return Response::html($this->view->render('profile/edit', [
                'title'   => 'Your profile',
                'csrf'    => $this->csrf->token(),
                'user'    => $user,
                'avatars' => Avatars::keys(),
                'error'   => 'Your session expired. Please try again.',
            ]), 400);
        }

        $avatar = (string) ($input['avatar_key'] ?? '');
        if (!Avatars::isValid($avatar)) {
            $avatar = Avatars::default();
        }
        $bio = mb_substr(trim((string) ($input['bio'] ?? '')), 0, 280);
        $pronouns = trim((string) ($input['pronouns'] ?? '')) ?: null;
        $contact  = trim((string) ($input['contact'] ?? '')) ?: null;

        $this->users->saveProfile($userId, $avatar, $pronouns, $bio, $contact);

        return (new Response('', 302))->withHeader('Location', '/');
    }
}
```

- [ ] **Step 7: Write `templates/profile/edit.php`** (uses the layout + avatar sprite; icons only)

```php
<?php $error = $error ?? null; $user = $user ?? []; $avatars = $avatars ?? []; ?>
<?php $content = function () use ($e, $error, $user, $avatars, $csrf) {
  $cur = $user['avatar_key'] ?? '';
  ob_start(); ?>
  <?php include __DIR__ . '/../partials/avatars.php'; ?>
  <h1 style="text-wrap:balance;">Make it yours</h1>
  <p style="opacity:.8;margin-top:0;">A few cute details so your crush knows it's really you.</p>
  <?php if ($error): ?><p role="alert" style="color:#b3243b;"><?= $e($error) ?></p><?php endif; ?>
  <form method="post" action="/profile" style="display:flex;flex-direction:column;gap:14px;">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <fieldset style="border:0;padding:0;margin:0;">
      <legend style="font-size:13px;font-weight:600;opacity:.7;">Pick an avatar</legend>
      <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:8px;">
        <?php foreach ($avatars as $key): ?>
          <label style="cursor:pointer;">
            <input type="radio" name="avatar_key" value="<?= $e($key) ?>" <?= $cur === $key ? 'checked' : '' ?>
                   style="position:absolute;opacity:0;width:0;height:0;">
            <span class="av-pick" style="display:inline-flex;width:52px;height:52px;border-radius:16px;align-items:center;justify-content:center;background:#fff;box-shadow:0 0 0 2px <?= $cur === $key ? '#ff3d8b' : '#eadcff' ?> inset;">
              <svg width="34" height="34"><use href="#av-<?= $e($key) ?>"/></svg>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>
    <label style="font-size:13px;font-weight:600;opacity:.7;">Pronouns (optional)
      <input type="text" name="pronouns" value="<?= $e($user['pronouns'] ?? '') ?>" placeholder="she/her"
             style="width:100%;padding:11px;border-radius:12px;border:1px solid #e7d4ff;">
    </label>
    <label style="font-size:13px;font-weight:600;opacity:.7;">About you
      <input type="text" name="bio" maxlength="280" value="<?= $e($user['bio'] ?? '') ?>" placeholder="i'm a sucker for tacos and bad puns"
             style="width:100%;padding:11px;border-radius:12px;border:1px solid #e7d4ff;">
    </label>
    <label style="font-size:13px;font-weight:600;opacity:.7;">Contact (optional)
      <input type="text" name="contact" value="<?= $e($user['contact'] ?? '') ?>" placeholder="phone or @handle"
             style="width:100%;padding:11px;border-radius:12px;border:1px solid #e7d4ff;">
    </label>
    <button type="submit" style="padding:12px;border:0;border-radius:14px;background:#ff3d8b;color:#fff;font-weight:700;font-size:16px;cursor:pointer;">
      Save my profile
    </button>
  </form>
  <?php return ob_get_clean(); };
$body = $content();
include __DIR__ . '/../layout.php';
```

- [ ] **Step 8: Run the profile tests** — Run: `vendor/bin/phpunit --filter "AvatarsTest|ProfileControllerTest"`
Expected: PASS (5 tests).

- [ ] **Step 9: Register routes** — in `config/routes.php`, add a trailing `ProfileController $profile` parameter to the factory and:

```php
    $router->add('GET',  '/profile', static fn(): Response => $profile->edit($currentUserId()));
    $router->add('POST', '/profile', static fn(): Response => $profile->save($currentUserId(), $_POST, (static fn($v) => is_string($v) ? $v : '')($_POST['csrf'] ?? '')));
```

- [ ] **Step 10: Wire in `public/index.php`** — add `use App\Profile\ProfileController;`, build `$profileCtrl = new ProfileController($view, $csrf, $users);`, and add `$profileCtrl` as the trailing argument to the routes-factory invocation (matching the new factory signature).

- [ ] **Step 11: Run the full suite** — Run: `vendor/bin/phpunit`
Expected: all green.

- [ ] **Step 12: Manual check** — serve on port 8888 and confirm the profile route guards:

```bash
DB_DSN="mysql:host=127.0.0.1;dbname=crush_dev;charset=utf8mb4" DB_USER=root DB_PASS= APP_URL="http://127.0.0.1:8888" \
  php -S 127.0.0.1:8888 -t public >/dev/null 2>&1 &
sleep 1
curl -s -o /dev/null -w '/profile (logged out): %{http_code} -> %{redirect_url}\n' 127.0.0.1:8888/profile
kill %1
```
Expected: `302 -> /login`.

- [ ] **Step 13: Commit**

```bash
git add app/Profile/ templates/profile/ templates/partials/avatars.php config/routes.php public/index.php tests/Profile/
git commit -m "feat(profile): avatars + ProfileController + edit page + routes"
```

---

## Self-Review

**1. Spec coverage:** Profile fields — display name (already on `users.name`), avatar pick (`Avatars` + `avatar_key`), pronouns, bio, contact, `profile_completed_at` stamp (spec §4,§8) — Tasks 1,2. Auth-gated profile page (§4) — Task 2 redirects when logged out. The reveal gate's dependency (`isProfileComplete`) is provided here (§4) and consumed by Plan v2-3. Icons only, CSRF on POST, escaped output — Task 2. Port 8888 dev — Task 2 manual check.

**2. Placeholder scan:** No "TBD". The avatar SVGs are real, complete inline shapes. Routes/wiring steps show the exact code to add.

**3. Type consistency:** `UserRepo::saveProfile(int,string,?string,string,?string): void`, `UserRepo::isProfileComplete(array): bool` (static); `Avatars::keys(): array`/`isValid(string): bool`/`default(): string`; `ProfileController::edit(?int): Response`/`save(?int,array,string): Response`. Consumes `UserRepo`, `View`, `Csrf`, `Response`, `Clock` as defined in v1 Plans 1–2. Routes factory gains a trailing `ProfileController`, matched in `public/index.php`.
