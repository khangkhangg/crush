# Crush — Plan 3: Invite Domain Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A signed-in sender can create a date invite (crush email, message, anonymity, date mode, optional proposed slots), get a shareable secure link, and see their invites on a dashboard. Model the invite lifecycle and response storage that the crush-facing flow (Plan 4) will build on.

**Architecture:** New `app/Invite` namespace. `InviteState` encodes the lifecycle state machine (pure logic, no DB). `InviteRepo` and `ResponseRepo` are PDO data access against new tables. `InviteController` handles the authenticated sender flow (dashboard, new-invite form, create). Auth is enforced by passing the current user id (resolved from the session in the front controller) into controller methods, which redirect to `/login` when absent.

**Tech Stack:** PHP 8.1+, PDO MySQL, PHPUnit 10. No new Composer dependencies.

## Global Constraints

- PHP floor 8.1. PSR-4: `App\` → `app/`, `Tests\` → `tests/`. No new Composer packages.
- **Icons only — never emojis** in templates/UI.
- All HTML output escaped via `App\Core\e()` / `View`. All POST routes validate CSRF (`App\Core\Csrf`).
- `public_token` is unguessable (≥ 32 bytes of randomness, hex). Prepared statements only.
- Integration tests use MySQL `crush_test`. Migrations are MySQL DDL.
- Only senders authenticate; crush-facing routes (Plan 4) require no auth.

## File Structure

- `migrations/0003_invites.sql` — `invites`, `invite_date_options`, `responses`.
- `app/Invite/InviteState.php` — lifecycle state machine.
- `app/Invite/InviteRepo.php` — invite + date-option data access.
- `app/Invite/ResponseRepo.php` — response data access.
- `app/Invite/InviteController.php` — sender dashboard + create flow.
- `templates/invite/dashboard.php`, `templates/invite/new.php`, `templates/invite/created.php`.

---

### Task 1: invites / date options / responses migration

**Files:**
- Create: `migrations/0003_invites.sql`
- Test: `tests/Invite/InviteSchemaTest.php`

**Interfaces:**
- Consumes: `Tests\Support\DatabaseTestCase` (Plan 2).
- Produces: `invites`, `invite_date_options`, `responses` tables.

- [ ] **Step 1: Write the failing test** — `tests/Invite/InviteSchemaTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Invite;

use Tests\Support\DatabaseTestCase;

