# Crush v2 — Plan 3: Reveal Gating Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the sender an on-site confirmation page for a crush's response that is **locked until the sender completes their profile** — a teaser shows the moment the crush answers; finishing the profile unlocks the full response + a calendar (`.ics`) download. The dashboard flags answered invites.

**Architecture:** A `RevealController` owns the sender-facing response page and the `.ics` download, both gated by login + invite ownership + `profile_completed_at`. The dashboard gains an "answered" link. Reuses `InviteRepo`, `ResponseRepo`, `UserRepo`, `IcsBuilder`.

**Tech Stack:** PHP 8.1+, PDO MySQL, PHPUnit 10.

## Global Constraints

- PHP floor 8.1. PSR-4. No new Composer packages.
- **Icons only — never emojis.** All HTML output escaped via `App\Core\e()`.
- Sender pages require login; a sender may only view **their own** invite's response (404 otherwise).
- The full response + `.ics` are locked until `UserRepo::isProfileComplete(sender)` is true. The teaser must **not** leak the crush's choices.
- Local dev serves on **port 8888**. Integration tests use MySQL `crush_test`.

## File Structure

- `app/Reveal/RevealController.php` — response page + `.ics` download.
- `templates/reveal/response.php` — one template branching on `$state` (`waiting` | `locked` | `reveal`).
- `templates/invite/dashboard.php` (modify) — "answered" link.
- `config/routes.php`, `public/index.php` (modify) — reveal routes.

---

### Task 1: RevealController response page (waiting / locked / reveal)

**Files:**
- Create: `app/Reveal/RevealController.php`
- Create: `templates/reveal/response.php`
- Modify: `config/routes.php`, `public/index.php`
- Test: `tests/Reveal/RevealControllerTest.php`

**Interfaces:**
- Consumes: `App\Invite\InviteRepo`, `App\Invite\ResponseRepo`, `App\Auth\UserRepo`, `App\Ics\IcsBuilder`, `App\Core\View`, `App\Core\Response`.
- Produces: `App\Reveal\RevealController` with `__construct(View $view, UserRepo $users, InviteRepo $invites, ResponseRepo $responses, IcsBuilder $ics)`:
  - `show(?int $userId, string $token): Response`:
    - `302 → /login` when `$userId` is null.
    - load invite by token; `404` if missing or `invite['sender_id'] !== $userId`.
    - load `ResponseRepo::findByInvite`. If null → render `reveal/response` with `state='waiting'` (200).
    - else if sender's `isProfileComplete` is false → render `state='locked'` (200) — a teaser only, no choices.
    - else → render `state='reveal'` with the full response (200).

