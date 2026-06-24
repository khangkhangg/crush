# Crush — Plan 4: Crush Respond Flow + Themes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A crush opens the secure link (no signup), sees a beautiful themed invite, picks a date & time, a meal vibe + wish, contact info, and a pickup location, and submits. The system A/B-assigns one of three romantic themes, tracks the funnel, transitions the invite state, and shows a themed confirmation (revealing the sender only when allowed).

**Architecture:** New `app/Theme` (`ThemeRepo`, `AbEventRepo`, `ABAssigner`) and `app/Respond` (`RespondController`). One respond template renders under a `theme-{key}` body class; three CSS files (`love-letter`, `bubblegum`, `midnight`) override a shared CSS-variable contract in `base.css`, which also carries the make-interfaces-feel-better motion layer. Pickup is stored raw here; Plan 5 enriches it.

**Tech Stack:** PHP 8.1+, PDO MySQL, PHPUnit 10. No new Composer dependencies.

## Global Constraints

- PHP floor 8.1. PSR-4. No new Composer packages.
- **Icons only — never emojis** anywhere (use the inline SVG sprite).
- All HTML escaped via `App\Core\e()` / `View`. POST `/i/{token}` validates CSRF. Crush routes require **no** auth.
- Anonymity: never expose the sender's identity/email in the crush view unless `is_anonymous = 0`, or (`is_anonymous = 1 AND reveal_on_response = 1`) on the post-submit confirmation only.
- Prepared statements only. Apply the make-interfaces-feel-better principles in CSS (staggered reveal, `scale(0.96)` press, layered shadows, concentric radii, specific transitions, `text-wrap: balance`, 44px hit areas).
- Integration tests use MySQL `crush_test`.

## File Structure

- `migrations/0004_themes_ab.sql` — `themes` (seeded x3), `ab_events`.
- `app/Theme/ThemeRepo.php`, `app/Theme/AbEventRepo.php`, `app/Theme/ABAssigner.php`.
- `app/Respond/RespondController.php`.
- `app/Respond/MealOptions.php` — the craving choices (single source of truth).
- `public/assets/css/base.css` + `public/assets/css/themes/{love-letter,bubblegum,midnight}.css`.
- `templates/respond/show.php`, `templates/respond/confirmed.php`, `templates/respond/closed.php`, `templates/partials/icons.php`.

---

### Task 1: themes + ab_events migration (seeded)

**Files:**
- Create: `migrations/0004_themes_ab.sql`
- Test: `tests/Theme/ThemeSchemaTest.php`

**Interfaces:**
- Consumes: `Tests\Support\DatabaseTestCase`.
- Produces: `themes` table seeded with `love-letter`, `bubblegum`, `midnight` (all active, weight 1); `ab_events` table.

- [ ] **Step 1: Write the failing test** — `tests/Theme/ThemeSchemaTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Theme;

use Tests\Support\DatabaseTestCase;

final class ThemeSchemaTest extends DatabaseTestCase
{
    public function test_themes_seeded_and_ab_events_exist(): void
    {
        $keys = array_column($this->pdo()->query('SELECT `key` FROM themes ORDER BY `key`')->fetchAll(), 'key');
        $this->assertSame(['bubblegum', 'love-letter', 'midnight'], $keys);

        $active = (int) $this->pdo()->query('SELECT COUNT(*) AS c FROM themes WHERE is_active = 1')->fetch()['c'];
        $this->assertSame(3, $active);

        $cols = array_column($this->pdo()->query('SHOW COLUMNS FROM ab_events')->fetchAll(), 'Field');
        foreach (['id', 'invite_id', 'theme_key', 'event', 'created_at'] as $c) {
            $this->assertContains($c, $cols);
        }
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter ThemeSchemaTest`
Expected: FAIL — table `themes` not found.

- [ ] **Step 3: Write `migrations/0004_themes_ab.sql`**

```sql
CREATE TABLE IF NOT EXISTS themes (
  `key`     VARCHAR(32)  NOT NULL PRIMARY KEY,
  name      VARCHAR(64)  NOT NULL,
  is_active TINYINT(1)   NOT NULL DEFAULT 1,
  weight    INT          NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO themes (`key`, name, is_active, weight) VALUES
  ('love-letter', 'Love Letter', 1, 1),
  ('bubblegum',   'Bubblegum Cutecore', 1, 1),
  ('midnight',    'Midnight Crush', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

CREATE TABLE IF NOT EXISTS ab_events (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  invite_id  BIGINT UNSIGNED NOT NULL,
  theme_key  VARCHAR(32) NOT NULL,
  event      VARCHAR(16) NOT NULL,
  created_at DATETIME    NOT NULL,
  KEY idx_ab_invite (invite_id),
  KEY idx_ab_theme_event (theme_key, event)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- [ ] **Step 4: Run to verify it passes** — Run: `vendor/bin/phpunit --filter ThemeSchemaTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add migrations/0004_themes_ab.sql tests/Theme/ThemeSchemaTest.php
git commit -m "feat(theme): themes (seeded x3) + ab_events migration"
```

---

### Task 2: ThemeRepo + AbEventRepo

**Files:**
- Create: `app/Theme/ThemeRepo.php`
- Create: `app/Theme/AbEventRepo.php`
- Test: `tests/Theme/ThemeRepoTest.php`

**Interfaces:**
- Consumes: `\PDO`, `App\Core\Clock`.
- Produces:
  - `App\Theme\ThemeRepo` with `__construct(\PDO $pdo)`, `listActive(): array` (rows with `key`,`name`,`weight` cast `is_active`/`weight` to int; ordered by `key`), `exists(string $key): bool`.
  - `App\Theme\AbEventRepo` with `__construct(\PDO $pdo, Clock $clock)`, `log(int $inviteId, string $themeKey, string $event): void`, `count(string $themeKey, string $event): int`.

- [ ] **Step 1: Write the failing test** — `tests/Theme/ThemeRepoTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Theme;

