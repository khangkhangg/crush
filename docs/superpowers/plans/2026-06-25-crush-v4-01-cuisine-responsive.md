# Crush v4 — Plan 1: Responsive Form + Cuisine Tags + Curated Vibes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the invite-form overflow + make it responsive, add an optional cuisine tag per suggested spot, and show the crush only the vibes the sender curated (collapsing to a cuisine-led view when there is exactly one).

**Architecture:** A global box-sizing reset + an opt-in wide card in `layout.php`; a `cuisine` column on `invite_places`; a responsive CSS-grid place editor with a cuisine input on the sender form; and curated/collapsing vibe logic computed in `RespondController::renderInvite` and rendered by `respond/_form.php`.

**Tech Stack:** PHP 8.1+, PDO MySQL, PHPUnit 10. No new Composer dependencies.

## Global Constraints

- PHP floor 8.1. PSR-4. No new Composer packages. Prepared statements.
- **Icons only — never emojis.** All HTML `$e()`-escaped. POSTs validate CSRF.
- Run the suite **serially** (one `vendor/bin/phpunit` at a time — concurrent runs corrupt `crush_test`).
- Integration tests use MySQL `crush_test`. Production: `https://crush.didudi.com`.

## File Structure

- `templates/layout.php` (modify) — box-sizing reset, `.card--wide`, `$cardClass`.
- `migrations/0011_place_cuisine.sql` — `invite_places.cuisine`.
- `app/Invite/InvitePlaceRepo.php` (modify) — `add` takes `?string $cuisine`.
- `templates/invite/new.php` (modify) — wide card, responsive place grid, cuisine input + datalist.
- `app/Invite/InviteController.php` (modify) — read `places[{key}][cuisine]`.
- `app/Respond/RespondController.php` (modify) — compute `visibleMeals` + `collapseMeal`.
- `templates/respond/_form.php` (modify) — curated/collapsed rendering + cuisine.

---

### Task 1: Global box-sizing reset + opt-in wide card

**Files:**
- Modify: `templates/layout.php`
- Test: `tests/View/LayoutTest.php`

**Interfaces:**
- Produces: `layout.php` emits `*,*::before,*::after{box-sizing:border-box}` and a `.card--wide{width:min(94vw,640px)}` rule; a `$cardClass` template var (default `''`) is appended to the `<main class="card …">`.

- [ ] **Step 1: Write the failing test** — `tests/View/LayoutTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\View;

use App\Core\View;
use PHPUnit\Framework\TestCase;

final class LayoutTest extends TestCase
{
    private function view(): View
    {
        return new View(\dirname(__DIR__, 2) . '/templates');
    }

    public function test_layout_has_box_sizing_reset_and_wide_card_rule(): void
    {
        // landing/home renders through layout.php
        $html = $this->view()->render('landing/home', ['title' => 'Crush', 'csrf' => 'x']);
        $this->assertStringContainsString('box-sizing:border-box', $html);
        $this->assertStringContainsString('.card--wide', $html);
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter LayoutTest`
Expected: FAIL — strings absent.

- [ ] **Step 3: Modify `templates/layout.php`** — add `$cardClass` default at the very top, the reset + wide rule in `<style>`, and apply the class:

```php
<?php $cardClass = $cardClass ?? ''; ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $e($title ?? 'Crush') ?></title>
  <style>
    *,*::before,*::after { box-sizing:border-box; }
    :root { color-scheme: light; }
    body { font-family: ui-rounded, "Segoe UI", system-ui, sans-serif; margin:0;
           background:linear-gradient(160deg,#ffd9ec,#e7d4ff 55%,#d4f0ff); color:#5a2a52;
           -webkit-font-smoothing:antialiased; min-height:100vh; display:flex;
           align-items:center; justify-content:center; }
    .card { background:#fff; border-radius:24px; padding:32px; width:min(92vw,380px);
            box-shadow:0 1px 2px rgba(90,42,82,.08),0 12px 28px rgba(157,123,255,.22); }
    .card--wide { width:min(94vw,640px); }
  </style>
</head>
<body>
  <main class="card <?= $e($cardClass) ?>"><?= $body ?></main>
</body>
</html>
```