- [ ] **Step 1: Write the failing test** — `tests/Reveal/RevealControllerTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Reveal;

use App\Auth\UserRepo;
use App\Core\View;
use App\Ics\IcsBuilder;
use App\Invite\InviteRepo;
use App\Invite\ResponseRepo;
use App\Reveal\RevealController;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class RevealControllerTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    private function controller(): RevealController
    {
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        return new RevealController(
            $view,
            new UserRepo($this->pdo(), $this->clock),
            new InviteRepo($this->pdo(), $this->clock),
            new ResponseRepo($this->pdo(), $this->clock),
            new IcsBuilder($this->clock)
        );
    }

    /** @return array{0:int,1:array} senderId, invite */
    private function seed(): array
    {
        $users = new UserRepo($this->pdo(), $this->clock);
        $sender = $users->create('sue@x.test', 'Sue', 'magic');
        $invite = (new InviteRepo($this->pdo(), $this->clock))->create([
            'sender_id' => $sender['id'], 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => false, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'expires_at' => '2026-02-01 00:00:00',
        ]);
        return [$sender['id'], $invite];
    }

    private function addResponse(int $inviteId): void
    {
        (new ResponseRepo($this->pdo(), $this->clock))->store($inviteId, [
            'chosen_start' => '2026-02-10 19:00:00', 'chosen_end' => '2026-02-10 21:00:00',
            'meal_choice' => 'dinner', 'meal_wish' => 'sushi', 'crush_contact' => '@cee',
            'pickup_name' => 'Tartine', 'pickup_address' => '1 Main St',
        ]);
    }

    public function test_logged_out_redirects(): void
    {
        [, $invite] = $this->seed();
        $res = $this->controller()->show(null, $invite['public_token']);
        $this->assertSame(302, $res->status());
        $this->assertSame('/login', $res->headers()['Location']);
    }

    public function test_other_users_invite_is_404(): void
    {
        [, $invite] = $this->seed();
        $stranger = (new UserRepo($this->pdo(), $this->clock))->create('x@x.test', 'X', 'magic')['id'];
        $this->assertSame(404, $this->controller()->show($stranger, $invite['public_token'])->status());
    }

    public function test_no_response_shows_waiting(): void
    {
        [$senderId, $invite] = $this->seed();
        $res = $this->controller()->show($senderId, $invite['public_token']);
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('waiting', strtolower($res->body()));
        $this->assertStringNotContainsString('dinner', $res->body());
    }

    public function test_response_but_incomplete_profile_is_locked_and_hides_choices(): void
    {
        [$senderId, $invite] = $this->seed();
        $this->addResponse($invite['id']);
        $res = $this->controller()->show($senderId, $invite['public_token']);
        $this->assertSame(200, $res->status());
        // Teaser must NOT leak the crush's actual choices.
        $this->assertStringNotContainsString('dinner', $res->body());
        $this->assertStringNotContainsString('Tartine', $res->body());
        $this->assertStringContainsString('/profile', $res->body()); // CTA to complete profile
    }

    public function test_response_and_complete_profile_reveals(): void
    {
        [$senderId, $invite] = $this->seed();
        $this->addResponse($invite['id']);
        (new UserRepo($this->pdo(), $this->clock))->saveProfile($senderId, 'fox', null, 'hi', null);

        $res = $this->controller()->show($senderId, $invite['public_token']);
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('dinner', $res->body());
        $this->assertStringContainsString('Tartine', $res->body());
        $this->assertStringContainsString('calendar', strtolower($res->body())); // download link
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter RevealControllerTest`
Expected: FAIL — `Class "App\Reveal\RevealController" not found`.

- [ ] **Step 3: Write `app/Reveal/RevealController.php`**

```php
<?php
declare(strict_types=1);

namespace App\Reveal;

use App\Auth\UserRepo;
use App\Core\Response;
use App\Core\View;
use App\Ics\IcsBuilder;
use App\Invite\InviteRepo;
use App\Invite\ResponseRepo;

final class RevealController
{
    public function __construct(
        private View $view,
        private UserRepo $users,
        private InviteRepo $invites,
        private ResponseRepo $responses,
        private IcsBuilder $ics,
    ) {}

    public function show(?int $userId, string $token): Response
    {
        $ctx = $this->load($userId, $token);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$invite, $response, $complete] = $ctx;

        if ($response === null) {
            return $this->render('waiting', $invite, null);
        }
        if (!$complete) {
            return $this->render('locked', $invite, null);
        }
        return $this->render('reveal', $invite, $response);
    }

    /**
     * @return Response|array{0:array,1:?array,2:bool} early Response, or [invite, response, profileComplete]
     */
    private function load(?int $userId, string $token): Response|array
    {
        if ($userId === null) {
            return (new Response('', 302))->withHeader('Location', '/login');
        }
        $invite = $this->invites->findByToken($token);
        if ($invite === null || $invite['sender_id'] !== $userId) {
            return Response::html($this->view->render('reveal/response', [
                'title' => 'Not found', 'state' => 'missing', 'invite' => null, 'response' => null,
            ]), 404);
        }
        $sender = $this->users->findById($userId);
        return [$invite, $this->responses->findByInvite((int) $invite['id']), UserRepo::isProfileComplete($sender ?? [])];
    }

    private function render(string $state, array $invite, ?array $response): Response
    {
        return Response::html($this->view->render('reveal/response', [
            'title'    => 'Your crush',
            'state'    => $state,
            'invite'   => $invite,
            'response' => $response,
        ]));
    }
}
```

