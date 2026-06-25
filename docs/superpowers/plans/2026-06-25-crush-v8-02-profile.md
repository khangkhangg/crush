# Crush v8 — Plan 2: Profile Rework (merge, pronouns, avatar indicator + upload) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the "complete your profile to unlock" step happen inline on the answered-invite screen (no extra navigation), drop the pronouns field, give the avatar picker a clear selected indicator, and let the user upload + use their own photo (server-cropped to a safe square).

**Architecture:** Extract the profile form into a reusable `templates/profile/_form.php` partial used by both `/profile` and the answered-invite "locked" state. `ProfileController::save` honors a safe `return_to` and processes an optional avatar upload via GD (validate → center-crop → 256px PNG → `storage/avatars/{id}.png`, `avatar_key='custom'`), served by a new `AvatarController`.

**Tech Stack:** PHP 8.1+, PDO MySQL, PHPUnit 10, ext-gd (present locally + on prod). No new Composer dependencies.

## Global Constraints

- PHP floor 8.1. PSR-4. No new Composer packages. Prepared statements.
- **Icons only — never emojis.** All HTML `$e()`-escaped. POSTs validate CSRF. Run the suite **serially** (FK/"Duplicate schema_migrations" bursts = concurrent-run corruption → reset `crush_test`).
- Avatar upload: validate it is a real image (`getimagesize`), ≤ 5 MB, type in JPEG/PNG/WebP/GIF; **re-encode** via GD (center-crop square → 256×256 → PNG) so no original bytes/EXIF survive; deterministic filename `{userId}.png` (no user-controlled path). Uploads live in `storage/avatars/` (rsync-excluded, persists across deploys). Production: `https://crush.didudi.com`.

## File Structure

- `templates/profile/_form.php` (new) — reusable profile form.
- `templates/profile/edit.php` (modify) — include the partial.
- `app/Profile/ProfileController.php` (modify) — `return_to`, avatar upload.
- `app/Profile/AvatarController.php` (new) — serve `/avatar/{id}`.
- `templates/reveal/response.php` (modify) — inline profile form in the locked state.
- `app/Reveal/RevealController.php` (modify) — pass profile data to the locked render.
- `config/routes.php`, `public/index.php` (modify) — `/avatar/{id}` route + wiring.

---

### Task 1: Profile form partial — remove pronouns, live avatar indicator, return_to

**Files:**
- Create: `templates/profile/_form.php`
- Modify: `templates/profile/edit.php`, `app/Profile/ProfileController.php`
- Test: `tests/Profile/ProfileFormTest.php`

**Interfaces:**
- `templates/profile/_form.php` renders the avatar picker (with a CSS `:checked` indicator), About, Contact, a hidden `return_to`, and the submit — consuming `$e, $user, $avatars, $csrf` and an optional `$returnTo` (default `''`). **No pronouns field.**
- `ProfileController::save` no longer reads pronouns (passes `null`), and redirects to a **safe** `return_to` (must start with `/`, else `/`).

- [ ] **Step 1: Write the failing test** — `tests/Profile/ProfileFormTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Profile;

use App\Auth\UserRepo;
use App\Core\ArrayStore;
use App\Core\Csrf;
use App\Core\View;
use App\Profile\ProfileController;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class ProfileFormTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    private function controller(Csrf $csrf): ProfileController
    {
        return new ProfileController(new View(\dirname(__DIR__, 2) . '/templates'), $csrf, new UserRepo($this->pdo(), $this->clock));
    }

    public function test_form_has_no_pronouns_and_has_selected_indicator(): void
    {
        $uid = (new UserRepo($this->pdo(), $this->clock))->create('u@x.test', 'U', 'magic')['id'];
        $body = $this->controller(new Csrf(new ArrayStore()))->edit($uid)->body();
        $this->assertStringNotContainsString('name="pronouns"', $body);
        $this->assertStringContainsString('av-pick', $body);
        $this->assertStringContainsString('input:checked', $body);     // live selected indicator
        $this->assertStringContainsString('name="return_to"', $body);
    }

    public function test_save_redirects_to_safe_return_to(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $uid = (new UserRepo($this->pdo(), $this->clock))->create('a@x.test', 'A', 'magic')['id'];
        $res = $this->controller($csrf)->save($uid, ['avatar_key' => 'fox', 'bio' => 'hi', 'return_to' => '/invites/tok/response'], $csrf->token());
        $this->assertSame(302, $res->status());
        $this->assertSame('/invites/tok/response', $res->headers()['Location']);
    }

    public function test_save_rejects_unsafe_return_to(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $uid = (new UserRepo($this->pdo(), $this->clock))->create('b@x.test', 'B', 'magic')['id'];
        $res = $this->controller($csrf)->save($uid, ['avatar_key' => 'fox', 'bio' => 'hi', 'return_to' => 'https://evil.com'], $csrf->token());
        $this->assertSame('/', $res->headers()['Location']);          // external URL ignored
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter ProfileFormTest`
Expected: FAIL.

