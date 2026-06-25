# Crush v9 — Plan 2: Translate the Sender Flow Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Wrap the sender-side screens (invite form, dashboard, share/"invite ready" screen, and the crush's answer-confirmation) in `$t()` and seed casual translations for all of those strings in the 9 non-English languages, so a non-English visitor sees the whole sender flow in their language.

**Architecture:** The i18n pipeline already exists (v9-1): `$t('English')` is injected into every template and falls back to English. This plan only (1) wraps the user-visible strings in `$t()` and (2) seeds `ui_translations` rows (migration 0018) for those strings.

**Tech Stack:** PHP 8.1+, PDO MySQL, PHPUnit 10. No new Composer dependencies.

## Global Constraints

- PHP floor 8.1. PSR-4. No new Composer packages.
- **Icons only — never emojis.** Every translated string is output as `$e($t('…'))` (translate then escape). Run the suite **serially** (FK/"Duplicate schema_migrations" bursts = concurrent-run corruption → reset `crush_test`).
- Languages to seed: vi, es, zh, hi, pt, fr, ko, ja, th (en is the key/fallback). **Casual, human, "17-year-old" tone** — natural spoken phrasing, not stiff/formal. Do NOT translate proper nouns ("Crush", cuisine names like "Italian"). Production: `https://crush.didudi.com`.

## File Structure

- `templates/invite/new.php`, `templates/invite/dashboard.php`, `templates/invite/created.php`, `templates/respond/confirmed.php` (modify) — wrap strings.
- `migrations/0018_seed_sender_flow.sql` (new) — translations.

---

### Task 1: Wrap the sender-flow strings in `$t()`

**Files:**
- Modify: `templates/invite/new.php`, `templates/invite/dashboard.php`, `templates/invite/created.php`, `templates/respond/confirmed.php`
- Test: `tests/I18n/SenderFlowI18nTest.php`

**Interfaces:** Every user-visible string in these four templates is rendered `$e($t('English string'))` (and placeholders via `$t`). The exact English text is preserved as the key. Meal-vibe labels render `$e($t($meal['label']))`. Dynamic data (names, links, dates) is **not** wrapped — only the static UI chrome.

**Canonical strings to wrap** (use this exact English so keys match the seed):
- new.php: `Send a crush invite` · `How will you send it?` · `Email it to them` · `I'll share the link` · `Their email` · `Their name` · `A little message (optional)` · `When should they pick?` · `Any time (final)` · `They propose, I confirm` · `Send anonymously (a secret admirer)` · `Reveal me after they respond` · `A spot to suggest?` · `I'm open — they pick` · `Let's do a vibe` · `restaurant name` · `cuisine` · `maps link (optional)` · `Other` · `Add another place` · `Create my invite` · `Creating as` · `not you?` · `use another email` · `log in` · the 6 meal labels `Coffee`/`Brunch`/`Lunch`/`Dinner`/`Dessert`/`Drinks` (via `$t($meal['label'])`)
- dashboard.php: `Your invites` · `Send a new crush invite` · `No invites yet. Send your first one above.` · badges `Waiting`/`Answered`/`Needs you`/`Confirmed`/`Closed` (wrap the label in the `$badge` map output) · `View` · `Copy link` · `A secret crush`
- created.php: `Your invite is ready` · `Share this private link with` · `Share your private invite link:` · `Share` · `Back to your invites`
- confirmed.php: `Your answer is on its way` · `You picked` · `Your secret admirer is` · `It's a date with` · `They'll be in touch soon.`

> Placeholders (`placeholder="…"`) wrap as `placeholder="<?= $e($t('restaurant name')) ?>"`. For the `+ Add another place` button keep the `+ ` literal and wrap only `Add another place`. Leave the JS-side `Copied!`/`Hide`/`Looking up…` strings as-is for now (JS strings are a later pass — this task is the PHP-rendered chrome).