- [ ] **Step 4: Write `templates/reveal/response.php`** (one template, branches on `$state`; icons only)

```php
<?php $state = $state ?? 'waiting'; $invite = $invite ?? null; $response = $response ?? null; ?>
<?php $content = function () use ($e, $state, $invite, $response) {
  $crush = $invite['crush_name'] ?? ($invite['crush_email'] ?? 'your crush');
  ob_start();
  if ($state === 'missing'): ?>
    <h1>Not found</h1><p class="subtitle">We couldn't find that invite.</p>
  <?php elseif ($state === 'waiting'): ?>
    <h1 style="text-wrap:balance;">Waiting on <?= $e($crush) ?></h1>
    <p style="opacity:.85;">No response yet. We'll let you know the moment they answer.</p>
  <?php elseif ($state === 'locked'): ?>
    <h1 style="text-wrap:balance;"><?= $e($crush) ?> answered!</h1>
    <p style="opacity:.85;">Complete your profile to unlock what they picked and add it to your calendar.</p>
    <a href="/profile" style="display:inline-block;margin-top:10px;padding:12px 18px;border-radius:14px;background:#ff3d8b;color:#fff;font-weight:700;text-decoration:none;">Complete my profile</a>
  <?php else: /* reveal */ ?>
    <h1 style="text-wrap:balance;">It's a date with <?= $e($crush) ?></h1>
    <ul style="list-style:none;padding:0;line-height:1.8;">
      <li><strong>When:</strong> <?= $e($response['chosen_start'] ?? '') ?></li>
      <?php if (!empty($response['meal_choice'])): ?><li><strong>Craving:</strong> <?= $e($response['meal_choice']) ?></li><?php endif; ?>
      <?php if (!empty($response['meal_wish'])): ?><li><strong>Wish:</strong> <?= $e($response['meal_wish']) ?></li><?php endif; ?>
      <?php if (!empty($response['crush_contact'])): ?><li><strong>Contact:</strong> <?= $e($response['crush_contact']) ?></li><?php endif; ?>
      <?php $place = trim((string)(($response['pickup_name'] ?? '') . ' ' . ($response['pickup_address'] ?? ''))); ?>
      <?php if ($place !== ''): ?><li><strong>Pickup:</strong> <?= $e($place) ?></li><?php endif; ?>
    </ul>
    <a href="/invites/<?= $e($invite['public_token']) ?>/calendar" style="display:inline-block;margin-top:8px;padding:12px 18px;border-radius:14px;background:#ff3d8b;color:#fff;font-weight:700;text-decoration:none;">Download calendar invite</a>
  <?php endif;
  return ob_get_clean(); };
$body = $content();
include __DIR__ . '/../layout.php';
```

- [ ] **Step 5: Run the test** — Run: `vendor/bin/phpunit --filter RevealControllerTest`
Expected: PASS (5 tests).

- [ ] **Step 6: Register the route + wire** — in `config/routes.php` add a trailing `RevealController $reveal` factory param and:

```php
    $router->add('GET', '/invites/{token}/response', static fn(string $token): Response => $reveal->show($currentUserId(), $token));
```

In `public/index.php` build `$revealCtrl = new RevealController($view, $users, $inviteRepo, $responseRepo, new IcsBuilder($clock));` (`$responseRepo` already exists from v1 respond wiring; if not in scope, construct `new ResponseRepo($pdo, $clock)`), and pass `$revealCtrl` as the trailing routes-factory argument.

- [ ] **Step 7: Run the full suite** — Run: `vendor/bin/phpunit`
Expected: all green.

- [ ] **Step 8: Commit**

