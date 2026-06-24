# Crush v6 — Plan 2: Two-Mode Vibe Form + Maps Preview + Delivery Animation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesign "Suggest a spot" into two modes — **Open** (the crush picks any vibe) or **Pick a vibe + several places** (one vibe, multiple restaurant options each with cuisine + maps link); add a **live maps preview** when a maps link is pasted; and **animate** the email field collapsing when the sender chooses to share the link themselves.

**Architecture:** Drop the one-place-per-vibe constraint (allow N options per vibe) + a `chosen_place_id` on responses. A mode toggle + a dynamic place repeater on the sender form; `InviteController::create` saves the options. A login-gated `/maps/preview` JSON endpoint backed by the existing `LinkResolver` drives a paste-preview. The crush page renders Open (6 chips) or Focused (one vibe + a radio list of place options), recording the chosen option.

**Tech Stack:** PHP 8.1+, PDO MySQL, PHPUnit 10. No new Composer dependencies.

## Global Constraints

- PHP floor 8.1. PSR-4. No new Composer packages. Prepared statements.
- **Icons only — never emojis.** All HTML `$e()`-escaped. POSTs validate CSRF. Run the suite **serially**.
- Maps resolution stays SSRF-guarded (`LinkResolver` + `SsrfGuard` allowlist: google.com/goo.gl/g.co). Integration tests use MySQL `crush_test`. Production: `https://crush.didudi.com`.

## File Structure

- `migrations/0015_place_options.sql` — drop unique, add `sort`, add `responses.chosen_place_id`.
- `app/Invite/InvitePlaceRepo.php` (modify) — `addOption`, `groupedForInvite`.
- `app/Invite/ResponseRepo.php` (modify) — store `chosen_place_id`.
- `templates/invite/new.php`, `app/Invite/InviteController.php` (modify) — mode toggle + repeater + delivery animation + save.
- `app/Maps/MapsController.php` (new), `config/routes.php`, `public/index.php` (modify) — `/maps/preview`.
- `templates/respond/_form.php`, `app/Respond/RespondController.php` (modify) — Open vs Focused crush UI.

---

### Task 1: Data layer — multiple options per vibe + chosen place

**Files:**
- Create: `migrations/0015_place_options.sql`
- Modify: `app/Invite/InvitePlaceRepo.php`, `app/Invite/ResponseRepo.php`
- Test: `tests/Invite/PlaceOptionsTest.php`

**Interfaces:**
- `invite_places`: unique `uq_place_invite_meal` dropped; `sort INT NOT NULL DEFAULT 0` added. `responses.chosen_place_id BIGINT UNSIGNED NULL`.
- `InvitePlaceRepo::addOption(int $inviteId, string $mealKey, string $name, ?string $url, ?string $rName, ?string $rAddr, ?string $clean, ?string $cuisine, int $sort = 0): int` — plain INSERT, returns the new id.
- `InvitePlaceRepo::groupedForInvite(int $inviteId): array` — `array<string, list<row>>` grouped by `meal_key`, ordered by `sort, id`.
- `ResponseRepo::create` stores `data['chosen_place_id'] ?? null`.

