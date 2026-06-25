# Crush v8 — Plan 1: Analytics + Quick UX Fixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add the site-wide analytics widget to every page, and land three quick UX fixes: the crush-name field becomes required, the share-screen Copy button matches the input height (and looks nicer), and the "Let's do a specific vibe" panel slides open/closed (same smooth animation as the email collapse) instead of hard-toggling `display:none`.

**Architecture:** A shared `templates/partials/analytics.php` included before `</body>` in every full-HTML template (the `layout.php` shells + the standalone pages). Small template/CSS/JS edits to `invite/new.php` and `invite/created.php`.

**Tech Stack:** PHP 8.1+, PDO MySQL, PHPUnit 10. No new Composer dependencies.

## Global Constraints

- PHP floor 8.1. PSR-4. No new Composer packages.
- **Icons only — never emojis.** All HTML `$e()`-escaped. Run the suite **serially** (FK/"Duplicate schema_migrations" bursts = concurrent-run corruption → `DROP DATABASE crush_test; CREATE DATABASE crush_test CHARACTER SET utf8mb4;` and re-run alone).
- Respect `prefers-reduced-motion`. Production: `https://crush.didudi.com`.

## File Structure

- `templates/partials/analytics.php` (new) — the widget `<script>`.
- `templates/layout.php`, `templates/admin/layout.php`, `templates/landing/home.php`, `templates/respond/confirmed.php`, `templates/respond/themes/{bubblegum,love-letter,midnight}.php` (modify) — include the partial.
- `templates/invite/new.php` (modify) — required crush name + animated vibe panel.
- `templates/invite/created.php` (modify) — Copy button height.

---

### Task 1: Site-wide analytics widget

**Files:**
- Create: `templates/partials/analytics.php`
- Modify: `templates/layout.php`, `templates/admin/layout.php`, `templates/landing/home.php`, `templates/respond/confirmed.php`, `templates/respond/themes/bubblegum.php`, `templates/respond/themes/love-letter.php`, `templates/respond/themes/midnight.php`
- Test: `tests/View/AnalyticsTest.php`

**Interfaces:** `partials/analytics.php` outputs the exact widget script; every full-HTML template includes it before `</body>`.

- [ ] **Step 1: Write the failing test** — `tests/View/AnalyticsTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\View;

use App\Core\View;
use PHPUnit\Framework\TestCase;

final class AnalyticsTest extends TestCase
{
    private function view(): View
    {
        return new View(\dirname(__DIR__, 2) . '/templates');
    }

    public function test_layout_pages_include_widget(): void
    {
        $html = $this->view()->render('invite/dashboard', ['title' => 'x', 'invites' => [], 'appUrl' => '']);
        $this->assertStringContainsString('user-statistic-widget.js', $html);
        $this->assertStringContainsString('pk_crushf9wlh77uytkd', $html);
    }

    public function test_standalone_crush_page_includes_widget(): void
    {
        $html = $this->view()->render('respond/confirmed', [
            'title' => 'Crush', 'theme' => 'bubblegum', 'when' => 'soon', 'reveal' => null, 'wasAnonymous' => false,
        ]);
        $this->assertStringContainsString('user-statistic-widget.js', $html);
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter AnalyticsTest`
Expected: FAIL — widget absent.

- [ ] **Step 3: Create `templates/partials/analytics.php`**

```php
<script async
  src="https://users.didudi.com/user-statistic-widget.js"
  data-site-key="pk_crushf9wlh77uytkd"
  data-api-origin="https://users.didudi.com"></script>
```

- [ ] **Step 4: Include it before `</body>` in every full-HTML template** — add this line immediately before the closing `</body>` tag in each of: `templates/layout.php`, `templates/admin/layout.php`, `templates/landing/home.php`, `templates/respond/confirmed.php`, `templates/respond/themes/bubblegum.php`, `templates/respond/themes/love-letter.php`, `templates/respond/themes/midnight.php`:

```php
<?php include __DIR__ . '/../partials/analytics.php'; ?>
```

> Adjust the relative path per file: from `templates/layout.php` and `templates/landing/...`/`templates/respond/...` it is `__DIR__ . '/../partials/analytics.php'`; from `templates/respond/themes/*.php` it is `__DIR__ . '/../../partials/analytics.php'`; from `templates/admin/layout.php` it is `__DIR__ . '/../partials/analytics.php'`. Verify each include resolves.

- [ ] **Step 5: Run the test, then the full suite (serially)** — Run: `vendor/bin/phpunit --filter AnalyticsTest` then `vendor/bin/phpunit`
Expected: all green.

- [ ] **Step 6: Commit**

```bash
git add templates/partials/analytics.php templates/layout.php templates/admin/layout.php templates/landing/home.php templates/respond/ tests/View/AnalyticsTest.php
git commit -m "feat(analytics): site-wide user-statistic widget on every page"
```

---

### Task 2: Required crush name + animated vibe panel + Copy button height

**Files:**
- Modify: `templates/invite/new.php`, `templates/invite/created.php`
- Test: `tests/Invite/QuickFixesTest.php`

**Interfaces:** The crush-name input is `required`. `#placePanel` opens/closes with a `max-height`+`opacity` transition (`.show` class) instead of `display:none`. The Copy button on `invite/created.php` stretches to the input height.