```bash
git add app/Reveal/RevealController.php templates/reveal/response.php config/routes.php public/index.php tests/Reveal/RevealControllerTest.php
git commit -m "feat(reveal): profile-gated response page (waiting/locked/reveal)"
```

---

### Task 2: Calendar (.ics) download + dashboard "answered" link

**Files:**
- Modify: `app/Reveal/RevealController.php` (add `downloadIcs`)
- Modify: `templates/invite/dashboard.php` (answered link)
- Modify: `config/routes.php`
- Test: `tests/Reveal/RevealIcsTest.php`

**Interfaces:**
- Produces: `RevealController::downloadIcs(?int $userId, string $token): Response` — same login + ownership guards; additionally requires `isProfileComplete` (else `302 → /invites/{token}/response`) and a stored response (else 404). Builds the `.ics` via `IcsBuilder` and returns it with `Content-Type: text/calendar; charset=utf-8` and `Content-Disposition: attachment; filename="Date.ics"`.
- Dashboard: each invite whose `status` is one of `responded`/`pending_sender`/`confirmed`/`closed` shows an "answered — view" link to `/invites/{token}/response`.

- [ ] **Step 1: Write the failing test** — `tests/Reveal/RevealIcsTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Reveal;

use App\Auth\UserRepo;
use App\Core\View;
use App\Ics\IcsBuilder;
use App\Invite\InviteRepo;
use App\Invite\ResponseRepo;
use App\Reveal\RevealController;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class RevealIcsTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    private function controller(): RevealController
    {
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        return new RevealController(
            $view, new UserRepo($this->pdo(), $this->clock),
            new InviteRepo($this->pdo(), $this->clock),
            new ResponseRepo($this->pdo(), $this->clock),
            new IcsBuilder($this->clock)
        );
    }

    private function seedWithResponse(bool $completeProfile): array
    {
        $users = new UserRepo($this->pdo(), $this->clock);
        $sender = $users->create('sue@x.test', 'Sue', 'magic');
        $invite = (new InviteRepo($this->pdo(), $this->clock))->create([
            'sender_id' => $sender['id'], 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => false, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'expires_at' => '2026-02-01 00:00:00',
        ]);
        (new ResponseRepo($this->pdo(), $this->clock))->store($invite['id'], [
            'chosen_start' => '2026-02-10 19:00:00', 'chosen_end' => '2026-02-10 21:00:00',
            'meal_choice' => 'dinner', 'pickup_name' => 'Tartine',
        ]);
        if ($completeProfile) {
            $users->saveProfile($sender['id'], 'fox', null, 'hi', null);
        }
        return [$sender['id'], $invite];
    }

    public function test_complete_profile_downloads_ics(): void
    {
        [$senderId, $invite] = $this->seedWithResponse(true);
        $res = $this->controller()->downloadIcs($senderId, $invite['public_token']);
        $this->assertSame(200, $res->status());
        $this->assertSame('text/calendar; charset=utf-8', $res->headers()['Content-Type']);
        $this->assertStringContainsString('attachment; filename="Date.ics"', $res->headers()['Content-Disposition']);
        $this->assertStringContainsString('BEGIN:VCALENDAR', $res->body());
        $this->assertStringContainsString('SUMMARY:Date with Cee', $res->body());
    }

    public function test_incomplete_profile_is_redirected(): void
    {
        [$senderId, $invite] = $this->seedWithResponse(false);
        $res = $this->controller()->downloadIcs($senderId, $invite['public_token']);
        $this->assertSame(302, $res->status());
        $this->assertStringContainsString('/response', $res->headers()['Location']);
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter RevealIcsTest`
Expected: FAIL — `Call to undefined method ...downloadIcs`.

- [ ] **Step 3: Add `downloadIcs` to `app/Reveal/RevealController.php`** (insert after `show`)