- [ ] **Step 3: Create `templates/profile/_form.php`** (the avatar grid gets a `:checked` indicator via a scoped style; pronouns removed; `return_to` hidden field)

```php
<?php $user = $user ?? []; $avatars = $avatars ?? []; $returnTo = $returnTo ?? ''; $cur = $user['avatar_key'] ?? ''; ?>
<style>
  .av-grid { display:flex; flex-wrap:wrap; gap:10px; margin-top:8px; }
  .av-grid label { cursor:pointer; }
  .av-grid input { position:absolute; opacity:0; width:0; height:0; }
  .av-pick { display:inline-flex; width:52px; height:52px; border-radius:16px; align-items:center; justify-content:center; background:#fff; box-shadow:0 0 0 2px #eadcff inset; transition:box-shadow .15s, transform .15s; overflow:hidden; }
  .av-pick img { width:100%; height:100%; object-fit:cover; }
  .av-grid input:checked + .av-pick { box-shadow:0 0 0 3px #ff3d8b inset; transform:scale(1.06); }
  .av-grid input:focus-visible + .av-pick { outline:2px solid #ff8fc0; outline-offset:2px; }
</style>
<form method="post" action="/profile" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:14px;">
  <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
  <input type="hidden" name="return_to" value="<?= $e($returnTo) ?>">
  <fieldset style="border:0;padding:0;margin:0;">
    <legend style="font-size:13px;font-weight:600;opacity:.7;">Pick an avatar</legend>
    <div class="av-grid">
      <?php if ($cur === 'custom'): ?>
        <label><input type="radio" name="avatar_key" value="custom" checked><span class="av-pick"><img src="/avatar/<?= (int) ($user['id'] ?? 0) ?>" alt="your photo"></span></label>
      <?php endif; ?>
      <?php foreach ($avatars as $key): ?>
        <label><input type="radio" name="avatar_key" value="<?= $e($key) ?>" <?= $cur === $key ? 'checked' : '' ?>><span class="av-pick"><svg width="34" height="34"><use href="#av-<?= $e($key) ?>"/></svg></span></label>
      <?php endforeach; ?>
    </div>
    <label style="display:inline-block;margin-top:10px;font-size:13px;font-weight:600;color:#ff3d8b;cursor:pointer;">
      Upload your own photo
      <input type="file" name="avatar_file" accept="image/*" style="display:none;">
    </label>
  </fieldset>
  <label style="font-size:13px;font-weight:600;opacity:.7;">About you
    <input class="field" type="text" name="bio" maxlength="280" value="<?= $e($user['bio'] ?? '') ?>" placeholder="i'm a sucker for tacos and bad puns">
  </label>
  <label style="font-size:13px;font-weight:600;opacity:.7;">Contact (optional)
    <input class="field" type="text" name="contact" value="<?= $e($user['contact'] ?? '') ?>" placeholder="phone or @handle">
  </label>
  <button type="submit" style="padding:12px;border:0;border-radius:14px;background:#ff3d8b;color:#fff;font-weight:700;font-size:16px;cursor:pointer;">Save my profile</button>
</form>
```

- [ ] **Step 4: Slim `templates/profile/edit.php` to use the partial**

