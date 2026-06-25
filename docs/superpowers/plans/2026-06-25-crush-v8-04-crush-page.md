# Crush v8 — Plan 4: Crush Response Page (time picker, spot map, pickup preview) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the crush's response screen friendlier — a day + time-of-day + exact-time picker instead of one raw datetime field; an embedded map popover when they pick a spot (slides in from the right on desktop, centered popover on mobile); and a live location preview when they paste a maps link for pickup.

**Architecture:** `templates/respond/_form.php` (shared by the 3 themes) gets the new controls + a theme-independent map modal. `RespondController::submit` combines `chosen_date`+`chosen_time` (keeping the old `chosen_start` as a fallback) and gains a **token-scoped** `mapsPreview` JSON endpoint (the crush is anonymous, so it is gated by a valid invite token, not login).

**Tech Stack:** PHP 8.1+, PDO MySQL, PHPUnit 10. No new Composer dependencies.

## Global Constraints

- PHP floor 8.1. PSR-4. No new Composer packages. Prepared statements.
- **Icons only — never emojis.** All HTML `$e()`-escaped. POSTs validate CSRF. Run the suite **serially** (FK/"Duplicate schema_migrations" bursts = concurrent-run corruption → reset `crush_test`).
- Map resolution stays SSRF-guarded (`LinkResolver` allowlist google/goo.gl/g.co). Map embed uses Google's keyless `output=embed`. Progressive enhancement (native date/time inputs work with JS off). Production: `https://crush.didudi.com`.

## File Structure

- `templates/respond/_form.php` (modify) — time picker, spot-map modal, pickup preview.
- `app/Respond/RespondController.php` (modify) — combine date+time, `mapsPreview` endpoint.
- `config/routes.php` (modify) — `GET /i/{token}/maps-preview`.

---

### Task 1: Friendly day + time-of-day + exact time picker

**Files:**
- Modify: `templates/respond/_form.php`, `app/Respond/RespondController.php`
- Test: `tests/Respond/TimePickerTest.php`

**Interfaces:** The form posts `chosen_date` (`<input type=date>`) + `chosen_time` (`<input type=time>`) with quick Morning/Afternoon/Evening buttons that prefill the time. `RespondController::submit` builds the start from `chosen_start` if present (back-compat), else from `chosen_date`+`chosen_time`.

- [ ] **Step 1: Write the failing test** — `tests/Respond/TimePickerTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Respond;

use App\Auth\UserRepo;
use App\Invite\InviteRepo;
use App\Invite\ResponseRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;
use Tests\Support\RespondControllerFactory;

final class TimePickerTest extends DatabaseTestCase
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
            'message' => null, 'theme_key' => 'bubblegum', 'expires_at' => '2026-12-01 00:00:00',
        ]);
    }

    public function test_form_has_date_and_time_inputs(): void
    {
        $inv = $this->invite();
        $body = RespondControllerFactory::make($this->pdo(), $this->clock)->open($inv['public_token'])->body();
        $this->assertStringContainsString('name="chosen_date"', $body);
        $this->assertStringContainsString('name="chosen_time"', $body);
        $this->assertStringContainsString('data-time="19:00"', $body);     // Evening quick-pick
    }

    public function test_submit_combines_date_and_time(): void
    {
        $inv = $this->invite();
        $csrf = new \App\Core\Csrf(new \App\Core\ArrayStore());
        $ctrl = RespondControllerFactory::make($this->pdo(), $this->clock, $csrf);
        $res = $ctrl->submit($inv['public_token'], [
            'chosen_date' => '2026-06-30', 'chosen_time' => '19:00', 'meal_choice' => 'dinner',
        ], $csrf->token());
        $this->assertContains($res->status(), [302, 200]);
        $row = (new ResponseRepo($this->pdo(), $this->clock))->findByInvite((int) $inv['id']);
        $this->assertNotNull($row);
        $this->assertStringStartsWith('2026-06-30 19:00', (string) $row['chosen_start']);
    }
}
```