- [ ] **Step 1: Write the failing test** — `tests/Invite/PlaceOptionsTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Invite;

use App\Auth\UserRepo;
use App\Invite\InvitePlaceRepo;
use App\Invite\InviteRepo;
use App\Invite\ResponseRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class PlaceOptionsTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    private function invite(): array
    {
        $sender = (new UserRepo($this->pdo(), $this->clock))->create('s@x.test', 'Sue', 'magic')['id'];
        return (new InviteRepo($this->pdo(), $this->clock))->create([
            'sender_id' => $sender, 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => false, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'expires_at' => '2026-12-01 00:00:00',
        ]);
    }

    public function test_multiple_options_per_vibe(): void
    {
        $inv = $this->invite();
        $places = new InvitePlaceRepo($this->pdo());
        $id1 = $places->addOption((int) $inv['id'], 'dinner', 'Tartine', null, null, null, null, 'Italian', 0);
        $id2 = $places->addOption((int) $inv['id'], 'dinner', 'Octo', null, null, null, null, 'Tapas', 1);
        $this->assertNotSame($id1, $id2);

        $grouped = $places->groupedForInvite((int) $inv['id']);
        $this->assertCount(2, $grouped['dinner']);
        $this->assertSame('Tartine', $grouped['dinner'][0]['place_name']);   // ordered by sort
        $this->assertSame('Octo', $grouped['dinner'][1]['place_name']);
        $this->assertSame('Tapas', $grouped['dinner'][1]['cuisine']);
    }

    public function test_response_stores_chosen_place_id(): void
    {
        $inv = $this->invite();
        $places = new InvitePlaceRepo($this->pdo());
        $pid = $places->addOption((int) $inv['id'], 'dinner', 'Tartine', null, null, null, null, 'Italian', 0);
        $responses = new ResponseRepo($this->pdo(), $this->clock);
        $responses->store((int) $inv['id'], [
            'chosen_start' => '2026-02-10 19:00:00', 'chosen_end' => '2026-02-10 21:00:00',
            'meal_choice' => 'dinner', 'chosen_place_id' => $pid,
        ]);
        $row = $responses->findByInvite((int) $inv['id']);
        $this->assertSame($pid, (int) $row['chosen_place_id']);
    }
}
```

