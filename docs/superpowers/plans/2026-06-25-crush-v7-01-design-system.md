# Crush v7 — Plan 1: Design System + Segmented Controls + Cuisine Chips Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Introduce a reusable component vocabulary (`.seg` segmented control, `.chips`/`.chip` choice chips, `.field`/`.label`) and apply it to the invite form — delivery, "when should they pick?", and vibe-mode become segmented pills; the cuisine input becomes a chip picker (pick one + Other).

**Architecture:** Component CSS lives in the shared `templates/layout.php` `<style>` (every sender-side card page inherits it). `templates/invite/new.php` swaps its bespoke radios/`<select>` for the components; `InviteController::create` reads a cuisine chip value plus an "Other" free-text field. Documented in `docs/design-guidelines.md`.

**Tech Stack:** PHP 8.1+, PDO MySQL, PHPUnit 10. No new Composer dependencies.

## Global Constraints

- PHP floor 8.1. PSR-4. No new Composer packages.
- **Icons only — never emojis.** All HTML `$e()`-escaped. POSTs validate CSRF. Run the suite **serially** (concurrent runs corrupt `crush_test`; if you see FK / "Duplicate schema_migrations" bursts, `DROP DATABASE crush_test; CREATE DATABASE crush_test CHARACTER SET utf8mb4;` and re-run alone).
- Progressive enhancement: controls submit correctly with JS off (real radios). Integration tests use MySQL `crush_test`. Production: `https://crush.didudi.com`.

## File Structure

- `templates/layout.php` (modify) — component CSS.
- `templates/invite/new.php` (modify) — segmented controls + cuisine chips.
- `app/Invite/InviteController.php` (modify) — cuisine `__other__` handling.

---

### Task 1: Component CSS in the shared layout

**Files:**
- Modify: `templates/layout.php`
- Test: `tests/View/DesignSystemTest.php`

**Interfaces:** `layout.php` `<style>` defines `.field`, `.label`, `.seg`, `.chips`, `.chip` (selected state via `:checked`, focus via `:focus-visible`).

- [ ] **Step 1: Write the failing test** — `tests/View/DesignSystemTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\View;

use App\Core\View;
use PHPUnit\Framework\TestCase;

final class DesignSystemTest extends TestCase
{
    public function test_layout_defines_component_classes(): void
    {
        // invite/dashboard renders through templates/layout.php (where the component CSS lives).
        $html = (new View(\dirname(__DIR__, 2) . '/templates'))->render('invite/dashboard', ['title' => 'x', 'invites' => [], 'appUrl' => '']);
        foreach (['.seg', '.chip', '.field', 'input:checked', ':focus-visible'] as $needle) {
            $this->assertStringContainsString($needle, $html, $needle);
        }
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter DesignSystemTest`
Expected: FAIL — classes absent.

- [ ] **Step 3: Add the component CSS to `templates/layout.php`** — inside the existing `<style>`, after the `.card--wide` rule, add:

```css
    .field { width:100%; padding:11px 13px; border-radius:12px; border:1px solid #e7d4ff; font-size:15px; font-family:inherit; background:#fff; color:inherit; }
    .label { display:block; font-size:13px; font-weight:600; opacity:.75; margin-bottom:6px; }
    .seg { display:inline-flex; flex-wrap:wrap; gap:6px; padding:4px; background:#f4ecff; border-radius:14px; }
    .seg label { position:relative; cursor:pointer; margin:0; }
    .seg input { position:absolute; inset:0; opacity:0; cursor:pointer; }
    .seg span { display:block; padding:9px 16px; border-radius:11px; font-weight:600; font-size:14px; color:#7a5e86; white-space:nowrap; transition:background .15s, color .15s, box-shadow .15s; }
    .seg input:checked + span { background:#fff; color:#ff3d8b; box-shadow:0 1px 3px rgba(157,123,255,.25); }
    .seg input:focus-visible + span { outline:2px solid #ff8fc0; outline-offset:1px; }
    .chips { display:flex; flex-wrap:wrap; gap:7px; }
    .chip { cursor:pointer; margin:0; }
    .chip input { position:absolute; opacity:0; width:0; height:0; }
    .chip span { display:inline-block; padding:7px 13px; border-radius:999px; border:1.5px solid #e7d4ff; font-size:13px; font-weight:600; color:#5a2a52; background:#fff; transition:background .15s, border-color .15s, color .15s; }
    .chip input:checked + span { background:#ff3d8b; border-color:#ff3d8b; color:#fff; }
    .chip input:focus-visible + span { outline:2px solid #ff8fc0; outline-offset:1px; }
```