use App\Theme\AbEventRepo;
use App\Theme\ThemeRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class ThemeRepoTest extends DatabaseTestCase
{
    public function test_list_active_returns_three_seeded_themes(): void
    {
        $repo = new ThemeRepo($this->pdo());
        $active = $repo->listActive();
        $this->assertCount(3, $active);
        $this->assertSame('bubblegum', $active[0]['key']);
        $this->assertSame(1, $active[0]['weight']);
        $this->assertTrue($repo->exists('midnight'));
        $this->assertFalse($repo->exists('nope'));
    }

    public function test_ab_event_log_and_count(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $events = new AbEventRepo($this->pdo(), $clock);
        $events->log(1, 'midnight', 'opened');
        $events->log(2, 'midnight', 'opened');
        $events->log(3, 'midnight', 'completed');

        $this->assertSame(2, $events->count('midnight', 'opened'));
        $this->assertSame(1, $events->count('midnight', 'completed'));
        $this->assertSame(0, $events->count('bubblegum', 'opened'));
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter ThemeRepoTest`
Expected: FAIL — `Class "App\Theme\ThemeRepo" not found`.

- [ ] **Step 3: Write `app/Theme/ThemeRepo.php`**

```php
<?php
declare(strict_types=1);

namespace App\Theme;

final class ThemeRepo
{
    public function __construct(private \PDO $pdo) {}

    /** @return array<int,array> */
    public function listActive(): array
    {
        $rows = $this->pdo->query(
            'SELECT `key`, name, is_active, weight FROM themes WHERE is_active = 1 ORDER BY `key` ASC'
        )->fetchAll();
        return array_map(static function (array $r): array {
            $r['is_active'] = (int) $r['is_active'];
            $r['weight'] = (int) $r['weight'];
            return $r;
        }, $rows);
    }

    public function exists(string $key): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM themes WHERE `key` = ?');
        $stmt->execute([$key]);
        return $stmt->fetchColumn() !== false;
    }
}
```

- [ ] **Step 4: Write `app/Theme/AbEventRepo.php`**

```php
<?php
declare(strict_types=1);

namespace App\Theme;

use App\Core\Clock;

final class AbEventRepo
{
    public function __construct(private \PDO $pdo, private Clock $clock) {}

    public function log(int $inviteId, string $themeKey, string $event): void
    {
        $this->pdo->prepare(
            'INSERT INTO ab_events (invite_id, theme_key, event, created_at) VALUES (?, ?, ?, ?)'
        )->execute([$inviteId, $themeKey, $event, $this->clock->now()->format('Y-m-d H:i:s')]);
    }

    public function count(string $themeKey, string $event): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) AS c FROM ab_events WHERE theme_key = ? AND event = ?');
        $stmt->execute([$themeKey, $event]);
        return (int) $stmt->fetch()['c'];
    }
}
```

- [ ] **Step 5: Run to verify it passes** — Run: `vendor/bin/phpunit --filter ThemeRepoTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Theme/ThemeRepo.php app/Theme/AbEventRepo.php tests/Theme/ThemeRepoTest.php
git commit -m "feat(theme): ThemeRepo + AbEventRepo"
```

---

### Task 3: ABAssigner

**Files:**
- Create: `app/Theme/ABAssigner.php`
- Test: `tests/Theme/ABAssignerTest.php`

**Interfaces:**
- Consumes: `App\Theme\ThemeRepo`, `App\Invite\InviteRepo`.
- Produces: `App\Theme\ABAssigner` with:
  - `__construct(ThemeRepo $themes, InviteRepo $invites, ?\Closure $randInt = null)` — `$randInt` is `fn(int $maxInclusive): int`, default `random_int(0, $max)`.
  - `assignTo(array $invite): string` — if `theme_key` already set and valid, returns it; otherwise weighted-picks an active theme, pins it via `InviteRepo::setTheme`, returns the chosen key.

- [ ] **Step 1: Write the failing test** — `tests/Theme/ABAssignerTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Theme;