```php
<?php $error = $error ?? null; $user = $user ?? []; $avatars = $avatars ?? []; $returnTo = $returnTo ?? ''; ?>
<?php $content = function () use ($e, $error, $user, $avatars, $csrf, $returnTo) {
  ob_start(); ?>
  <?php include __DIR__ . '/../partials/avatars.php'; ?>
  <h1 style="text-wrap:balance;">Make it yours</h1>
  <p style="opacity:.8;margin-top:0;">A few cute details so your crush knows it's really you.</p>
  <?php if ($error): ?><p role="alert" style="color:#b3243b;"><?= $e($error) ?></p><?php endif; ?>
  <?php include __DIR__ . '/_form.php'; ?>
  <?php return ob_get_clean(); };
$body = $content();
include __DIR__ . '/../layout.php';
```

- [ ] **Step 5: Update `ProfileController::save`** — drop pronouns, add a safe `return_to`:

```php
        $bio     = mb_substr(trim((string) ($input['bio'] ?? '')), 0, 280);
        $contact = trim((string) ($input['contact'] ?? '')) ?: null;
        $avatar  = (string) ($input['avatar_key'] ?? '');
        if (!Avatars::isValid($avatar)) {
            $avatar = Avatars::default();
        }
        $this->users->saveProfile($userId, $avatar, null, $bio, $contact);

        $returnTo = (string) ($input['return_to'] ?? '');
        $dest = (str_starts_with($returnTo, '/') && !str_starts_with($returnTo, '//')) ? $returnTo : '/';
        return (new Response('', 302))->withHeader('Location', $dest);
```

(`edit()` passes `'returnTo' => ''` in its render data; the bad-CSRF re-render keeps working — it renders `profile/edit`.)

- [ ] **Step 6: Run the tests, then the full suite (serially)** — Run: `vendor/bin/phpunit --filter ProfileFormTest` then `vendor/bin/phpunit`
Expected: green. (Task 2 adds the `custom`/upload save path; here `avatar_key='custom'` without a custom file falls to `Avatars::default()` — fine for this task.) Update any existing profile test that asserted a pronouns field or the old inline avatar markup.

- [ ] **Step 7: Commit**

```bash
git add templates/profile/ app/Profile/ProfileController.php tests/Profile/ProfileFormTest.php
git commit -m "feat(profile): reusable form partial, drop pronouns, live avatar indicator, safe return_to"
```

---

### Task 2: Avatar upload (GD crop) + serve route

**Files:**
- Modify: `app/Profile/ProfileController.php`
- Create: `app/Profile/AvatarController.php`, `app/Profile/AvatarStore.php`
- Modify: `config/routes.php`, `public/index.php`
- Test: `tests/Profile/AvatarUploadTest.php`

**Interfaces:**
- `App\Profile\AvatarStore`: `__construct(string $dir)`; `path(int $userId): string` (`{dir}/{id}.png`); `has(int $userId): bool`; `store(int $userId, string $tmpPath): bool` — validate (`getimagesize`, type allowlist, ≤5 MB) + GD center-crop square → 256×256 → `imagepng` to `path`; returns false on invalid.
- `ProfileController` gains an `AvatarStore` dep; in `save`, when `$_FILES['avatar_file']` is a valid upload and `AvatarStore::store` succeeds → `avatar_key = 'custom'`. The `$input['avatar_key'] === 'custom'` choice is honored only when `AvatarStore::has($userId)`.
- `App\Profile\AvatarController::__construct(AvatarStore)`; `show(int $userId): Response` — `image/png` body with `Cache-Control: private, max-age=300`; 404 when absent. Route `GET /avatar/{id}`.

