# Crush v3 — Plan 2: Locale + Language Columns Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Detect a visitor's language from `Accept-Language` (English/Vietnamese/Korean) and store a per-user / per-invite default language, ready for the localized emails.

**Architecture:** A pure `App\Core\Locale` helper (q-weighted `Accept-Language` parsing), plus nullable `users.lang` / `invites.lang` columns with `UserRepo::setLang` and `InviteRepo::create` accepting an optional `lang`. Wiring into the landing/respond flows happens in later phases.

**Tech Stack:** PHP 8.1+, PDO MySQL, PHPUnit 10. No new Composer dependencies.

## Global Constraints

- PHP floor 8.1. PSR-4. No new Composer packages. Prepared statements.
- Supported languages: **`en` (default), `vi`, `ko`**. Unknown/empty → `en`.
- Integration tests use MySQL `crush_test`.

## File Structure

- `app/Core/Locale.php` — detection + supported-language helper.
- `migrations/0009_lang.sql` — `users.lang`, `invites.lang`.
- `app/Auth/UserRepo.php` (modify) — `setLang`.
- `app/Invite/InviteRepo.php` (modify) — accept optional `lang` in `create`.

---

### Task 1: Locale helper + lang migration

**Files:**
- Create: `app/Core/Locale.php`
- Create: `migrations/0009_lang.sql`
- Test: `tests/Core/LocaleTest.php`
- Test: `tests/Core/LangSchemaTest.php`

**Interfaces:**
- Produces:
  - `App\Core\Locale` with `const SUPPORTED = ['en', 'vi', 'ko']`; `static detect(?string $acceptLanguage): string` (best q-weighted match on the primary subtag, fallback `'en'`); `static isSupported(string $lang): bool`.
  - `users.lang VARCHAR(5) NULL`, `invites.lang VARCHAR(5) NULL`.

- [ ] **Step 1: Write the failing tests** — `tests/Core/LocaleTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Core;

use App\Core\Locale;
use PHPUnit\Framework\TestCase;

final class LocaleTest extends TestCase
{
    public function test_detects_supported_primary_subtag(): void
    {
        $this->assertSame('vi', Locale::detect('vi-VN,vi;q=0.9,en;q=0.8'));
        $this->assertSame('ko', Locale::detect('ko'));
        $this->assertSame('en', Locale::detect('en-US,en;q=0.9'));
    }

    public function test_falls_back_to_en(): void
    {
        $this->assertSame('en', Locale::detect('fr-FR,fr;q=0.9'));
        $this->assertSame('en', Locale::detect(''));
        $this->assertSame('en', Locale::detect(null));
    }

    public function test_highest_q_wins(): void
    {
        $this->assertSame('vi', Locale::detect('en;q=0.5,vi;q=0.9'));
        $this->assertSame('ko', Locale::detect('en;q=0.3,fr;q=0.8,ko;q=0.95'));
    }

    public function test_is_supported(): void
    {
        $this->assertTrue(Locale::isSupported('vi'));
        $this->assertTrue(Locale::isSupported('en'));
        $this->assertFalse(Locale::isSupported('fr'));
    }
}
```

And `tests/Core/LangSchemaTest.php`:

```php
<?php
declare(strict_types=1);

namespace Tests\Core;

use Tests\Support\DatabaseTestCase;

final class LangSchemaTest extends DatabaseTestCase
{
    public function test_lang_columns_exist(): void
    {
        $cols = fn(string $t) => array_column($this->pdo()->query("SHOW COLUMNS FROM `{$t}`")->fetchAll(), 'Field');
        $this->assertContains('lang', $cols('users'));
        $this->assertContains('lang', $cols('invites'));
    }
}
```

- [ ] **Step 2: Run to verify they fail** — Run: `vendor/bin/phpunit --filter "LocaleTest|LangSchemaTest"`
Expected: FAIL — `Class "App\Core\Locale" not found` / column `lang` missing.

- [ ] **Step 3: Write `app/Core/Locale.php`**

```php
<?php
declare(strict_types=1);

namespace App\Core;

final class Locale
{
    public const SUPPORTED = ['en', 'vi', 'ko'];

    public static function detect(?string $acceptLanguage): string
    {
        if ($acceptLanguage === null || trim($acceptLanguage) === '') {
            return 'en';
        }
        $best = null;
        $bestQ = -1.0;
        foreach (explode(',', $acceptLanguage) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $bits = explode(';', $part);
            $tag = strtolower(trim($bits[0]));
            $q = 1.0;
            foreach (array_slice($bits, 1) as $param) {
                $param = trim($param);
                if (str_starts_with($param, 'q=')) {
                    $q = (float) substr($param, 2);
                }
            }
            $primary = explode('-', $tag)[0];
            if (self::isSupported($primary) && $q > $bestQ) {
                $best = $primary;
                $bestQ = $q;
            }
        }
        return $best ?? 'en';
    }

    public static function isSupported(string $lang): bool
    {
        return in_array($lang, self::SUPPORTED, true);
    }
}
```

- [ ] **Step 4: Write `migrations/0009_lang.sql`**

```sql
ALTER TABLE users   ADD COLUMN lang VARCHAR(5) NULL;
ALTER TABLE invites ADD COLUMN lang VARCHAR(5) NULL;
```

