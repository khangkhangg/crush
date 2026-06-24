# Crush — Plan 7: Admin Panel + Security Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the admin control panel (mailer/settings config + send-test, theme A/B funnel + weights, moderation) and the abuse controls this app needs because it emails strangers: rate limiting on sending, and a one-click block/report so a crush can stop further invites.

**Architecture:** New `app/Security` (`RateLimiter`, `BlockRepo`) and `app/Admin` (`AdminController`). A `rate_limits` + `blocks` migration and a `bin/make-admin.php` CLI. Rate limiting + block checks wire into `InviteController::create`; the block link goes into the invite email; the admin panel is gated on `users.is_admin`.

**Tech Stack:** PHP 8.1+, PDO MySQL, PHPUnit 10. No new Composer dependencies.

## Global Constraints

- PHP floor 8.1. PSR-4. No new Composer packages.
- **Icons only — never emojis.** All HTML output escaped via `App\Core\e()`. All admin/abuse POST routes validate CSRF.
- Admin routes require `users.is_admin = 1`; non-admins get 403. The block/report route is public (no auth).
- Prepared statements only. Secrets stay in `settings`, never committed.
- Integration tests use MySQL `crush_test`.

## File Structure

- `migrations/0005_rate_blocks.sql` — `rate_limits`, `blocks`.
- `bin/make-admin.php` — CLI to grant admin.
- `app/Security/RateLimiter.php`, `app/Security/BlockRepo.php`.
- `app/Admin/AdminController.php`, `app/Admin/BlockController.php` (public block flow).
- `templates/admin/*.php`, `templates/respond/blocked.php`.
- Modify: `app/Invite/InviteController.php`, `app/Mail/Postman.php`, `app/Theme/ThemeRepo.php`, `config/routes.php`, `public/index.php`.

---

### Task 1: rate_limits + blocks migration + make-admin CLI

**Files:**
- Create: `migrations/0005_rate_blocks.sql`
- Create: `bin/make-admin.php`
- Test: `tests/Security/SecuritySchemaTest.php`

**Interfaces:**
- Produces: `rate_limits` (unique `scope`+`identifier`) and `blocks` (unique `sender_id`+`crush_email`) tables; a `bin/make-admin.php` CLI that sets `is_admin=1` for an email.

- [ ] **Step 1: Write the failing test** — `tests/Security/SecuritySchemaTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Security;

use Tests\Support\DatabaseTestCase;

final class SecuritySchemaTest extends DatabaseTestCase
{
    public function test_tables_and_columns(): void
    {
        $cols = fn(string $t) => array_column($this->pdo()->query("SHOW COLUMNS FROM `{$t}`")->fetchAll(), 'Field');
        foreach (['id', 'scope', 'identifier', 'window_start', 'count'] as $c) {
            $this->assertContains($c, $cols('rate_limits'), "rate_limits.$c");
        }
        foreach (['id', 'sender_id', 'crush_email', 'reason', 'created_at'] as $c) {
            $this->assertContains($c, $cols('blocks'), "blocks.$c");
        }
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter SecuritySchemaTest`
Expected: FAIL — table `rate_limits` not found.

- [ ] **Step 3: Write `migrations/0005_rate_blocks.sql`**

```sql
CREATE TABLE IF NOT EXISTS rate_limits (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  scope        VARCHAR(48)  NOT NULL,
  identifier   VARCHAR(191) NOT NULL,
  window_start DATETIME     NOT NULL,
  count        INT          NOT NULL DEFAULT 0,
  UNIQUE KEY uq_rate (scope, identifier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS blocks (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  sender_id   BIGINT UNSIGNED NOT NULL,
  crush_email VARCHAR(191) NOT NULL,
  reason      VARCHAR(191) NULL,
  created_at  DATETIME     NOT NULL,
  UNIQUE KEY uq_block (sender_id, crush_email),
  KEY idx_block_email (crush_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- [ ] **Step 4: Run to verify it passes** — Run: `vendor/bin/phpunit --filter SecuritySchemaTest`
Expected: PASS.

- [ ] **Step 5: Write `bin/make-admin.php`**

```php
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Config;
use App\Core\DB;

$email = $argv[1] ?? null;
if ($email === null) {
    fwrite(STDERR, "Usage: php bin/make-admin.php <email>\n");
    exit(1);
}

/** @var Config $config */
$config = require dirname(__DIR__) . '/config/config.php';
$pdo = DB::connect($config);
$stmt = $pdo->prepare('UPDATE users SET is_admin = 1 WHERE email = ?');
$stmt->execute([$email]);

if ($stmt->rowCount() === 0) {
    fwrite(STDERR, "No user with email {$email}\n");
    exit(1);
}
echo "Granted admin to {$email}\n";
```

- [ ] **Step 6: Commit**

```bash
git add migrations/0005_rate_blocks.sql bin/make-admin.php tests/Security/SecuritySchemaTest.php
git commit -m "feat(security): rate_limits + blocks migration + make-admin CLI"
```

---

### Task 2: RateLimiter + wire into invite create

**Files:**
- Create: `app/Security/RateLimiter.php`
- Test: `tests/Security/RateLimiterTest.php`
- Modify: `app/Invite/InviteController.php` (enforce limits on create)
- Test: `tests/Invite/InviteRateLimitTest.php`

**Interfaces:**
- Produces: `App\Security\RateLimiter` with `__construct(\PDO $pdo, Clock $clock)` and `hit(string $scope, string $identifier, int $limit, int $windowSeconds): bool` — fixed-window counter; returns `true` (and records the hit) while under `limit` in the current window, `false` once the limit is reached.
- `InviteController::__construct` gains a trailing `RateLimiter $limits`; `create()` returns a 429 form render when the per-sender (`invites_per_sender`, 20/day) or per-crush-email (`invites_per_email`, 3/day) limit is exceeded — checked **after** CSRF + email validation, **before** creating.

- [ ] **Step 1: Write the failing RateLimiter test** — `tests/Security/RateLimiterTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Security;