- [ ] **Step 1: Write the failing test** — `tests/Profile/AvatarUploadTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Profile;

use App\Profile\AvatarController;
use App\Profile\AvatarStore;
use PHPUnit\Framework\TestCase;

final class AvatarUploadTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/crush_av_' . bin2hex(random_bytes(4));
        @mkdir($this->dir, 0775, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        @rmdir($this->dir);
    }

    private function makePng(string $path, int $w = 400, int $h = 300): void
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 200, 80, 140));
        imagepng($im, $path);
        imagedestroy($im);
    }

    public function test_store_crops_to_256_square_png(): void
    {
        $store = new AvatarStore($this->dir);
        $src = $this->dir . '/src.png';
        $this->makePng($src, 400, 300);
        $this->assertTrue($store->store(7, $src));
        $this->assertTrue($store->has(7));
        [$w, $h, $type] = getimagesize($store->path(7));
        $this->assertSame(256, $w);
        $this->assertSame(256, $h);
        $this->assertSame(IMAGETYPE_PNG, $type);
    }

    public function test_store_rejects_non_image(): void
    {
        $store = new AvatarStore($this->dir);
        $bad = $this->dir . '/bad.txt';
        file_put_contents($bad, 'not an image');
        $this->assertFalse($store->store(8, $bad));
        $this->assertFalse($store->has(8));
    }

    public function test_controller_serves_png_then_404(): void
    {
        $store = new AvatarStore($this->dir);
        $ctrl = new AvatarController($store);
        $this->assertSame(404, $ctrl->show(9)->status());
        $src = $this->dir . '/s.png';
        $this->makePng($src);
        $store->store(9, $src);
        $res = $ctrl->show(9);
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('image/png', implode(' ', $res->headers()));
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter AvatarUploadTest`
Expected: FAIL — classes missing.

- [ ] **Step 3: Write `app/Profile/AvatarStore.php`**

```php
<?php
declare(strict_types=1);

namespace App\Profile;

final class AvatarStore
{
    private const MAX_BYTES = 5_242_880;
    private const ALLOWED = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF];

    public function __construct(private string $dir) {}

    public function path(int $userId): string
    {
        return rtrim($this->dir, '/') . '/' . $userId . '.png';
    }

    public function has(int $userId): bool
    {
        return is_file($this->path($userId));
    }

    public function store(int $userId, string $tmpPath): bool
    {
        if (!is_file($tmpPath) || filesize($tmpPath) > self::MAX_BYTES) {
            return false;
        }
        $info = @getimagesize($tmpPath);
        if ($info === false || !in_array($info[2], self::ALLOWED, true)) {
            return false;
        }
        $src = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($tmpPath),
            IMAGETYPE_PNG  => @imagecreatefrompng($tmpPath),
            IMAGETYPE_WEBP => @imagecreatefromwebp($tmpPath),
            IMAGETYPE_GIF  => @imagecreatefromgif($tmpPath),
            default        => false,
        };
        if (!$src) {
            return false;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $side = min($w, $h);
        $x = (int) (($w - $side) / 2);
        $y = (int) (($h - $side) / 2);
        $dst = imagecreatetruecolor(256, 256);
        imagecopyresampled($dst, $src, 0, 0, $x, $y, 256, 256, $side, $side);
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0775, true);
        }
        $ok = imagepng($dst, $this->path($userId));
        imagedestroy($src);
        imagedestroy($dst);
        return (bool) $ok;
    }
}
```

- [ ] **Step 4: Write `app/Profile/AvatarController.php`**

```php
<?php
declare(strict_types=1);

namespace App\Profile;

use App\Core\Response;

final class AvatarController
{
    public function __construct(private AvatarStore $store) {}

    public function show(int $userId): Response
    {
        if (!$this->store->has($userId)) {
            return new Response('', 404);
        }
        $bytes = (string) file_get_contents($this->store->path($userId));
        return (new Response($bytes, 200))
            ->withHeader('Content-Type', 'image/png')
            ->withHeader('Cache-Control', 'private, max-age=300');
    }
}
```

- [ ] **Step 5: Process the upload in `ProfileController::save`** — add an `AvatarStore $avatars` ctor param (rename existing `$avatars` template var carefully — the ctor field is the store; `Avatars::keys()` stays static). Before computing `$avatar`, handle the file + the `custom` choice:

```php
        $file = $input['_files']['avatar_file'] ?? ($_FILES['avatar_file'] ?? null);
        $uploaded = is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
            && $this->avatarStore->store($userId, (string) $file['tmp_name']);

        $avatar = (string) ($input['avatar_key'] ?? '');
        if ($uploaded) {
            $avatar = 'custom';
        } elseif ($avatar === 'custom' && $this->avatarStore->has($userId)) {
            $avatar = 'custom';
        } elseif (!Avatars::isValid($avatar)) {
            $avatar = Avatars::default();
        }
        $this->users->saveProfile($userId, $avatar, null, $bio, $contact);
```

