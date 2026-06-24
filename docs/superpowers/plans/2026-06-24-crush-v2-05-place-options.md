# Crush v2 — Plan 5: Place Options Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let the sender attach **one restaurant per meal vibe** at invite creation (name + optional Maps link, unfurled by the existing resolver). When the crush picks a vibe, the matched spot reveals; the chosen vibe's place flows into the response so the sender's confirmation + `.ics` show "Dinner at Tartine."

**Architecture:** A new `invite_places` table (unique per invite+meal) and `InvitePlaceRepo`. `InviteController::create` resolves + stores the places (via the existing `LinkResolver`). `RespondController::open` passes places to the invite page (meal chips carry the matched spot, revealed via tiny inline JS); `submit` copies the chosen vibe's place into the response `pickup_*` fields when the crush didn't type their own.

**Tech Stack:** PHP 8.1+, PDO MySQL, PHPUnit 10.

## Global Constraints

- PHP floor 8.1. PSR-4. No new Composer packages.
- **Icons only — never emojis.** All HTML output escaped via `App\Core\e()`.
- Prepared statements only. One place per (invite, meal vibe). A place link is run through `LinkResolver` (SSRF-guarded) on save.
- The crush's own typed pickup, when present, takes precedence over the vibe's place.
- Integration tests use MySQL `crush_test`. Local dev serves on **port 8888**.

## File Structure

- `migrations/0007_invite_places.sql` — `invite_places`.
- `app/Invite/InvitePlaceRepo.php` — place data access.
- `app/Invite/InviteController.php` (modify) — store places on create.
- `templates/invite/new.php` (modify) — per-vibe "add a spot" inputs.
- `app/Respond/RespondController.php` (modify) — pass places to open; copy chosen place on submit.
- `templates/respond/show.php` (modify) — reveal matched spot on vibe pick.
- `config/routes.php` / `public/index.php` (modify) — wiring.

---

### Task 1: invite_places migration + InvitePlaceRepo

**Files:**
- Create: `migrations/0007_invite_places.sql`
- Create: `app/Invite/InvitePlaceRepo.php`
- Test: `tests/Invite/InvitePlaceRepoTest.php`

**Interfaces:**
- Consumes: `\PDO`, `App\Auth\UserRepo` + `App\Invite\InviteRepo` (test setup).
- Produces:
  - `invite_places` table: `id, invite_id, meal_key, place_name, place_url, place_resolved_name, place_resolved_address, place_clean_url`; `UNIQUE(invite_id, meal_key)`; FK `invite_id → invites(id) ON DELETE CASCADE`.
  - `App\Invite\InvitePlaceRepo` with `__construct(\PDO $pdo)`:
    - `add(int $inviteId, string $mealKey, string $placeName, ?string $placeUrl, ?string $resolvedName, ?string $resolvedAddress, ?string $cleanUrl): void` — upsert on (invite_id, meal_key).
    - `forInvite(int $inviteId): array` — rows keyed by `meal_key`.
    - `forMeal(int $inviteId, string $mealKey): ?array`.

- [ ] **Step 1: Write the failing test** — `tests/Invite/InvitePlaceRepoTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Invite;

use App\Auth\UserRepo;
use App\Invite\InvitePlaceRepo;
use App\Invite\InviteRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class InvitePlaceRepoTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    private function inviteId(): int
    {
        $sender = (new UserRepo($this->pdo(), $this->clock))->create('s@x.test', 'Sue', 'magic')['id'];
        return (new InviteRepo($this->pdo(), $this->clock))->create([
            'sender_id' => $sender, 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => false, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'expires_at' => '2026-02-01 00:00:00',
        ])['id'];
    }

    public function test_add_forinvite_formeal(): void
    {
        $repo = new InvitePlaceRepo($this->pdo());
        $id = $this->inviteId();

        $repo->add($id, 'dinner', 'Tartine', 'https://maps.app.goo.gl/x', 'Tartine Bakery', '1 Main St', 'https://maps.google.com/?q=Tartine');
        $repo->add($id, 'coffee', 'Blue Bottle', null, null, null, null);

        $byMeal = $repo->forInvite($id);
        $this->assertArrayHasKey('dinner', $byMeal);
        $this->assertArrayHasKey('coffee', $byMeal);
        $this->assertSame('Tartine', $byMeal['dinner']['place_name']);
        $this->assertSame('Tartine Bakery', $byMeal['dinner']['place_resolved_name']);

        $this->assertSame('Blue Bottle', $repo->forMeal($id, 'coffee')['place_name']);
        $this->assertNull($repo->forMeal($id, 'lunch'));
    }

    public function test_add_is_upsert_per_meal(): void
    {
        $repo = new InvitePlaceRepo($this->pdo());
        $id = $this->inviteId();
        $repo->add($id, 'dinner', 'First', null, null, null, null);
        $repo->add($id, 'dinner', 'Second', null, null, null, null);

        $this->assertSame('Second', $repo->forMeal($id, 'dinner')['place_name']);
        $this->assertCount(1, $repo->forInvite($id));
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter InvitePlaceRepoTest`
Expected: FAIL — table `invite_places` not found.