> If `ResponseRepo::findByInvite` is named differently (e.g. `forInvite`), use the existing finder — check the class.

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter PlaceOptionsTest`
Expected: FAIL — `addOption`/`groupedForInvite` undefined; `chosen_place_id` column missing.

- [ ] **Step 3: Write `migrations/0015_place_options.sql`**

```sql
ALTER TABLE invite_places DROP INDEX uq_place_invite_meal;
ALTER TABLE invite_places ADD COLUMN sort INT NOT NULL DEFAULT 0;
ALTER TABLE responses ADD COLUMN chosen_place_id BIGINT UNSIGNED NULL;
```

- [ ] **Step 4: Add `addOption` + `groupedForInvite` to `InvitePlaceRepo`**

```php
    public function addOption(
        int $inviteId, string $mealKey, string $name, ?string $url,
        ?string $rName, ?string $rAddr, ?string $clean, ?string $cuisine, int $sort = 0
    ): int {
        $this->pdo->prepare(
            'INSERT INTO invite_places
               (invite_id, meal_key, place_name, place_url, place_resolved_name, place_resolved_address, place_clean_url, cuisine, sort)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$inviteId, $mealKey, $name, $url, $rName, $rAddr, $clean, $cuisine, $sort]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string,array<int,array>> */
    public function groupedForInvite(int $inviteId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM invite_places WHERE invite_id = ? ORDER BY sort, id');
        $stmt->execute([$inviteId]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[$row['meal_key']][] = $row;
        }
        return $out;
    }
```

- [ ] **Step 5: Store `chosen_place_id` in `ResponseRepo`** — `ResponseRepo::store(int $inviteId, array $data)` builds its INSERT from the `private const FIELDS` list. Add `'chosen_place_id'` to that `FIELDS` array (append after `pickup_clean_url`). `store` then includes it automatically (`$data['chosen_place_id'] ?? null`). No other change to the method.

- [ ] **Step 6: Run the test, then the full suite (serially)** — Run: `vendor/bin/phpunit --filter PlaceOptionsTest` then `vendor/bin/phpunit`
Expected: green. The v4 `InvitePlaceCuisineTest`/`CuratedVibesTest` use `add()` (still present, now without the unique — `add()`'s ON DUPLICATE KEY UPDATE referenced the dropped unique key, so **change `add()` to a plain INSERT** as part of this task, or have `add()` delegate to `addOption(...,0)` and drop the upsert). Make `add()` delegate:

```php
    public function add(int $inviteId, string $mealKey, string $name, ?string $url, ?string $rName, ?string $rAddr, ?string $clean, ?string $cuisine = null): void
    {
        $this->addOption($inviteId, $mealKey, $name, $url, $rName, $rAddr, $clean, $cuisine, 0);
    }
```

Re-run until green (the curated-vibes tests create one place per vibe → still one row each; `forInvite`/`forMeal` still return a single row per key for those).

- [ ] **Step 7: Commit**

```bash
git add migrations/0015_place_options.sql app/Invite/InvitePlaceRepo.php app/Invite/ResponseRepo.php tests/Invite/PlaceOptionsTest.php
git commit -m "feat(places): multiple options per vibe + response chosen_place_id"
```

---

### Task 2: Sender form — two modes + repeater + delivery animation

**Files:**
- Modify: `templates/invite/new.php`, `app/Invite/InviteController.php`
- Test: `tests/Invite/InviteVibeModeTest.php`

**Interfaces:**
- The form has a `place_mode` toggle (`open` default | `focused`). Focused reveals a `focus_vibe` `<select>` (the 6 vibes) + a repeater of place rows (`opts[N][name]`, `opts[N][cuisine]`, `opts[N][url]`) with an "Add another place" button. The email label/field collapses (animated) when `delivery=link`.
- `InviteController::create`: when `place_mode === 'focused'` and `focus_vibe` is a valid meal key, save each non-empty `opts[]` row via `addOption(invite, focus_vibe, name, url, resolved…, cuisine, sort=index)`; when `open`, save no places. (Drop the old `places[vibe][name]` parsing.)

- [ ] **Step 1: Write the failing test** — `tests/Invite/InviteVibeModeTest.php`

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

final class InviteVibeModeTest extends DatabaseTestCase
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

    private function uid(): int
    {
        return (new UserRepo($this->pdo(), $this->clock))->create('u@x.test', 'U', 'magic')['id'];
    }

    public function test_form_has_mode_toggle_and_repeater(): void
    {
        $body = $this->controller(new Csrf(new ArrayStore()))->showNew($this->uid())->body();
        $this->assertStringContainsString('name="place_mode"', $body);
        $this->assertStringContainsString('name="focus_vibe"', $body);
        $this->assertStringContainsString('id="addPlace"', $body);          // repeater control
    }

    public function test_focused_mode_saves_multiple_options(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $uid = $this->uid();
        $this->controller($csrf)->create($uid, [
            'crush_email' => 'c@x.test', 'date_mode' => 'instant',
            'place_mode' => 'focused', 'focus_vibe' => 'dinner',
            'opts' => [['name' => 'Tartine', 'cuisine' => 'Italian', 'url' => ''], ['name' => 'Octo', 'cuisine' => 'Tapas', 'url' => '']],
        ], $csrf->token());

        $inv = (new InviteRepo($this->pdo(), $this->clock))->listBySender($uid)[0];
        $grouped = (new InvitePlaceRepo($this->pdo()))->groupedForInvite((int) $inv['id']);
        $this->assertCount(2, $grouped['dinner']);
    }

    public function test_open_mode_saves_no_places(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $uid = $this->uid();
        $this->controller($csrf)->create($uid, [
            'crush_email' => 'c@x.test', 'date_mode' => 'instant', 'place_mode' => 'open',
        ], $csrf->token());
        $inv = (new InviteRepo($this->pdo(), $this->clock))->listBySender($uid)[0];
        $this->assertSame([], (new InvitePlaceRepo($this->pdo()))->groupedForInvite((int) $inv['id']));
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter InviteVibeModeTest`
Expected: FAIL.

- [ ] **Step 3: Replace the place section in `templates/invite/new.php`** — swap the `<style>`+`<fieldset class iv-places>`+`<datalist>` block (the v4 place grid) for the mode toggle + repeater, and update the closure's `use(...)` if needed (the new markup uses only `$e`, `$meals` — already captured). Insert (replacing the old place fieldset/datalist):

```php
    <style>
      .iv-collapse { overflow:hidden; transition:max-height .3s ease, opacity .3s ease; max-height:120px; opacity:1; }
      .iv-collapse.hide { max-height:0; opacity:0; }
      .iv-opt { display:grid; grid-template-columns:1.4fr 1fr 1.6fr auto; gap:8px; align-items:center; margin-top:8px; }
      .iv-opt input { min-width:0; width:100%; padding:9px; border-radius:10px; border:1px solid #e7d4ff; }
      .iv-opt .rm { border:0;background:none;color:#b3243b;cursor:pointer;font-size:18px;line-height:1; }
      .iv-prev { font-size:12px; color:#7a5; margin:2px 0 0 2px; min-height:14px; }
      @media (max-width:560px){ .iv-opt{ grid-template-columns:1fr 1fr; } .iv-opt .iv-u{ grid-column:1/-1; } }
      #placePanel.hide { display:none; }
    </style>
    <fieldset style="border:0;padding:0;margin:0;">
      <legend style="font-size:13px;font-weight:600;opacity:.7;">A spot to suggest?</legend>
      <label style="display:flex;align-items:center;gap:6px;"><input type="radio" name="place_mode" value="open" checked> I'm open — they pick the vibe</label>
      <label style="display:flex;align-items:center;gap:6px;"><input type="radio" name="place_mode" value="focused"> Let's do a specific vibe</label>
      <div id="placePanel" class="hide" style="margin-top:8px;">
        <select name="focus_vibe" style="width:100%;padding:11px;border-radius:12px;border:1px solid #e7d4ff;">
          <?php foreach (($meals ?? []) as $meal): ?>
            <option value="<?= $e($meal['key']) ?>"><?= $e($meal['label']) ?></option>
          <?php endforeach; ?>
        </select>
        <div id="optList">
          <div class="iv-opt">
            <input type="text" name="opts[0][name]" placeholder="restaurant name">
            <input type="text" name="opts[0][cuisine]" placeholder="cuisine" list="cuisines">
            <input class="iv-u" type="text" name="opts[0][url]" placeholder="maps link (optional)" data-maps>
            <button type="button" class="rm" aria-label="Remove">&times;</button>
          </div>
        </div>
        <button type="button" id="addPlace" style="margin-top:6px;padding:8px 12px;border:1px dashed #e7d4ff;border-radius:10px;background:#fff;color:#ff3d8b;font-weight:600;cursor:pointer;">+ Add another place</button>
      </div>
    </fieldset>
    <datalist id="cuisines">
      <?php foreach (['Italian','Japanese','Korean','Vietnamese','Thai','Chinese','Mexican','Indian','American','French','Mediterranean','BBQ','Vegan','Dessert'] as $c): ?>
        <option value="<?= $e($c) ?>"></option>
      <?php endforeach; ?>
    </datalist>
```

Wrap the existing email label (lines 24-27) so it can collapse — change it to:

```php
    <div id="emailWrap" class="iv-collapse">
    <label>Their email
      <input type="email" id="crush_email" name="crush_email" value="<?= $val('crush_email') ?>"
             style="width:100%;padding:11px;border-radius:12px;border:1px solid #e7d4ff;">
    </label>
    </div>
```

Replace the trailing `<script>` (the delivery sync) with one that also animates the email collapse, drives the mode panel, the repeater, and (Task 3 will add) maps preview hooks:

```php
    <script>
    (function(){
      var email = document.getElementById('crush_email');
      var wrap = document.getElementById('emailWrap');
      function syncDelivery(){
        var m = document.querySelector('input[name="delivery"]:checked');
        var link = m && m.value === 'link';
        if (email) email.required = !link;
        if (wrap) wrap.classList.toggle('hide', !!link);
      }
      document.querySelectorAll('input[name="delivery"]').forEach(function(r){ r.addEventListener('change', syncDelivery); });
      syncDelivery();

      var panel = document.getElementById('placePanel');
      function syncMode(){
        var m = document.querySelector('input[name="place_mode"]:checked');
        if (panel) panel.classList.toggle('hide', !(m && m.value === 'focused'));
      }
      document.querySelectorAll('input[name="place_mode"]').forEach(function(r){ r.addEventListener('change', syncMode); });
      syncMode();

      var list = document.getElementById('optList');
      var add = document.getElementById('addPlace');
      if (add) add.addEventListener('click', function(){
        var n = list.children.length;
        var row = list.children[0].cloneNode(true);
        row.querySelectorAll('input').forEach(function(inp){
          inp.value = '';
          inp.name = inp.name.replace(/opts\[\d+\]/, 'opts[' + n + ']');
        });
        var pv = row.querySelector('.iv-prev'); if (pv) pv.remove();
        list.appendChild(row);
      });
      if (list) list.addEventListener('click', function(e){
        if (e.target.classList.contains('rm') && list.children.length > 1) e.target.closest('.iv-opt').remove();
      });
    })();
    </script>
```

- [ ] **Step 4: Rewrite the place-saving loop in `InviteController::create`** — replace the `MealOptions::CHOICES` place loop with mode-aware option saving:

```php
        $placeMode = ($input['place_mode'] ?? 'open') === 'focused' ? 'focused' : 'open';
        if ($placeMode === 'focused') {
            $vibe = (string) ($input['focus_vibe'] ?? '');
            if (MealOptions::isValid($vibe)) {
                foreach ((array) ($input['opts'] ?? []) as $i => $opt) {
                    $name = trim((string) ($opt['name'] ?? ''));
                    if ($name === '') {
                        continue;
                    }
                    $url = trim((string) ($opt['url'] ?? ''));
                    $cuisine = trim((string) ($opt['cuisine'] ?? '')) ?: null;
                    $resolved = $url !== '' ? $this->maps->resolve($url) : ['name' => null, 'address' => null, 'clean_url' => null];
                    $this->places->addOption(
                        (int) $invite['id'], $vibe, $name, $url !== '' ? $url : null,
                        $resolved['name'], $resolved['address'], $resolved['clean_url'], $cuisine, (int) $i
                    );
                }
            }
        }
```

- [ ] **Step 5: Update the v4 cuisine-create test** — `tests/Invite/InviteCuisineCreateTest.php` posts the old `places[dinner][...]` shape + asserts the form has `places[dinner][cuisine]`/`<datalist id="cuisines"`. Update it to the new shape: the form assertion → `name="focus_vibe"` + `<datalist id="cuisines"`; the create assertion → post `place_mode=focused`, `focus_vibe=dinner`, `opts[0][name]=Tartine`, `opts[0][cuisine]=Italian`, then assert `groupedForInvite(...)['dinner'][0]['cuisine'] === 'Italian'`. (The standalone `InvitePlaceCuisineTest` uses the repo directly and is unaffected.)

- [ ] **Step 6: Run the tests, then the full suite (serially)** — Run: `vendor/bin/phpunit --filter "InviteVibeModeTest|InviteCuisineCreateTest"` then `vendor/bin/phpunit`
Expected: all green.

- [ ] **Step 7: Commit**

```bash
git add templates/invite/new.php app/Invite/InviteController.php tests/Invite/
git commit -m "feat(invite): two-mode vibe form (open/focused) + delivery collapse animation"
```

---

### Task 3: Live maps preview endpoint + paste hook

**Files:**
- Create: `app/Maps/MapsController.php`
- Modify: `config/routes.php`, `public/index.php`, `templates/invite/new.php`
- Test: `tests/Maps/MapsControllerTest.php`

**Interfaces:**
- `MapsController::__construct(LinkResolver $maps)`; `preview(?int $userId, string $url): Response` — `401` JSON when logged out; resolves via `LinkResolver`; returns `Response::json(['name' => …, 'address' => …])` (200). Route `GET /maps/preview`. A JS hook on `[data-maps]` inputs (in the form script) fetches the endpoint on `change`/paste and writes the resolved name/address into a sibling `.iv-prev` line.

- [ ] **Step 1: Write the failing test** — `tests/Maps/MapsControllerTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Maps;

use App\Maps\LinkResolver;
use App\Maps\MapsController;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeFetcher;

final class MapsControllerTest extends TestCase
{
    private function controller(array $map = []): MapsController
    {
        return new MapsController(new LinkResolver(new FakeFetcher($map)));
    }

    public function test_requires_login(): void
    {
        $res = $this->controller()->preview(null, 'https://maps.app.goo.gl/x');
        $this->assertSame(401, $res->status());
    }

    public function test_resolves_place(): void
    {
        // FakeFetcher maps url => ['finalUrl'=>…, 'body'=>…]; resolver reads og:title + /maps/place/.
        $fetch = ['https://maps.app.goo.gl/x' => [
            'finalUrl' => 'https://www.google.com/maps/place/Octo+Tapas/@10,106,15z',
            'body' => '<meta property="og:title" content="Octo Tapas Restobar">',
        ]];
        $res = $this->controller($fetch)->preview(7, 'https://maps.app.goo.gl/x');
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('Octo Tapas', $res->body());
        $this->assertStringContainsString('application/json', implode(' ', $res->headers()));
    }

    public function test_blank_url_returns_empty(): void
    {
        $res = $this->controller()->preview(7, '');
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('null', $res->body());     // name/address null
    }
}
```

> Confirm the `FakeFetcher` constructor shape (URL → [finalUrl, body]) against `tests/Support/FakeFetcher.php`; adjust the fixture to match. Confirm `Response::json` exists; if not, build the JSON `Response` with `->withHeader('Content-Type', 'application/json')` and a `json_encode` body.

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter MapsControllerTest`
Expected: FAIL — class missing.

- [ ] **Step 3: Write `app/Maps/MapsController.php`**

```php
<?php
declare(strict_types=1);

namespace App\Maps;

use App\Core\Response;

final class MapsController
{
    public function __construct(private LinkResolver $maps) {}

    public function preview(?int $userId, string $url): Response
    {
        if ($userId === null) {
            return $this->json(['error' => 'auth'], 401);
        }
        $url = trim($url);
        if ($url === '') {
            return $this->json(['name' => null, 'address' => null]);
        }
        $r = $this->maps->resolve($url);
        return $this->json(['name' => $r['name'], 'address' => $r['address']]);
    }

    private function json(array $data, int $status = 200): Response
    {
        return (new Response((string) json_encode($data), $status))
            ->withHeader('Content-Type', 'application/json');
    }
}
```

> If `Response::json` already exists in the codebase, use it instead of the private helper.

- [ ] **Step 4: Wire the route + controller** — `config/routes.php` (add `MapsController $maps` to the factory signature + the route):

```php
    $router->add('GET', '/maps/preview', static fn(): Response => $maps->preview(
        $currentUserId(), is_string($_GET['url'] ?? null) ? $_GET['url'] : ''
    ));
```

`public/index.php`: build `$mapsCtrl = new MapsController($linkResolver);` (reuse the existing `LinkResolver` instance — find its variable; if it's inline, extract it) and pass `$mapsCtrl` into the routes factory.

- [ ] **Step 5: Add the paste-preview hook to the form script in `templates/invite/new.php`** — inside the existing form IIFE (before its closing `})();`), add:

```php
      function showPrev(input){
        var url = (input.value || '').trim();
        var row = input.closest('.iv-opt');
        var prev = row && row.querySelector('.iv-prev');
        if (!prev) { prev = document.createElement('div'); prev.className = 'iv-prev'; row && row.appendChild(prev); }
        if (!url) { prev.textContent = ''; return; }
        prev.textContent = 'Looking up…';
        fetch('/maps/preview?url=' + encodeURIComponent(url), { headers: { 'Accept': 'application/json' } })
          .then(function(r){ return r.json(); })
          .then(function(d){ prev.textContent = d && (d.name || d.address) ? ('Found: ' + (d.name || d.address)) : ''; })
          .catch(function(){ prev.textContent = ''; });
      }
      document.addEventListener('change', function(e){
        if (e.target && e.target.hasAttribute && e.target.hasAttribute('data-maps')) showPrev(e.target);
      });