- [ ] **Step 4: Run to verify it passes** — Run: `vendor/bin/phpunit --filter LayoutTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add templates/layout.php tests/View/LayoutTest.php
git commit -m "feat(ui): global box-sizing reset + opt-in wide card"
```

---

### Task 2: cuisine column + InvitePlaceRepo

**Files:**
- Create: `migrations/0011_place_cuisine.sql`
- Modify: `app/Invite/InvitePlaceRepo.php`
- Test: `tests/Invite/InvitePlaceCuisineTest.php`

**Interfaces:**
- Produces: `invite_places.cuisine VARCHAR(40) NULL`; `InvitePlaceRepo::add(int,string,string,?string,?string,?string,?string,?string $cuisine = null): void` stores cuisine; `forInvite`/`forMeal` return it (already `SELECT *`).

- [ ] **Step 1: Write the failing test** — `tests/Invite/InvitePlaceCuisineTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Invite;

use App\Auth\UserRepo;
use App\Invite\InvitePlaceRepo;
use App\Invite\InviteRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class InvitePlaceCuisineTest extends DatabaseTestCase
{
    public function test_cuisine_round_trips(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $sender = (new UserRepo($this->pdo(), $clock))->create('s@x.test', 'Sue', 'magic')['id'];
        $invite = (new InviteRepo($this->pdo(), $clock))->create([
            'sender_id' => $sender, 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => false, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'expires_at' => '2026-02-01 00:00:00',
        ]);
        $places = new InvitePlaceRepo($this->pdo());
        $places->add((int) $invite['id'], 'dinner', 'Tartine', null, null, null, null, 'Italian');

        $row = $places->forMeal((int) $invite['id'], 'dinner');
        $this->assertSame('Italian', $row['cuisine']);

        // default null when omitted
        $places->add((int) $invite['id'], 'lunch', 'Noodle Bar', null, null, null, null);
        $this->assertNull($places->forMeal((int) $invite['id'], 'lunch')['cuisine']);
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter InvitePlaceCuisineTest`
Expected: FAIL — unknown column `cuisine` / `add` arity.

- [ ] **Step 3: Write `migrations/0011_place_cuisine.sql`**

```sql
ALTER TABLE invite_places ADD COLUMN cuisine VARCHAR(40) NULL;
```

- [ ] **Step 4: Modify `InvitePlaceRepo::add`** — add the trailing parameter + column:

```php
    public function add(
        int $inviteId,
        string $mealKey,
        string $placeName,
        ?string $placeUrl,
        ?string $resolvedName,
        ?string $resolvedAddress,
        ?string $cleanUrl,
        ?string $cuisine = null
    ): void {
        $this->pdo->prepare(
            'INSERT INTO invite_places
               (invite_id, meal_key, place_name, place_url, place_resolved_name, place_resolved_address, place_clean_url, cuisine)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               place_name = VALUES(place_name),
               place_url = VALUES(place_url),
               place_resolved_name = VALUES(place_resolved_name),
               place_resolved_address = VALUES(place_resolved_address),
               place_clean_url = VALUES(place_clean_url),
               cuisine = VALUES(cuisine)'
        )->execute([$inviteId, $mealKey, $placeName, $placeUrl, $resolvedName, $resolvedAddress, $cleanUrl, $cuisine]);
    }
```

- [ ] **Step 5: Run to verify it passes** — Run: `vendor/bin/phpunit --filter InvitePlaceCuisineTest`
Expected: PASS.