- [ ] **Step 3: Write `migrations/0007_invite_places.sql`**

```sql
CREATE TABLE IF NOT EXISTS invite_places (
  id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  invite_id              BIGINT UNSIGNED NOT NULL,
  meal_key               VARCHAR(32)  NOT NULL,
  place_name             VARCHAR(191) NOT NULL,
  place_url              VARCHAR(1024) NULL,
  place_resolved_name    VARCHAR(191) NULL,
  place_resolved_address VARCHAR(512) NULL,
  place_clean_url        VARCHAR(1024) NULL,
  UNIQUE KEY uq_place_invite_meal (invite_id, meal_key),
  CONSTRAINT fk_place_invite FOREIGN KEY (invite_id) REFERENCES invites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- [ ] **Step 4: Write `app/Invite/InvitePlaceRepo.php`**

```php
<?php
declare(strict_types=1);

namespace App\Invite;

final class InvitePlaceRepo
{
    public function __construct(private \PDO $pdo) {}

    public function add(
        int $inviteId,
        string $mealKey,
        string $placeName,
        ?string $placeUrl,
        ?string $resolvedName,
        ?string $resolvedAddress,
        ?string $cleanUrl
    ): void {
        $this->pdo->prepare(
            'INSERT INTO invite_places
               (invite_id, meal_key, place_name, place_url, place_resolved_name, place_resolved_address, place_clean_url)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               place_name = VALUES(place_name),
               place_url = VALUES(place_url),
               place_resolved_name = VALUES(place_resolved_name),
               place_resolved_address = VALUES(place_resolved_address),
               place_clean_url = VALUES(place_clean_url)'
        )->execute([$inviteId, $mealKey, $placeName, $placeUrl, $resolvedName, $resolvedAddress, $cleanUrl]);
    }

    /** @return array<string,array> keyed by meal_key */
    public function forInvite(int $inviteId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM invite_places WHERE invite_id = ?');
        $stmt->execute([$inviteId]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[$row['meal_key']] = $row;
        }
        return $out;
    }