```

(The `.iv-prev` line sits under each option row; cloned rows get their own via the delegated handler.)

- [ ] **Step 6: Run the test, then the full suite (serially)** — Run: `vendor/bin/phpunit --filter MapsControllerTest` then `vendor/bin/phpunit`
Expected: all green.

- [ ] **Step 7: Commit**

```bash
git add app/Maps/MapsController.php config/routes.php public/index.php templates/invite/new.php tests/Maps/MapsControllerTest.php
git commit -m "feat(maps): live place preview endpoint + paste lookup on the form"
```

---

### Task 4: Crush page — Open vs Focused (pick a place option)

**Files:**
- Modify: `app/Respond/RespondController.php`, `templates/respond/_form.php`
- Test: `tests/Respond/FocusedOptionsTest.php`

**Interfaces:**
- `RespondController::renderInvite` uses `InvitePlaceRepo::groupedForInvite`. It computes: `openMode` (no places) → all 6 vibes; otherwise the curated vibe(s). When exactly one vibe has **≥2** options → **focused**: pass `focusVibe` + its `options` list; `_form.php` renders the vibe label + a radio list of place options (`name="chosen_place"`, each with cuisine + map link), plus a hidden `meal_choice` = that vibe. `RespondController::submit` reads `chosen_place` (validates it belongs to the invite) and stores it as `chosen_place_id`.
- The 0-options and single-option-per-vibe and multi-vibe cases keep the v4 chip behavior.

- [ ] **Step 1: Write the failing test** — `tests/Respond/FocusedOptionsTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Respond;