- [ ] **Step 6: Run the full suite (serially)** — Run: `vendor/bin/phpunit`
Expected: all green (existing `InvitePlacesCreateTest` still passes — `add`'s new param defaults null).

- [ ] **Step 7: Commit**

```bash
git add migrations/0011_place_cuisine.sql app/Invite/InvitePlaceRepo.php tests/Invite/InvitePlaceCuisineTest.php
git commit -m "feat(places): cuisine column + InvitePlaceRepo.add cuisine"
```

---

### Task 3: Sender form — responsive place grid + cuisine input

**Files:**
- Modify: `templates/invite/new.php`
- Modify: `app/Invite/InviteController.php`
- Test: `tests/Invite/InviteCuisineCreateTest.php`

**Interfaces:**
- Consumes: `InvitePlaceRepo::add(..., $cuisine)` (Task 2).
- Produces: `new.php` sets `$cardClass = 'card--wide'`, renders a responsive `.iv-place` grid with a `places[{key}][cuisine]` input + shared `<datalist id="cuisines">`; `InviteController::create` passes `$placeInput[$key]['cuisine']` (trimmed, null when blank) to `add`.

- [ ] **Step 1: Write the failing test** — `tests/Invite/InviteCuisineCreateTest.php`

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
use Tests\Support\DatabaseTestCase;
use Tests\Support\FakeFetcher;
use Tests\Support\FrozenClock;
use Tests\Support\SpyMailer;

final class InviteCuisineCreateTest extends DatabaseTestCase
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
        $places = new InvitePlaceRepo($this->pdo());
        return new InviteController(
            $view, $csrf, new InviteRepo($this->pdo(), $this->clock), new UserRepo($this->pdo(), $this->clock),
            $this->clock, 'http://localhost',
            new Postman(new SpyMailer(), new IcsBuilder($this->clock), new EmailTemplateRepo($this->pdo()), 'http://localhost'),
            new RateLimiter($this->pdo(), $this->clock), new BlockRepo($this->pdo(), $this->clock),
            $places, new LinkResolver(new FakeFetcher([]))
        );
    }

    public function test_form_has_cuisine_input_and_wide_card(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $uid = (new UserRepo($this->pdo(), $this->clock))->create('u@x.test', 'U', 'magic')['id'];
        $res = $this->controller($csrf)->showNew($uid);
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('card--wide', $res->body());
        $this->assertStringContainsString('places[dinner][cuisine]', $res->body());
        $this->assertStringContainsString('<datalist id="cuisines"', $res->body());
    }

    public function test_create_stores_cuisine(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $places = new InvitePlaceRepo($this->pdo());
        $uid = (new UserRepo($this->pdo(), $this->clock))->create('u2@x.test', 'U', 'magic')['id'];
        $res = $this->controller($csrf)->create($uid, [
            'crush_email' => 'c@x.test', 'date_mode' => 'instant',
            'places' => ['dinner' => ['name' => 'Tartine', 'cuisine' => 'Italian', 'url' => '']],
        ], $csrf->token());
        $this->assertSame(302, $res->status());

        $invite = (new InviteRepo($this->pdo(), $this->clock))->listBySender($uid)[0];
        $this->assertSame('Italian', $places->forMeal((int) $invite['id'], 'dinner')['cuisine']);
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter InviteCuisineCreateTest`
Expected: FAIL — no cuisine input / cuisine not stored.

- [ ] **Step 3: Rework the place section in `templates/invite/new.php`** — set the wide card at the very top (line 1), and replace the `<fieldset>` place block (lines 43–54) with a responsive grid + cuisine input + datalist. New line 1:

```php
<?php $error = $error ?? null; $old = $old ?? []; $csrf = $csrf ?? ''; $meals = $meals ?? []; $me = $me ?? null; $cardClass = 'card--wide'; ?>
```

Replace the `<fieldset>` (the "Suggest a spot for each vibe" block) with:

```php
    <style>
      .iv-places { display:flex; flex-direction:column; gap:10px; margin-top:8px; }
      .iv-place { display:grid; grid-template-columns:84px 1.4fr 1fr 1.4fr; gap:8px; align-items:center; }
      .iv-place > .iv-label { font-size:13px; opacity:.8; }
      .iv-place input { min-width:0; width:100%; padding:9px; border-radius:10px; border:1px solid #e7d4ff; }
      @media (max-width:560px) {
        .iv-place { grid-template-columns:1fr 1fr; }
        .iv-place > .iv-label { grid-column:1 / -1; font-weight:600; opacity:.7; }
        .iv-place .iv-url { grid-column:1 / -1; }
      }
    </style>
    <fieldset style="border:0;padding:0;margin:0;">
      <legend style="font-size:13px;font-weight:600;opacity:.7;">Suggest a spot for each vibe (optional)</legend>
      <div class="iv-places">
        <?php foreach (($meals ?? []) as $meal): ?>
          <div class="iv-place">
            <span class="iv-label"><?= $e($meal['label']) ?></span>
            <input type="text" name="places[<?= $e($meal['key']) ?>][name]" placeholder="restaurant name">
            <input type="text" name="places[<?= $e($meal['key']) ?>][cuisine]" placeholder="cuisine" list="cuisines">
            <input class="iv-url" type="text" name="places[<?= $e($meal['key']) ?>][url]" placeholder="maps link (optional)">
          </div>
        <?php endforeach; ?>
      </div>
    </fieldset>
    <datalist id="cuisines">
      <?php foreach (['Italian','Japanese','Korean','Vietnamese','Thai','Chinese','Mexican','Indian','American','French','Mediterranean','BBQ','Vegan','Dessert'] as $c): ?>
        <option value="<?= $e($c) ?>"></option>
      <?php endforeach; ?>
    </datalist>
```

(Leave the other fields as-is; they now fit thanks to the Task 1 box-sizing reset.)

- [ ] **Step 4: Read cuisine in `InviteController::create`** — in the `MealOptions::CHOICES` loop, capture cuisine and pass it to `add`:

```php
            $url = trim((string) ($placeInput[$key]['url'] ?? ''));
            $cuisine = trim((string) ($placeInput[$key]['cuisine'] ?? '')) ?: null;
            $resolved = $url !== '' ? $this->maps->resolve($url) : ['name' => null, 'address' => null, 'clean_url' => null];
            $this->places->add(
                (int) $invite['id'], $key, $name, $url !== '' ? $url : null,
                $resolved['name'], $resolved['address'], $resolved['clean_url'], $cuisine
            );
```

- [ ] **Step 5: Run the test** — Run: `vendor/bin/phpunit --filter InviteCuisineCreateTest`
Expected: PASS.

- [ ] **Step 6: Run the full suite (serially)** — Run: `vendor/bin/phpunit`
Expected: all green (the existing place-render regression test still finds `places[dinner][name]`).

- [ ] **Step 7: Commit**

```bash
git add templates/invite/new.php app/Invite/InviteController.php tests/Invite/InviteCuisineCreateTest.php
git commit -m "feat(invite): responsive place grid + cuisine input"
```

---

### Task 4: Crush page — curated + collapsing vibes with cuisine

**Files:**
- Modify: `app/Respond/RespondController.php`
- Modify: `templates/respond/_form.php`
- Test: `tests/Respond/CuratedVibesTest.php`

**Interfaces:**
- Consumes: `InvitePlaceRepo::forInvite` (returns `cuisine`).
- Produces: `RespondController::renderInvite` passes `meals` = the curated subset (vibes with a place; all 6 if none) and `collapseMeal` = the single curated meal row when exactly one, else `null`. `respond/_form.php`: with `collapseMeal` renders a hidden `meal_choice` + a cuisine-led line and no chips; otherwise renders chips for the visible meals, each carrying `data-cuisine`; the reveal line shows "Label · Cuisine at Spot".

- [ ] **Step 1: Write the failing test** — `tests/Respond/CuratedVibesTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Respond;

use App\Auth\UserRepo;
use App\Invite\InvitePlaceRepo;
use App\Invite\InviteRepo;
use App\Respond\RespondController;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;
use Tests\Support\RespondControllerFactory;

final class CuratedVibesTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    private function makeInvite(array $placedMeals): array
    {
        $sender = (new UserRepo($this->pdo(), $this->clock))->create('s@x.test', 'Sue', 'magic')['id'];
        $invite = (new InviteRepo($this->pdo(), $this->clock))->create([
            'sender_id' => $sender, 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => false, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'theme_key' => 'bubblegum', 'expires_at' => '2026-12-01 00:00:00',
        ]);
        $places = new InvitePlaceRepo($this->pdo());
        foreach ($placedMeals as $key => $cuisine) {
            $places->add((int) $invite['id'], $key, ucfirst($key) . ' Spot', null, null, null, null, $cuisine);
        }
        return $invite;
    }

    public function test_zero_curated_shows_all_six(): void
    {
        $invite = $this->makeInvite([]);
        $body = RespondControllerFactory::make($this->pdo(), $this->clock)->open($invite['public_token'])->body();
        foreach (['coffee', 'brunch', 'lunch', 'dinner', 'dessert', 'drinks'] as $k) {
            $this->assertStringContainsString('value="' . $k . '"', $body);
        }
    }

    public function test_multiple_curated_shows_only_those(): void
    {
        $invite = $this->makeInvite(['dinner' => 'Italian', 'coffee' => null]);
        $body = RespondControllerFactory::make($this->pdo(), $this->clock)->open($invite['public_token'])->body();
        $this->assertStringContainsString('value="dinner"', $body);
        $this->assertStringContainsString('value="coffee"', $body);
        $this->assertStringNotContainsString('value="lunch"', $body);
        $this->assertStringContainsString('Italian', $body);          // cuisine surfaced
    }

    public function test_single_curated_collapses_to_hidden_choice(): void
    {
        $invite = $this->makeInvite(['dinner' => 'Korean']);
        $body = RespondControllerFactory::make($this->pdo(), $this->clock)->open($invite['public_token'])->body();
        $this->assertStringContainsString('type="hidden" name="meal_choice" value="dinner"', $body);
        $this->assertStringNotContainsString('type="radio" name="meal_choice"', $body);
        $this->assertStringContainsString('Korean', $body);
    }
}
```

> **Test helper:** if a `Tests\Support\RespondControllerFactory` does not already exist, create it (a tiny factory that builds a `RespondController` with all real repos against `$pdo`, mirroring how the existing `tests/Respond/*` build the controller — copy the constructor wiring from `tests/Respond/RespondOpenTest.php`). Naming the construction in one helper keeps the three cases readable.

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter CuratedVibesTest`
Expected: FAIL — all 6 always shown; no hidden collapse.

- [ ] **Step 3: Compute curated vibes in `RespondController::renderInvite`** — replace the `meals`/`places` data it passes:

```php
        $placesByMeal = $this->places->forInvite((int) $invite['id']);
        $curated = array_values(array_filter(
            MealOptions::CHOICES,
            static fn(array $m): bool => isset($placesByMeal[$m['key']])
        ));
        $visibleMeals = $curated !== [] ? $curated : MealOptions::CHOICES;
        $collapseMeal = count($visibleMeals) === 1 ? $visibleMeals[0] : null;
```

and in the `$this->view->render('respond/themes/' . $key, [...])` data array set:

```php
            'meals'        => $visibleMeals,
            'places'       => $placesByMeal,
            'collapseMeal' => $collapseMeal,
```

(Remove the old `'meals' => MealOptions::CHOICES` / `'places' => $this->places->forInvite(...)` lines.)

- [ ] **Step 4: Render curated/collapsed in `templates/respond/_form.php`** — add the `collapseMeal` default + branch the meal section. Replace the `<fieldset class="rf-meals">…</fieldset>` block (lines 8–19) with:

```php
<?php $collapseMeal = $collapseMeal ?? null; ?>
<?php if ($collapseMeal !== null):
        $cp = $places[$collapseMeal['key']] ?? null;
        $cspot = $cp ? ($cp['place_resolved_name'] ?: $cp['place_name']) : '';
        $ccuisine = $cp['cuisine'] ?? null; ?>
  <input type="hidden" name="meal_choice" value="<?= $e($collapseMeal['key']) ?>">
  <div class="rf-collapsed">
    <?php if ($ccuisine): ?><strong class="rf-cuisine"><?= $e($ccuisine) ?></strong><?php endif; ?>
    <span><?= $e($collapseMeal['label']) ?><?php if ($cspot !== ''): ?> at <?= $e($cspot) ?><?php endif; ?></span>
  </div>
<?php else: ?>
  <fieldset class="rf-meals">
    <legend>What are you craving?</legend>
    <div class="rf-chips">
      <?php foreach ($meals as $m): $p = $places[$m['key']] ?? null;
            $plabel = $p ? ($p['place_resolved_name'] ?: $p['place_name']) : '';
            $pcuisine = $p['cuisine'] ?? ''; ?>
        <label class="rf-chip">
          <input type="radio" name="meal_choice" value="<?= $e($m['key']) ?>"
                 data-place="<?= $e($plabel) ?>" data-cuisine="<?= $e($pcuisine) ?>" data-label="<?= $e($m['label']) ?>">
          <svg class="rf-ic"><use href="#<?= $e($m['icon']) ?>"/></svg>
          <span><?= $e($m['label']) ?><?php if ($pcuisine !== ''): ?> · <?= $e($pcuisine) ?><?php endif; ?></span>
        </label>
      <?php endforeach; ?>
    </div>
  </fieldset>
<?php endif; ?>
```

and update the reveal `<script>` to include cuisine — change the `change` handler body to:

```js
      if (r.dataset.place) {
        var c = r.dataset.cuisine ? r.dataset.cuisine + ' · ' : '';
        p.textContent = c + r.dataset.label + ' at ' + r.dataset.place; p.style.display = 'block';
      } else { p.style.display = 'none'; }
```

- [ ] **Step 5: Run the test** — Run: `vendor/bin/phpunit --filter CuratedVibesTest`
Expected: PASS.

- [ ] **Step 6: Run the full suite (serially)** — Run: `vendor/bin/phpunit`
Expected: all green (existing respond tests: invites with no places still show all 6 chips, so submit-by-meal flows are unaffected).

- [ ] **Step 7: Commit**

```bash
git add app/Respond/RespondController.php templates/respond/_form.php tests/Respond/CuratedVibesTest.php tests/Support/RespondControllerFactory.php
git commit -m "feat(respond): curated + collapsing vibes with cuisine"
```

---

## Self-Review

**1. Spec coverage:** Layout overflow fixed (box-sizing reset + `min-width:0` grid inputs) + responsive (mobile stack) + wide desktop card (spec §A) — Tasks 1,3. Cuisine column + repo + form input + datalist + persisted (§B) — Tasks 2,3. Crush sees only curated vibes, collapses to cuisine-led at one, falls back to all 6 at zero (§B) — Task 4. Icons only; escaped; CSRF — throughout (no new POST surfaces here).

**2. Placeholder scan:** No "TBD". The `RespondControllerFactory` helper is created if absent (Task 4 Step 1 note), copying existing wiring. Full code for every change.

**3. Type consistency:** `InvitePlaceRepo::add(int,string,string,?string,?string,?string,?string,?string=null): void`; `RespondController::renderInvite` passes `meals`/`places`/`collapseMeal`; `_form.php` consumes `collapseMeal` (default null). `new.php` sets `$cardClass='card--wide'` consumed by `layout.php`'s new `$cardClass` var. `InviteController::create` reads `places[{key}][cuisine]`. Existing callers of `add` (without cuisine) keep working via the default.