use App\Security\RateLimiter;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class RateLimiterTest extends DatabaseTestCase
{
    public function test_allows_up_to_limit_then_blocks(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $rl = new RateLimiter($this->pdo(), $clock);

        $this->assertTrue($rl->hit('test', 'a', 3, 3600));
        $this->assertTrue($rl->hit('test', 'a', 3, 3600));
        $this->assertTrue($rl->hit('test', 'a', 3, 3600));
        $this->assertFalse($rl->hit('test', 'a', 3, 3600));
    }

    public function test_separate_identifiers_are_independent(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $rl = new RateLimiter($this->pdo(), $clock);
        $this->assertTrue($rl->hit('test', 'a', 1, 3600));
        $this->assertFalse($rl->hit('test', 'a', 1, 3600));
        $this->assertTrue($rl->hit('test', 'b', 1, 3600));
    }

    public function test_window_resets_after_expiry(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $rl = new RateLimiter($this->pdo(), $clock);
        $this->assertTrue($rl->hit('test', 'a', 1, 60));
        $this->assertFalse($rl->hit('test', 'a', 1, 60));
        $clock->advance(61);
        $this->assertTrue($rl->hit('test', 'a', 1, 60));
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter RateLimiterTest`
Expected: FAIL — `Class "App\Security\RateLimiter" not found`.

- [ ] **Step 3: Write `app/Security/RateLimiter.php`**

```php
<?php
declare(strict_types=1);

namespace App\Security;

use App\Core\Clock;

final class RateLimiter
{
    public function __construct(private \PDO $pdo, private Clock $clock) {}

    public function hit(string $scope, string $identifier, int $limit, int $windowSeconds): bool
    {
        $now = $this->clock->now();
        $nowStr = $now->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'SELECT id, window_start, count FROM rate_limits WHERE scope = ? AND identifier = ?'
        );
        $stmt->execute([$scope, $identifier]);
        $row = $stmt->fetch();

        if ($row === false) {
            $this->pdo->prepare(
                'INSERT INTO rate_limits (scope, identifier, window_start, count) VALUES (?, ?, ?, 1)'
            )->execute([$scope, $identifier, $nowStr]);
            return true;
        }

        $windowStart = new \DateTimeImmutable((string) $row['window_start'], new \DateTimeZone('UTC'));
        $elapsed = $now->getTimestamp() - $windowStart->getTimestamp();

        if ($elapsed >= $windowSeconds) {
            $this->pdo->prepare('UPDATE rate_limits SET window_start = ?, count = 1 WHERE id = ?')
                ->execute([$nowStr, $row['id']]);
            return true;
        }

        if ((int) $row['count'] >= $limit) {
            return false;
        }

        $this->pdo->prepare('UPDATE rate_limits SET count = count + 1 WHERE id = ?')
            ->execute([$row['id']]);
        return true;
    }
}
```

- [ ] **Step 4: Run to verify it passes** — Run: `vendor/bin/phpunit --filter RateLimiterTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Write the invite rate-limit test** — `tests/Invite/InviteRateLimitTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Invite;

use App\Auth\UserRepo;
use App\Core\ArrayStore;
use App\Core\Csrf;
use App\Core\IcsThrowawayHelper; // placeholder import removed below
use App\Core\View;
use App\Ics\IcsBuilder;
use App\Invite\InviteController;
use App\Invite\InviteRepo;
use App\Mail\Postman;
use App\Security\RateLimiter;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;
use Tests\Support\SpyMailer;

final class InviteRateLimitTest extends DatabaseTestCase
{
    public function test_per_email_limit_blocks_fourth_invite(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $csrf  = new Csrf(new ArrayStore());
        $view  = new View(\dirname(__DIR__, 2) . '/templates');
        $invites = new InviteRepo($this->pdo(), $clock);
        $users   = new UserRepo($this->pdo(), $clock);
        $postman = new Postman(new SpyMailer(), new IcsBuilder($clock), $view, 'http://localhost');
        $limiter = new RateLimiter($this->pdo(), $clock);
        $ctrl = new InviteController($view, $csrf, $invites, $users, $clock, 'http://localhost', $postman, $limiter);

        $sender = $users->create('s@x.test', 'Sue', 'magic')['id'];
        $data = ['crush_email' => 'crush@x.test', 'date_mode' => 'instant'];

        // per-email limit is 3/day; the 4th to the same email is blocked
        for ($i = 0; $i < 3; $i++) {
            $res = $ctrl->create($sender, $data, $csrf->token());
            $this->assertSame(302, $res->status());
        }
        $res = $ctrl->create($sender, $data, $csrf->token());
        $this->assertSame(429, $res->status());
    }
}
```

> Remove the bogus `use App\Core\IcsThrowawayHelper;` line — it is a typo guard; the real imports are the ones below it.

- [ ] **Step 6: Run to verify it fails** — Run: `vendor/bin/phpunit --filter InviteRateLimitTest`
Expected: FAIL — `InviteController::__construct()` arg count / `RateLimiter` not wired.

- [ ] **Step 7: Wire limits into `InviteController`** — add `use App\Security\RateLimiter;`, a trailing constructor param `private RateLimiter $limits,`, and in `create()` after the email validation passes and before `$this->invites->create([...])`, insert:

```php
        $okSender = $this->limits->hit('invites_per_sender', (string) $userId, 20, 86400);
        $okEmail  = $this->limits->hit('invites_per_email', strtolower($email), 3, 86400);
        if (!$okSender || !$okEmail) {
            return $this->renderForm('You have sent too many invites for now. Please try again later.', $input, 429);
        }
```

- [ ] **Step 8: Run both tests + full suite** — Run: `vendor/bin/phpunit --filter "RateLimiterTest|InviteRateLimitTest"` then `vendor/bin/phpunit`
Expected: green. (Update `InviteControllerTest` to pass a `RateLimiter` — `new RateLimiter($this->pdo(), $this->clock)` — as the trailing constructor arg; fix arity failures until the whole suite passes.)

- [ ] **Step 9: Commit**

```bash
git add app/Security/RateLimiter.php app/Invite/InviteController.php \
        tests/Security/RateLimiterTest.php tests/Invite/InviteRateLimitTest.php tests/Invite/InviteControllerTest.php
git commit -m "feat(security): rate limiter + invite send limits"
```

---

### Task 3: BlockRepo + public block/report flow + email link

**Files:**
- Create: `app/Security/BlockRepo.php`
- Create: `app/Admin/BlockController.php`
- Create: `templates/respond/blocked.php`
- Test: `tests/Security/BlockRepoTest.php`
- Test: `tests/Security/BlockFlowTest.php`
- Modify: `app/Invite/InviteController.php` (block check on create), `app/Mail/Postman.php` + `templates/email/invite.php` (block link), `config/routes.php`, `public/index.php`.

**Interfaces:**
- `App\Security\BlockRepo` — `__construct(\PDO $pdo, Clock $clock)`, `block(int $senderId, string $crushEmail, ?string $reason = null): void` (idempotent), `isBlocked(int $senderId, string $crushEmail): bool`, `recent(int $limit = 50): array`.
- `App\Admin\BlockController` — `__construct(View $view, InviteRepo $invites, BlockRepo $blocks)`, `report(string $token): Response` — looks up the invite by token, blocks `sender_id`→`crush_email`, marks the invite `blocked`, renders `respond/blocked`. Public, no auth.
- `InviteController::create` checks `BlockRepo::isBlocked` (after rate limit) and refuses with a friendly message if blocked.
- `Postman::sendInvite` adds an `unsubscribe` link (`{appUrl}/unsubscribe/{token}`) passed to the invite template.

- [ ] **Step 1: Write the failing BlockRepo test** — `tests/Security/BlockRepoTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Security;

use App\Security\BlockRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class BlockRepoTest extends DatabaseTestCase
{
    public function test_block_is_idempotent_and_queryable(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $repo = new BlockRepo($this->pdo(), $clock);

        $this->assertFalse($repo->isBlocked(1, 'c@x.test'));
        $repo->block(1, 'c@x.test', 'reported');
        $repo->block(1, 'c@x.test', 'reported'); // idempotent, no error
        $this->assertTrue($repo->isBlocked(1, 'c@x.test'));
        $this->assertFalse($repo->isBlocked(2, 'c@x.test'));
        $this->assertCount(1, $repo->recent());
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter BlockRepoTest`
Expected: FAIL — `Class "App\Security\BlockRepo" not found`.

- [ ] **Step 3: Write `app/Security/BlockRepo.php`**

```php
<?php
declare(strict_types=1);

namespace App\Security;

use App\Core\Clock;

final class BlockRepo
{
    public function __construct(private \PDO $pdo, private Clock $clock) {}

    public function block(int $senderId, string $crushEmail, ?string $reason = null): void
    {
        $this->pdo->prepare(
            'INSERT INTO blocks (sender_id, crush_email, reason, created_at) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE reason = VALUES(reason)'
        )->execute([$senderId, strtolower($crushEmail), $reason, $this->clock->now()->format('Y-m-d H:i:s')]);
    }

    public function isBlocked(int $senderId, string $crushEmail): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM blocks WHERE sender_id = ? AND crush_email = ?');
        $stmt->execute([$senderId, strtolower($crushEmail)]);
        return $stmt->fetchColumn() !== false;
    }

    /** @return array<int,array> */
    public function recent(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM blocks ORDER BY created_at DESC, id DESC LIMIT ?');
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
```

- [ ] **Step 4: Run to verify it passes** — Run: `vendor/bin/phpunit --filter BlockRepoTest`
Expected: PASS.

- [ ] **Step 5: Write the block-flow test** — `tests/Security/BlockFlowTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Security;

use App\Admin\BlockController;
use App\Auth\UserRepo;
use App\Core\View;
use App\Invite\InviteRepo;
use App\Invite\InviteState;
use App\Security\BlockRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class BlockFlowTest extends DatabaseTestCase
{
    public function test_report_link_blocks_sender_and_marks_invite(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $invites = new InviteRepo($this->pdo(), $clock);
        $blocks = new BlockRepo($this->pdo(), $clock);
        $sender = (new UserRepo($this->pdo(), $clock))->create('s@x.test', 'Sue', 'magic')['id'];
        $invite = $invites->create([
            'sender_id' => $sender, 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => true, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'expires_at' => '2026-02-01 00:00:00',
        ]);

        $ctrl = new BlockController($view, $invites, $blocks);
        $res = $ctrl->report($invite['public_token']);

        $this->assertSame(200, $res->status());
        $this->assertTrue($blocks->isBlocked($sender, 'c@x.test'));
        $this->assertSame(InviteState::BLOCKED, $invites->findByToken($invite['public_token'])['status']);
    }

    public function test_unknown_token_is_404(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $ctrl = new BlockController(
            new View(\dirname(__DIR__, 2) . '/templates'),
            new InviteRepo($this->pdo(), $clock),
            new BlockRepo($this->pdo(), $clock)
        );
        $this->assertSame(404, $ctrl->report('nope')->status());
    }
}
```

- [ ] **Step 6: Run to verify it fails** — Run: `vendor/bin/phpunit --filter BlockFlowTest`
Expected: FAIL — `Class "App\Admin\BlockController" not found`.

- [ ] **Step 7: Write `app/Admin/BlockController.php`**

```php
<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Response;
use App\Core\View;
use App\Invite\InviteRepo;
use App\Invite\InviteState;
use App\Security\BlockRepo;

final class BlockController
{
    public function __construct(
        private View $view,
        private InviteRepo $invites,
        private BlockRepo $blocks,
    ) {}

    public function report(string $token): Response
    {
        $invite = $this->invites->findByToken($token);
        if ($invite === null) {
            return Response::html($this->view->render('respond/blocked', [
                'title' => 'Not found', 'known' => false,
            ]), 404);
        }
        $this->blocks->block((int) $invite['sender_id'], (string) $invite['crush_email'], 'reported');
        $this->invites->updateStatus((int) $invite['id'], InviteState::BLOCKED);

        return Response::html($this->view->render('respond/blocked', [
            'title' => 'Done', 'known' => true,
        ]));
    }
}
```

- [ ] **Step 8: Write `templates/respond/blocked.php`**

```php
<?php $known = $known ?? true; ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($title ?? 'Crush') ?></title>
<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/themes/bubblegum.css"></head>
<body class="theme-bubblegum"><main class="card">
  <?php if ($known): ?>
    <h1 style="text-wrap:balance;">You won't hear from them again</h1>
    <p class="subtitle">We've stopped this sender from inviting you. Take care.</p>
  <?php else: ?>
    <p class="subtitle">This link is no longer valid.</p>
  <?php endif; ?>
</main></body></html>
```

- [ ] **Step 9: Run the block-flow test** — Run: `vendor/bin/phpunit --filter BlockFlowTest`
Expected: PASS.

- [ ] **Step 10: Enforce blocks in `InviteController::create`** — add `use App\Security\BlockRepo;`, a trailing constructor param `private BlockRepo $blocks,`, and after the rate-limit check (and before create) insert:

```php
        if ($this->blocks->isBlocked($userId, $email)) {
            return $this->renderForm('This person has asked not to receive invites.', $input, 403);
        }
```

- [ ] **Step 11: Add the unsubscribe link to the invite email** — in `app/Mail/Postman.php` `sendInvite`, pass `'unsubscribe' => rtrim($this->appUrl, '/') . '/unsubscribe/' . $invite['public_token']` into the render, and in `templates/email/invite.php` add at the bottom:

```php
  <p style="color:#bbb;font-size:11px;margin-top:18px;">Not interested? <a href="<?= $e($unsubscribe) ?>" style="color:#bbb;">Block &amp; report</a>.</p>
```

(Add `<?php $unsubscribe = $unsubscribe ?? '#'; ?>` at the top of `invite.php`.)

- [ ] **Step 12: Register the route + wire** — in `config/routes.php` add `BlockController $block` to the factory and `$router->add('GET', '/unsubscribe/{token}', static fn(string $token): Response => $block->report($token));`. In `public/index.php` build `$blockRepo = new BlockRepo($pdo, $clock);` and `$blockCtrl = new BlockController($view, $inviteRepo, $blockRepo);`, pass `$blockRepo` into `InviteController` (trailing) and `$blockCtrl` into the routes factory.

- [ ] **Step 13: Update affected tests + full suite** — `InviteControllerTest`, `InviteRateLimitTest` now need a trailing `BlockRepo`. Update them, then run `vendor/bin/phpunit` until green.

- [ ] **Step 14: Commit**

```bash
git add app/Security/BlockRepo.php app/Admin/BlockController.php templates/respond/blocked.php \
        app/Invite/InviteController.php app/Mail/Postman.php templates/email/invite.php \
        config/routes.php public/index.php tests/
git commit -m "feat(security): block/report flow + invite block enforcement"
```

---

### Task 4: Admin auth guard + settings panel + send-test

**Files:**
- Create: `app/Admin/AdminController.php`
- Create: `templates/admin/layout.php`, `templates/admin/dashboard.php`, `templates/admin/settings.php`
- Modify: `config/routes.php`, `public/index.php`
- Test: `tests/Admin/AdminAuthTest.php`, `tests/Admin/AdminSettingsTest.php`

**Interfaces:**
- `App\Admin\AdminController::__construct(View $view, Csrf $csrf, UserRepo $users, SettingsRepo $settings, ThemeRepo $themes, AbEventRepo $events, InviteRepo $invites, BlockRepo $blocks, string $appUrl)`.
- `dashboard(?int $userId): Response`, `settings(?int $userId): Response`, `saveSettings(?int $userId, array $input, string $csrf): Response`, `sendTest(?int $userId, string $csrf): Response`. Each calls a private `requireAdmin(?int): ?array` that returns the admin user or, when not an admin, signals a 403 (the method returns the 403 response).
- A non-admin (or logged-out) user gets a 403 on every admin route.
- `saveSettings` whitelists known keys and writes them via `SettingsRepo::set`. `sendTest` builds a fresh mailer from current settings (`MailerFactory::make`) and sends a test email to the admin's own address; reports success/failure on the settings page.

- [ ] **Step 1: Write `tests/Admin/AdminAuthTest.php`**

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
use App\Security\BlockRepo;
use App\Settings\SettingsRepo;
use App\Theme\AbEventRepo;
use App\Theme\ThemeRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class AdminAuthTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    private function controller(): AdminController
    {
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        return new AdminController(
            $view, new Csrf(new ArrayStore()),
            new UserRepo($this->pdo(), $this->clock),
            new SettingsRepo($this->pdo()),
            new ThemeRepo($this->pdo()),
            new AbEventRepo($this->pdo(), $this->clock),
            new InviteRepo($this->pdo(), $this->clock),
            new BlockRepo($this->pdo(), $this->clock),
            'http://localhost'
        );
    }

    public function test_logged_out_is_forbidden(): void
    {
        $this->assertSame(403, $this->controller()->dashboard(null)->status());
    }

    public function test_non_admin_is_forbidden(): void
    {
        $user = (new UserRepo($this->pdo(), new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'))))
            ->create('plain@x.test', 'Plain', 'magic');
        $this->assertSame(403, $this->controller()->dashboard($user['id'])->status());
    }

    public function test_admin_sees_dashboard(): void
    {
        $pdo = $this->pdo();
        $user = (new UserRepo($pdo, new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'))))
            ->create('admin@x.test', 'Boss', 'magic');
        $pdo->prepare('UPDATE users SET is_admin = 1 WHERE id = ?')->execute([$user['id']]);

        $res = $this->controller()->dashboard($user['id']);
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('Admin', $res->body());
    }
}
```

- [ ] **Step 2: Write `tests/Admin/AdminSettingsTest.php`**

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
use App\Security\BlockRepo;
use App\Settings\SettingsRepo;
use App\Theme\AbEventRepo;
use App\Theme\ThemeRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class AdminSettingsTest extends DatabaseTestCase
{
    private function admin(): array
    {
        $pdo = $this->pdo();
        $user = (new UserRepo($pdo, new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'))))
            ->create('admin@x.test', 'Boss', 'magic');
        $pdo->prepare('UPDATE users SET is_admin = 1 WHERE id = ?')->execute([$user['id']]);
        return $user;
    }

    private function controller(Csrf $csrf): AdminController
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        return new AdminController(
            $view, $csrf, new UserRepo($this->pdo(), $clock), new SettingsRepo($this->pdo()),
            new ThemeRepo($this->pdo()), new AbEventRepo($this->pdo(), $clock),
            new InviteRepo($this->pdo(), $clock), new BlockRepo($this->pdo(), $clock), 'http://localhost'
        );
    }

    public function test_save_settings_persists_whitelisted_keys(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $admin = $this->admin();
        $ctrl = $this->controller($csrf);

        $res = $ctrl->saveSettings($admin['id'], [
            'mail_driver' => 'resend', 'from_email' => 'love@crush.app', 'evil_key' => 'nope',
        ], $csrf->token());

        $this->assertSame(302, $res->status());
        $settings = new SettingsRepo($this->pdo());
        $this->assertSame('resend', $settings->get('mail_driver'));
        $this->assertSame('love@crush.app', $settings->get('from_email'));
        $this->assertNull($settings->get('evil_key')); // not whitelisted
    }

    public function test_save_settings_rejects_bad_csrf(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $admin = $this->admin();
        $this->assertSame(400, $this->controller($csrf)->saveSettings($admin['id'], [], 'wrong')->status());
    }
}
```

- [ ] **Step 3: Run to verify they fail** — Run: `vendor/bin/phpunit --filter "AdminAuthTest|AdminSettingsTest"`
Expected: FAIL — `Class "App\Admin\AdminController" not found`.

- [ ] **Step 4: Write `app/Admin/AdminController.php`** (dashboard + settings + send-test; moderation/themes methods added in Task 5)

```php
<?php
declare(strict_types=1);

namespace App\Admin;

use App\Auth\UserRepo;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\View;
use App\Invite\InviteRepo;
use App\Mail\Email;
use App\Mail\MailerFactory;
use App\Security\BlockRepo;
use App\Settings\SettingsRepo;
use App\Theme\AbEventRepo;
use App\Theme\ThemeRepo;

final class AdminController
{
    private const SETTING_KEYS = [
        'mail_driver', 'from_email', 'from_name',
        'resend_api_key', 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_encryption',
        'google_client_id', 'google_client_secret', 'google_redirect_uri',
        'invite_expiry_days',
    ];

    public function __construct(
        private View $view,
        private Csrf $csrf,
        private UserRepo $users,
        private SettingsRepo $settings,
        private ThemeRepo $themes,
        private AbEventRepo $events,
        private InviteRepo $invites,
        private BlockRepo $blocks,
        private string $appUrl,
    ) {}

    public function dashboard(?int $userId): Response
    {
        if (($admin = $this->requireAdmin($userId)) === null) {
            return $this->forbidden();
        }
        return $this->render('admin/dashboard', [
            'title' => 'Admin', 'blocks' => count($this->blocks->recent()),
            'driver' => $this->settings->get('mail_driver', 'php'),
        ]);
    }

    public function settings(?int $userId, ?string $flash = null): Response
    {
        if ($this->requireAdmin($userId) === null) {
            return $this->forbidden();
        }
        return $this->render('admin/settings', [
            'title' => 'Settings', 'csrf' => $this->csrf->token(),
            'values' => $this->settings->all(), 'flash' => $flash, 'keys' => self::SETTING_KEYS,
        ]);
    }

    public function saveSettings(?int $userId, array $input, string $csrf): Response
    {
        if ($this->requireAdmin($userId) === null) {
            return $this->forbidden();
        }
        if (!$this->csrf->validate($csrf)) {
            return $this->settings($userId, 'Session expired, please retry.')->withStatus(400);
        }
        foreach (self::SETTING_KEYS as $key) {
            if (array_key_exists($key, $input) && is_string($input[$key])) {
                $this->settings->set($key, trim($input[$key]));
            }
        }
        return (new Response('', 302))->withHeader('Location', '/admin/settings');
    }

    public function sendTest(?int $userId, string $csrf): Response
    {
        if (($admin = $this->requireAdmin($userId)) === null) {
            return $this->forbidden();
        }
        if (!$this->csrf->validate($csrf)) {
            return $this->settings($userId, 'Session expired, please retry.')->withStatus(400);
        }
        try {
            MailerFactory::make($this->settings)->send(new Email(
                (string) $admin['email'],
                'Crush test email',
                '<p>This is a Crush test email. Your mail settings work.</p>'
            ));
            $flash = 'Test email sent to ' . $admin['email'] . '.';
        } catch (\Throwable $e) {
            $flash = 'Test failed: ' . $e->getMessage();
        }
        return $this->settings($userId, $flash);
    }

    private function requireAdmin(?int $userId): ?array
    {
        if ($userId === null) {
            return null;
        }
        $user = $this->users->findById($userId);
        return ($user !== null && (int) $user['is_admin'] === 1) ? $user : null;
    }

    private function forbidden(): Response
    {
        return Response::html($this->view->render('admin/dashboard', [
            'title' => 'Forbidden', 'forbidden' => true,
        ]), 403);
    }

    private function render(string $tpl, array $data): Response
    {
        return Response::html($this->view->render($tpl, $data));
    }
}
```

- [ ] **Step 5: Write `templates/admin/layout.php`**

```php
<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($title ?? 'Admin') ?></title>
<style>
  body{font-family:system-ui,sans-serif;margin:0;background:#f6f6fb;color:#222;-webkit-font-smoothing:antialiased}
  .wrap{max-width:760px;margin:0 auto;padding:24px}
  nav a{margin-right:14px;color:#7a3cff;text-decoration:none;font-weight:600}
  .panel{background:#fff;border-radius:14px;padding:20px;box-shadow:0 1px 2px rgba(0,0,0,.05),0 8px 20px rgba(0,0,0,.06);margin-top:16px}
  label{display:block;font-size:13px;font-weight:600;color:#555;margin-top:10px}
  input,select{width:100%;padding:9px;border:1px solid #e3e3ef;border-radius:10px;font-size:14px}
  button{margin-top:14px;padding:10px 16px;border:0;border-radius:10px;background:#7a3cff;color:#fff;font-weight:700;cursor:pointer}
  table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:8px;border-bottom:1px solid #eee;font-size:14px}
  .flash{background:#eef9f0;border:1px solid #cce9d4;color:#246b39;padding:10px;border-radius:10px;margin-top:12px}
</style></head>
<body><div class="wrap">
  <nav><a href="/admin">Dashboard</a><a href="/admin/settings">Settings</a><a href="/admin/themes">Themes</a><a href="/admin/moderation">Moderation</a></nav>
  <?= $body ?>
</div></body></html>
```

- [ ] **Step 6: Write `templates/admin/dashboard.php`**

```php
<?php $forbidden = $forbidden ?? false; ?>
<?php $content = function () use ($e, $forbidden, $title) {
  if ($forbidden) { return '<div class="panel"><h1>Forbidden</h1><p>You need admin access.</p></div>'; }
  ob_start(); ?>
  <div class="panel">
    <h1>Admin dashboard</h1>
    <p>Active mail driver: <strong><?= $e($GLOBALS['__driver'] ?? '') ?></strong></p>
  </div>
  <?php return ob_get_clean(); };
$GLOBALS['__driver'] = $driver ?? '';
$body = $content();
include __DIR__ . '/layout.php';
```

> Simpler: pass `$driver`/`$blocks` and reference them directly in the template instead of `$GLOBALS`. Rewrite the dashboard body to use the closure's `use (...)` list (`$driver`, `$blocks`) — avoid `$GLOBALS`. The engineer should implement it cleanly with `use ($e, $driver, $blocks, $forbidden)`.

- [ ] **Step 7: Write `templates/admin/settings.php`**

```php
<?php $flash = $flash ?? null; $values = $values ?? []; $keys = $keys ?? []; ?>
<?php $content = function () use ($e, $flash, $values, $keys, $csrf) {
  ob_start(); ?>
  <div class="panel">
    <h1>Settings</h1>
    <?php if ($flash): ?><div class="flash"><?= $e($flash) ?></div><?php endif; ?>
    <form method="post" action="/admin/settings">
      <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
      <?php foreach ($keys as $key): ?>
        <label><?= $e($key) ?>
          <input type="text" name="<?= $e($key) ?>" value="<?= $e($values[$key] ?? '') ?>">
        </label>
      <?php endforeach; ?>
      <button type="submit">Save settings</button>
    </form>
    <form method="post" action="/admin/settings/test" style="margin-top:8px">
      <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
      <button type="submit">Send test email</button>
    </form>
  </div>
  <?php return ob_get_clean(); };
$body = $content();
include __DIR__ . '/layout.php';
```

- [ ] **Step 8: Run the admin tests** — Run: `vendor/bin/phpunit --filter "AdminAuthTest|AdminSettingsTest"`
Expected: PASS. (Fix the dashboard template to pass `$driver`/`$blocks` via the closure `use` list, not `$GLOBALS`.)

- [ ] **Step 9: Register routes + wire** — in `config/routes.php` add `AdminController $admin` to the factory and:

```php
    $router->add('GET',  '/admin',               static fn(): Response => $admin->dashboard($currentUserId()));
    $router->add('GET',  '/admin/settings',      static fn(): Response => $admin->settings($currentUserId()));
    $router->add('POST', '/admin/settings',      static fn(): Response => $admin->saveSettings($currentUserId(), $_POST, (static fn($v) => is_string($v) ? $v : '')($_POST['csrf'] ?? '')));
    $router->add('POST', '/admin/settings/test', static fn(): Response => $admin->sendTest($currentUserId(), (static fn($v) => is_string($v) ? $v : '')($_POST['csrf'] ?? '')));
```

In `public/index.php` build `$adminCtrl = new AdminController($view, $csrf, $users, $settings, $themeRepo, $abEvents, $inviteRepo, $blockRepo, $appUrl);` and pass it into the routes factory.

- [ ] **Step 10: Run the full suite** — Run: `vendor/bin/phpunit`
Expected: all green.

- [ ] **Step 11: Commit**

```bash
git add app/Admin/AdminController.php templates/admin/ config/routes.php public/index.php tests/Admin/
git commit -m "feat(admin): auth guard + settings panel + send-test email"
```

---

### Task 5: Admin themes funnel + weights + moderation

**Files:**
- Modify: `app/Theme/ThemeRepo.php` (admin methods)
- Modify: `app/Admin/AdminController.php` (themes + moderation)
- Create: `templates/admin/themes.php`, `templates/admin/moderation.php`
- Modify: `config/routes.php`
- Test: `tests/Admin/AdminThemesTest.php`, `tests/Admin/AdminModerationTest.php`

**Interfaces:**
- `ThemeRepo` gains: `all(): array` (incl inactive), `setActive(string $key, bool $active): void`, `setWeight(string $key, int $weight): void`.
- `AdminController::themes(?int $userId): Response` — renders each theme with its funnel (`opened`/`completed` counts from `AbEventRepo` + conversion %) and a weight/active form.
- `AdminController::saveThemes(?int $userId, array $input, string $csrf): Response` — updates weights (ints) + active flags.
- `AdminController::moderation(?int $userId, ?string $search = null): Response` — recent invites (optionally filtered by `crush_email`) + recent blocks.
- `AdminController::blockFromAdmin(?int $userId, array $input, string $csrf): Response` — admin blocks a `sender_id`+`crush_email`.

- [ ] **Step 1: Write `tests/Admin/AdminThemesTest.php`**

```php
<?php
declare(strict_types=1);

namespace Tests\Admin;

use App\Theme\AbEventRepo;
use App\Theme\ThemeRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class AdminThemesTest extends DatabaseTestCase
{
    public function test_theme_repo_admin_methods(): void
    {
        $repo = new ThemeRepo($this->pdo());
        $this->assertCount(3, $repo->all());

        $repo->setWeight('midnight', 5);
        $repo->setActive('love-letter', false);

        $byKey = [];
        foreach ($repo->all() as $t) { $byKey[$t['key']] = $t; }
        $this->assertSame(5, $byKey['midnight']['weight']);
        $this->assertSame(0, $byKey['love-letter']['is_active']);
        // listActive now excludes the deactivated theme
        $this->assertCount(2, $repo->listActive());
    }

    public function test_funnel_counts(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $events = new AbEventRepo($this->pdo(), $clock);
        $events->log(1, 'midnight', 'opened');
        $events->log(2, 'midnight', 'opened');
        $events->log(1, 'midnight', 'completed');
        $this->assertSame(2, $events->count('midnight', 'opened'));
        $this->assertSame(1, $events->count('midnight', 'completed'));
    }
}
```

- [ ] **Step 2: Write `tests/Admin/AdminModerationTest.php`**

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
use App\Security\BlockRepo;
use App\Settings\SettingsRepo;
use App\Theme\AbEventRepo;
use App\Theme\ThemeRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class AdminModerationTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    private function adminId(): int
    {
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $u = (new UserRepo($this->pdo(), $this->clock))->create('admin@x.test', 'Boss', 'magic');
        $this->pdo()->prepare('UPDATE users SET is_admin = 1 WHERE id = ?')->execute([$u['id']]);
        return $u['id'];
    }

    private function controller(Csrf $csrf): AdminController
    {
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        return new AdminController(
            $view, $csrf, new UserRepo($this->pdo(), $this->clock), new SettingsRepo($this->pdo()),
            new ThemeRepo($this->pdo()), new AbEventRepo($this->pdo(), $this->clock),
            new InviteRepo($this->pdo(), $this->clock), new BlockRepo($this->pdo(), $this->clock), 'http://localhost'
        );
    }

    public function test_moderation_lists_invites(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $adminId = $this->adminId();
        (new InviteRepo($this->pdo(), $this->clock))->create([
            'sender_id' => $adminId, 'crush_email' => 'target@x.test', 'crush_name' => 'T',
            'is_anonymous' => false, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'expires_at' => '2026-02-01 00:00:00',
        ]);

        $res = $this->controller($csrf)->moderation($adminId);
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('target@x.test', $res->body());
    }

    public function test_admin_block_records_block(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $adminId = $this->adminId();
        $res = $this->controller($csrf)->blockFromAdmin($adminId, [
            'sender_id' => (string) $adminId, 'crush_email' => 'x@x.test',
        ], $csrf->token());
        $this->assertSame(302, $res->status());
        $this->assertTrue((new BlockRepo($this->pdo(), $this->clock))->isBlocked($adminId, 'x@x.test'));
    }
}
```

- [ ] **Step 3: Run to verify they fail** — Run: `vendor/bin/phpunit --filter "AdminThemesTest|AdminModerationTest"`
Expected: FAIL (missing `ThemeRepo::all`, `AdminController::moderation`, etc.).

- [ ] **Step 4: Add admin methods to `app/Theme/ThemeRepo.php`**

```php
    /** @return array<int,array> */
    public function all(): array
    {
        $rows = $this->pdo->query('SELECT `key`, name, is_active, weight FROM themes ORDER BY `key` ASC')->fetchAll();
        return array_map(static function (array $r): array {
            $r['is_active'] = (int) $r['is_active'];
            $r['weight'] = (int) $r['weight'];
            return $r;
        }, $rows);
    }

    public function setActive(string $key, bool $active): void
    {
        $this->pdo->prepare('UPDATE themes SET is_active = ? WHERE `key` = ?')->execute([$active ? 1 : 0, $key]);
    }

    public function setWeight(string $key, int $weight): void
    {
        $this->pdo->prepare('UPDATE themes SET weight = ? WHERE `key` = ?')->execute([max(0, $weight), $key]);
    }
```

- [ ] **Step 5: Add `themes`, `saveThemes`, `moderation`, `blockFromAdmin` to `AdminController`**

```php
    public function themes(?int $userId): Response
    {
        if ($this->requireAdmin($userId) === null) {
            return $this->forbidden();
        }
        $rows = [];
        foreach ($this->themes->all() as $t) {
            $opened = $this->events->count($t['key'], 'opened');
            $done   = $this->events->count($t['key'], 'completed');
            $rows[] = $t + [
                'opened' => $opened, 'completed' => $done,
                'rate' => $opened > 0 ? round($done / $opened * 100, 1) : 0.0,
            ];
        }
        return $this->render('admin/themes', [
            'title' => 'Themes', 'csrf' => $this->csrf->token(), 'themes' => $rows,
        ]);
    }

    public function saveThemes(?int $userId, array $input, string $csrf): Response
    {
        if ($this->requireAdmin($userId) === null) {
            return $this->forbidden();
        }
        if (!$this->csrf->validate($csrf)) {
            return $this->themes($userId)->withStatus(400);
        }
        $weights = (array) ($input['weight'] ?? []);
        $active  = (array) ($input['active'] ?? []);
        foreach ($this->themes->all() as $t) {
            $key = $t['key'];
            if (isset($weights[$key]) && is_numeric($weights[$key])) {
                $this->themes->setWeight($key, (int) $weights[$key]);
            }
            $this->themes->setActive($key, isset($active[$key]));
        }
        return (new Response('', 302))->withHeader('Location', '/admin/themes');
    }

    public function moderation(?int $userId, ?string $search = null): Response
    {
        if ($this->requireAdmin($userId) === null) {
            return $this->forbidden();
        }
        $invites = $search !== null && $search !== ''
            ? $this->invites->searchByCrushEmail($search)
            : $this->invites->recent();
        return $this->render('admin/moderation', [
            'title' => 'Moderation', 'csrf' => $this->csrf->token(),
            'invites' => $invites, 'blocks' => $this->blocks->recent(), 'search' => $search,
        ]);
    }

    public function blockFromAdmin(?int $userId, array $input, string $csrf): Response
    {
        if ($this->requireAdmin($userId) === null) {
            return $this->forbidden();
        }
        if ($this->csrf->validate($csrf)) {
            $sid = (int) ($input['sender_id'] ?? 0);
            $email = trim((string) ($input['crush_email'] ?? ''));
            if ($sid > 0 && $email !== '') {
                $this->blocks->block($sid, $email, 'admin');
            }
        }
        return (new Response('', 302))->withHeader('Location', '/admin/moderation');
    }
```

- [ ] **Step 6: Add `recent` + `searchByCrushEmail` to `app/Invite/InviteRepo.php`**

```php
    /** @return array<int,array> */
    public function recent(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM invites ORDER BY created_at DESC, id DESC LIMIT ?');
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'cast'], $stmt->fetchAll());
    }

    /** @return array<int,array> */
    public function searchByCrushEmail(string $email): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM invites WHERE crush_email LIKE ? ORDER BY created_at DESC, id DESC LIMIT 50');
        $stmt->execute(['%' . $email . '%']);
        return array_map([$this, 'cast'], $stmt->fetchAll());
    }