- [ ] **Step 1: Write the failing test** — `tests/I18n/SenderFlowI18nTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\I18n;

use App\Core\View;
use App\I18n\Translator;
use Tests\Support\DatabaseTestCase;

final class SenderFlowI18nTest extends DatabaseTestCase
{
    private function viewVi(): View
    {
        // seed a couple of vi strings, then render with the vi translator
        $ins = $this->pdo()->prepare('INSERT INTO ui_translations (lang, `key`, value) VALUES (?, ?, ?)');
        $ins->execute(['vi', 'Your invites', 'Lời mời của bạn']);
        $ins->execute(['vi', 'Your invite is ready', 'Lời mời của bạn đã sẵn sàng']);
        return new View(\dirname(__DIR__, 2) . '/templates', new Translator($this->pdo(), 'vi'));
    }

    public function test_dashboard_is_translated(): void
    {
        $html = $this->viewVi()->render('invite/dashboard', ['title' => 'x', 'invites' => [], 'appUrl' => '']);
        $this->assertStringContainsString('Lời mời của bạn', $html);          // "Your invites" wrapped
        $this->assertStringNotContainsString('>Your invites<', $html);
    }

    public function test_created_is_translated(): void
    {
        $html = $this->viewVi()->render('invite/created', ['title' => 'x', 'link' => 'https://c.app/i/t', 'invite' => ['crush_name' => 'A', 'crush_email' => 'a@x.test'], 'shareLinks' => []]);
        $this->assertStringContainsString('Lời mời của bạn đã sẵn sàng', $html); // "Your invite is ready" wrapped
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter SenderFlowI18nTest`
Expected: FAIL — strings still hardcoded English.

- [ ] **Step 3: Wrap the strings** in the four templates per the canonical list, using `$e($t('…'))` for text and `$t('…')` inside `placeholder="…"`. For `dashboard.php`'s `$badge` map, wrap the label at output: `$e($t($label))`. For `confirmed.php`/`new.php` keep the surrounding markup; only the human text becomes `$t()`-wrapped.

- [ ] **Step 4: Run the test, then the full suite (serially)** — Run: `vendor/bin/phpunit --filter SenderFlowI18nTest` then `vendor/bin/phpunit`
Expected: green (other tests assert on dynamic content or English defaults, which are unchanged when no translation is seeded — `$t` returns English).

> If an existing test asserts an exact English UI string in these templates (e.g. a dashboard/created render test), it still passes because those tests use a `null`/`en` translator → `$t` returns English. Only update a test if it breaks.

- [ ] **Step 5: Commit**

```bash
git add templates/invite/new.php templates/invite/dashboard.php templates/invite/created.php templates/respond/confirmed.php tests/I18n/SenderFlowI18nTest.php
git commit -m "feat(i18n): wrap the sender-flow screens in \$t()"
```

---

### Task 2: Seed sender-flow translations (9 languages)

**Files:**
- Create: `migrations/0018_seed_sender_flow.sql`
- Test: `tests/I18n/SenderFlowSeedTest.php`

**Interfaces:** `ui_translations` gains rows for every canonical sender-flow string (Task 1 list) in vi, es, zh, hi, pt, fr, ko, ja, th — natural, casual phrasing.

- [ ] **Step 1: Write the failing test** — `tests/I18n/SenderFlowSeedTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\I18n;

use App\I18n\Translator;
use Tests\Support\DatabaseTestCase;

final class SenderFlowSeedTest extends DatabaseTestCase
{
    public function test_core_sender_strings_translated_in_all_langs(): void
    {
        $keys = ['Send a crush invite', 'Create my invite', 'Your invites', 'Your invite is ready', 'Your answer is on its way', 'Dinner'];
        foreach (['vi', 'es', 'zh', 'hi', 'pt', 'fr', 'ko', 'ja', 'th'] as $lang) {
            $tr = new Translator($this->pdo(), $lang);
            foreach ($keys as $k) {
                $val = $tr->t($k);
                $this->assertNotSame($k, $val, "$lang missing translation for: $k");   // a real translation, not the English fallback
                $this->assertNotSame('', trim($val));
            }
        }
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter SenderFlowSeedTest`
Expected: FAIL — no rows yet.