- [ ] **Step 5: Run to verify they pass** — Run: `vendor/bin/phpunit --filter "LocaleTest|LangSchemaTest"`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Core/Locale.php migrations/0009_lang.sql tests/Core/LocaleTest.php tests/Core/LangSchemaTest.php
git commit -m "feat(i18n): Locale detection + users/invites lang columns"
```

---

### Task 2: UserRepo::setLang + InviteRepo lang on create

**Files:**
- Modify: `app/Auth/UserRepo.php`
- Modify: `app/Invite/InviteRepo.php`
- Test: `tests/Auth/UserLangTest.php`
- Test: `tests/Invite/InviteLangTest.php`

**Interfaces:**
- Produces:
  - `UserRepo::setLang(int $id, string $lang): void` — sets `users.lang`.
  - `InviteRepo::create` stores `data['lang'] ?? null` into `invites.lang`; `findById`/`findByToken` return it.

- [ ] **Step 1: Write the failing tests** — `tests/Auth/UserLangTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Auth;

use App\Auth\UserRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class UserLangTest extends DatabaseTestCase
{
    public function test_set_lang(): void
    {
        $repo = new UserRepo($this->pdo(), new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z')));
        $user = $repo->create('a@x.test', 'Ann', 'magic');
        $this->assertNull($user['lang']);
        $repo->setLang($user['id'], 'vi');
        $this->assertSame('vi', $repo->findById($user['id'])['lang']);
    }
}
```

And `tests/Invite/InviteLangTest.php`:

```php
<?php
declare(strict_types=1);

namespace Tests\Invite;

use App\Auth\UserRepo;
use App\Invite\InviteRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class InviteLangTest extends DatabaseTestCase
{
    public function test_create_stores_lang(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $sender = (new UserRepo($this->pdo(), $clock))->create('s@x.test', 'Sue', 'magic')['id'];
        $invites = new InviteRepo($this->pdo(), $clock);

        $withLang = $invites->create([
            'sender_id' => $sender, 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => false, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'lang' => 'ko', 'expires_at' => '2026-02-01 00:00:00',
        ]);
        $this->assertSame('ko', $invites->findById($withLang['id'])['lang']);

        $noLang = $invites->create([
            'sender_id' => $sender, 'crush_email' => 'c2@x.test', 'crush_name' => 'Dee',
            'is_anonymous' => false, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'expires_at' => '2026-02-01 00:00:00',
        ]);
        $this->assertNull($invites->findById($noLang['id'])['lang']);
    }
}
```

- [ ] **Step 2: Run to verify they fail** — Run: `vendor/bin/phpunit --filter "UserLangTest|InviteLangTest"`
Expected: FAIL — undefined `setLang` / `lang` not stored.

- [ ] **Step 3: Add `setLang` to `app/Auth/UserRepo.php`** (after `setPasswordHash`)

```php
    public function setLang(int $id, string $lang): void
    {
        $this->pdo->prepare('UPDATE users SET lang = ? WHERE id = ?')->execute([$lang, $id]);
    }
```

- [ ] **Step 4: Add `lang` to `InviteRepo::create`** — in `app/Invite/InviteRepo.php`, extend the INSERT to include `lang`. Update the column list, the placeholders, and the values array:

```php
        $stmt = $this->pdo->prepare(
            'INSERT INTO invites
             (public_token, sender_id, crush_email, crush_name, is_anonymous,
              reveal_on_response, date_mode, status, theme_key, message, lang, created_at, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
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
            $data['lang'] ?? null,
            $this->clock->now()->format('Y-m-d H:i:s'),
            $data['expires_at'],
        ]);
```

(The `cast()` helper and `findById`/`findByToken` already return all columns including `lang` — no change needed there.)

- [ ] **Step 5: Run to verify they pass** — Run: `vendor/bin/phpunit --filter "UserLangTest|InviteLangTest"`
Expected: PASS.

- [ ] **Step 6: Run the full suite** — Run: `vendor/bin/phpunit`
Expected: all green (existing `InviteRepoTest` still passes — `lang` defaults to null when not provided).

- [ ] **Step 7: Commit**

```bash
git add app/Auth/UserRepo.php app/Invite/InviteRepo.php tests/Auth/UserLangTest.php tests/Invite/InviteLangTest.php
git commit -m "feat(i18n): UserRepo.setLang + InviteRepo lang on create"
```

---

## Self-Review

**1. Spec coverage:** `Locale` detection en/vi/ko from `Accept-Language`, fallback en (spec §5) — Task 1. `users.lang` + `invites.lang` columns (§5,§7) — Task 1. `setLang` + invite `lang` storage (§5) — Task 2. Wiring of detection into landing/respond is deferred to Plan v3-5; email language selection to Plan v3-3 — both consume these primitives.

**2. Placeholder scan:** No "TBD". Full code throughout; the `InviteRepo::create` change shows the complete rewritten INSERT.

**3. Type consistency:** `Locale::detect(?string): string`, `isSupported(string): bool`, `SUPPORTED` array; `UserRepo::setLang(int,string): void`; `InviteRepo::create(array): array` now reads `data['lang']`. Consumes `DatabaseTestCase`, `Clock`, `InviteState::SENT` as defined. No call-site signature changes (create still takes one array; `lang` is optional).