    public function forMeal(int $inviteId, string $mealKey): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM invite_places WHERE invite_id = ? AND meal_key = ?');
        $stmt->execute([$inviteId, $mealKey]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}
```

- [ ] **Step 5: Run to verify it passes** — Run: `vendor/bin/phpunit --filter InvitePlaceRepoTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add migrations/0007_invite_places.sql app/Invite/InvitePlaceRepo.php tests/Invite/InvitePlaceRepoTest.php
git commit -m "feat(places): invite_places migration + InvitePlaceRepo"
```

---

### Task 2: Store places on invite create

**Files:**
- Modify: `app/Invite/InviteController.php`
- Modify: `templates/invite/new.php`
- Modify: `public/index.php`
- Test: `tests/Invite/InvitePlacesCreateTest.php`

**Interfaces:**
- `InviteController::__construct` gains a trailing `InvitePlaceRepo $places` and `LinkResolver $maps` (for resolving place links). `showNew` passes `meals => MealOptions::CHOICES` to the form. `create`, after creating the invite, reads `places[<meal_key>][name]` / `places[<meal_key>][url]` from input; for each non-empty name (where the meal key is valid), resolves the url via `LinkResolver` and stores via `InvitePlaceRepo::add`.

- [ ] **Step 1: Write the failing test** — `tests/Invite/InvitePlacesCreateTest.php`

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
use App\Mail\Postman;
use App\Maps\LinkResolver;
use App\Security\BlockRepo;
use App\Security\RateLimiter;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FakeFetcher;
use Tests\Support\FrozenClock;
use Tests\Support\SpyMailer;

final class InvitePlacesCreateTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    public function test_create_stores_resolved_places_per_vibe(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $invites = new InviteRepo($this->pdo(), $this->clock);
        $users = new UserRepo($this->pdo(), $this->clock);
        $postman = new Postman(new SpyMailer(), new IcsBuilder($this->clock), $view, 'http://localhost');
        $placeRepo = new InvitePlaceRepo($this->pdo());
        $fetcher = new FakeFetcher([
            'https://maps.app.goo.gl/dinner' => [
                'finalUrl' => 'https://www.google.com/maps/place/Tartine+Bakery/@1,2,17z',
                'body' => '<meta property="og:title" content="Tartine Bakery">',
            ],
        ]);
        $ctrl = new InviteController(
            $view, $csrf, $invites, $users, $this->clock, 'http://localhost', $postman,
            new RateLimiter($this->pdo(), $this->clock), new BlockRepo($this->pdo(), $this->clock),
            $placeRepo, new LinkResolver($fetcher)
        );

        $sender = $users->create('sue@x.test', 'Sue', 'magic')['id'];
        $ctrl->create($sender, [
            'crush_email' => 'c@x.test', 'date_mode' => 'instant',
            'places' => [
                'dinner' => ['name' => 'Tartine', 'url' => 'https://maps.app.goo.gl/dinner'],
                'coffee' => ['name' => 'Blue Bottle', 'url' => ''],
                'lunch'  => ['name' => '', 'url' => ''], // empty -> skipped
            ],
        ], $csrf->token());

        $invite = $invites->listBySender($sender)[0];
        $places = $placeRepo->forInvite((int) $invite['id']);
        $this->assertArrayHasKey('dinner', $places);
        $this->assertArrayHasKey('coffee', $places);
        $this->assertArrayNotHasKey('lunch', $places);
        $this->assertSame('Tartine Bakery', $places['dinner']['place_resolved_name']); // resolved
        $this->assertSame('Blue Bottle', $places['coffee']['place_name']);
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter InvitePlacesCreateTest`
Expected: FAIL — `InviteController::__construct()` too few arguments.

- [ ] **Step 3: Modify `app/Invite/InviteController.php`** — add imports `use App\Invite\InvitePlaceRepo;`, `use App\Maps\LinkResolver;`, `use App\Respond\MealOptions;`. Add trailing constructor params `private InvitePlaceRepo $places, private LinkResolver $maps,`. In `showNew`, pass meals to the form:

```php
    public function showNew(?int $userId): Response
    {
        if ($userId === null) {
            return $this->redirect('/login');
        }
        return $this->renderForm();
    }
```

Update `renderForm` to include meals:

```php
    private function renderForm(?string $error = null, array $old = [], int $status = 200): Response
    {
        return Response::html($this->view->render('invite/new', [
            'title' => 'New invite',
            'csrf'  => $this->csrf->token(),
            'error' => $error,
            'old'   => $old,
            'meals' => MealOptions::CHOICES,
        ]), $status);
    }
```

In `create`, after the `addDateOption` loop and before `$this->postman->sendInvite($invite)`, add place handling:

```php
        $placeInput = (array) ($input['places'] ?? []);
        foreach (MealOptions::CHOICES as $meal) {
            $key = $meal['key'];
            $name = trim((string) ($placeInput[$key]['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $url = trim((string) ($placeInput[$key]['url'] ?? ''));
            $resolved = $url !== '' ? $this->maps->resolve($url) : ['name' => null, 'address' => null, 'clean_url' => null];
            $this->places->add(
                (int) $invite['id'], $key, $name, $url !== '' ? $url : null,
                $resolved['name'], $resolved['address'], $resolved['clean_url']
            );
        }
```

- [ ] **Step 4: Modify `templates/invite/new.php`** — before the submit button, add an optional "suggest a spot" section listing each meal vibe:

```php
    <fieldset style="border:0;padding:0;margin:0;">
      <legend style="font-size:13px;font-weight:600;opacity:.7;">Suggest a spot for each vibe (optional)</legend>
      <?php foreach (($meals ?? []) as $meal): ?>
        <div style="display:flex;gap:8px;margin-top:8px;align-items:center;">
          <span style="min-width:72px;font-size:13px;opacity:.8;"><?= $e($meal['label']) ?></span>
          <input type="text" name="places[<?= $e($meal['key']) ?>][name]" placeholder="restaurant name"
                 style="flex:1;padding:9px;border-radius:10px;border:1px solid #e7d4ff;">
          <input type="text" name="places[<?= $e($meal['key']) ?>][url]" placeholder="maps link (optional)"
                 style="flex:1;padding:9px;border-radius:10px;border:1px solid #e7d4ff;">
        </div>
      <?php endforeach; ?>
    </fieldset>
```

(Add `$meals = $meals ?? [];` to the optional-var defaults at the top of `new.php`.)

- [ ] **Step 5: Run the test** — Run: `vendor/bin/phpunit --filter InvitePlacesCreateTest`
Expected: PASS.

- [ ] **Step 6: Wire in `public/index.php`** — build `$invitePlaceRepo = new InvitePlaceRepo($pdo);` and `$linkResolver` already exists (from v1 Plan 5). Pass `$invitePlaceRepo, $linkResolver` as the two new trailing args to `new InviteController(...)`.

- [ ] **Step 7: Update existing InviteController test constructions** — `tests/Invite/InviteControllerTest.php`, `tests/Invite/InviteRateLimitTest.php` construct `InviteController`; add the two trailing args `new InvitePlaceRepo($this->pdo()), new LinkResolver(new FakeFetcher([]))` (add imports). Run `vendor/bin/phpunit` until green.

- [ ] **Step 8: Commit**

```bash
git add app/Invite/InviteController.php templates/invite/new.php public/index.php tests/Invite/
git commit -m "feat(places): sender attaches a spot per vibe on create"
```

---

### Task 3: Reveal the matched spot + copy chosen place into the response

**Files:**
- Modify: `app/Respond/RespondController.php`
- Modify: `templates/respond/show.php`
- Modify: `public/index.php`
- Test: `tests/Respond/RespondPlaceTest.php`

**Interfaces:**
- `RespondController::__construct` gains a trailing `InvitePlaceRepo $places`. `open` passes `places => InvitePlaceRepo::forInvite` to the template (meal chips carry the matched spot, revealed via inline JS). `submit`: after computing the chosen `meal_choice`, if the crush typed **no** pickup (`pickup_raw` empty) and a place exists for that vibe, copy the place into the response `pickup_name`/`pickup_address`/`pickup_clean_url`.

- [ ] **Step 1: Write the failing test** — `tests/Respond/RespondPlaceTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Respond;

use App\Auth\MagicLink;
use App\Auth\UserRepo;
use App\Core\ArrayStore;
use App\Core\Csrf;
use App\Core\View;
use App\Ics\IcsBuilder;
use App\Invite\InvitePlaceRepo;
use App\Invite\InviteRepo;
use App\Invite\ResponseRepo;
use App\Mail\Postman;
use App\Maps\LinkResolver;
use App\Respond\CrushOnboarder;
use App\Respond\RespondController;
use App\Theme\AbEventRepo;
use App\Theme\ABAssigner;
use App\Theme\ThemeRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FakeFetcher;
use Tests\Support\FrozenClock;
use Tests\Support\SpyMailer;

final class RespondPlaceTest extends DatabaseTestCase
{
    private FrozenClock $clock;
    private Csrf $csrf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $this->csrf = new Csrf(new ArrayStore());
    }

    private function controller(InvitePlaceRepo $places): RespondController
    {
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $invites = new InviteRepo($this->pdo(), $this->clock);
        $users = new UserRepo($this->pdo(), $this->clock);
        $postman = new Postman(new SpyMailer(), new IcsBuilder($this->clock), $view, 'http://localhost');
        $onboarder = new CrushOnboarder($users, new MagicLink($this->pdo(), $users, $this->clock, 900), $postman, 'http://localhost');
        return new RespondController(
            $view, $this->csrf, $invites, new ResponseRepo($this->pdo(), $this->clock), $users,
            new ABAssigner(new ThemeRepo($this->pdo()), $invites, fn(int $m) => 0),
            new AbEventRepo($this->pdo(), $this->clock), $this->clock,
            new LinkResolver(new FakeFetcher([])), $postman, $onboarder, $places
        );
    }

    public function test_chosen_vibe_place_copied_into_response_when_no_typed_pickup(): void
    {
        $places = new InvitePlaceRepo($this->pdo());
        $ctrl = $this->controller($places);
        $invites = new InviteRepo($this->pdo(), $this->clock);
        $sender = (new UserRepo($this->pdo(), $this->clock))->create('sue@x.test', 'Sue', 'magic')['id'];
        $invite = $invites->create([
            'sender_id' => $sender, 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => false, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'expires_at' => '2026-02-01 00:00:00',
        ]);
        $places->add((int) $invite['id'], 'dinner', 'Tartine', null, 'Tartine Bakery', '1 Main St', 'https://maps.google.com/?q=Tartine');

        $ctrl->submit($invite['public_token'], [
            'chosen_start' => '2026-02-10T19:00', 'meal_choice' => 'dinner', // no pickup_raw
        ], $this->csrf->token());

        $stored = (new ResponseRepo($this->pdo(), $this->clock))->findByInvite((int) $invite['id']);
        $this->assertSame('Tartine Bakery', $stored['pickup_name']);
        $this->assertStringContainsString('maps.google.com', $stored['pickup_clean_url']);
    }

    public function test_typed_pickup_takes_precedence_over_vibe_place(): void
    {
        $places = new InvitePlaceRepo($this->pdo());
        $ctrl = $this->controller($places);
        $invites = new InviteRepo($this->pdo(), $this->clock);
        $sender = (new UserRepo($this->pdo(), $this->clock))->create('sue@x.test', 'Sue', 'magic')['id'];
        $invite = $invites->create([
            'sender_id' => $sender, 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => false, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'expires_at' => '2026-02-01 00:00:00',
        ]);
        $places->add((int) $invite['id'], 'dinner', 'Tartine', null, 'Tartine Bakery', '1 Main St', null);

        $ctrl->submit($invite['public_token'], [
            'chosen_start' => '2026-02-10T19:00', 'meal_choice' => 'dinner',
            'pickup_raw' => '742 Evergreen Terrace',
        ], $this->csrf->token());

        $stored = (new ResponseRepo($this->pdo(), $this->clock))->findByInvite((int) $invite['id']);
        $this->assertSame('742 Evergreen Terrace', $stored['pickup_raw']);
        $this->assertSame('742 Evergreen Terrace', $stored['pickup_address']); // typed address wins, not Tartine
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter RespondPlaceTest`
Expected: FAIL — `RespondController::__construct()` too few arguments.

- [ ] **Step 3: Modify `app/Respond/RespondController.php`** — add `use App\Invite\InvitePlaceRepo;`, a trailing constructor param `private InvitePlaceRepo $places,`. In `open()`'s render data array add `'places' => $this->places->forInvite((int) $invite['id']),`. In `submit()`, replace the pickup block so the chosen vibe's place fills in when the crush typed nothing:

```php
        $pickupRaw = $this->clean($input['pickup_raw'] ?? null);
        $pickup = $this->maps->resolve((string) ($pickupRaw ?? ''));
        if ($pickupRaw === null && $meal !== null) {
            $place = $this->places->forMeal((int) $invite['id'], $meal);
            if ($place !== null) {
                $pickup = [
                    'name'      => $place['place_resolved_name'] ?: $place['place_name'],
                    'address'   => $place['place_resolved_address'],
                    'clean_url' => $place['place_clean_url'] ?: $place['place_url'],
                ];
            }
        }
```

(Keep the `store(...)` call's pickup fields reading from `$pickupRaw` + `$pickup['name'|'address'|'clean_url']` exactly as before.)

- [ ] **Step 4: Modify `templates/respond/show.php`** — give each meal chip the matched place as data, and add a small reveal panel + inline JS. After the `$meals` foreach building chips, change the chip markup to include the place (when present) and add below the fieldset:

In the meal loop, add a `data-place` attribute:
```php
        <?php $p = ($places[$m['key']] ?? null); $plabel = $p ? ($p['place_resolved_name'] ?: $p['place_name']) : ''; ?>
        <label class="meal-chip">
          <input type="radio" name="meal_choice" value="<?= $e($m['key']) ?>" data-place="<?= $e($plabel) ?>">
          <svg class="ic"><use href="#<?= $e($m['icon']) ?>"/></svg>
          <span><?= $e($m['label']) ?></span>
        </label>
```

After the meals fieldset, add:
```php
    <p id="place-reveal" style="display:none;font-weight:600;color:var(--accent);"></p>
    <script>
      (function(){
        var p = document.getElementById('place-reveal');
        document.querySelectorAll('input[name="meal_choice"]').forEach(function(r){
          r.addEventListener('change', function(){
            if (r.dataset.place) { p.textContent = r.value + ' at ' + r.dataset.place; p.style.display='block'; }
            else { p.style.display='none'; }
          });
        });
      })();
    </script>
```

(Add `$places = $places ?? [];` to the optional-var defaults at the top of `show.php`.)

- [ ] **Step 5: Run the test** — Run: `vendor/bin/phpunit --filter RespondPlaceTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Wire in `public/index.php`** — pass the existing `$invitePlaceRepo` (built in Task 2) as the trailing arg to `new RespondController(...)`.

- [ ] **Step 7: Update existing RespondController test constructions** — `tests/Respond/RespondOpenTest.php`, `RespondSubmitTest.php`, `RespondPickupTest.php`, `RespondOnboardTest.php`, `tests/Mail/MailWiringTest.php` — add a trailing `new InvitePlaceRepo($this->pdo())` to each `new RespondController(...)` (add the import). Run `vendor/bin/phpunit` until green.

- [ ] **Step 8: Run the full suite** — Run: `vendor/bin/phpunit`
Expected: all green (~140 tests).

- [ ] **Step 9: Commit**

```bash
git add app/Respond/RespondController.php templates/respond/show.php public/index.php tests/
git commit -m "feat(places): reveal matched spot + copy chosen place into response"
```

---

## Self-Review

**1. Spec coverage:** One restaurant per meal vibe at creation, optional, resolved via `LinkResolver` (spec §6) — Tasks 1,2. `invite_places` table (§6,§8) — Task 1. Revealed to the crush on vibe pick (§6) — Task 3 (`data-place` + inline JS; v2-6 re-skins per theme). Chosen vibe's place copied into the response `pickup_*` so confirmation + `.ics` show it (§6) — Task 3. Crush's typed pickup takes precedence (§6) — Task 3 `if ($pickupRaw === null ...)`. Icons only, escaped, prepared statements. Port 8888 dev.

**2. Placeholder scan:** No "TBD". The inline reveal JS is complete and minimal; v2-6 will restyle the respond templates but the data plumbing (store/forInvite/forMeal/copy-into-response) is the durable contract and is fully implemented + tested here.

**3. Type consistency:** `InvitePlaceRepo::add(int,string,string,?string,?string,?string,?string): void`, `forInvite(int): array<string,array>`, `forMeal(int,string): ?array`. `InviteController` gains trailing `InvitePlaceRepo` + `LinkResolver`; `RespondController` gains trailing `InvitePlaceRepo`; both matched in `public/index.php` and the test helpers. Consumes `LinkResolver::resolve(string): array{name,address,clean_url}`, `MealOptions::CHOICES`, `ResponseRepo::store` (existing pickup fields) as defined in v1.