- [ ] **Step 1: Write the failing test** — `tests/Invite/QuickFixesTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Invite;

use App\Auth\UserRepo;
use App\Core\ArrayStore;
use App\Core\Csrf;
use App\Core\View;
use App\Ics\IcsBuilder;
use App\Invite\InviteController;
use App\Invite\InvitePlaceRepo;
use App\Invite\InviteRepo;
use App\Mail\EmailTemplateRepo;
use App\Mail\Postman;
use App\Maps\LinkResolver;
use App\Security\BlockRepo;
use App\Security\RateLimiter;
use App\Share\ShareTargetRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FakeFetcher;
use Tests\Support\FrozenClock;
use Tests\Support\SpyMailer;

final class QuickFixesTest extends DatabaseTestCase
{
    public function test_crush_name_required_and_panel_animates(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $ctrl = new InviteController(
            $view, new Csrf(new ArrayStore()), new InviteRepo($this->pdo(), $clock), new UserRepo($this->pdo(), $clock),
            $clock, 'http://localhost',
            new Postman(new SpyMailer(), new IcsBuilder($clock), new EmailTemplateRepo($this->pdo()), 'http://localhost'),
            new RateLimiter($this->pdo(), $clock), new BlockRepo($this->pdo(), $clock),
            new InvitePlaceRepo($this->pdo()), new LinkResolver(new FakeFetcher([])), new ShareTargetRepo($this->pdo())
        );
        $uid = (new UserRepo($this->pdo(), $clock))->create('u@x.test', 'U', 'magic')['id'];
        $body = $ctrl->showNew($uid)->body();
        $this->assertMatchesRegularExpression('/name="crush_name"[^>]*\brequired\b/', $body);
        $this->assertStringNotContainsString('#placePanel.hide { display:none; }', $body);  // no hard toggle
        $this->assertStringContainsString('#placePanel.show', $body);                        // animated open
    }

    public function test_copy_button_stretches_to_input_height(): void
    {
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $html = $view->render('invite/created', ['title' => 'x', 'link' => 'https://c.app/i/t', 'invite' => ['crush_name' => 'A', 'crush_email' => 'a@x.test'], 'shareLinks' => []]);
        $this->assertStringContainsString('align-items:stretch', $html);   // copy row stretches the button
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter QuickFixesTest`
Expected: FAIL.

- [ ] **Step 3: Required crush name in `templates/invite/new.php`** — add `required` to the crush-name input:

```php
    <label>Their name
      <input type="text" name="crush_name" required value="<?= $val('crush_name') ?>"
             style="width:100%;padding:11px;border-radius:12px;border:1px solid #e7d4ff;">
    </label>
```

(Change the label text from "Their name (optional)" to "Their name".)

- [ ] **Step 4: Animate the vibe panel in `templates/invite/new.php`** — in the page `<style>`, replace the `#placePanel.hide { display:none; }` rule with:

```css
      #placePanel { overflow:hidden; max-height:0; opacity:0; transition:max-height .35s ease, opacity .3s ease; }
      #placePanel.show { max-height:1600px; opacity:1; }
      @media (prefers-reduced-motion: reduce) { #placePanel { transition:none; } }
```

Change the panel's initial class from `class="hide"` to no state class:

```php
      <div id="placePanel" style="margin-top:8px;">
```

and in the form `<script>` IIFE, change `syncMode` to toggle `.show`:

```php
      function syncMode(){
        var m = document.querySelector('input[name="place_mode"]:checked');
        if (panel) panel.classList.toggle('show', !!(m && m.value === 'focused'));
      }
```

- [ ] **Step 5: Fix the Copy button height in `templates/invite/created.php`** — change the copy row to stretch the button, and center the icon:

```php
  <div style="display:flex;gap:8px;align-items:stretch;">
    <input id="lnk" readonly value="<?= $e($link) ?>"
           style="flex:1;min-width:0;padding:11px;border-radius:12px;border:1px solid #e7d4ff;font-size:13px;" onclick="this.select()">
    <button type="button" id="copyBtn" aria-label="Copy link"
            style="display:flex;align-items:center;justify-content:center;padding:0 16px;border:0;border-radius:12px;background:#ff3d8b;color:#fff;font-weight:700;cursor:pointer;transition:transform .12s;">
      <svg width="18" height="18"><use href="#ic-copy"/></svg>
    </button>
    <span id="copiedMsg" aria-live="polite" style="align-self:center;opacity:0;color:#16a34a;font-weight:700;font-size:13px;transition:opacity .2s;">Copied!</span>
  </div>
```

- [ ] **Step 6: Run the test, then the full suite (serially)** — Run: `vendor/bin/phpunit --filter QuickFixesTest` then `vendor/bin/phpunit`
Expected: all green (existing create tests still pass — `crush_name` is supplied or optional at the controller level; `required` is a client-side hint, the controller already treats name as optional, so server tests that omit it are unaffected).

- [ ] **Step 7: Commit**

```bash
git add templates/invite/new.php templates/invite/created.php tests/Invite/QuickFixesTest.php
git commit -m "feat(invite): required crush name, animated vibe panel, taller copy button"
```

---

## Self-Review

**1. Spec coverage:** Analytics widget on every page (item 10) — Task 1. Required crush name (item 5) — Task 2. Vibe panel slide animation matching the email collapse (item 6) — Task 2. Copy button height/polish (item 7) — Task 2. Icons only; escaped; reduced-motion respected — throughout.

**2. Placeholder scan:** No "TBD". The analytics partial is included in all 7 full-HTML templates (path adjusted per depth). The vibe panel switches from `display:none` to a `max-height`+`opacity` transition (same family as the email collapse). Full code throughout.

**3. Type consistency:** No PHP signature changes. `partials/analytics.php` is a static include. `new.php` adds `required` (client hint; controller unchanged), swaps the panel toggle class `hide`→`show`. `created.php` copy row uses `align-items:stretch`. Tests render templates via `View`; `QuickFixesTest` asserts markup only.