```php
    public function downloadIcs(?int $userId, string $token): Response
    {
        $ctx = $this->load($userId, $token);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$invite, $response, $complete] = $ctx;

        if ($response === null) {
            return Response::html('<h1>Not found</h1>', 404);
        }
        if (!$complete) {
            return (new Response('', 302))->withHeader('Location', '/invites/' . $invite['public_token'] . '/response');
        }

        $crush = $invite['crush_name'] ?: 'your crush';
        $place = trim((string) (($response['pickup_name'] ?? '') . ' ' . ($response['pickup_address'] ?? '')));
        $ics = $this->ics->build([
            'uid'         => $invite['public_token'] . '@crush',
            'summary'     => 'Date with ' . $crush,
            'start'       => (string) $response['chosen_start'],
            'end'         => (string) $response['chosen_end'],
            'location'    => $place !== '' ? $place : null,
            'description' => !empty($response['meal_choice']) ? (string) $response['meal_choice'] : null,
        ]);

        return new Response($ics, 200, [
            'Content-Type'        => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="Date.ics"',
        ]);
    }
```

- [ ] **Step 4: Run the test** — Run: `vendor/bin/phpunit --filter RevealIcsTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Add the route** — in `config/routes.php`:

```php
    $router->add('GET', '/invites/{token}/calendar', static fn(string $token): Response => $reveal->downloadIcs($currentUserId(), $token));
```

- [ ] **Step 6: Add the "answered" link to `templates/invite/dashboard.php`** — inside the per-invite `<li>`, after the status span, add:

```php
          <?php if (in_array($inv['status'], ['responded', 'pending_sender', 'confirmed', 'closed'], true)): ?>
            <a href="/invites/<?= $e($inv['public_token']) ?>/response" style="display:inline-block;margin-top:6px;color:#ff3d8b;font-weight:700;text-decoration:none;">They answered — view</a>
          <?php endif; ?>
```

- [ ] **Step 7: Run the full suite** — Run: `vendor/bin/phpunit`
Expected: all green.

- [ ] **Step 8: Manual check on port 8888** — confirm the response route guards when logged out:

```bash
DB_DSN="mysql:host=127.0.0.1;dbname=crush_dev;charset=utf8mb4" DB_USER=root DB_PASS= APP_URL="http://127.0.0.1:8888" \
  php -S 127.0.0.1:8888 -t public >/dev/null 2>&1 &
sleep 1
curl -s -o /dev/null -w '/invites/x/response (logged out): %{http_code} -> %{redirect_url}\n' 127.0.0.1:8888/invites/sometoken/response
kill %1
```
Expected: `302 -> /login`.

- [ ] **Step 9: Commit**

```bash
git add app/Reveal/RevealController.php templates/invite/dashboard.php config/routes.php tests/Reveal/RevealIcsTest.php
git commit -m "feat(reveal): gated .ics download + dashboard answered link"
```

---

## Self-Review

**1. Spec coverage:** Sender confirmation page with full response + calendar (spec §3,§4) — Tasks 1,2. Reveal locked until sender's `profile_completed_at` (§4) — Task 1 `locked` state + Task 2 download gate. Teaser does not leak choices (§4) — Task 1 `locked` template + test asserting `dinner`/`Tartine` absent. "Crush answered" indicator (§3) — Task 2 dashboard link. Ownership guard (§4) — `load()` 404 on non-owner. `.ics` download (§3) — Task 2. Icons only, escaped output. Port 8888 dev — Task 2 manual check.

**2. Placeholder scan:** No "TBD". The `load()` helper returns either an early `Response` or the `[invite, response, complete]` tuple — a real control-flow pattern, fully shown. Templates are complete.

**3. Type consistency:** `RevealController::show(?int,string): Response`, `downloadIcs(?int,string): Response`, private `load(?int,string): Response|array`. Consumes `InviteRepo::findByToken`, `ResponseRepo::findByInvite`, `UserRepo::findById`/`isProfileComplete` (static), `IcsBuilder::build(array): string`, `Response` (the `new Response(body,status,headers)` constructor from v1 Plan 1) as defined. Routes factory gains a trailing `RevealController`, matched in `public/index.php`. Dashboard template change keys off the existing `status` field.