> **Factory change:** `RespondControllerFactory::make` gains an optional trailing `?Csrf $csrf = null` param (`use App\Core\Csrf;`) and uses `$csrf ?? new Csrf(new ArrayStore())` for the controller, so a test can pass its own Csrf and submit a valid token. All existing `make($pdo, $clock)` callers keep working (default null).

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter TimePickerTest`
Expected: FAIL.

- [ ] **Step 3: Replace the datetime field in `templates/respond/_form.php`** — swap the `Pick a day & time` label/input for:

```php
  <label class="rf-field">Pick a day
    <input type="date" name="chosen_date" required>
  </label>
  <div class="rf-tod" role="group" aria-label="Time of day" style="display:flex;gap:8px;flex-wrap:wrap;margin:2px 0 6px;">
    <button type="button" class="rf-tod-b" data-time="09:00">Morning</button>
    <button type="button" class="rf-tod-b" data-time="14:00">Afternoon</button>
    <button type="button" class="rf-tod-b" data-time="19:00">Evening</button>
  </div>
  <label class="rf-field">at
    <input type="time" name="chosen_time" required>
  </label>
```

and in the form `<script>` IIFE, prefill the time from the quick buttons:

```php
  var timeInput = document.querySelector('input[name="chosen_time"]');
  document.querySelectorAll('.rf-tod-b').forEach(function(b){
    b.addEventListener('click', function(){
      if (timeInput) timeInput.value = b.getAttribute('data-time');
      document.querySelectorAll('.rf-tod-b').forEach(function(x){ x.removeAttribute('data-on'); });
      b.setAttribute('data-on', '1');
    });
  });
```

(Add minimal theme-neutral styling for `.rf-tod-b` inline or rely on the theme's button styling; selected pill via `[data-on]`.)

- [ ] **Step 4: Combine date+time in `RespondController::submit`** — replace the `$start = $this->parseDate(...)` line:

```php
        $rawStart = trim((string) ($input['chosen_start'] ?? ''));
        if ($rawStart === '') {
            $d = trim((string) ($input['chosen_date'] ?? ''));
            $t = trim((string) ($input['chosen_time'] ?? ''));
            $rawStart = ($d !== '' && $t !== '') ? $d . ' ' . $t : '';
        }
        $start = $this->parseDate($rawStart);
```

- [ ] **Step 5: Run the tests, then the full suite (serially)** — Run: `vendor/bin/phpunit --filter TimePickerTest` then `vendor/bin/phpunit`
Expected: green (existing submit tests post `chosen_start` → still parsed via the back-compat branch).

- [ ] **Step 6: Commit**

```bash
git add templates/respond/_form.php app/Respond/RespondController.php tests/Respond/TimePickerTest.php
git commit -m "feat(respond): friendly day + time-of-day + exact time picker"
```

---

### Task 2: Spot map popover + pickup location preview

**Files:**
- Modify: `app/Respond/RespondController.php`, `config/routes.php`, `templates/respond/_form.php`
- Test: `tests/Respond/MapsPreviewTest.php`

**Interfaces:**
- `RespondController::mapsPreview(string $token, string $url): Response` — `404` JSON if the token is not a real invite; else resolves `$url` via `LinkResolver` and returns `{name,address}` as `application/json`. Route `GET /i/{token}/maps-preview`.
- `_form.php`: place-option radios carry `data-place` (the place name); selecting one opens a theme-independent **map modal** with an `<iframe …output=embed>` (desktop: slides in from the right; mobile: centered popover; closable). The `pickup_raw` input carries `data-maps` and shows a resolved-location line via the token-scoped endpoint.

- [ ] **Step 1: Write the failing test** — `tests/Respond/MapsPreviewTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Respond;

use App\Auth\UserRepo;
use App\Invite\InviteRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;
use Tests\Support\RespondControllerFactory;