- [ ] **Step 4: Run to verify it passes** — Run: `vendor/bin/phpunit --filter DesignSystemTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add templates/layout.php tests/View/DesignSystemTest.php
git commit -m "feat(ui): design-system component CSS (seg, chips, field) in layout"
```

---

### Task 2: Segmented controls on the invite form

**Files:**
- Modify: `templates/invite/new.php`
- Test: `tests/Invite/SegmentedFormTest.php`

**Interfaces:** Delivery, `date_mode` ("when should they pick?"), and `place_mode` render as `.seg` segmented controls (same `name`/`value` as before, so `InviteController` is unchanged). The `date_mode` `<select>` is replaced by a `.seg`.

- [ ] **Step 1: Write the failing test** — `tests/Invite/SegmentedFormTest.php`

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

final class SegmentedFormTest extends DatabaseTestCase
{
    public function test_form_uses_segmented_controls(): void
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

        $this->assertStringContainsString('class="seg"', $body);
        $this->assertStringContainsString('name="delivery"', $body);
        $this->assertStringContainsString('name="date_mode"', $body);     // now radios, not a <select>
        $this->assertStringContainsString('name="place_mode"', $body);
        $this->assertStringNotContainsString('<select name="date_mode"', $body);
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter SegmentedFormTest`
Expected: FAIL — no `.seg` / `date_mode` still a select.

- [ ] **Step 3: Convert the three controls in `templates/invite/new.php`** —

Delivery (replace the `<fieldset … How will you send it?>` block):

```php
    <div>
      <span class="label">How will you send it?</span>
      <div class="seg" role="radiogroup" aria-label="How will you send it?">
        <label><input type="radio" name="delivery" value="email" checked><span>Email it to them</span></label>
        <label><input type="radio" name="delivery" value="link"><span>I'll share the link</span></label>
      </div>
    </div>
```

"When should they pick?" (replace the `<label>When should they pick?</label>` + `<select name="date_mode">` block):

```php
    <div>
      <span class="label">When should they pick?</span>
      <div class="seg" role="radiogroup" aria-label="When should they pick?">
        <label><input type="radio" name="date_mode" value="instant" checked><span>Any time (final)</span></label>
        <label><input type="radio" name="date_mode" value="confirm"><span>They propose, I confirm</span></label>
      </div>
    </div>
```

Vibe mode (replace the two `place_mode` radio `<label>`s — keep the `#placePanel` div untouched):

```php
      <span class="label">A spot to suggest?</span>
      <div class="seg" role="radiogroup" aria-label="A spot to suggest?">
        <label><input type="radio" name="place_mode" value="open" checked><span>I'm open — they pick</span></label>
        <label><input type="radio" name="place_mode" value="focused"><span>Let's do a vibe</span></label>
      </div>
```

(Leave the existing `<script>` — it already reads `input[name="delivery"]:checked` and `input[name="place_mode"]:checked`, which still works with the segmented radios. The `is_anonymous`/`reveal_on_response` checkboxes and the email/name/message fields are unchanged.)

- [ ] **Step 4: Run the test, then the full suite (serially)** — Run: `vendor/bin/phpunit --filter SegmentedFormTest` then `vendor/bin/phpunit`
Expected: all green (the existing create/delivery/vibe tests post the same `delivery`/`date_mode`/`place_mode` values — radios submit identically to the old select/radios).

- [ ] **Step 5: Commit**

```bash
git add templates/invite/new.php tests/Invite/SegmentedFormTest.php
git commit -m "feat(invite): segmented controls for delivery, timing, and vibe mode"
```

---

### Task 3: Cuisine chips (pick one + Other)

**Files:**
- Modify: `templates/invite/new.php`, `app/Invite/InviteController.php`
- Test: `tests/Invite/CuisineChipsTest.php`

**Interfaces:** Each place option is a small card: name + maps link on top, then a `.chips` cuisine picker (common cuisines as single-select radios `opts[N][cuisine]`, plus an "Other" chip `value="__other__"` that reveals `opts[N][cuisine_custom]`). `InviteController::create` resolves cuisine: if `opts[N][cuisine] === '__other__'` use `opts[N][cuisine_custom]`, else the chip value (null when blank). The repeater clone renumbers `opts[N]` across name/cuisine/custom/url and resets selection.

- [ ] **Step 1: Write the failing test** — `tests/Invite/CuisineChipsTest.php`

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

final class CuisineChipsTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    private function controller(Csrf $csrf): InviteController
    {
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        return new InviteController(
            $view, $csrf, new InviteRepo($this->pdo(), $this->clock), new UserRepo($this->pdo(), $this->clock),
            $this->clock, 'http://localhost',
            new Postman(new SpyMailer(), new IcsBuilder($this->clock), new EmailTemplateRepo($this->pdo()), 'http://localhost'),
            new RateLimiter($this->pdo(), $this->clock), new BlockRepo($this->pdo(), $this->clock),
            new InvitePlaceRepo($this->pdo()), new LinkResolver(new FakeFetcher([])), new ShareTargetRepo($this->pdo())
        );
    }