(Name the ctor field `private AvatarStore $avatarStore`. The test passes the file via `$_FILES`; the route handler in `index.php` passes `$_POST` as input and reads `$_FILES` directly — keep `save` reading `$_FILES['avatar_file']` with the `$input['_files']` fallback for testability.)

- [ ] **Step 6: Wire the controller + route + store** — `public/index.php`: `$avatarStore = new AvatarStore(dirname(__DIR__) . '/storage/avatars'); $avatarCtrl = new AvatarController($avatarStore);` pass `$avatarStore` into `ProfileController` (trailing arg) and `$avatarCtrl` into the routes factory. `config/routes.php`: add `AvatarController $avatar` to the factory signature + `$router->add('GET', '/avatar/{id}', static fn(string $id): Response => $avatar->show((int) $id));`.

- [ ] **Step 7: Update existing `ProfileController` test construction(s)** — pass a trailing `new AvatarStore(sys_get_temp_dir() . '/crush_av_test')` (the `ProfileFormTest` from Task 1 + any others). Run `vendor/bin/phpunit` until green.

- [ ] **Step 8: Run the tests, then the full suite (serially)** — Run: `vendor/bin/phpunit --filter "AvatarUploadTest|ProfileFormTest"` then `vendor/bin/phpunit`
Expected: all green.

- [ ] **Step 9: Commit**

```bash
git add app/Profile/ config/routes.php public/index.php tests/Profile/AvatarUploadTest.php
git commit -m "feat(profile): upload + crop-to-square custom avatar, served at /avatar/{id}"
```

---

### Task 3: Inline the profile form on the answered-invite screen

**Files:**
- Modify: `app/Reveal/RevealController.php`, `templates/reveal/response.php`
- Modify: `public/index.php`
- Test: `tests/Reveal/InlineProfileTest.php`

**Interfaces:**
- `RevealController` gains a `Csrf $csrf` dep. In the `locked` state it passes `user` (the sender), `avatars` (`Avatars::keys()`), `csrf`, and `returnTo` (`/invites/{token}/response`) to `reveal/response.php`.
- `reveal/response.php` `locked` branch renders the profile form inline (`include … /profile/_form.php`) under the "they answered" heading, instead of the "Complete my profile" link.

- [ ] **Step 1: Write the failing test** — `tests/Reveal/InlineProfileTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Reveal;

use App\Auth\UserRepo;
use App\Core\ArrayStore;
use App\Core\Csrf;
use App\Core\View;
use App\Ics\IcsBuilder;
use App\Invite\InvitePlaceRepo;
use App\Invite\InviteRepo;
use App\Invite\ResponseRepo;
use App\Reveal\RevealController;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class InlineProfileTest extends DatabaseTestCase
{
    public function test_locked_state_shows_inline_profile_form(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $users = new UserRepo($this->pdo(), $clock);
        $invites = new InviteRepo($this->pdo(), $clock);
        $responses = new ResponseRepo($this->pdo(), $clock);

        $sender = $users->create('s@x.test', 'Sue', 'magic');           // profile NOT complete -> locked
        $invite = $invites->create([
            'sender_id' => $sender['id'], 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => false, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'expires_at' => '2026-12-01 00:00:00',
        ]);
        $responses->store((int) $invite['id'], ['chosen_start' => '2026-02-10 19:00:00', 'meal_choice' => 'dinner']);

        $ctrl = new RevealController(
            new View(\dirname(__DIR__, 2) . '/templates'), $users, $invites, $responses,
            new IcsBuilder($clock), new InvitePlaceRepo($this->pdo()), new Csrf(new ArrayStore())
        );
        $body = $ctrl->show($sender['id'], $invite['public_token'])->body();
        $this->assertStringContainsString('answered', $body);                 // context heading
        $this->assertStringContainsString('action="/profile"', $body);        // inline form
        $this->assertStringContainsString('/invites/' . $invite['public_token'] . '/response', $body); // return_to
        $this->assertStringNotContainsString('Complete my profile', $body);   // old CTA gone
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter InlineProfileTest`
Expected: FAIL — ctor arity / old CTA.