```

- [ ] **Step 7: Write `templates/admin/themes.php`**

```php
<?php $themes = $themes ?? []; ?>
<?php $content = function () use ($e, $themes, $csrf) {
  ob_start(); ?>
  <div class="panel">
    <h1>Themes &amp; A/B funnel</h1>
    <form method="post" action="/admin/themes">
      <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
      <table>
        <tr><th>Theme</th><th>Opened</th><th>Completed</th><th>Rate</th><th>Weight</th><th>Active</th></tr>
        <?php foreach ($themes as $t): ?>
          <tr>
            <td><?= $e($t['name']) ?></td>
            <td><?= $e((string) $t['opened']) ?></td>
            <td><?= $e((string) $t['completed']) ?></td>
            <td><?= $e((string) $t['rate']) ?>%</td>
            <td><input type="number" name="weight[<?= $e($t['key']) ?>]" value="<?= $e((string) $t['weight']) ?>" min="0" style="width:70px"></td>
            <td><input type="checkbox" name="active[<?= $e($t['key']) ?>]" <?= $t['is_active'] ? 'checked' : '' ?>></td>
          </tr>
        <?php endforeach; ?>
      </table>
      <button type="submit">Save themes</button>
    </form>
  </div>
  <?php return ob_get_clean(); };
$body = $content();
include __DIR__ . '/layout.php';
```

- [ ] **Step 8: Write `templates/admin/moderation.php`**

```php
<?php $invites = $invites ?? []; $blocks = $blocks ?? []; $search = $search ?? ''; ?>
<?php $content = function () use ($e, $invites, $blocks, $search, $csrf) {
  ob_start(); ?>
  <div class="panel">
    <h1>Moderation</h1>
    <form method="get" action="/admin/moderation">
      <label>Search by crush email <input type="text" name="q" value="<?= $e((string) $search) ?>"></label>
      <button type="submit">Search</button>
    </form>
    <table>
      <tr><th>Crush email</th><th>Status</th><th>Sender</th><th></th></tr>
      <?php foreach ($invites as $inv): ?>
        <tr>
          <td><?= $e($inv['crush_email']) ?></td>
          <td><?= $e($inv['status']) ?></td>
          <td><?= $e((string) $inv['sender_id']) ?></td>
          <td>
            <form method="post" action="/admin/block" style="margin:0">
              <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
              <input type="hidden" name="sender_id" value="<?= $e((string) $inv['sender_id']) ?>">
              <input type="hidden" name="crush_email" value="<?= $e($inv['crush_email']) ?>">
              <button type="submit">Block</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
    <h2>Recent blocks</h2>
    <table>
      <tr><th>Sender</th><th>Crush email</th><th>Reason</th></tr>
      <?php foreach ($blocks as $b): ?>
        <tr><td><?= $e((string) $b['sender_id']) ?></td><td><?= $e($b['crush_email']) ?></td><td><?= $e((string) ($b['reason'] ?? '')) ?></td></tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php return ob_get_clean(); };