    private function uid(string $e = 'u@x.test'): int
    {
        return (new UserRepo($this->pdo(), $this->clock))->create($e, 'U', 'magic')['id'];
    }

    public function test_form_has_cuisine_chips_and_other(): void
    {
        $body = $this->controller(new Csrf(new ArrayStore()))->showNew($this->uid())->body();
        $this->assertStringContainsString('class="chips"', $body);
        $this->assertStringContainsString('value="Italian"', $body);
        $this->assertStringContainsString('value="__other__"', $body);
        $this->assertStringContainsString('opts[0][cuisine_custom]', $body);
    }

    public function test_chip_cuisine_is_stored(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $uid = $this->uid('a@x.test');
        $this->controller($csrf)->create($uid, [
            'crush_email' => 'c@x.test', 'date_mode' => 'instant', 'place_mode' => 'focused', 'focus_vibe' => 'dinner',
            'opts' => [['name' => 'Tartine', 'cuisine' => 'Italian', 'url' => '']],
        ], $csrf->token());
        $inv = (new InviteRepo($this->pdo(), $this->clock))->listBySender($uid)[0];
        $this->assertSame('Italian', (new InvitePlaceRepo($this->pdo()))->groupedForInvite((int) $inv['id'])['dinner'][0]['cuisine']);
    }