final class InviteSchemaTest extends DatabaseTestCase
{
    public function test_tables_and_key_columns_exist(): void
    {
        $cols = fn(string $t) => array_column(
            $this->pdo()->query("SHOW COLUMNS FROM `{$t}`")->fetchAll(),
            'Field'
        );

        foreach (['id', 'public_token', 'sender_id', 'crush_email', 'crush_name',
                  'is_anonymous', 'reveal_on_response', 'date_mode', 'status',
                  'theme_key', 'message', 'created_at', 'expires_at'] as $c) {
            $this->assertContains($c, $cols('invites'), "invites.$c");
        }
        foreach (['id', 'invite_id', 'start_at', 'end_at'] as $c) {
            $this->assertContains($c, $cols('invite_date_options'), "invite_date_options.$c");
        }
        foreach (['id', 'invite_id', 'chosen_start', 'chosen_end', 'meal_choice',
                  'meal_wish', 'crush_contact', 'pickup_raw', 'pickup_name',
                  'pickup_address', 'pickup_clean_url', 'created_at'] as $c) {
            $this->assertContains($c, $cols('responses'), "responses.$c");
        }
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter InviteSchemaTest`
Expected: FAIL — table `crush_test.invites` not found.

- [ ] **Step 3: Write `migrations/0003_invites.sql`**

```sql
CREATE TABLE IF NOT EXISTS invites (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  public_token       CHAR(64)     NOT NULL,
  sender_id          BIGINT UNSIGNED NOT NULL,
  crush_email        VARCHAR(191) NOT NULL,
  crush_name         VARCHAR(191) NULL,
  is_anonymous       TINYINT(1)   NOT NULL DEFAULT 0,
  reveal_on_response TINYINT(1)   NOT NULL DEFAULT 0,
  date_mode          VARCHAR(16)  NOT NULL,
  status             VARCHAR(24)  NOT NULL,
  theme_key          VARCHAR(32)  NULL,
  message            TEXT         NULL,
  created_at         DATETIME     NOT NULL,
  expires_at         DATETIME     NOT NULL,
  UNIQUE KEY uq_invite_token (public_token),
  KEY idx_invite_sender (sender_id),
  KEY idx_invite_crush (crush_email),
  CONSTRAINT fk_invite_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS invite_date_options (
  id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  invite_id BIGINT UNSIGNED NOT NULL,
  start_at  DATETIME NOT NULL,
  end_at    DATETIME NOT NULL,
  KEY idx_opt_invite (invite_id),
  CONSTRAINT fk_opt_invite FOREIGN KEY (invite_id) REFERENCES invites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS responses (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  invite_id        BIGINT UNSIGNED NOT NULL,
  chosen_start     DATETIME     NULL,
  chosen_end       DATETIME     NULL,
  meal_choice      VARCHAR(32)  NULL,
  meal_wish        TEXT         NULL,
  crush_contact    VARCHAR(191) NULL,
  pickup_raw       TEXT         NULL,
  pickup_name      VARCHAR(191) NULL,
  pickup_address   VARCHAR(512) NULL,
  pickup_clean_url VARCHAR(1024) NULL,
  created_at       DATETIME     NOT NULL,
  UNIQUE KEY uq_response_invite (invite_id),
  CONSTRAINT fk_response_invite FOREIGN KEY (invite_id) REFERENCES invites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- [ ] **Step 4: Run to verify it passes** — Run: `vendor/bin/phpunit --filter InviteSchemaTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add migrations/0003_invites.sql tests/Invite/InviteSchemaTest.php
git commit -m "feat(invite): invites + date options + responses migration"
```

---

### Task 2: InviteState machine

**Files:**
- Create: `app/Invite/InviteState.php`
- Test: `tests/Invite/InviteStateTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `App\Invite\InviteState` with class constants for each state (strings) and:
  - `canTransition(string $from, string $to): bool`
  - `assert(string $from, string $to): void` (throws `\InvalidArgumentException` on illegal transition)
  - `all(): array` (list of valid state strings).

- [ ] **Step 1: Write the failing test** — `tests/Invite/InviteStateTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Invite;

use App\Invite\InviteState;
use PHPUnit\Framework\TestCase;

final class InviteStateTest extends TestCase
{
    public function test_valid_transitions(): void
    {
        $this->assertTrue(InviteState::canTransition(InviteState::SENT, InviteState::OPENED));
        $this->assertTrue(InviteState::canTransition(InviteState::OPENED, InviteState::RESPONDED));
        $this->assertTrue(InviteState::canTransition(InviteState::RESPONDED, InviteState::CONFIRMED));
        $this->assertTrue(InviteState::canTransition(InviteState::RESPONDED, InviteState::PENDING_SENDER));
        $this->assertTrue(InviteState::canTransition(InviteState::PENDING_SENDER, InviteState::CONFIRMED));
        $this->assertTrue(InviteState::canTransition(InviteState::PENDING_SENDER, InviteState::DECLINED));
        $this->assertTrue(InviteState::canTransition(InviteState::CONFIRMED, InviteState::CLOSED));
    }

    public function test_invalid_transitions(): void
    {
        $this->assertFalse(InviteState::canTransition(InviteState::SENT, InviteState::CONFIRMED));
        $this->assertFalse(InviteState::canTransition(InviteState::CLOSED, InviteState::OPENED));
        $this->assertFalse(InviteState::canTransition(InviteState::CONFIRMED, InviteState::DECLINED));
    }

    public function test_assert_throws_on_illegal(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        InviteState::assert(InviteState::CLOSED, InviteState::SENT);
    }

    public function test_sent_can_expire_or_block(): void
    {
        $this->assertTrue(InviteState::canTransition(InviteState::SENT, InviteState::EXPIRED));
        $this->assertTrue(InviteState::canTransition(InviteState::OPENED, InviteState::BLOCKED));
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter InviteStateTest`
Expected: FAIL — `Class "App\Invite\InviteState" not found`.

- [ ] **Step 3: Write `app/Invite/InviteState.php`**

```php
<?php
declare(strict_types=1);

namespace App\Invite;

final class InviteState
{
    public const DRAFT          = 'draft';
    public const SENT           = 'sent';
    public const OPENED         = 'opened';
    public const RESPONDED      = 'responded';
    public const PENDING_SENDER = 'pending_sender';
    public const CONFIRMED      = 'confirmed';
    public const DECLINED       = 'declined';
    public const CLOSED         = 'closed';
    public const EXPIRED        = 'expired';
    public const BLOCKED        = 'blocked';

    /** @var array<string,string[]> */
    private const TRANSITIONS = [
        self::DRAFT          => [self::SENT],
        self::SENT           => [self::OPENED, self::EXPIRED, self::BLOCKED],
        self::OPENED         => [self::RESPONDED, self::EXPIRED, self::BLOCKED],
        self::RESPONDED      => [self::CONFIRMED, self::PENDING_SENDER],
        self::PENDING_SENDER => [self::CONFIRMED, self::DECLINED, self::EXPIRED],
        self::CONFIRMED      => [self::CLOSED],
        self::DECLINED       => [self::SENT, self::CLOSED],
        self::CLOSED         => [],
        self::EXPIRED        => [],
        self::BLOCKED        => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function assert(string $from, string $to): void
    {
        if (!self::canTransition($from, $to)) {
            throw new \InvalidArgumentException("Illegal invite transition: {$from} -> {$to}");
        }
    }

    /** @return string[] */
    public static function all(): array
    {
        return array_keys(self::TRANSITIONS);
    }
}
```

- [ ] **Step 4: Run to verify it passes** — Run: `vendor/bin/phpunit --filter InviteStateTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Invite/InviteState.php tests/Invite/InviteStateTest.php
git commit -m "feat(invite): lifecycle state machine"
```

---

### Task 3: InviteRepo

**Files:**
- Create: `app/Invite/InviteRepo.php`
- Test: `tests/Invite/InviteRepoTest.php`

**Interfaces:**
- Consumes: `\PDO`, `App\Core\Clock`, `App\Auth\UserRepo` (for test setup), `App\Invite\InviteState`.
- Produces: `App\Invite\InviteRepo` with:
  - `__construct(\PDO $pdo, Clock $clock)`
  - `create(array $data): array` — required keys: `sender_id` (int), `crush_email` (string), `crush_name` (?string), `is_anonymous` (bool), `reveal_on_response` (bool), `date_mode` (string), `message` (?string), `expires_at` (string `Y-m-d H:i:s`). Optional: `theme_key` (?string), `status` (defaults to `InviteState::SENT`). Generates a 64-char hex `public_token` and `created_at`. Returns the row.
  - `findById(int $id): ?array`
  - `findByToken(string $token): ?array`
  - `listBySender(int $senderId): array` (newest first)
  - `updateStatus(int $id, string $status): void`
  - `setTheme(int $id, string $themeKey): void`
  - `addDateOption(int $inviteId, string $startAt, string $endAt): void`
  - `dateOptions(int $inviteId): array`
  - Rows cast `id`, `sender_id`, `is_anonymous`, `reveal_on_response` to int.

- [ ] **Step 1: Write the failing test** — `tests/Invite/InviteRepoTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Invite;

use App\Auth\UserRepo;
use App\Invite\InviteRepo;
use App\Invite\InviteState;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class InviteRepoTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    private function repo(): InviteRepo
    {
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        return new InviteRepo($this->pdo(), $this->clock);
    }

    private function sender(): int
    {
        return (new UserRepo($this->pdo(), $this->clock))->create('s@x.test', 'Sue', 'magic')['id'];
    }

    private function baseData(int $sender): array
    {
        return [
            'sender_id'          => $sender,
            'crush_email'        => 'crush@x.test',
            'crush_name'         => 'Cee',
            'is_anonymous'       => true,
            'reveal_on_response' => false,
            'date_mode'          => 'instant',
            'message'            => 'hi',
            'expires_at'         => '2026-02-01 00:00:00',
        ];
    }

    public function test_create_generates_token_and_defaults_status_sent(): void
    {
        $repo = $this->repo();
        $invite = $repo->create($this->baseData($this->sender()));

        $this->assertIsInt($invite['id']);
        $this->assertSame(64, strlen($invite['public_token']));
        $this->assertSame(InviteState::SENT, $invite['status']);
        $this->assertSame(1, $invite['is_anonymous']);
        $this->assertSame($invite['id'], $repo->findByToken($invite['public_token'])['id']);
    }

    public function test_list_by_sender_newest_first(): void
    {
        $repo = $this->repo();
        $sender = $this->sender();
        $a = $repo->create($this->baseData($sender));
        $this->clock->advance(60);
        $b = $repo->create($this->baseData($sender));

        $list = $repo->listBySender($sender);
        $this->assertCount(2, $list);
        $this->assertSame($b['id'], $list[0]['id']);
    }

    public function test_update_status_and_set_theme(): void
    {
        $repo = $this->repo();
        $invite = $repo->create($this->baseData($this->sender()));
        $repo->updateStatus($invite['id'], InviteState::OPENED);
        $repo->setTheme($invite['id'], 'midnight');

        $reloaded = $repo->findById($invite['id']);
        $this->assertSame(InviteState::OPENED, $reloaded['status']);
        $this->assertSame('midnight', $reloaded['theme_key']);
    }

    public function test_date_options_round_trip(): void
    {
        $repo = $this->repo();
        $invite = $repo->create($this->baseData($this->sender()));
        $repo->addDateOption($invite['id'], '2026-02-10 19:00:00', '2026-02-10 21:00:00');
        $repo->addDateOption($invite['id'], '2026-02-11 19:00:00', '2026-02-11 21:00:00');

        $opts = $repo->dateOptions($invite['id']);
        $this->assertCount(2, $opts);
        $this->assertSame('2026-02-10 19:00:00', $opts[0]['start_at']);
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter InviteRepoTest`
Expected: FAIL — `Class "App\Invite\InviteRepo" not found`.

- [ ] **Step 3: Write `app/Invite/InviteRepo.php`**

```php
<?php
declare(strict_types=1);

namespace App\Invite;

use App\Core\Clock;

final class InviteRepo
{
    public function __construct(private \PDO $pdo, private Clock $clock) {}

    public function create(array $data): array
    {
        $token = bin2hex(random_bytes(32));
        $stmt = $this->pdo->prepare(
            'INSERT INTO invites
             (public_token, sender_id, crush_email, crush_name, is_anonymous,
              reveal_on_response, date_mode, status, theme_key, message, created_at, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $token,
            $data['sender_id'],
            $data['crush_email'],
            $data['crush_name'] ?? null,
            !empty($data['is_anonymous']) ? 1 : 0,
            !empty($data['reveal_on_response']) ? 1 : 0,
            $data['date_mode'],
            $data['status'] ?? InviteState::SENT,
            $data['theme_key'] ?? null,
            $data['message'] ?? null,
            $this->clock->now()->format('Y-m-d H:i:s'),
            $data['expires_at'],
        ]);
        return $this->findById((int) $this->pdo->lastInsertId());
    }

    public function findById(int $id): ?array
    {
        return $this->one('SELECT * FROM invites WHERE id = ?', [$id]);
    }

    public function findByToken(string $token): ?array
    {
        return $this->one('SELECT * FROM invites WHERE public_token = ?', [$token]);
    }

    /** @return array<int,array> */
    public function listBySender(int $senderId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM invites WHERE sender_id = ? ORDER BY created_at DESC, id DESC'
        );
        $stmt->execute([$senderId]);
        return array_map([$this, 'cast'], $stmt->fetchAll());
    }

    public function updateStatus(int $id, string $status): void
    {
        $this->pdo->prepare('UPDATE invites SET status = ? WHERE id = ?')->execute([$status, $id]);
    }

    public function setTheme(int $id, string $themeKey): void
    {
        $this->pdo->prepare('UPDATE invites SET theme_key = ? WHERE id = ?')->execute([$themeKey, $id]);
    }

    public function addDateOption(int $inviteId, string $startAt, string $endAt): void
    {
        $this->pdo->prepare(
            'INSERT INTO invite_date_options (invite_id, start_at, end_at) VALUES (?, ?, ?)'
        )->execute([$inviteId, $startAt, $endAt]);
    }

    /** @return array<int,array> */
    public function dateOptions(int $inviteId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM invite_date_options WHERE invite_id = ? ORDER BY start_at ASC, id ASC'
        );
        $stmt->execute([$inviteId]);
        return $stmt->fetchAll();
    }

    private function one(string $sql, array $params): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $this->cast($row);
    }

    private function cast(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['sender_id'] = (int) $row['sender_id'];
        $row['is_anonymous'] = (int) $row['is_anonymous'];
        $row['reveal_on_response'] = (int) $row['reveal_on_response'];
        return $row;
    }
}
```

- [ ] **Step 4: Run to verify it passes** — Run: `vendor/bin/phpunit --filter InviteRepoTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Invite/InviteRepo.php tests/Invite/InviteRepoTest.php
git commit -m "feat(invite): InviteRepo data access"
```

---

### Task 4: ResponseRepo

**Files:**
- Create: `app/Invite/ResponseRepo.php`
- Test: `tests/Invite/ResponseRepoTest.php`

**Interfaces:**
- Consumes: `\PDO`, `App\Core\Clock`, `App\Invite\InviteRepo` + `App\Auth\UserRepo` (test setup).
- Produces: `App\Invite\ResponseRepo` with:
  - `__construct(\PDO $pdo, Clock $clock)`
  - `store(int $inviteId, array $data): array` — keys (all optional, default null): `chosen_start`, `chosen_end`, `meal_choice`, `meal_wish`, `crush_contact`, `pickup_raw`, `pickup_name`, `pickup_address`, `pickup_clean_url`. Sets `created_at`. Returns the stored row.
  - `findByInvite(int $inviteId): ?array`
  - Rows cast `id`, `invite_id` to int.

- [ ] **Step 1: Write the failing test** — `tests/Invite/ResponseRepoTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Invite;

use App\Auth\UserRepo;
use App\Invite\InviteRepo;
use App\Invite\ResponseRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class ResponseRepoTest extends DatabaseTestCase
{
    private function ids(): array
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $sender = (new UserRepo($this->pdo(), $clock))->create('s@x.test', 'Sue', 'magic')['id'];
        $invite = (new InviteRepo($this->pdo(), $clock))->create([
            'sender_id' => $sender, 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => false, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'expires_at' => '2026-02-01 00:00:00',
        ]);
        return [$invite['id'], new ResponseRepo($this->pdo(), $clock)];
    }

    public function test_store_and_find(): void
    {
        [$inviteId, $repo] = $this->ids();
        $resp = $repo->store($inviteId, [
            'chosen_start' => '2026-02-10 19:00:00',
            'chosen_end'   => '2026-02-10 21:00:00',
            'meal_choice'  => 'sushi',
            'meal_wish'    => 'surprise me',
            'crush_contact'=> '@cee',
            'pickup_name'  => 'Sushi Place',
            'pickup_address'=> '1 Main St',
            'pickup_clean_url' => 'https://maps.google.com/?q=Sushi+Place',
        ]);

        $this->assertIsInt($resp['id']);
        $this->assertSame($inviteId, $resp['invite_id']);
        $this->assertSame('sushi', $resp['meal_choice']);

        $found = $repo->findByInvite($inviteId);
        $this->assertSame('Sushi Place', $found['pickup_name']);
        $this->assertNull($repo->findByInvite(999999));
    }

    public function test_store_with_minimal_data(): void
    {
        [$inviteId, $repo] = $this->ids();
        $resp = $repo->store($inviteId, ['meal_choice' => 'coffee']);
        $this->assertSame('coffee', $resp['meal_choice']);
        $this->assertNull($resp['pickup_address']);
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter ResponseRepoTest`
Expected: FAIL — `Class "App\Invite\ResponseRepo" not found`.

- [ ] **Step 3: Write `app/Invite/ResponseRepo.php`**

```php
<?php
declare(strict_types=1);

namespace App\Invite;

use App\Core\Clock;

final class ResponseRepo
{
    private const FIELDS = [
        'chosen_start', 'chosen_end', 'meal_choice', 'meal_wish', 'crush_contact',
        'pickup_raw', 'pickup_name', 'pickup_address', 'pickup_clean_url',
    ];

    public function __construct(private \PDO $pdo, private Clock $clock) {}

    public function store(int $inviteId, array $data): array
    {
        $values = [$inviteId];
        foreach (self::FIELDS as $f) {
            $values[] = $data[$f] ?? null;
        }
        $values[] = $this->clock->now()->format('Y-m-d H:i:s');

        $cols = 'invite_id, ' . implode(', ', self::FIELDS) . ', created_at';
        $marks = implode(', ', array_fill(0, count(self::FIELDS) + 2, '?'));

        $this->pdo->prepare("INSERT INTO responses ({$cols}) VALUES ({$marks})")->execute($values);
        return $this->findByInvite($inviteId);
    }

    public function findByInvite(int $inviteId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM responses WHERE invite_id = ?');
        $stmt->execute([$inviteId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $row['id'] = (int) $row['id'];
        $row['invite_id'] = (int) $row['invite_id'];
        return $row;
    }
}
```

- [ ] **Step 4: Run to verify it passes** — Run: `vendor/bin/phpunit --filter ResponseRepoTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Invite/ResponseRepo.php tests/Invite/ResponseRepoTest.php
git commit -m "feat(invite): ResponseRepo data access"
```

---

### Task 5: InviteController + sender flow + routes + templates

**Files:**
- Create: `app/Invite/InviteController.php`
- Create: `templates/invite/dashboard.php`
- Create: `templates/invite/new.php`
- Create: `templates/invite/created.php`
- Modify: `config/routes.php`
- Modify: `public/index.php`
- Test: `tests/Invite/InviteControllerTest.php`

**Interfaces:**
- Consumes: `App\Invite\InviteRepo`, `App\Auth\UserRepo`, `App\Core\View`, `App\Core\Csrf`, `App\Core\Clock`, `App\Core\Response`.
- Produces: `App\Invite\InviteController` with methods returning `App\Core\Response`:
  - `__construct(View $view, Csrf $csrf, InviteRepo $invites, UserRepo $users, Clock $clock, string $appUrl)`
  - `dashboard(?int $userId): Response` — redirect to `/login` when `$userId` is null; else render the sender's invites.
  - `showNew(?int $userId): Response` — new-invite form (CSRF).
  - `create(?int $userId, array $input, string $csrf): Response` — validate + create; on success redirect to `/i/{token}/created`; render the form with errors otherwise.
  - `showCreated(?int $userId, string $token): Response` — confirmation page showing the shareable link (only if the invite belongs to this sender).

- [ ] **Step 1: Write the controller test** — `tests/Invite/InviteControllerTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Invite;

use App\Auth\UserRepo;
use App\Core\ArrayStore;
use App\Core\Csrf;
use App\Core\View;
use App\Invite\InviteController;
use App\Invite\InviteRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class InviteControllerTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    private function controller(Csrf $csrf): InviteController
    {
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        return new InviteController(
            $view, $csrf,
            new InviteRepo($this->pdo(), $this->clock),
            new UserRepo($this->pdo(), $this->clock),
            $this->clock, 'http://localhost'
        );
    }

    private function sender(): int
    {
        $c = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        return (new UserRepo($this->pdo(), $c))->create('s@x.test', 'Sue', 'magic')['id'];
    }

    public function test_dashboard_redirects_when_anonymous(): void
    {
        $res = $this->controller(new Csrf(new ArrayStore()))->dashboard(null);
        $this->assertSame(302, $res->status());
        $this->assertSame('/login', $res->headers()['Location']);
    }

    public function test_new_form_renders_with_csrf(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf)->showNew($this->sender());
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString($csrf->token(), $res->body());
        $this->assertStringContainsString('name="crush_email"', $res->body());
    }

    public function test_create_rejects_bad_csrf(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf)->create($this->sender(), [
            'crush_email' => 'c@x.test', 'date_mode' => 'instant',
        ], 'wrong');
        $this->assertSame(400, $res->status());
    }

    public function test_create_rejects_invalid_email(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf)->create($this->sender(), [
            'crush_email' => 'not-an-email', 'date_mode' => 'instant',
        ], $csrf->token());
        $this->assertSame(422, $res->status());
    }

    public function test_create_success_redirects_to_created(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $ctrl = $this->controller($csrf);
        $res = $ctrl->create($this->sender(), [
            'crush_email' => 'c@x.test', 'crush_name' => 'Cee', 'message' => 'hi',
            'date_mode' => 'instant', 'is_anonymous' => '1',
        ], $csrf->token());

        $this->assertSame(302, $res->status());
        $this->assertStringContainsString('/created', $res->headers()['Location']);
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter InviteControllerTest`
Expected: FAIL — `Class "App\Invite\InviteController" not found`.

- [ ] **Step 3: Write `app/Invite/InviteController.php`**

```php
<?php
declare(strict_types=1);

namespace App\Invite;

use App\Auth\UserRepo;
use App\Core\Clock;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\View;

final class InviteController
{
    public function __construct(
        private View $view,
        private Csrf $csrf,
        private InviteRepo $invites,
        private UserRepo $users,
        private Clock $clock,
        private string $appUrl,
    ) {}

    public function dashboard(?int $userId): Response
    {
        if ($userId === null) {
            return $this->redirect('/login');
        }
        return Response::html($this->view->render('invite/dashboard', [
            'title'   => 'Your invites',
            'invites' => $this->invites->listBySender($userId),
            'appUrl'  => rtrim($this->appUrl, '/'),
        ]));
    }

    public function showNew(?int $userId): Response
    {
        if ($userId === null) {
            return $this->redirect('/login');
        }
        return $this->renderForm();
    }

    public function create(?int $userId, array $input, string $csrf): Response
    {
        if ($userId === null) {
            return $this->redirect('/login');
        }
        if (!$this->csrf->validate($csrf)) {
            return $this->renderForm('Your session expired. Please try again.', $input, 400);
        }

        $email = trim((string) ($input['crush_email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->renderForm('Please enter a valid email for your crush.', $input, 422);
        }

        $dateMode = ($input['date_mode'] ?? 'instant') === 'confirm' ? 'confirm' : 'instant';

        $invite = $this->invites->create([
            'sender_id'          => $userId,
            'crush_email'        => $email,
            'crush_name'         => trim((string) ($input['crush_name'] ?? '')) ?: null,
            'is_anonymous'       => !empty($input['is_anonymous']),
            'reveal_on_response' => !empty($input['reveal_on_response']),
            'date_mode'          => $dateMode,
            'message'            => trim((string) ($input['message'] ?? '')) ?: null,
            'expires_at'         => $this->clock->now()->modify('+30 days')->format('Y-m-d H:i:s'),
        ]);

        // Proposed slots (confirm mode): start/end pairs.
        $starts = (array) ($input['slot_start'] ?? []);
        $ends   = (array) ($input['slot_end'] ?? []);
        foreach ($starts as $i => $start) {
            $start = trim((string) $start);
            $end   = trim((string) ($ends[$i] ?? ''));
            if ($start !== '' && $end !== '') {
                $this->invites->addDateOption($invite['id'], $start, $end);
            }
        }

        return $this->redirect('/i/' . $invite['public_token'] . '/created');
    }

    public function showCreated(?int $userId, string $token): Response
    {
        if ($userId === null) {
            return $this->redirect('/login');
        }
        $invite = $this->invites->findByToken($token);
        if ($invite === null || $invite['sender_id'] !== $userId) {
            return Response::html($this->view->render('invite/dashboard', [
                'title' => 'Your invites', 'invites' => $this->invites->listBySender($userId),
                'appUrl' => rtrim($this->appUrl, '/'),
            ]), 404);
        }
        return Response::html($this->view->render('invite/created', [
            'title'  => 'Invite ready',
            'link'   => rtrim($this->appUrl, '/') . '/i/' . $invite['public_token'],
            'invite' => $invite,
        ]));
    }

    private function renderForm(?string $error = null, array $old = [], int $status = 200): Response
    {
        return Response::html($this->view->render('invite/new', [
            'title' => 'New invite',
            'csrf'  => $this->csrf->token(),
            'error' => $error,
            'old'   => $old,
        ]), $status);
    }

    private function redirect(string $to): Response
    {
        return (new Response('', 302))->withHeader('Location', $to);
    }
}
```

- [ ] **Step 4: Write `templates/invite/dashboard.php`**

```php
<?php $invites = $invites ?? []; ?>
<?php $content = function () use ($e, $invites, $appUrl) { ob_start(); ?>
  <h1 style="text-wrap:balance;">Your invites</h1>
  <a href="/invites/new"
     style="display:inline-block;padding:12px 18px;border-radius:14px;background:#ff3d8b;color:#fff;font-weight:700;text-decoration:none;">
    Send a new crush invite
  </a>
  <?php if (empty($invites)): ?>
    <p style="opacity:.75;margin-top:20px;">No invites yet. Send your first one above.</p>
  <?php else: ?>
    <ul style="list-style:none;padding:0;margin-top:20px;display:flex;flex-direction:column;gap:12px;">
      <?php foreach ($invites as $inv): ?>
        <li style="padding:14px;border-radius:14px;background:#faf2ff;border:1px solid #eadcff;">
          <strong><?= $e($inv['crush_name'] ?: $inv['crush_email']) ?></strong>
          <span style="float:right;font-size:12px;opacity:.7;"><?= $e($inv['status']) ?></span>
          <div style="font-size:12px;opacity:.7;margin-top:4px;word-break:break-all;">
            <?= $e($appUrl) ?>/i/<?= $e($inv['public_token']) ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif;
  return ob_get_clean(); };
$body = $content();
include __DIR__ . '/../layout.php';
```

- [ ] **Step 5: Write `templates/invite/new.php`**

```php
<?php $error = $error ?? null; $old = $old ?? []; ?>
<?php $content = function () use ($e, $csrf, $error, $old) {
  $val = fn(string $k) => $e($old[$k] ?? '');
  ob_start(); ?>
  <h1 style="text-wrap:balance;">Send a crush invite</h1>
  <?php if ($error): ?><p role="alert" style="color:#b3243b;"><?= $e($error) ?></p><?php endif; ?>
  <form method="post" action="/invites" style="display:flex;flex-direction:column;gap:12px;">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <label>Their email
      <input type="email" name="crush_email" required value="<?= $val('crush_email') ?>"
             style="width:100%;padding:11px;border-radius:12px;border:1px solid #e7d4ff;">
    </label>
    <label>Their name (optional)
      <input type="text" name="crush_name" value="<?= $val('crush_name') ?>"
             style="width:100%;padding:11px;border-radius:12px;border:1px solid #e7d4ff;">
    </label>
    <label>A little message (optional)
      <textarea name="message" rows="3"
                style="width:100%;padding:11px;border-radius:12px;border:1px solid #e7d4ff;"><?= $val('message') ?></textarea>
    </label>
    <label>When should they pick?
      <select name="date_mode" style="width:100%;padding:11px;border-radius:12px;border:1px solid #e7d4ff;">
        <option value="instant">Let them pick any time (final)</option>
        <option value="confirm">They propose, I confirm</option>
      </select>
    </label>
    <label style="display:flex;align-items:center;gap:8px;">
      <input type="checkbox" name="is_anonymous" value="1"> Send anonymously (a secret admirer)
    </label>
    <label style="display:flex;align-items:center;gap:8px;">
      <input type="checkbox" name="reveal_on_response" value="1"> Reveal me after they respond
    </label>
    <button type="submit"
            style="padding:12px;border:0;border-radius:14px;background:#ff3d8b;color:#fff;font-weight:700;cursor:pointer;">
      Create my invite
    </button>
  </form>
  <?php return ob_get_clean(); };
$body = $content();
include __DIR__ . '/../layout.php';
```

- [ ] **Step 6: Write `templates/invite/created.php`**

```php
<?php $content = function () use ($e, $link, $invite) { ob_start(); ?>
  <h1 style="text-wrap:balance;">Your invite is ready</h1>
  <p style="opacity:.8;">Share this private link with <strong><?= $e($invite['crush_name'] ?: $invite['crush_email']) ?></strong>:</p>
  <input id="lnk" readonly value="<?= $e($link) ?>"
         style="width:100%;padding:11px;border-radius:12px;border:1px solid #e7d4ff;font-size:13px;"
         onclick="this.select()">
  <p style="margin-top:16px;"><a href="/invites" style="color:#ff3d8b;font-weight:600;">Back to your invites</a></p>
  <?php return ob_get_clean(); };
$body = $content();
include __DIR__ . '/../layout.php';
```

- [ ] **Step 7: Run the controller test** — Run: `vendor/bin/phpunit --filter InviteControllerTest`
Expected: PASS (6 tests).

- [ ] **Step 8: Register routes** — in `config/routes.php`, add the `InviteController $invite` parameter to the factory and these routes (the front controller passes `$session->userId()`):

```php
    $router->add('GET',  '/',              static fn(): Response => $invite->dashboard($currentUserId()));
    $router->add('GET',  '/invites',       static fn(): Response => $invite->dashboard($currentUserId()));
    $router->add('GET',  '/invites/new',   static fn(): Response => $invite->showNew($currentUserId()));
    $router->add('POST', '/invites',       static fn(): Response => $invite->create($currentUserId(), $_POST, (static fn($v) => is_string($v) ? $v : '')($_POST['csrf'] ?? '')));
    $router->add('GET',  '/i/{token}/created', static fn(string $token): Response => $invite->showCreated($currentUserId(), $token));
```

The factory signature becomes:

```php
return static function (
    Router $router,
    AuthController $auth,
    GoogleController $google,
    InviteController $invite,
    callable $currentUserId
): void {
```

(Keep all existing auth routes. `$currentUserId` is a closure returning `?int`.)

- [ ] **Step 9: Wire in `public/index.php`** — after building `$users`, add:

```php
use App\Invite\InviteController;
use App\Invite\InviteRepo;

$inviteRepo = new InviteRepo($pdo, $clock);
$inviteCtrl = new InviteController(
    $view, $csrf, $inviteRepo, $users, $clock,
    (string) $config->get('app_url', 'http://localhost')
);
$currentUserId = static fn(): ?int => $session->userId();
```

And update the routes invocation:

```php
(require dirname(__DIR__) . '/config/routes.php')($router, $auth, $googleCtrl, $inviteCtrl, $currentUserId);
```

- [ ] **Step 10: Run the full suite** — Run: `vendor/bin/phpunit`
Expected: all green (49 tests).

- [ ] **Step 11: Manual check** — serve with crush_dev and confirm `/invites/new` redirects to `/login` when logged out:

```bash
DB_DSN="mysql:host=127.0.0.1;dbname=crush_dev;charset=utf8mb4" DB_USER=root DB_PASS= APP_URL="http://127.0.0.1:8080" \
  php -S 127.0.0.1:8080 -t public &
sleep 1
curl -s -o /dev/null -w '%{http_code} -> %{redirect_url}\n' 127.0.0.1:8080/invites/new
kill %1
```
Expected: `302 -> /login` (auth guard works).

- [ ] **Step 12: Commit**

```bash
git add app/Invite/InviteController.php templates/invite/ config/routes.php public/index.php \
        tests/Invite/InviteControllerTest.php
git commit -m "feat(invite): sender dashboard + create flow + routes"
```

---

## Self-Review

**1. Spec coverage:** invites/date-options/responses tables (spec §4) — Task 1. State machine incl. instant vs confirm + pending_sender + expired/blocked (§5) — Task 2. Per-invite `is_anonymous`, `reveal_on_response`, `date_mode` set at creation (§3,5,6,8) — Tasks 3,5. Unguessable token (§14) — Task 3. Sender create flow + dashboard + shareable link (§6) — Task 5. Response storage incl. pickup fields (§7,9) — Task 4 (consumed by Plan 4's crush flow). Auth guard so only senders act, crush routes unauthenticated (§7,13) — Task 5 redirects when no user id. CSRF on POST `/invites` (§14) — Task 5. Theme assignment deferred to Plan 4 (theme_key nullable). Icons only — templates use no emojis.

**2. Placeholder scan:** No "TBD"; all steps have full code and exact commands. The `/i/{token}` public open route is intentionally Plan 4 (only `/i/{token}/created`, sender-scoped, exists here).

**3. Type consistency:** `InviteRepo::create(array): array`, `findById/findByToken(): ?array`, `listBySender(): array`, `updateStatus/setTheme/addDateOption(): void`, `dateOptions(): array`; `ResponseRepo::store(int,array): array`, `findByInvite(): ?array`; `InviteState::canTransition/assert/all`; `InviteController` methods take `?int $userId` and return `Response`. `Clock`, `Csrf`, `View`, `UserRepo` consumed as defined in Plans 1–2. Routes factory extended with `InviteController` + `callable $currentUserId`, matched by `public/index.php`.