$body = $content();
include __DIR__ . '/layout.php';
```

- [ ] **Step 9: Run admin tests** — Run: `vendor/bin/phpunit --filter "AdminThemesTest|AdminModerationTest"`
Expected: PASS.

- [ ] **Step 10: Register routes** — in `config/routes.php` add:

```php
    $router->add('GET',  '/admin/themes',     static fn(): Response => $admin->themes($currentUserId()));
    $router->add('POST', '/admin/themes',     static fn(): Response => $admin->saveThemes($currentUserId(), $_POST, (static fn($v) => is_string($v) ? $v : '')($_POST['csrf'] ?? '')));
    $router->add('GET',  '/admin/moderation', static fn(): Response => $admin->moderation($currentUserId(), (static fn($v) => is_string($v) ? $v : null)($_GET['q'] ?? null)));
    $router->add('POST', '/admin/block',      static fn(): Response => $admin->blockFromAdmin($currentUserId(), $_POST, (static fn($v) => is_string($v) ? $v : '')($_POST['csrf'] ?? '')));
```

- [ ] **Step 11: Run the full suite** — Run: `vendor/bin/phpunit`
Expected: all green.

- [ ] **Step 12: Manual check** — make an admin and load the panel:

```bash
DB_DSN="mysql:host=127.0.0.1;dbname=crush_dev;charset=utf8mb4" DB_USER=root DB_PASS= APP_URL="http://127.0.0.1:8080" php -S 127.0.0.1:8080 -t public >/dev/null 2>&1 &
sleep 1
curl -s -o /dev/null -w '/admin (logged out): %{http_code}\n' 127.0.0.1:8080/admin
kill %1
```
Expected: `/admin (logged out): 403`.

- [ ] **Step 13: Commit**

```bash
git add app/Theme/ThemeRepo.php app/Admin/AdminController.php app/Invite/InviteRepo.php \
        templates/admin/themes.php templates/admin/moderation.php config/routes.php tests/Admin/