    public function test_other_cuisine_uses_custom_text(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $uid = $this->uid('b@x.test');
        $this->controller($csrf)->create($uid, [
            'crush_email' => 'c@x.test', 'date_mode' => 'instant', 'place_mode' => 'focused', 'focus_vibe' => 'dinner',
            'opts' => [['name' => 'Pho House', 'cuisine' => '__other__', 'cuisine_custom' => 'Fusion', 'url' => '']],
        ], $csrf->token());
        $inv = (new InviteRepo($this->pdo(), $this->clock))->listBySender($uid)[0];
        $this->assertSame('Fusion', (new InvitePlaceRepo($this->pdo()))->groupedForInvite((int) $inv['id'])['dinner'][0]['cuisine']);
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter CuisineChipsTest`
Expected: FAIL.

- [ ] **Step 3: Restructure the place option in `templates/invite/new.php`** — replace the single `.iv-opt` row markup (inside `#optList`) with the card + chips, and update the `.iv-opt` CSS. New CSS (replace the `.iv-opt` rules in the page `<style>`):

```css
      .iv-opt { border:1px solid #eadcff; border-radius:14px; padding:10px; margin-top:8px; background:#fdfaff; }
      .iv-opt-top { display:grid; grid-template-columns:1.5fr 1.5fr auto; gap:8px; align-items:center; }
      .iv-opt-top input { min-width:0; }
      .iv-opt .rm { border:0; background:none; color:#b3243b; cursor:pointer; font-size:18px; line-height:1; }
      .iv-opt .chips { margin-top:8px; }
      .iv-other { margin-top:6px; }
      .iv-prev { font-size:12px; color:#7a5; margin:6px 0 0 2px; min-height:14px; }
      @media (max-width:560px){ .iv-opt-top { grid-template-columns:1fr auto; } .iv-opt-top .iv-u { grid-column:1 / -1; } }
```

New `#optList` row markup:

```php
        <div id="optList">
          <div class="iv-opt">
            <div class="iv-opt-top">
              <input class="field" type="text" name="opts[0][name]" placeholder="restaurant name">
              <input class="field iv-u" type="text" name="opts[0][url]" placeholder="maps link (optional)" data-maps>
              <button type="button" class="rm" aria-label="Remove">&times;</button>
            </div>
            <div class="chips">
              <?php foreach (['Italian','Japanese','Korean','Vietnamese','Thai','Chinese','Mexican','Indian','American','French','Dessert'] as $c): ?>
                <label class="chip"><input type="radio" name="opts[0][cuisine]" value="<?= $e($c) ?>"><span><?= $e($c) ?></span></label>
              <?php endforeach; ?>
              <label class="chip"><input type="radio" name="opts[0][cuisine]" value="__other__" data-other><span>Other</span></label>
            </div>
            <input class="field iv-other" type="text" name="opts[0][cuisine_custom]" placeholder="cuisine" hidden>
          </div>
        </div>
```

(Remove the old `<datalist id="cuisines">` block — chips replace it.) Add to the form `<script>` IIFE a handler that toggles the Other field, and update the existing clone handler to reset radios + the other field. Inside the IIFE, replace the add-click clone body and add the Other toggle:

```php
      function syncOther(scope){
        (scope || document).querySelectorAll('.iv-opt').forEach(function(opt){
          var other = opt.querySelector('input[data-other]');
          var field = opt.querySelector('.iv-other');
          if (field) field.hidden = !(other && other.checked);
        });
      }
      document.addEventListener('change', function(e){
        if (e.target && e.target.name && /\[cuisine\]$/.test(e.target.name)) syncOther(e.target.closest('.iv-opt'));
      });
      syncOther();
```

and the clone handler (replace the existing `if (add && list)` body):

```php
      if (add && list) add.addEventListener('click', function(){
        var n = list.children.length;
        var row = list.children[0].cloneNode(true);
        row.querySelectorAll('input').forEach(function(inp){
          if (inp.type === 'radio') inp.checked = false; else inp.value = '';
          inp.name = inp.name.replace(/opts\[\d+\]/, 'opts[' + n + ']');
        });
        var field = row.querySelector('.iv-other'); if (field) field.hidden = true;
        var pv = row.querySelector('.iv-prev'); if (pv) pv.remove();
        list.appendChild(row);
      });
```

- [ ] **Step 4: Resolve cuisine in `InviteController::create`** — in the `opts` loop, replace the cuisine read:

```php
                $cuisine = trim((string) ($opt['cuisine'] ?? ''));
                if ($cuisine === '__other__') {
                    $cuisine = trim((string) ($opt['cuisine_custom'] ?? ''));
                }
                $cuisine = $cuisine !== '' ? $cuisine : null;
```

(Keep the rest of the loop — `addOption(..., $cuisine, (int) $i)` — unchanged.)

- [ ] **Step 5: Run the tests, then the full suite (serially)** — Run: `vendor/bin/phpunit --filter "CuisineChipsTest|InviteVibeModeTest|InviteCuisineCreateTest"` then `vendor/bin/phpunit`
Expected: all green. (`InviteVibeModeTest`/`InviteCuisineCreateTest` post `opts[..][cuisine]=Italian` — a chip value — which still stores `Italian`. The `InviteCuisineCreateTest` form assertion checks `<datalist id="cuisines"` — **update it** to `class="chips"` since the datalist is gone.)

- [ ] **Step 6: Commit**

```bash
git add templates/invite/new.php app/Invite/InviteController.php tests/Invite/
git commit -m "feat(invite): cuisine chip picker (pick one + Other) per place"
```

---

## Self-Review

**1. Spec coverage:** Reusable component system (`.seg`/`.chips`/`.field`) in the shared layout + guidelines doc — Task 1 (doc already written at `docs/design-guidelines.md`). Delivery, "when", and vibe-mode as segmented pills — Task 2. Cuisine as chips (pick one + Other) per place — Task 3. Icons only; escaped; CSRF; progressive-enhanced (real radios) — throughout.

**2. Placeholder scan:** No "TBD". The `date_mode` select → segmented radios (same name/value, controller unchanged). Cuisine chips replace the datalist; `__other__` → custom text. Repeater clone resets radios + Other field. Full code throughout.

**3. Type consistency:** `layout.php` gains component CSS (no PHP signature change). `new.php` uses `delivery`/`date_mode`/`place_mode` radios (same names `InviteController::create` already reads) and `opts[N][cuisine]`/`opts[N][cuisine_custom]`. `InviteController::create` resolves `__other__` → `cuisine_custom`, else chip value; passes to the existing `addOption(...)`. No new controller params. Existing tests posting `opts[..][cuisine]=Italian` and `date_mode`/`delivery`/`place_mode` values keep working (radios submit identically).