final class MapsPreviewTest extends DatabaseTestCase
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
            'message' => null, 'theme_key' => 'bubblegum', 'expires_at' => '2026-12-01 00:00:00',
        ]);
    }

    public function test_unknown_token_is_404(): void
    {
        $res = RespondControllerFactory::make($this->pdo(), $this->clock)->mapsPreview('nope', '123 Main St');
        $this->assertSame(404, $res->status());
    }

    public function test_valid_token_resolves_address(): void
    {
        $inv = $this->invite();
        $res = RespondControllerFactory::make($this->pdo(), $this->clock)->mapsPreview($inv['public_token'], '123 Main St');
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('application/json', implode(' ', $res->headers()));
        $this->assertStringContainsString('123 Main St', $res->body());     // plain address echoed back
    }

    public function test_form_has_map_modal_and_pickup_hook(): void
    {
        $inv = $this->invite();
        $body = RespondControllerFactory::make($this->pdo(), $this->clock)->open($inv['public_token'])->body();
        $this->assertStringContainsString('id="rfMapModal"', $body);
        $this->assertStringContainsString('output=embed', $body);
        $this->assertStringContainsString('data-maps', $body);              // pickup field hook
    }
}
```

> `LinkResolver` returns the plain string as `address` for a non-URL input (the resolver's `looksLikeUrl` false branch), so `mapsPreview` echoes `123 Main St` back as the address — no network call in the test.

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter MapsPreviewTest`
Expected: FAIL.

- [ ] **Step 3: Add `mapsPreview` to `RespondController`** — it already has `$this->invites` (`InviteRepo`) and `$this->maps` (`LinkResolver`):

```php
    public function mapsPreview(string $token, string $url): Response
    {
        if ($this->invites->findByToken($token) === null) {
            return (new Response((string) json_encode(['error' => 'not_found']), 404))
                ->withHeader('Content-Type', 'application/json');
        }
        $url = trim($url);
        $r = $url === '' ? ['name' => null, 'address' => null] : $this->maps->resolve($url);
        return (new Response((string) json_encode(['name' => $r['name'], 'address' => $r['address']]), 200))
            ->withHeader('Content-Type', 'application/json');
    }
```

(Add `use App\Core\Response;` if not already imported — it is.)

- [ ] **Step 4: Register the route** — `config/routes.php`:

```php
    $router->add('GET', '/i/{token}/maps-preview', static fn(string $token): Response => $respond->mapsPreview(
        $token, is_string($_GET['url'] ?? null) ? $_GET['url'] : ''
    ));
```

- [ ] **Step 5: Add the map modal + place data + pickup hook to `templates/respond/_form.php`** —

(a) On every place-option radio (the `$focusOptions` loop **and** the open-mode `$meals` loop where a place exists), add `data-place="<?= $e($oname) ?>"` (focused) / `data-place="<?= $e($plabel) ?>"` (open) so a selection can open the map.

(b) Add the modal markup after `</form>` (still inside the included partial output):

```php
<div id="rfMapModal" class="rf-map-modal" aria-hidden="true">
  <div class="rf-map-card" role="dialog" aria-label="Map">
    <button type="button" class="rf-map-x" aria-label="Close">&times;</button>
    <div id="rfMapFrame"></div>
  </div>
</div>
<style>
  .rf-map-modal { position:fixed; inset:0; background:rgba(40,20,50,.4); display:none; z-index:60; }
  .rf-map-modal.show { display:block; }
  .rf-map-card { position:absolute; background:#fff; box-shadow:0 20px 50px rgba(0,0,0,.3); }
  .rf-map-card iframe { width:100%; height:100%; border:0; display:block; }
  .rf-map-x { position:absolute; top:6px; right:8px; z-index:2; border:0; background:#fff; border-radius:50%; width:30px; height:30px; font-size:20px; line-height:1; cursor:pointer; }
  /* mobile: centered popover */
  .rf-map-card { left:50%; top:50%; transform:translate(-50%,-50%); width:92vw; max-width:520px; height:60vh; border-radius:16px; }
  .rf-map-card iframe { border-radius:16px; }
  /* desktop: slide in from the right */
  @media (min-width:760px) {
    .rf-map-card { left:auto; right:0; top:0; transform:translateX(100%); width:min(46vw,520px); height:100%; border-radius:18px 0 0 18px; transition:transform .3s ease; }
    .rf-map-modal.show .rf-map-card { transform:translateX(0); }
  }
  @media (prefers-reduced-motion: reduce) { .rf-map-card { transition:none; } }
</style>
```