git commit -m "feat(admin): theme funnel + weights + moderation"
```

---

## Self-Review

**1. Spec coverage:** Admin panel: mailer config + send-test (spec §10,12) — Task 4; theme A/B funnel + weights toggle (§11,12) — Task 5; moderation list/search + block (§12) — Task 5; settings store (§4,12) — Task 4. `is_admin` gate (§12) — Task 4 `requireAdmin`. Rate limits per-sender/per-email (§14) — Task 2. Block/report one-click from the crush email, halting further invites (§14) — Task 3. `rate_limits`/`blocks` tables (§4) — Task 1. Make-admin tooling — Task 1. Icons only; CSRF on all admin/abuse POSTs; prepared statements throughout.

**2. Placeholder scan:** No "TBD". The dashboard template note (avoid `$GLOBALS`) is an explicit instruction to implement cleanly, not a placeholder. `bin/make-admin.php` is a real, working CLI.

**3. Type consistency:** `RateLimiter::hit(string,string,int,int): bool`; `BlockRepo::block/isBlocked/recent`; `BlockController::report(string): Response`; `ThemeRepo::all/setActive/setWeight`; `InviteRepo::recent/searchByCrushEmail`; `AdminController` methods take `?int $userId` and return `Response`, gated by `requireAdmin(?int): ?array`. Controllers gain trailing `RateLimiter`/`BlockRepo` params; `public/index.php` builds and injects `SettingsRepo`, `ThemeRepo`, `AbEventRepo`, `RateLimiter`, `BlockRepo`, `AdminController`, `BlockController`, all matched to constructors and the routes factory. Consumes `UserRepo`, `SettingsRepo`, `ThemeRepo`, `AbEventRepo`, `InviteRepo`, `MailerFactory`, `Csrf`, `View`, `Clock` from Plans 1–6.