- [ ] **Step 3: Add `Csrf` + profile data to `RevealController`** — add `use App\Core\Csrf;` + `use App\Profile\Avatars;`, a trailing ctor param `private Csrf $csrf,`, and in `render` provide locked-state extras:

```php
        $sender = $this->users->findById((int) ($invite['sender_id'] ?? 0));
        return Response::html($this->view->render('reveal/response', [
            'title'    => 'Your crush',
            'state'    => $state,
            'invite'   => $invite,
            'response' => $response,
            'chosenPlace' => $chosenPlace,
            'user'     => $sender,
            'avatars'  => Avatars::keys(),
            'csrf'     => $this->csrf->token(),
            'returnTo' => '/invites/' . ($invite['public_token'] ?? '') . '/response',
        ]));
```

(Keep the existing `chosenPlace` computation. `$invite` may be `null` for the `missing` state — guard `sender_id` with `?? 0` and the template only uses these in the `locked` branch.)

- [ ] **Step 4: Render the inline form in `templates/reveal/response.php`** — replace the `locked` branch body:

```php
  <?php elseif ($state === 'locked'):
        $user = $user ?? []; $avatars = $avatars ?? []; $returnTo = $returnTo ?? ''; ?>
    <?php include __DIR__ . '/../partials/avatars.php'; ?>
    <h1 style="text-wrap:balance;"><?= $e($crush) ?> answered!</h1>
    <p style="opacity:.85;">Add a few cute details so they know it's really you — then you'll see what they picked.</p>
    <?php include __DIR__ . '/../profile/_form.php'; ?>
```

- [ ] **Step 5: Wire `Csrf` into `RevealController` in `public/index.php`** — add `$csrf` as the trailing arg: `new RevealController($view, $users, $inviteRepo, $responseRepo, new IcsBuilder($clock), $invitePlaceRepo, $csrf)`.

- [ ] **Step 6: Update existing RevealController test ctors** — `RevealControllerTest`, `RevealIcsTest`, `ChosenPlaceTest` build `new RevealController(...)`; add a trailing `new Csrf(new ArrayStore())` (import). Run `vendor/bin/phpunit` until green (existing locked-state test that asserted "Complete my profile" must be updated to assert the inline form).

- [ ] **Step 7: Run the test, then the full suite (serially)** — Run: `vendor/bin/phpunit --filter "InlineProfileTest|RevealControllerTest"` then `vendor/bin/phpunit`
Expected: all green.

- [ ] **Step 8: Commit**

```bash
git add app/Reveal/RevealController.php templates/reveal/response.php public/index.php tests/Reveal/
git commit -m "feat(reveal): complete profile inline on the answered-invite screen"
```

---

## Self-Review

**1. Spec coverage:** Merge profile confirmation + entry into one screen (item 1) — Task 3. Remove pronouns (item 2) — Task 1. Avatar selected indicator (item 3) — Task 1 (`:checked` CSS). Upload your own avatar (item 3) — Task 2. Icons only; escaped; CSRF; upload re-encoded/validated — throughout.

**2. Placeholder scan:** No "TBD". The profile form is a shared partial used by `/profile` and the locked reveal. Upload is GD-validated + re-encoded (no original bytes survive), deterministic filename, stored in rsync-excluded `storage/avatars`. `return_to` is `/`-prefixed-only (no `//`). Full code throughout.

**3. Type consistency:** `AvatarStore::store(int,string):bool`/`has(int):bool`/`path(int):string`. `AvatarController::show(int):Response`. `ProfileController::__construct(View,Csrf,UserRepo,AvatarStore)`; `save` reads `return_to`, processes `$_FILES['avatar_file']`, passes `null` pronouns. `RevealController::__construct(...,Csrf)` matched in `public/index.php` + 3 reveal test ctors. `_form.php` consumes `$user,$avatars,$csrf,$returnTo`. Routes factory gains `AvatarController`.
