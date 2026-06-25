# Crush v7 — Plan 2: Desktop Map Embed + Chosen Place on Detail Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** On desktop, paste a maps link and get a closable popover with an embedded interactive Google map of the place. And on the sender's detail view, surface the specific place the crush picked from a focused vibe's options.

**Architecture:** A shared map modal in `templates/invite/new.php` driven by the existing `/maps/preview` resolve (no API key — Google's `output=embed` iframe). `InvitePlaceRepo::findById` + a new `RevealController` dependency load the chosen `invite_places` row so `reveal/response.php` can show "they picked X".

**Tech Stack:** PHP 8.1+, PDO MySQL, PHPUnit 10. No new Composer dependencies.

## Global Constraints

- PHP floor 8.1. PSR-4. No new Composer packages. Prepared statements.
- **Icons only — never emojis.** All HTML `$e()`-escaped. Run the suite **serially** (FK/"Duplicate schema_migrations" bursts = concurrent-run corruption → `DROP DATABASE crush_test; CREATE DATABASE crush_test CHARACTER SET utf8mb4;` and re-run alone).
- The map embed uses Google's keyless `output=embed` iframe; the place query is `encodeURIComponent`'d. Desktop-only (skip the iframe on narrow viewports). Production: `https://crush.didudi.com`.

## File Structure

- `templates/invite/new.php` (modify) — map modal + open/close JS.
- `app/Invite/InvitePlaceRepo.php` (modify) — `findById`.
- `app/Reveal/RevealController.php` (modify) — `InvitePlaceRepo` dep, load chosen place.
- `templates/reveal/response.php` (modify) — show the chosen place.
- `public/index.php` (modify) — pass `InvitePlaceRepo` to `RevealController`.

---

### Task 1: Desktop map-embed popover

**Files:**
- Modify: `templates/invite/new.php`
- Test: `tests/Invite/MapModalTest.php`

**Interfaces:** A hidden `#mapModal` (backdrop + card + close button + `#mapFrame` container) lives in the form. When a `[data-maps]` resolve returns a place (name/address) on a wide viewport, the inline preview gains a "View map" button that opens the modal with an `<iframe src="https://maps.google.com/maps?q=…&output=embed">`. Closable via the button, backdrop click, or Esc.

- [ ] **Step 1: Write the failing test** — `tests/Invite/MapModalTest.php`

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

final class MapModalTest extends DatabaseTestCase
{
    public function test_form_has_map_modal(): void
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
        $this->assertStringContainsString('id="mapModal"', $body);
        $this->assertStringContainsString('id="mapFrame"', $body);
        $this->assertStringContainsString('output=embed', $body);   // embed URL is built in the JS
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter MapModalTest`
Expected: FAIL — no modal.

- [ ] **Step 3: Add the modal markup + styles to `templates/invite/new.php`** — add the modal just before the closing `</form>` is not required; place it right after the `</form>` (a sibling, still inside the `$content` closure). Add to the page `<style>`:

```css
      .map-modal { position:fixed; inset:0; background:rgba(60,30,70,.45); display:none; align-items:center; justify-content:center; z-index:50; padding:16px; }
      .map-modal.show { display:flex; }
      .map-modal .card2 { background:#fff; border-radius:18px; padding:10px; width:min(92vw,560px); box-shadow:0 20px 50px rgba(90,42,82,.35); }
      .map-modal .bar { display:flex; align-items:center; justify-content:space-between; padding:4px 6px 8px; }
      .map-modal .bar strong { font-size:14px; }
      .map-modal .x { border:0; background:none; font-size:22px; line-height:1; cursor:pointer; color:#7a5e86; }
      .map-modal iframe { width:100%; height:300px; border:0; border-radius:12px; display:block; }
```

and after `</form>`:

```php
  <div id="mapModal" class="map-modal" aria-hidden="true">
    <div class="card2" role="dialog" aria-label="Map preview">
      <div class="bar"><strong id="mapTitle">Map preview</strong><button type="button" class="x" id="mapClose" aria-label="Close">&times;</button></div>
      <div id="mapFrame"></div>
    </div>
  </div>
```

- [ ] **Step 4: Wire the modal into the form `<script>` IIFE** — replace the existing `showPrev` body (the v6-2 maps hook) so it adds a "View map" affordance and opens the modal, and add open/close helpers:

```php
      var modal = document.getElementById('mapModal');
      var frame = document.getElementById('mapFrame');
      var mapTitle = document.getElementById('mapTitle');
      function openMap(place){
        if (!modal || !frame) return;
        frame.innerHTML = '<iframe loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://maps.google.com/maps?q=' + encodeURIComponent(place) + '&output=embed"></iframe>';
        if (mapTitle) mapTitle.textContent = place;
        modal.classList.add('show'); modal.setAttribute('aria-hidden', 'false');
      }
      function closeMap(){
        if (!modal || !frame) return;
        modal.classList.remove('show'); modal.setAttribute('aria-hidden', 'true'); frame.innerHTML = '';
      }
      var mapClose = document.getElementById('mapClose');
      if (mapClose) mapClose.addEventListener('click', closeMap);
      if (modal) modal.addEventListener('click', function(e){ if (e.target === modal) closeMap(); });
      document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeMap(); });

      function showPrev(input){
        var url = (input.value || '').trim();
        var row = input.closest('.iv-opt');
        var prev = row && row.querySelector('.iv-prev');
        if (!prev) { prev = document.createElement('div'); prev.className = 'iv-prev'; row && row.appendChild(prev); }
        if (!url) { prev.textContent = ''; return; }
        prev.textContent = 'Looking up…';
        fetch('/maps/preview?url=' + encodeURIComponent(url), { headers: { 'Accept': 'application/json' } })
          .then(function(r){ return r.json(); })
          .then(function(d){
            var place = d && (d.address || d.name);
            if (!place) { prev.textContent = ''; return; }
            prev.textContent = 'Found: ' + place + '  ';
            if (window.innerWidth >= 700) {
              var btn = document.createElement('button');
              btn.type = 'button'; btn.textContent = 'View map';
              btn.style.cssText = 'border:0;background:none;color:#ff3d8b;font-weight:700;cursor:pointer;padding:0;';
              btn.addEventListener('click', function(){ openMap(place); });
              prev.appendChild(btn);
              openMap(place);   // auto-open once on resolve (desktop)
            }
          })
          .catch(function(){ prev.textContent = ''; });
      }
      document.addEventListener('change', function(e){
        if (e.target && e.target.hasAttribute && e.target.hasAttribute('data-maps')) showPrev(e.target);
      });
```

(If a `showPrev` + `change` listener already exists from v6-2, replace that block with this one — do not duplicate the `change` listener.)

- [ ] **Step 5: Run the test, then the full suite (serially)** — Run: `vendor/bin/phpunit --filter MapModalTest` then `vendor/bin/phpunit`
Expected: all green.

- [ ] **Step 6: Commit**

```bash
git add templates/invite/new.php tests/Invite/MapModalTest.php
git commit -m "feat(maps): desktop embedded map preview popover on paste"
```

---

### Task 2: Show the crush's chosen place on the detail view

**Files:**
- Modify: `app/Invite/InvitePlaceRepo.php`, `app/Reveal/RevealController.php`, `templates/reveal/response.php`, `public/index.php`
- Test: `tests/Reveal/ChosenPlaceTest.php`

**Interfaces:**
- `InvitePlaceRepo::findById(int $id): ?array`.
- `RevealController::__construct` gains a trailing `InvitePlaceRepo $places`. In the `reveal` render path, when `response['chosen_place_id']` is set, load that place and pass `chosenPlace` (the row, or `null`) to `reveal/response.php`.
- `reveal/response.php` in the reveal branch shows "They picked **{place name}** · {cuisine}" (with the place's map link when available) above/within the answer.

- [ ] **Step 1: Write the failing test** — `tests/Reveal/ChosenPlaceTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Reveal;

use App\Auth\UserRepo;
use App\Ics\IcsBuilder;
use App\Invite\InvitePlaceRepo;
use App\Invite\InviteRepo;
use App\Invite\ResponseRepo;
use App\Reveal\RevealController;
use App\Core\View;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class ChosenPlaceTest extends DatabaseTestCase
{
    public function test_detail_shows_chosen_place(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $users = new UserRepo($this->pdo(), $clock);
        $invites = new InviteRepo($this->pdo(), $clock);
        $responses = new ResponseRepo($this->pdo(), $clock);
        $places = new InvitePlaceRepo($this->pdo());

        $sender = $users->create('s@x.test', 'Sue', 'magic');
        $users->saveProfile($sender['id'], 'fox', null, 'hi', null);          // complete profile so reveal unlocks
        $invite = $invites->create([
            'sender_id' => $sender['id'], 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => false, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'expires_at' => '2026-12-01 00:00:00',
        ]);
        $pid = $places->addOption((int) $invite['id'], 'dinner', 'Octo Tapas', null, null, null, 'https://www.google.com/maps/search/?api=1&query=Octo', 'Tapas', 0);
        $responses->store((int) $invite['id'], [
            'chosen_start' => '2026-02-10 19:00:00', 'chosen_end' => '2026-02-10 21:00:00',
            'meal_choice' => 'dinner', 'chosen_place_id' => $pid,
        ]);

        $ctrl = new RevealController($this->view(), $users, $invites, $responses, new IcsBuilder($clock), $places);
        $body = $ctrl->show($sender['id'], $invite['public_token'])->body();
        $this->assertStringContainsString('Octo Tapas', $body);
        $this->assertStringContainsString('Tapas', $body);
    }

    private function view(): View
    {
        return new View(\dirname(__DIR__, 2) . '/templates');
    }
}
```

> Confirm `RevealController::show(?int $userId, string $token)` and the `InviteState::CONFIRMED` constant name against the code; adjust if the reveal-unlock requires a different status/profile shape (mirror an existing passing reveal test's setup).

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter ChosenPlaceTest`
Expected: FAIL — ctor arity / chosen place not shown.

- [ ] **Step 3: Add `InvitePlaceRepo::findById`**

```php
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM invite_places WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
```

- [ ] **Step 4: Thread the chosen place through `RevealController`** — add `use App\Invite\InvitePlaceRepo;`, a trailing ctor param `private InvitePlaceRepo $places,`, and in `render` (or wherever the `reveal` state passes `response`), compute and pass `chosenPlace`:

```php
        $chosenPlace = null;
        if ($response !== null && !empty($response['chosen_place_id'])) {
            $chosenPlace = $this->places->findById((int) $response['chosen_place_id']);
        }
```

and add `'chosenPlace' => $chosenPlace` to the `reveal/response` render data array. (If `render()` is a shared helper for all states, computing `chosenPlace` there with the `$response` it already receives is fine — it is `null` for non-reveal states.)

- [ ] **Step 5: Show it in `templates/reveal/response.php`** — in the `reveal` branch, add `$chosenPlace = $chosenPlace ?? null;` near the top, and just before the `<ul>` answer list, insert:

```php
    <?php if ($chosenPlace !== null):
      $cpName = $chosenPlace['place_resolved_name'] ?: $chosenPlace['place_name'];
      $cpCuisine = $chosenPlace['cuisine'] ?? '';
      $cpMap = $chosenPlace['place_clean_url'] ?? ''; ?>
      <p style="font-size:15px;margin:6px 0;">They picked
        <strong><?= $e($cpName) ?></strong><?php if ($cpCuisine !== ''): ?> · <?= $e($cpCuisine) ?><?php endif; ?>
        <?php if (is_string($cpMap) && str_starts_with((string) $cpMap, 'http')): ?>
          — <a href="<?= $e($cpMap) ?>" target="_blank" rel="noopener" style="color:#ff3d8b;">map</a>
        <?php endif; ?>
      </p>
    <?php endif; ?>
```

- [ ] **Step 6: Pass `InvitePlaceRepo` in `public/index.php`** — change the `RevealController` construction to include the existing `$invitePlaceRepo` as the trailing arg: `new RevealController($view, $users, $inviteRepo, $responseRepo, new IcsBuilder($clock), $invitePlaceRepo)`.

- [ ] **Step 7: Update the existing RevealController test ctors** — `tests/Reveal/RevealControllerTest.php` and `tests/Reveal/RevealIcsTest.php` build `new RevealController(...)`; add a trailing `new InvitePlaceRepo($this->pdo())` (import it). Run `vendor/bin/phpunit` until green.

- [ ] **Step 8: Run the test, then the full suite (serially)** — Run: `vendor/bin/phpunit --filter ChosenPlaceTest` then `vendor/bin/phpunit`
Expected: all green.

- [ ] **Step 9: Commit**

```bash
git add app/Invite/InvitePlaceRepo.php app/Reveal/RevealController.php templates/reveal/response.php public/index.php tests/Reveal/
git commit -m "feat(reveal): show the crush's chosen place on the detail view"
```

---

## Self-Review

**1. Spec coverage:** Desktop embedded map popover on paste (closable; keyless `output=embed`; desktop-only) — Task 1. Crush's chosen place surfaced on the sender detail — Task 2. Icons only; escaped; map links http-gated — throughout.

**2. Placeholder scan:** No "TBD". The embed query is `encodeURIComponent`'d; the modal is desktop-gated (`innerWidth >= 700`). `chosenPlace` is null-safe for non-focused responses. Full code throughout.

**3. Type consistency:** `InvitePlaceRepo::findById(int): ?array`. `RevealController::__construct` gains a trailing `InvitePlaceRepo` (matched in `public/index.php` + `RevealControllerTest`/`RevealIcsTest`). `reveal/response.php` consumes `chosenPlace` (defaulted null). The map modal in `new.php` is template/JS only (no controller change). `showPrev` consumes `/maps/preview`'s `{name,address}` (Plan v6-2 / the title-cleanup fix make `address` the place name).