use App\Auth\UserRepo;
use App\Invite\InviteRepo;
use App\Theme\ABAssigner;
use App\Theme\ThemeRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class ABAssignerTest extends DatabaseTestCase
{
    private function invite(?string $themeKey = null): array
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $sender = (new UserRepo($this->pdo(), $clock))->create('s@x.test', 'Sue', 'magic')['id'];
        return (new InviteRepo($this->pdo(), $clock))->create([
            'sender_id' => $sender, 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => false, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'theme_key' => $themeKey, 'expires_at' => '2026-02-01 00:00:00',
        ]);
    }

    public function test_assigns_and_pins_when_unset(): void
    {
        $invites = new InviteRepo($this->pdo(), new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z')));
        // randInt(2) => 0 lands on the first active theme (bubblegum, weights all 1).
        $assigner = new ABAssigner(new ThemeRepo($this->pdo()), $invites, fn(int $max) => 0);

        $invite = $this->invite(null);
        $key = $assigner->assignTo($invite);
        $this->assertSame('bubblegum', $key);
        $this->assertSame('bubblegum', $invites->findById($invite['id'])['theme_key']);
    }

    public function test_picks_last_theme_at_max_random(): void
    {
        $invites = new InviteRepo($this->pdo(), new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z')));
        // Sum of weights is 3; randInt(2) => 2 lands on the third theme (midnight).
        $assigner = new ABAssigner(new ThemeRepo($this->pdo()), $invites, fn(int $max) => 2);
        $key = $assigner->assignTo($this->invite(null));
        $this->assertSame('midnight', $key);
    }

    public function test_keeps_existing_theme(): void
    {
        $invites = new InviteRepo($this->pdo(), new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z')));
        $assigner = new ABAssigner(new ThemeRepo($this->pdo()), $invites, fn(int $max) => 0);
        $key = $assigner->assignTo($this->invite('love-letter'));
        $this->assertSame('love-letter', $key);
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter ABAssignerTest`
Expected: FAIL — `Class "App\Theme\ABAssigner" not found`.

- [ ] **Step 3: Write `app/Theme/ABAssigner.php`**

```php
<?php
declare(strict_types=1);

namespace App\Theme;

use App\Invite\InviteRepo;

final class ABAssigner
{
    /** @var \Closure(int):int */
    private \Closure $randInt;

    public function __construct(
        private ThemeRepo $themes,
        private InviteRepo $invites,
        ?\Closure $randInt = null,
    ) {
        $this->randInt = $randInt ?? static fn(int $max): int => random_int(0, $max);
    }

    public function assignTo(array $invite): string
    {
        $current = $invite['theme_key'] ?? null;
        if (is_string($current) && $current !== '' && $this->themes->exists($current)) {
            return $current;
        }

        $active = $this->themes->listActive();
        if ($active === []) {
            throw new \RuntimeException('No active themes to assign.');
        }

        $total = array_sum(array_map(static fn(array $t): int => max(1, (int) $t['weight']), $active));
        $r = ($this->randInt)($total - 1);

        $cursor = 0;
        $chosen = $active[array_key_last($active)]['key'];
        foreach ($active as $theme) {
            $cursor += max(1, (int) $theme['weight']);
            if ($r < $cursor) {
                $chosen = $theme['key'];
                break;
            }
        }

        $this->invites->setTheme((int) $invite['id'], $chosen);
        return $chosen;
    }
}
```

- [ ] **Step 4: Run to verify it passes** — Run: `vendor/bin/phpunit --filter ABAssignerTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Theme/ABAssigner.php tests/Theme/ABAssignerTest.php
git commit -m "feat(theme): weighted A/B theme assigner"
```

---

### Task 4: MealOptions + RespondController open (GET)

**Files:**
- Create: `app/Respond/MealOptions.php`
- Create: `app/Respond/RespondController.php`
- Test: `tests/Respond/RespondOpenTest.php`

**Interfaces:**
- Consumes: `App\Invite\InviteRepo`, `App\Auth\UserRepo`, `App\Theme\ABAssigner`, `App\Theme\AbEventRepo`, `App\Core\View`, `App\Core\Csrf`, `App\Core\Clock`, `App\Invite\InviteState`, `App\Invite\ResponseRepo`.
- Produces:
  - `App\Respond\MealOptions` with `const CHOICES` = ordered list of `['key' => , 'label' => , 'icon' => ]` and `static keys(): string[]`, `static isValid(string $key): bool`.
  - `App\Respond\RespondController` with `__construct(View $view, Csrf $csrf, InviteRepo $invites, ResponseRepo $responses, UserRepo $users, ABAssigner $assigner, AbEventRepo $events, Clock $clock)`, and `open(string $token): Response`:
    - 404 themed-neutral page if token unknown.
    - If invite `status` ∈ {`closed`,`expired`,`blocked`} or `expires_at` < now → render `respond/closed`.
    - Assign theme via `ABAssigner::assignTo`; if `status === sent`, transition to `opened` and log `opened` ab_event.
    - Render `respond/show` with: theme key, csrf, sender label (anonymity-aware: `'a secret admirer'` when `is_anonymous`, else sender name/email), message, meal choices, date options, `date_mode`.

- [ ] **Step 1: Write the failing test** — `tests/Respond/RespondOpenTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Respond;

use App\Auth\UserRepo;
use App\Core\ArrayStore;
use App\Core\Csrf;
use App\Core\View;
use App\Invite\InviteRepo;
use App\Invite\InviteState;
use App\Invite\ResponseRepo;
use App\Respond\RespondController;
use App\Theme\AbEventRepo;
use App\Theme\ABAssigner;
use App\Theme\ThemeRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class RespondOpenTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    private function controller(): RespondController
    {
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $invites = new InviteRepo($this->pdo(), $this->clock);
        return new RespondController(
            $view, new Csrf(new ArrayStore()), $invites,
            new ResponseRepo($this->pdo(), $this->clock),
            new UserRepo($this->pdo(), $this->clock),
            new ABAssigner(new ThemeRepo($this->pdo()), $invites, fn(int $m) => 0),
            new AbEventRepo($this->pdo(), $this->clock),
            $this->clock
        );
    }

    private function makeInvite(array $over = []): array
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $sender = (new UserRepo($this->pdo(), $clock))->create('sue@x.test', 'Sue', 'magic')['id'];
        return (new InviteRepo($this->pdo(), $clock))->create(array_merge([
            'sender_id' => $sender, 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => true, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => 'hi there', 'expires_at' => '2026-02-01 00:00:00',
        ], $over));
    }

    public function test_unknown_token_is_404(): void
    {
        $res = $this->controller()->open('nope');
        $this->assertSame(404, $res->status());
    }

    public function test_open_assigns_theme_marks_opened_and_hides_anonymous_sender(): void
    {
        $invites = new InviteRepo($this->pdo(), $this->clock ?? new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z')));
        $ctrl = $this->controller();
        $invite = $this->makeInvite();

        $res = $ctrl->open($invite['public_token']);
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('secret admirer', $res->body());
        $this->assertStringNotContainsString('sue@x.test', $res->body());

        $reloaded = (new InviteRepo($this->pdo(), $this->clock))->findByToken($invite['public_token']);
        $this->assertSame('bubblegum', $reloaded['theme_key']);
        $this->assertSame(InviteState::OPENED, $reloaded['status']);
    }

    public function test_expired_invite_renders_closed(): void
    {
        $ctrl = $this->controller();
        $invite = $this->makeInvite(['expires_at' => '2025-01-01 00:00:00']);
        $res = $ctrl->open($invite['public_token']);
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('no longer', strtolower($res->body()));
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter RespondOpenTest`
Expected: FAIL — `Class "App\Respond\MealOptions" not found`.

- [ ] **Step 3: Write `app/Respond/MealOptions.php`**

```php
<?php
declare(strict_types=1);

namespace App\Respond;

final class MealOptions
{
    public const CHOICES = [
        ['key' => 'coffee',  'label' => 'Coffee',  'icon' => 'ic-coffee'],
        ['key' => 'brunch',  'label' => 'Brunch',  'icon' => 'ic-sparkle'],
        ['key' => 'lunch',   'label' => 'Lunch',   'icon' => 'ic-utensils'],
        ['key' => 'dinner',  'label' => 'Dinner',  'icon' => 'ic-utensils'],
        ['key' => 'dessert', 'label' => 'Dessert', 'icon' => 'ic-heart'],
        ['key' => 'drinks',  'label' => 'Drinks',  'icon' => 'ic-wine'],
    ];

    /** @return string[] */
    public static function keys(): array
    {
        return array_column(self::CHOICES, 'key');
    }

    public static function isValid(string $key): bool
    {
        return in_array($key, self::keys(), true);
    }
}
```

- [ ] **Step 4: Write `app/Respond/RespondController.php`** (open() now; submit() added in Task 5)

```php
<?php
declare(strict_types=1);

namespace App\Respond;

use App\Auth\UserRepo;
use App\Core\Clock;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\View;
use App\Invite\InviteRepo;
use App\Invite\InviteState;
use App\Invite\ResponseRepo;
use App\Theme\AbEventRepo;
use App\Theme\ABAssigner;

final class RespondController
{
    public function __construct(
        private View $view,
        private Csrf $csrf,
        private InviteRepo $invites,
        private ResponseRepo $responses,
        private UserRepo $users,
        private ABAssigner $assigner,
        private AbEventRepo $events,
        private Clock $clock,
    ) {}

    public function open(string $token): Response
    {
        $invite = $this->invites->findByToken($token);
        if ($invite === null) {
            return Response::html($this->view->render('respond/closed', [
                'title' => 'Not found', 'theme' => 'bubblegum',
                'reason' => 'This invite could not be found.',
            ]), 404);
        }

        if ($this->isUnavailable($invite)) {
            return Response::html($this->view->render('respond/closed', [
                'title' => 'No longer available', 'theme' => $invite['theme_key'] ?: 'bubblegum',
                'reason' => 'This invite is no longer available.',
            ]));
        }

        $theme = $this->assigner->assignTo($invite);

        if ($invite['status'] === InviteState::SENT) {
            $this->invites->updateStatus((int) $invite['id'], InviteState::OPENED);
            $this->events->log((int) $invite['id'], $theme, 'opened');
        }

        return Response::html($this->view->render('respond/show', [
            'title'       => 'You have an invite',
            'theme'       => $theme,
            'csrf'        => $this->csrf->token(),
            'token'       => $invite['public_token'],
            'senderLabel' => $this->senderLabel($invite),
            'message'     => $invite['message'],
            'dateMode'    => $invite['date_mode'],
            'options'     => $this->invites->dateOptions((int) $invite['id']),
            'meals'       => MealOptions::CHOICES,
        ]));
    }

    private function isUnavailable(array $invite): bool
    {
        $terminal = [InviteState::CLOSED, InviteState::EXPIRED, InviteState::BLOCKED];
        if (in_array($invite['status'], $terminal, true)) {
            return true;
        }
        return $invite['expires_at'] < $this->clock->now()->format('Y-m-d H:i:s');
    }

    private function senderLabel(array $invite): string
    {
        if ((int) $invite['is_anonymous'] === 1) {
            return 'a secret admirer';
        }
        $sender = $this->users->findById((int) $invite['sender_id']);
        return $sender['name'] ?? 'someone';
    }
}
```

- [ ] **Step 5: Write `templates/partials/icons.php`** (inline SVG sprite — no emojis)

```php
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <symbol id="ic-heart" viewBox="0 0 24 24"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z" fill="currentColor"/></symbol>
  <symbol id="ic-mail" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></symbol>
  <symbol id="ic-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></symbol>
  <symbol id="ic-sparkle" viewBox="0 0 24 24"><path d="M9.94 15.5A2 2 0 0 0 8.5 14.06l-6.14-1.58a.5.5 0 0 1 0-.96L8.5 9.94A2 2 0 0 0 9.94 8.5l1.58-6.14a.5.5 0 0 1 .96 0L14.06 8.5A2 2 0 0 0 15.5 9.94l6.14 1.58a.5.5 0 0 1 0 .96L15.5 14.06a2 2 0 0 0-1.44 1.44l-1.58 6.14a.5.5 0 0 1-.96 0Z" fill="currentColor"/></symbol>
  <symbol id="ic-cal" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></symbol>
  <symbol id="ic-utensils" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2v7a2 2 0 0 0 2 2 2 2 0 0 0 2-2V2M7 2v20M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/></symbol>
  <symbol id="ic-wine" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M8 22h8M7 10h10M12 15v7M12 15a5 5 0 0 0 5-5c0-2-.5-4-1-8H8c-.5 4-1 6-1 8a5 5 0 0 0 5 5Z"/></symbol>
  <symbol id="ic-coffee" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M17 8h1a4 4 0 1 1 0 8h-1M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4ZM6 2v2M10 2v2M14 2v2"/></symbol>
</svg>
```

- [ ] **Step 6: Write a minimal `templates/respond/closed.php`** (so open() renders; full themed version refined in Task 6)

```php
<?php $reason = $reason ?? 'This invite is no longer available.'; $theme = $theme ?? 'bubblegum'; ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($title ?? 'Crush') ?></title>
<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/themes/<?= $e($theme) ?>.css"></head>
<body class="theme-<?= $e($theme) ?>"><main class="card"><p class="subtitle"><?= $e($reason) ?></p></main></body></html>
```

- [ ] **Step 7: Write a minimal `templates/respond/show.php`** (functional now; styled fully in Task 6)

```php
<?php
$message = $message ?? null; $options = $options ?? []; $meals = $meals ?? [];
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($title ?? 'Crush') ?></title>
<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/themes/<?= $e($theme) ?>.css"></head>
<body class="theme-<?= $e($theme) ?>">
<?php include __DIR__ . '/../partials/icons.php'; ?>
<main class="card invite-card">
  <p class="kicker"><?= $e($senderLabel) ?> has a crush on you</p>
  <?php if ($message): ?><p class="message"><?= $e($message) ?></p><?php endif; ?>
  <form method="post" action="/i/<?= $e($token) ?>" class="respond-form">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <label class="field">Pick a day &amp; time
      <input type="datetime-local" name="chosen_start" required>
    </label>
    <fieldset class="meals">
      <legend>What are you craving?</legend>
      <?php foreach ($meals as $m): ?>
        <label class="meal-chip">
          <input type="radio" name="meal_choice" value="<?= $e($m['key']) ?>">
          <svg class="ic"><use href="#<?= $e($m['icon']) ?>"/></svg>
          <span><?= $e($m['label']) ?></span>
        </label>
      <?php endforeach; ?>
    </fieldset>
    <label class="field">Any wish? (optional)
      <input type="text" name="meal_wish" placeholder="surprise me">
    </label>
    <label class="field">Your contact (optional)
      <input type="text" name="crush_contact" placeholder="phone or @handle">
    </label>
    <label class="field">Where should they pick you up? (optional)
      <input type="text" name="pickup_raw" placeholder="address or Google Maps link">
    </label>
    <button type="submit" class="cta">Send my answer</button>
  </form>
</main>
</body></html>
```

- [ ] **Step 8: Run the open test** — Run: `vendor/bin/phpunit --filter RespondOpenTest`
Expected: PASS (3 tests). (The minimal CSS files don't exist yet — that's fine; the test checks HTML, not styling.)

- [ ] **Step 9: Commit**

```bash
git add app/Respond/MealOptions.php app/Respond/RespondController.php \
        templates/partials/icons.php templates/respond/show.php templates/respond/closed.php \
        tests/Respond/RespondOpenTest.php
git commit -m "feat(respond): open flow (theme assign, anonymity, opened event)"
```

---

### Task 5: RespondController submit (POST)

**Files:**
- Modify: `app/Respond/RespondController.php` (add `submit`)
- Create: `templates/respond/confirmed.php` (minimal; styled in Task 6)
- Test: `tests/Respond/RespondSubmitTest.php`

**Interfaces:**
- Produces: `RespondController::submit(string $token, array $input, string $csrf): Response`:
  - 404 if token unknown; render `respond/closed` if unavailable.
  - Reject bad CSRF (400). Require a valid `chosen_start` (422 otherwise); compute `chosen_end = chosen_start + 2h`.
  - Validate `meal_choice` against `MealOptions::isValid` (drop if invalid). Store response via `ResponseRepo::store` with `chosen_start`,`chosen_end`,`meal_choice`,`meal_wish`,`crush_contact`,`pickup_raw`.
  - Transition: `instant` → `RESPONDED` then `CONFIRMED`; `confirm` → `RESPONDED` then `PENDING_SENDER`. Log `completed` ab_event.
  - Render `respond/confirmed`: reveal the sender's name/email only if `is_anonymous = 0` OR (`is_anonymous = 1 AND reveal_on_response = 1`).

- [ ] **Step 1: Write the failing test** — `tests/Respond/RespondSubmitTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Respond;

use App\Auth\UserRepo;
use App\Core\ArrayStore;
use App\Core\Csrf;
use App\Core\View;
use App\Invite\InviteRepo;
use App\Invite\InviteState;
use App\Invite\ResponseRepo;
use App\Respond\RespondController;
use App\Theme\AbEventRepo;
use App\Theme\ABAssigner;
use App\Theme\ThemeRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class RespondSubmitTest extends DatabaseTestCase
{
    private FrozenClock $clock;
    private Csrf $csrf;

    private function controller(): RespondController
    {
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $this->csrf = new Csrf(new ArrayStore());
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $invites = new InviteRepo($this->pdo(), $this->clock);
        return new RespondController(
            $view, $this->csrf, $invites,
            new ResponseRepo($this->pdo(), $this->clock),
            new UserRepo($this->pdo(), $this->clock),
            new ABAssigner(new ThemeRepo($this->pdo()), $invites, fn(int $m) => 0),
            new AbEventRepo($this->pdo(), $this->clock),
            $this->clock
        );
    }

    private function makeInvite(array $over = []): array
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $sender = (new UserRepo($this->pdo(), $clock))->create('sue@x.test', 'Sue', 'magic')['id'];
        return (new InviteRepo($this->pdo(), $clock))->create(array_merge([
            'sender_id' => $sender, 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => true, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'expires_at' => '2026-02-01 00:00:00',
        ], $over));
    }

    public function test_bad_csrf_rejected(): void
    {
        $ctrl = $this->controller();
        $invite = $this->makeInvite();
        $res = $ctrl->submit($invite['public_token'], ['chosen_start' => '2026-02-10T19:00'], 'wrong');
        $this->assertSame(400, $res->status());
    }

    public function test_missing_date_rejected(): void
    {
        $ctrl = $this->controller();
        $invite = $this->makeInvite();
        $res = $ctrl->submit($invite['public_token'], ['meal_choice' => 'sushi'], $this->csrf->token());
        $this->assertSame(422, $res->status());
    }

    public function test_instant_submit_confirms_and_stores(): void
    {
        $ctrl = $this->controller();
        $invite = $this->makeInvite(['date_mode' => 'instant', 'is_anonymous' => false]);
        $res = $ctrl->submit($invite['public_token'], [
            'chosen_start' => '2026-02-10T19:00', 'meal_choice' => 'dinner',
            'meal_wish' => 'sushi please', 'crush_contact' => '@cee', 'pickup_raw' => '1 Main St',
        ], $this->csrf->token());

        $this->assertSame(200, $res->status());
        $invites = new InviteRepo($this->pdo(), $this->clock);
        $reloaded = $invites->findByToken($invite['public_token']);
        $this->assertSame(InviteState::CONFIRMED, $reloaded['status']);

        $stored = (new ResponseRepo($this->pdo(), $this->clock))->findByInvite((int) $invite['id']);
        $this->assertSame('dinner', $stored['meal_choice']);
        $this->assertSame('1 Main St', $stored['pickup_raw']);
        // Not anonymous -> sender revealed.
        $this->assertStringContainsString('Sue', $res->body());
    }

    public function test_confirm_mode_goes_pending_and_keeps_secret(): void
    {
        $ctrl = $this->controller();
        $invite = $this->makeInvite(['date_mode' => 'confirm', 'is_anonymous' => true, 'reveal_on_response' => false]);
        $res = $ctrl->submit($invite['public_token'], [
            'chosen_start' => '2026-02-10T19:00', 'meal_choice' => 'coffee',
        ], $this->csrf->token());

        $reloaded = (new InviteRepo($this->pdo(), $this->clock))->findByToken($invite['public_token']);
        $this->assertSame(InviteState::PENDING_SENDER, $reloaded['status']);
        $this->assertStringNotContainsString('sue@x.test', $res->body());
    }

    public function test_anonymous_with_reveal_shows_sender(): void
    {
        $ctrl = $this->controller();
        $invite = $this->makeInvite(['is_anonymous' => true, 'reveal_on_response' => true]);
        $res = $ctrl->submit($invite['public_token'], [
            'chosen_start' => '2026-02-10T19:00', 'meal_choice' => 'coffee',
        ], $this->csrf->token());
        $this->assertStringContainsString('Sue', $res->body());
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter RespondSubmitTest`
Expected: FAIL — `Call to undefined method ... submit()`.

- [ ] **Step 3: Add `submit()` to `app/Respond/RespondController.php`** (insert after `open()`)

```php
    public function submit(string $token, array $input, string $csrf): Response
    {
        $invite = $this->invites->findByToken($token);
        if ($invite === null) {
            return Response::html($this->view->render('respond/closed', [
                'title' => 'Not found', 'theme' => 'bubblegum',
                'reason' => 'This invite could not be found.',
            ]), 404);
        }
        if ($this->isUnavailable($invite)) {
            return Response::html($this->view->render('respond/closed', [
                'title' => 'No longer available', 'theme' => $invite['theme_key'] ?: 'bubblegum',
                'reason' => 'This invite is no longer available.',
            ]));
        }

        $theme = $this->assigner->assignTo($invite);

        if (!$this->csrf->validate($csrf)) {
            return $this->reshow($invite, $theme, 'Your session expired. Please try again.', 400);
        }

        $start = $this->parseDate((string) ($input['chosen_start'] ?? ''));
        if ($start === null) {
            return $this->reshow($invite, $theme, 'Please pick a day and time.', 422);
        }
        $end = $start->modify('+2 hours');

        $meal = (string) ($input['meal_choice'] ?? '');
        $meal = MealOptions::isValid($meal) ? $meal : null;

        $this->responses->store((int) $invite['id'], [
            'chosen_start'  => $start->format('Y-m-d H:i:s'),
            'chosen_end'    => $end->format('Y-m-d H:i:s'),
            'meal_choice'   => $meal,
            'meal_wish'     => $this->clean($input['meal_wish'] ?? null),
            'crush_contact' => $this->clean($input['crush_contact'] ?? null),
            'pickup_raw'    => $this->clean($input['pickup_raw'] ?? null),
        ]);

        $final = $invite['date_mode'] === 'confirm' ? InviteState::PENDING_SENDER : InviteState::CONFIRMED;
        $this->invites->updateStatus((int) $invite['id'], InviteState::RESPONDED);
        $this->invites->updateStatus((int) $invite['id'], $final);
        $this->events->log((int) $invite['id'], $theme, 'completed');

        return Response::html($this->view->render('respond/confirmed', [
            'title'    => 'Your answer is in',
            'theme'    => $theme,
            'dateMode' => $invite['date_mode'],
            'reveal'   => $this->revealLabel($invite),
            'when'     => $start->format('D, M j \a\t g:i A'),
        ]));
    }

    private function parseDate(string $raw): ?\DateTimeImmutable
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        try {
            return new \DateTimeImmutable($raw);
        } catch (\Exception) {
            return null;
        }
    }

    private function clean(mixed $v): ?string
    {
        if (!is_string($v)) {
            return null;
        }
        $v = trim($v);
        return $v === '' ? null : $v;
    }

    private function revealLabel(array $invite): ?string
    {
        $anon = (int) $invite['is_anonymous'] === 1;
        $reveal = (int) $invite['reveal_on_response'] === 1;
        if ($anon && !$reveal) {
            return null;
        }
        $sender = $this->users->findById((int) $invite['sender_id']);
        return $sender['name'] ?? $sender['email'] ?? null;
    }

    private function reshow(array $invite, string $theme, string $error, int $status): Response
    {
        return Response::html($this->view->render('respond/show', [
            'title' => 'You have an invite', 'theme' => $theme,
            'csrf' => $this->csrf->token(), 'token' => $invite['public_token'],
            'senderLabel' => $this->senderLabel($invite), 'message' => $invite['message'],
            'dateMode' => $invite['date_mode'],
            'options' => $this->invites->dateOptions((int) $invite['id']),
            'meals' => MealOptions::CHOICES, 'error' => $error,
        ]), $status);
    }
```

> Also update `templates/respond/show.php` to render an optional `$error`: add `<?php $error = $error ?? null; ?>` at the top and, just inside `<main>`, `<?php if ($error): ?><p class="error" role="alert"><?= $e($error) ?></p><?php endif; ?>`.

- [ ] **Step 4: Write `templates/respond/confirmed.php`** (minimal; styled in Task 6)

```php
<?php $reveal = $reveal ?? null; ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($title ?? 'Crush') ?></title>
<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/themes/<?= $e($theme) ?>.css"></head>
<body class="theme-<?= $e($theme) ?>">
<?php include __DIR__ . '/../partials/icons.php'; ?>
<main class="card confirm-card">
  <svg class="big-ic"><use href="#ic-heart"/></svg>
  <h1>Your answer is on its way</h1>
  <p class="when">You picked <strong><?= $e($when) ?></strong>.</p>
  <?php if ($reveal): ?>
    <p class="reveal">Your secret admirer is <strong><?= $e($reveal) ?></strong>.</p>
  <?php else: ?>
    <p class="reveal">They'll be in touch soon.</p>
  <?php endif; ?>
</main>
</body></html>
```

- [ ] **Step 5: Run the submit test** — Run: `vendor/bin/phpunit --filter RespondSubmitTest`
Expected: PASS (5 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Respond/RespondController.php templates/respond/confirmed.php templates/respond/show.php \
        tests/Respond/RespondSubmitTest.php
git commit -m "feat(respond): submit flow (store, state transition, reveal)"
```

---

### Task 6: Theme CSS (base + 3) + routes + wiring + polish

**Files:**
- Create: `public/assets/css/base.css`
- Create: `public/assets/css/themes/love-letter.css`
- Create: `public/assets/css/themes/bubblegum.css`
- Create: `public/assets/css/themes/midnight.css`
- Modify: `config/routes.php` (add `/i/{token}` GET + POST)
- Modify: `public/index.php` (build `RespondController` + theme deps)
- Test: `tests/Respond/RespondRoutingTest.php`

**Interfaces:**
- Consumes: everything from Tasks 1–5.
- Produces: working `GET /i/{token}` and `POST /i/{token}` routes; three theme stylesheets sharing the `base.css` CSS-variable contract.

Apply the **make-interfaces-feel-better** skill (installed at `.agents/skills/make-interfaces-feel-better`): staggered reveal, `scale(0.96)` press, layered shadows, concentric radii, specific (non-`all`) transitions, `text-wrap: balance`, 44px hit areas, antialiased text.

- [ ] **Step 1: Write `public/assets/css/base.css`** (structure + motion; colors come from theme vars)

```css
:root{
  --bg:#fff; --surface:#fff; --ink:#333; --muted:rgba(0,0,0,.6);
  --accent:#ff3d8b; --accent-ink:#fff; --chip-bg:#f4f4f6; --chip-ink:#333;
  --field-border:#e6e6ee; --radius:24px; --font:"Segoe UI",system-ui,sans-serif;
  --shadow:0 1px 2px rgba(0,0,0,.06),0 12px 28px rgba(0,0,0,.12);
}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
  padding:24px;background:var(--bg);color:var(--ink);font-family:var(--font);
  -webkit-font-smoothing:antialiased;}
.card{background:var(--surface);border-radius:var(--radius);padding:28px;width:min(94vw,420px);
  box-shadow:var(--shadow);}
.invite-card>*{opacity:0;transform:translateY(12px);animation:rise .5s cubic-bezier(0.2,0,0,1) forwards;}
.invite-card>*:nth-child(1){animation-delay:.05s}.invite-card>*:nth-child(2){animation-delay:.15s}
.invite-card>*:nth-child(3){animation-delay:.25s}.invite-card>*:nth-child(4){animation-delay:.35s}
@keyframes rise{to{opacity:1;transform:translateY(0)}}
.kicker{font-size:22px;font-weight:800;text-wrap:balance;margin:0 0 6px;color:var(--accent)}
.message{font-size:15px;line-height:1.5;text-wrap:pretty;opacity:.85}
.respond-form{display:flex;flex-direction:column;gap:14px;margin-top:8px}
.field{display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:600;color:var(--muted)}
.field input{padding:12px;border-radius:14px;border:1px solid var(--field-border);font-size:16px;font-family:inherit}
.meals{border:0;padding:0;margin:0;display:flex;flex-wrap:wrap;gap:8px}
.meals legend{font-size:13px;font-weight:600;color:var(--muted);margin-bottom:6px}
.meal-chip{display:inline-flex;align-items:center;gap:6px;min-height:44px;padding:8px 12px;border-radius:999px;
  background:var(--chip-bg);color:var(--chip-ink);cursor:pointer;
  transition:scale .12s cubic-bezier(0.2,0,0,1),box-shadow .2s;}
.meal-chip:active{scale:.96}
.meal-chip input{position:absolute;opacity:0;width:0;height:0}
.meal-chip:has(input:checked){box-shadow:0 0 0 2px var(--accent) inset}
.meal-chip .ic{width:16px;height:16px}
.cta{margin-top:6px;min-height:48px;border:0;border-radius:16px;background:var(--accent);color:var(--accent-ink);
  font-weight:700;font-size:16px;cursor:pointer;transition:scale .12s cubic-bezier(0.2,0,0,1),box-shadow .2s;}
.cta:active{scale:.96}
.error{color:#b3243b;font-size:14px;margin:0 0 8px}
.confirm-card{text-align:center}
.confirm-card .big-ic{width:48px;height:48px;color:var(--accent)}
.confirm-card h1{font-size:24px;text-wrap:balance}
.subtitle{color:var(--muted);text-align:center}
```

- [ ] **Step 2: Write `public/assets/css/themes/love-letter.css`**

```css
.theme-love-letter{
  --bg:#f4e9d6;--surface:#fbf3e6;--ink:#6b3b2e;--muted:#8a6a55;
  --accent:#b3243b;--accent-ink:#fff;--chip-bg:#f0dcc2;--chip-ink:#8a4a37;
  --field-border:#d9b78f;--radius:22px;--font:Georgia,"Times New Roman",serif;
  --shadow:0 1px 2px rgba(107,59,46,.12),0 14px 30px rgba(107,59,46,.18);
}
.theme-love-letter .kicker{font-style:italic}
```

- [ ] **Step 3: Write `public/assets/css/themes/bubblegum.css`**

```css
.theme-bubblegum{
  --bg:linear-gradient(160deg,#ffd9ec,#e7d4ff 55%,#d4f0ff);
  --surface:#fff;--ink:#7a2e6b;--muted:#9a6a90;
  --accent:#ff3d8b;--accent-ink:#fff;--chip-bg:#fff0f7;--chip-ink:#ff3d8b;
  --field-border:#f3d4e8;--radius:24px;--font:"Trebuchet MS","Segoe UI",sans-serif;
  --shadow:0 1px 2px rgba(255,61,139,.12),0 14px 30px rgba(157,123,255,.22);
}
.theme-bubblegum .cta{box-shadow:0 5px 0 #c81e68}
```

- [ ] **Step 4: Write `public/assets/css/themes/midnight.css`**

```css
.theme-midnight{
  --bg:radial-gradient(120% 80% at 70% 10%,#3a1a5e,#160c2e 60%,#0c0720);
  --surface:rgba(255,255,255,.06);--ink:#ede6ff;--muted:#b8a8e0;
  --accent:#ff5fa2;--accent-ink:#fff;--chip-bg:rgba(255,255,255,.08);--chip-ink:#ffb3da;
  --field-border:rgba(255,143,199,.4);--radius:24px;--font:"Segoe UI",system-ui,sans-serif;
  --shadow:0 0 24px rgba(157,123,255,.4);
}
.theme-midnight .card{backdrop-filter:blur(8px);border:1px solid rgba(255,143,199,.25)}
.theme-midnight .field input{background:rgba(255,255,255,.06);color:var(--ink)}
.theme-midnight .cta{background:linear-gradient(90deg,#ff5fa2,#9d7bff)}
```

- [ ] **Step 5: Write the routing test** — `tests/Respond/RespondRoutingTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Respond;

use App\Core\Router;
use PHPUnit\Framework\TestCase;

final class RespondRoutingTest extends TestCase
{
    public function test_respond_routes_registered(): void
    {
        $router = new Router();
        $router->add('GET', '/i/{token}', static fn() => 'open');
        $router->add('POST', '/i/{token}', static fn() => 'submit');

        $get = $router->match('GET', '/i/abc123');
        $this->assertSame(['token' => 'abc123'], $get['params']);
        $post = $router->match('POST', '/i/abc123');
        $this->assertNotNull($post);
    }
}
```

> This is a lightweight guard that the token route shape works; the real registration is wired in Steps 6–7 and exercised by the manual check.

- [ ] **Step 6: Register routes** — in `config/routes.php`, add the `RespondController $respond` parameter to the factory and these routes (place BEFORE any catch-all; order is fine alongside existing routes):

```php
    $router->add('GET',  '/i/{token}', static fn(string $token): Response => $respond->open($token));
    $router->add('POST', '/i/{token}', static fn(string $token): Response => $respond->submit($token, $_POST, (static fn($v) => is_string($v) ? $v : '')($_POST['csrf'] ?? '')));
```

Update the factory signature to:

```php
return static function (
    Router $router,
    AuthController $auth,
    GoogleController $google,
    InviteController $invite,
    callable $currentUserId,
    RespondController $respond
): void {
```

(Keep the existing `/i/{token}/created` sender route — the router matches it before `/i/{token}` only if registered earlier; to be safe, register `/i/{token}/created` BEFORE `/i/{token}` since `{token}` would otherwise capture `abc/created`. Note `{token}` matches `[^/]+`, so `/i/abc/created` does NOT match `/i/{token}`; order is not a concern. Keep `created` route as-is.)

- [ ] **Step 7: Wire in `public/index.php`** — after the invite wiring, add:

```php
use App\Respond\RespondController;
use App\Theme\AbEventRepo;
use App\Theme\ABAssigner;
use App\Theme\ThemeRepo;
use App\Invite\ResponseRepo;

$responseRepo = new ResponseRepo($pdo, $clock);
$themeRepo    = new ThemeRepo($pdo);
$abEvents     = new AbEventRepo($pdo, $clock);
$assigner     = new ABAssigner($themeRepo, $inviteRepo);
$respondCtrl  = new RespondController(
    $view, $csrf, $inviteRepo, $responseRepo, $users, $assigner, $abEvents, $clock
);
```

And extend the routes invocation:

```php
(require dirname(__DIR__) . '/config/routes.php')($router, $auth, $googleCtrl, $inviteCtrl, $currentUserId, $respondCtrl);
```

- [ ] **Step 8: Run the full suite** — Run: `vendor/bin/phpunit`
Expected: all green (~67 tests).

- [ ] **Step 9: Manual check** — create an invite in `crush_dev`, open its link, confirm a theme renders:

```bash
DB_DSN="mysql:host=127.0.0.1;dbname=crush_dev;charset=utf8mb4" DB_USER=root DB_PASS= APP_URL="http://127.0.0.1:8080" \
  php -S 127.0.0.1:8080 -t public &
sleep 1
# Seed a token directly and open it:
TOKEN=$(php -r '$p=new PDO("mysql:host=127.0.0.1;dbname=crush_dev;charset=utf8mb4","root","");$p->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);require "vendor/autoload.php";$c=new App\Core\SystemClock();$u=new App\Auth\UserRepo($p,$c);$s=$u->findByEmail("seed@x.test")?:$u->create("seed@x.test","Seed","magic");$i=new App\Invite\InviteRepo($p,$c);$inv=$i->create(["sender_id"=>$s["id"],"crush_email"=>"c@x.test","crush_name"=>"Cee","is_anonymous"=>1,"reveal_on_response"=>0,"date_mode"=>"instant","message"=>"hi","expires_at"=>"2030-01-01 00:00:00"]);echo $inv["public_token"];')
echo "token=$TOKEN"
curl -s "127.0.0.1:8080/i/$TOKEN" | grep -o 'theme-[a-z-]*' | head -1
curl -s "127.0.0.1:8080/i/$TOKEN" | grep -c 'secret admirer'
curl -s -o /dev/null -w 'base.css: %{http_code}\n' "127.0.0.1:8080/assets/css/base.css"
kill %1
```
Expected: prints a `theme-...` class, `1` for the secret-admirer label, and `base.css: 200`.

- [ ] **Step 10: Commit**

```bash
git add public/assets/css config/routes.php public/index.php tests/Respond/RespondRoutingTest.php
git commit -m "feat(respond): theme stylesheets + routes + wiring"
```

---

## Self-Review

**1. Spec coverage:** Crush opens with no auth (§7,13) — Task 4 `open()`, public route. Themed "tap to open"/reveal experience across 3 themes (§11, brainstorm) — Tasks 4–6 + CSS. A/B assignment weighted + pinned + funnel events (§11) — Tasks 1–3, logged on open/complete. Date pick + meal vibe + wish + contact + pickup (§7) — Tasks 4–5 form + store. Instant vs confirm state transitions + reveal-on-response (§5,6,8) — Task 5 `submit()`. Anonymity never leaks sender pre-reveal (§8,14) — `senderLabel`/`revealLabel`, tested. Icons only, motion/feel layer (§15) — sprite + base.css. Pickup stored raw; Plan 5 enriches (§9). CSRF on POST (§14) — Task 5.

**2. Placeholder scan:** No "TBD". Minimal templates in Tasks 4–5 are intentionally functional-first; Task 6 adds the real stylesheets. The `respond/show` and `confirmed` templates link the theme CSS from the start.

**3. Type consistency:** `ThemeRepo::listActive(): array`/`exists(): bool`; `AbEventRepo::log(): void`/`count(): int`; `ABAssigner::assignTo(array): string`; `MealOptions::isValid(string): bool`; `RespondController::open(string): Response`/`submit(string,array,string): Response`. Consumes `InviteRepo` (`findByToken`,`updateStatus`,`setTheme`,`dateOptions`), `ResponseRepo::store`, `UserRepo::findById`, `InviteState` constants, `View`,`Csrf`,`Clock`,`Response` as defined in Plans 1–3. Routes factory extended with `RespondController`, matched in `public/index.php`.