- [ ] **Step 3: Write `migrations/0018_seed_sender_flow.sql`** — one `INSERT … ON DUPLICATE KEY UPDATE value = VALUES(value)` covering **every** canonical sender-flow string from Task 1 × the 9 languages. Translate naturally and casually (a 17-year-old's voice in each language), not literally/formally. Keep proper nouns untranslated. SQL-escape apostrophes (`''`). utf8mb4. Structure:

```sql
INSERT INTO ui_translations (lang, `key`, value) VALUES
-- "Send a crush invite"
('vi','Send a crush invite','Gửi lời mời hẹn hò'),
('es','Send a crush invite','Manda una invitación a tu crush'),
('zh','Send a crush invite','发送一封约会邀请'),
('hi','Send a crush invite','अपने crush को डेट पर बुलाओ'),
('pt','Send a crush invite','Manda um convite pro seu crush'),
('fr','Send a crush invite','Envoie une invitation à ton crush'),
('ko','Send a crush invite','크러시에게 데이트 신청 보내기'),
('ja','Send a crush invite','好きな人にデートのお誘いを送る'),
('th','Send a crush invite','ส่งคำชวนเดตให้คนที่แอบชอบ'),
-- … repeat the 9-row block for EVERY canonical string (How will you send it?, Email it to them,
--    I'll share the link, Their email, Their name, A little message (optional), When should they pick?,
--    Any time (final), They propose I confirm, Send anonymously (a secret admirer), Reveal me after they respond,
--    A spot to suggest?, I'm open — they pick, Let's do a vibe, restaurant name, cuisine, maps link (optional),
--    Other, Add another place, Create my invite, Creating as, not you?, use another email, log in,
--    Coffee, Brunch, Lunch, Dinner, Dessert, Drinks,
--    Your invites, Send a new crush invite, No invites yet. Send your first one above.,
--    Waiting, Answered, Needs you, Confirmed, Closed, View, Copy link, A secret crush,
--    Your invite is ready, Share this private link with, Share your private invite link:, Share, Back to your invites,
--    Your answer is on its way, You picked, Your secret admirer is, It's a date with, They'll be in touch soon.)
ON DUPLICATE KEY UPDATE value = VALUES(value);
```

> Produce the full multilingual SQL (every key × 9 langs). Match the English keys to Task 1 **exactly** (same punctuation/casing) or the lookup misses. Verify the file parses (`php -r` or a dry mysql import) before committing.

- [ ] **Step 4: Run the test, then the full suite (serially)** — Run: `vendor/bin/phpunit --filter "SenderFlowSeedTest|SenderFlowI18nTest"` then `vendor/bin/phpunit`
Expected: all green.

- [ ] **Step 5: Commit**

```bash
git add migrations/0018_seed_sender_flow.sql tests/I18n/SenderFlowSeedTest.php
git commit -m "feat(i18n): seed sender-flow translations (9 languages)"
```

---

## Self-Review

**1. Spec coverage:** The sender flow (invite form, dashboard, share screen, answer confirmation) is wrapped in `$t()` (Task 1) and translated into the 9 non-English languages (Task 2), so a non-English visitor sees the whole flow localized. Icons only; every string `$e($t())`-escaped; casual tone. The responder + profile flow is v9-3.

**2. Placeholder scan:** No "TBD" in the plan structure — but Task 2's migration must be filled with the COMPLETE multilingual SQL (every canonical key × 9 langs), not the abbreviated comment. JS-side strings ("Copied!"/"Hide") are deferred (a later JS-i18n pass). Untranslated long-tail safely falls back to English.

**3. Type consistency:** No PHP signature changes — templates call the already-injected `$t`/`$e`. Keys in `0018` match the Task 1 wrapped English exactly. `ON DUPLICATE KEY UPDATE` keeps it idempotent + co-exists with the v9-1 `0017` landing/about seed.