use App\Auth\UserRepo;
use App\Invite\InvitePlaceRepo;
use App\Invite\InviteRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;
use Tests\Support\RespondControllerFactory;

final class FocusedOptionsTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    public function test_focused_shows_place_option_radios(): void
    {
        $sender = (new UserRepo($this->pdo(), $this->clock))->create('s@x.test', 'Sue', 'magic')['id'];
        $invite = (new InviteRepo($this->pdo(), $this->clock))->create([
            'sender_id' => $sender, 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => false, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'theme_key' => 'bubblegum', 'expires_at' => '2026-12-01 00:00:00',
        ]);
        $places = new InvitePlaceRepo($this->pdo());
        $places->addOption((int) $invite['id'], 'dinner', 'Tartine', null, null, null, null, 'Italian', 0);
        $places->addOption((int) $invite['id'], 'dinner', 'Octo', null, null, null, null, 'Tapas', 1);

        $body = RespondControllerFactory::make($this->pdo(), $this->clock)->open($invite['public_token'])->body();
        $this->assertStringContainsString('name="chosen_place"', $body);   // place-option radios
        $this->assertStringContainsString('Tartine', $body);
        $this->assertStringContainsString('Octo', $body);
        $this->assertStringContainsString('type="hidden" name="meal_choice" value="dinner"', $body);
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter FocusedOptionsTest`
Expected: FAIL.

- [ ] **Step 3: Compute focused mode in `RespondController::renderInvite`** — replace the v4 curated block with one using `groupedForInvite`:

```php
        $grouped = $this->places->groupedForInvite((int) $invite['id']);
        $curatedKeys = array_values(array_filter(
            array_map(static fn(array $m) => $m['key'], MealOptions::CHOICES),
            static fn(string $k) => isset($grouped[$k])
        ));
        $focusVibe = null;
        $focusOptions = [];
        $collapseMeal = null;
        $visibleMeals = MealOptions::CHOICES;
        if (count($curatedKeys) === 1) {
            $only = $curatedKeys[0];
            if (count($grouped[$only]) >= 2) {
                $focusVibe = $this->mealByKey($only);     // helper: find CHOICES row by key
                $focusOptions = $grouped[$only];
            } else {
                $collapseMeal = $this->mealByKey($only);
            }
            $visibleMeals = [$this->mealByKey($only)];
        } elseif (count($curatedKeys) >= 2) {
            $visibleMeals = array_values(array_filter(MealOptions::CHOICES, static fn(array $m) => isset($grouped[$m['key']])));
        }
        // first-option-per-vibe map for the existing chip reveal:
        $places = [];
        foreach ($grouped as $k => $rows) { $places[$k] = $rows[0]; }
```

and pass to the theme render: `'meals' => $visibleMeals, 'places' => $places, 'collapseMeal' => $collapseMeal, 'focusVibe' => $focusVibe, 'focusOptions' => $focusOptions`. Add a private helper `mealByKey(string $k): array` returning the `MealOptions::CHOICES` row (or a `['key'=>$k,'label'=>ucfirst($k),'icon'=>'ic-utensils']` fallback).

- [ ] **Step 4: Render focused options in `templates/respond/_form.php`** — add `$focusVibe = $focusVibe ?? null; $focusOptions = $focusOptions ?? [];` at the top, and a new branch **before** the `collapseMeal` branch:

```php
<?php if ($focusVibe !== null): ?>
  <input type="hidden" name="meal_choice" value="<?= $e($focusVibe['key']) ?>">
  <fieldset class="rf-meals">
    <legend><?= $e($focusVibe['label']) ?> — pick a spot</legend>
    <div class="rf-chips">
      <?php foreach ($focusOptions as $opt):
        $oname = $opt['place_resolved_name'] ?: $opt['place_name'];
        $ocuisine = $opt['cuisine'] ?? ''; $omap = $opt['place_clean_url'] ?? ''; ?>
        <label class="rf-chip">
          <input type="radio" name="chosen_place" value="<?= $e($opt['id']) ?>">
          <span><?= $e($oname) ?><?php if ($ocuisine !== ''): ?> · <?= $e($ocuisine) ?><?php endif; ?></span>
          <?php if (is_string($omap) && str_starts_with((string) $omap, 'http')): ?>
            <a href="<?= $e($omap) ?>" target="_blank" rel="noopener" style="font-size:11px;">map</a>
          <?php endif; ?>
        </label>
      <?php endforeach; ?>
    </div>
  </fieldset>
<?php elseif ($collapseMeal !== null): ?>
```

(turn the existing `<?php if ($collapseMeal !== null): ?>` into the `elseif` above; the rest of the chip/collapse logic is unchanged.)

- [ ] **Step 5: Record `chosen_place` in `RespondController::submit`** — after computing `$meal`, read + validate the chosen option and add it to the response data:

```php
        $chosenPlaceId = null;
        $cp = (int) ($input['chosen_place'] ?? 0);
        if ($cp > 0) {
            foreach ($this->places->groupedForInvite((int) $invite['id']) as $rows) {
                foreach ($rows as $row) {
                    if ((int) $row['id'] === $cp) { $chosenPlaceId = $cp; break 2; }
                }
            }
        }
```

and add `'chosen_place_id' => $chosenPlaceId,` to the existing `$this->responses->store((int) $invite['id'], [...])` data array (the `store` call near line 137).

- [ ] **Step 6: Run the test, then the full suite (serially)** — Run: `vendor/bin/phpunit --filter FocusedOptionsTest` then `vendor/bin/phpunit`
Expected: all green (the v4 `CuratedVibesTest` still passes: 0 places → all 6; 1 place → collapse; ≥2 vibes → chips).

- [ ] **Step 7: Commit**

```bash
git add app/Respond/RespondController.php templates/respond/_form.php tests/Respond/FocusedOptionsTest.php
git commit -m "feat(respond): focused vibe with selectable place options"
```

---

## Self-Review

**1. Spec coverage:** Two modes — Open (all vibes) vs Focused (one vibe + multiple place options each with cuisine + maps) (#2) — Tasks 1,2,4. Delivery email field animated collapse (#1) — Task 2. Maps live preview on paste (#6) — Task 3. Icons-only, escaped, CSRF, SSRF-guarded — throughout.

**2. Placeholder scan:** No "TBD". Backward-compat: `add()` delegates to `addOption`; 0/1/≥2-vibe crush cases preserved; old per-vibe invites still render via the first-option map. The `add()`-upsert removal is called out (the dropped unique key broke `ON DUPLICATE KEY`). Full code throughout.

**3. Type consistency:** `InvitePlaceRepo::addOption(int,string,string,?string,?string,?string,?string,?string,int): int`, `groupedForInvite(int): array<string,list<row>>`, `add(...)` delegates. `ResponseRepo::create` reads `chosen_place_id`. `MapsController::__construct(LinkResolver)`, `preview(?int,string): Response`. `RespondController::renderInvite` passes `focusVibe`/`focusOptions`/`collapseMeal`/`meals`/`places`; `submit` stores `chosen_place_id`. `_form.php` consumes `focusVibe`/`focusOptions` (defaulted). `InviteController::create` reads `place_mode`/`focus_vibe`/`opts[]`. Routes factory gains `MapsController` (matched in `public/index.php`).