(c) Add to the form `<script>` IIFE: open the modal on place selection, and the pickup preview:

```php
  var token = <?= json_encode((string) ($token ?? ''), JSON_UNESCAPED_SLASHES) ?>;
  var mm = document.getElementById('rfMapModal');
  var mf = document.getElementById('rfMapFrame');
  function openRfMap(place){
    if (!mm || !mf || !place) return;
    mf.innerHTML = '<iframe loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://maps.google.com/maps?q=' + encodeURIComponent(place) + '&output=embed"></iframe>';
    mm.classList.add('show'); mm.setAttribute('aria-hidden','false');
  }
  function closeRfMap(){ if (mm){ mm.classList.remove('show'); mm.setAttribute('aria-hidden','true'); if (mf) mf.innerHTML=''; } }
  var rx = mm && mm.querySelector('.rf-map-x'); if (rx) rx.addEventListener('click', closeRfMap);
  if (mm) mm.addEventListener('click', function(e){ if (e.target === mm) closeRfMap(); });
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeRfMap(); });
  document.querySelectorAll('input[name="chosen_place"], input[name="meal_choice"]').forEach(function(r){
    r.addEventListener('change', function(){ if (r.dataset.place) openRfMap(r.dataset.place); });
  });

  var pickup = document.querySelector('input[name="pickup_raw"]');
  if (pickup) pickup.addEventListener('change', function(){
    var v = (pickup.value || '').trim();
    var line = document.getElementById('rfPickupPrev');
    if (!line) { line = document.createElement('div'); line.id = 'rfPickupPrev'; line.style.cssText='font-size:12px;opacity:.8;margin-top:4px;'; pickup.parentNode.appendChild(line); }
    if (!v) { line.textContent=''; return; }
    line.textContent = 'Looking up…';
    fetch('/i/' + encodeURIComponent(token) + '/maps-preview?url=' + encodeURIComponent(v), { headers:{'Accept':'application/json'} })
      .then(function(r){ return r.json(); })
      .then(function(d){ line.textContent = d && (d.address || d.name) ? ('Pickup: ' + (d.address || d.name)) : ''; })
      .catch(function(){ line.textContent=''; });
  });
```

> The existing `.rf-place` reveal handler on `meal_choice` stays; this adds the map-open behavior. `$token` is already in scope in `_form.php` (the form action uses it).

- [ ] **Step 6: Run the tests, then the full suite (serially)** — Run: `vendor/bin/phpunit --filter "MapsPreviewTest|TimePickerTest|CuratedVibesTest|FocusedOptionsTest"` then `vendor/bin/phpunit`
Expected: all green.

- [ ] **Step 7: Commit**

```bash
git add app/Respond/RespondController.php config/routes.php templates/respond/_form.php tests/Respond/MapsPreviewTest.php
git commit -m "feat(respond): spot map popover (slide on desktop) + pickup location preview"
```

---

## Self-Review

**1. Spec coverage:** Friendly day + time-of-day + exact time picker (item: time picker) — Task 1. Map embed popover on spot pick, desktop slide-right / mobile popover (item) — Task 2. Pickup maps-link preview for the (anonymous) crush via a token-scoped endpoint (item) — Task 2. Icons only; escaped; SSRF-guarded; progressive-enhanced — throughout.

**2. Placeholder scan:** No "TBD". The crush is anonymous, so the preview endpoint is gated by a valid invite token (not login) and only ever fetches Google domains (`SsrfGuard`). Native date/time inputs keep no-JS submit working; `submit` accepts `chosen_start` (back-compat) or `chosen_date`+`chosen_time`. Full code throughout.

**3. Type consistency:** `RespondController::mapsPreview(string,string): Response`; `submit` reads `chosen_date`/`chosen_time` with `chosen_start` fallback. Route `GET /i/{token}/maps-preview`. `_form.php` consumes `$token` (already in scope), adds `data-place`/`data-maps` hooks + the `#rfMapModal`. No controller ctor change (RespondController already has `InviteRepo` + `LinkResolver`). The embed query is `encodeURIComponent`'d; the modal is closable (button/backdrop/Esc).
