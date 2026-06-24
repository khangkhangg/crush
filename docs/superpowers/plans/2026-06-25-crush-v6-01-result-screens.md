# Crush v6 — Plan 1: Result Screens (copy feedback, envelope, dashboard + detail) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Polish the post-creation screens — a "Copied!" indicator on the share screen, a letter/envelope look for the crush's "answer is on its way" confirmation, and a nicer `/invites` dashboard with a click-through detail view (full answer + status timeline + calendar/reshare + who).

**Architecture:** Template/CSS/JS changes to `invite/created.php`, `respond/confirmed.php`, `invite/dashboard.php`, and the existing sender detail page `reveal/response.php` (enriched, with a little extra data from `RevealController`). No data-model change.

**Tech Stack:** PHP 8.1+, PDO MySQL, PHPUnit 10. No new Composer dependencies.

## Global Constraints

- PHP floor 8.1. PSR-4. No new Composer packages.
- **Icons only — never emojis.** All HTML `$e()`-escaped. Run the suite **serially**.
- Integration tests use MySQL `crush_test`. Production: `https://crush.didudi.com`.

## File Structure

- `templates/invite/created.php` (modify) — "Copied!" feedback.
- `templates/respond/confirmed.php` (modify) — letter/envelope look.
- `templates/invite/dashboard.php` (modify) — card redesign.
- `app/Reveal/RevealController.php`, `templates/reveal/response.php` (modify) — enriched detail.

---

### Task 1: "Copied!" feedback on the share screen

**Files:**
- Modify: `templates/invite/created.php`
- Test: `tests/Invite/ShareScreenTest.php` (add an assertion)

**Interfaces:** The Copy button shows a transient "Copied!" indicator (text + scale animation) on success; reverts after ~2s.

- [ ] **Step 1: Add an assertion to `tests/Invite/ShareScreenTest.php`** — in `test_share_screen_lists_targets_with_invite_link`, add:

```php
        $this->assertStringContainsString('id="copiedMsg"', $body);   // the feedback element exists
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter ShareScreenTest`
Expected: FAIL — `copiedMsg` absent.

- [ ] **Step 3: Add the indicator + animation to `templates/invite/created.php`** — add a `<span id="copiedMsg">` next to the copy button row and a keyframe; wire it in the existing copy handler. Replace the copy-button row `<div>` (the one holding `#lnk` + `#copyBtn`) with:

```php
  <div style="display:flex;gap:8px;align-items:center;">
    <input id="lnk" readonly value="<?= $e($link) ?>"
           style="flex:1;min-width:0;padding:11px;border-radius:12px;border:1px solid #e7d4ff;font-size:13px;" onclick="this.select()">
    <button type="button" id="copyBtn" aria-label="Copy link"
            style="padding:0 14px;border:0;border-radius:12px;background:#ff3d8b;color:#fff;font-weight:700;cursor:pointer;transition:transform .12s;">
      <svg width="18" height="18"><use href="#ic-copy"/></svg>
    </button>
    <span id="copiedMsg" aria-live="polite" style="opacity:0;color:#16a34a;font-weight:700;font-size:13px;transition:opacity .2s;">Copied!</span>
  </div>
```

and update the copy handler inside the existing `<script>` (replace the `if (copy) copy.addEventListener(...)` block):

```php
    var copy = document.getElementById('copyBtn');
    var msg = document.getElementById('copiedMsg');
    function flashCopied(){
      if (msg) { msg.style.opacity = '1'; setTimeout(function(){ msg.style.opacity = '0'; }, 2000); }
      if (copy) { copy.style.transform = 'scale(1.15)'; setTimeout(function(){ copy.style.transform = 'scale(1)'; }, 150); }
    }
    if (copy) copy.addEventListener('click', function(){
      if (navigator.clipboard) { navigator.clipboard.writeText(url).then(flashCopied); }
      else { var l = document.getElementById('lnk'); if (l) { l.select(); document.execCommand('copy'); flashCopied(); } }
    });
```

- [ ] **Step 4: Run the test, then the full suite (serially)** — Run: `vendor/bin/phpunit --filter ShareScreenTest` then `vendor/bin/phpunit`
Expected: all green.

- [ ] **Step 5: Commit**

```bash
git add templates/invite/created.php tests/Invite/ShareScreenTest.php
git commit -m "feat(share): Copied! feedback + button animation on copy"
```

---

### Task 2: Letter/envelope "answer is on its way"

**Files:**
- Modify: `templates/respond/confirmed.php`
- Test: `tests/Respond/ConfirmedEnvelopeTest.php`

**Interfaces:** The confirmation renders an envelope illustration with the message on a "letter," theme-independent (inline styles + SVG). Still shows the picked time + reveal/closing line.

- [ ] **Step 1: Write the failing test** — `tests/Respond/ConfirmedEnvelopeTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Respond;

use App\Core\View;
use PHPUnit\Framework\TestCase;

final class ConfirmedEnvelopeTest extends TestCase
{
    public function test_envelope_markup_and_content(): void
    {
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $html = $view->render('respond/confirmed', [
            'title' => 'Crush', 'theme' => 'bubblegum', 'when' => 'Tue, Jun 30 at 8:08 AM',
            'reveal' => 'khang', 'wasAnonymous' => true,
        ]);
        $this->assertStringContainsString('class="envelope"', $html);   // envelope illustration
        $this->assertStringContainsString('Tue, Jun 30 at 8:08 AM', $html);
        $this->assertStringContainsString('khang', $html);              // reveal preserved
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter ConfirmedEnvelopeTest`
Expected: FAIL — no `envelope` markup.

- [ ] **Step 3: Rewrite `templates/respond/confirmed.php`** — keep the theme CSS links + the existing data, wrap the message in an envelope/letter with a small open animation:

```php
<?php $reveal = $reveal ?? null; $wasAnonymous = $wasAnonymous ?? false; ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($title ?? 'Crush') ?></title>
<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/themes/<?= $e($theme) ?>.css">
<style>
  .envelope { width:200px; height:140px; margin:8px auto 22px; position:relative;
    animation:env-rise .7s cubic-bezier(.2,.8,.2,1) both; }
  @keyframes env-rise { from { transform:translateY(14px); opacity:0; } to { transform:translateY(0); opacity:1; } }
  .envelope .body { position:absolute; inset:0; background:#fff; border:2px solid #ff8fc0; border-radius:12px;
    box-shadow:0 10px 24px rgba(255,61,139,.22); }
  .envelope .letter { position:absolute; left:14px; right:14px; top:-14px; height:70px; background:#fff;
    border:2px solid #ffd1e6; border-radius:8px; animation:letter-out 1s ease .3s both; }
  @keyframes letter-out { from { top:30px; } to { top:-14px; } }
  .envelope .flap { position:absolute; left:0; right:0; top:0; height:0; border-left:100px solid transparent;
    border-right:100px solid transparent; border-top:72px solid #ff5fa6; border-radius:12px 12px 0 0;
    transform-origin:top; animation:flap-open .6s ease both; }
  @keyframes flap-open { from { transform:rotateX(0); } to { transform:rotateX(160deg); } }
  .envelope .heart { position:absolute; left:50%; top:54%; transform:translate(-50%,-50%); width:34px; height:34px; color:#ff3d8b; }
</style>
</head>
<body class="theme-<?= $e($theme) ?>">
<?php include __DIR__ . '/../partials/icons.php'; ?>
<main class="card confirm-card">
  <div class="envelope" aria-hidden="true">
    <div class="body"></div>
    <div class="letter"></div>
    <div class="flap"></div>
    <svg class="heart"><use href="#ic-heart"/></svg>
  </div>
  <h1>Your answer is on its way</h1>
  <p class="when">You picked <strong><?= $e($when) ?></strong>.</p>
  <?php if ($reveal && $wasAnonymous): ?>
    <p class="reveal">Your secret admirer is <strong><?= $e($reveal) ?></strong>.</p>
  <?php elseif ($reveal && !$wasAnonymous): ?>
    <p class="reveal">It's a date with <strong><?= $e($reveal) ?></strong>.</p>
  <?php else: ?>
    <p class="reveal">They'll be in touch soon.</p>
  <?php endif; ?>
</main>
</body></html>
```

- [ ] **Step 4: Run the test, then the full suite (serially)** — Run: `vendor/bin/phpunit --filter ConfirmedEnvelopeTest` then `vendor/bin/phpunit`
Expected: all green.

- [ ] **Step 5: Commit**

```bash
git add templates/respond/confirmed.php tests/Respond/ConfirmedEnvelopeTest.php
git commit -m "feat(respond): letter/envelope look for the answer confirmation"
```

---

### Task 3: Dashboard card redesign

**Files:**
- Modify: `templates/invite/dashboard.php`
- Test: `tests/Invite/DashboardTest.php`

**Interfaces:** Each invite is a card showing the crush name, a status badge (mapped to friendly text), and a "View" link to `/invites/{token}/response` when answered; the raw `/i/{token}` URL is replaced with a subtle copy-link affordance (still present for sharing but not a wall of hex).

- [ ] **Step 1: Write the failing test** — `tests/Invite/DashboardTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Invite;

use App\Core\View;
use PHPUnit\Framework\TestCase;

final class DashboardTest extends TestCase
{
    public function test_cards_show_status_badge_and_view_link(): void
    {
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $html = $view->render('invite/dashboard', [
            'title' => 'Your invites', 'appUrl' => 'https://crush.app',
            'invites' => [[
                'crush_name' => 'Mia', 'crush_email' => 'mia@x.test', 'status' => 'confirmed',
                'public_token' => 'tok123',
            ]],
        ]);
        $this->assertStringContainsString('Mia', $html);
        $this->assertStringContainsString('iv-badge', $html);                       // status badge
        $this->assertStringContainsString('/invites/tok123/response', $html);       // view detail link
        $this->assertStringNotContainsString('https://crush.app/i/tok123', $html);  // raw URL not dumped
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter DashboardTest`
Expected: FAIL — old markup dumps the raw URL / no badge.

- [ ] **Step 3: Rewrite `templates/invite/dashboard.php`**

```php
<?php $invites = $invites ?? []; $appUrl = $appUrl ?? ''; ?>
<?php
$badge = static function (string $status): array {
  return [
    'sent'           => ['Waiting', '#9d7bff'],
    'responded'      => ['Answered', '#ff3d8b'],
    'pending_sender' => ['Needs you', '#f59e0b'],
    'confirmed'      => ['Confirmed', '#16a34a'],
    'closed'         => ['Closed', '#9aa0a6'],
  ][$status] ?? [ucfirst(str_replace('_', ' ', $status)), '#9aa0a6'];
};
$content = function () use ($e, $invites, $appUrl, $badge) { ob_start(); ?>
  <h1 style="text-wrap:balance;">Your invites</h1>
  <a href="/invites/new"
     style="display:inline-block;padding:12px 18px;border-radius:14px;background:#ff3d8b;color:#fff;font-weight:700;text-decoration:none;">
    Send a new crush invite
  </a>
  <?php if (empty($invites)): ?>
    <p style="opacity:.75;margin-top:20px;">No invites yet. Send your first one above.</p>
  <?php else: ?>
    <ul style="list-style:none;padding:0;margin-top:20px;display:flex;flex-direction:column;gap:12px;">
      <?php foreach ($invites as $inv):
        [$label, $color] = $badge((string) $inv['status']);
        $answered = in_array($inv['status'], ['responded', 'pending_sender', 'confirmed', 'closed'], true); ?>
        <li style="padding:14px 16px;border-radius:16px;background:#faf2ff;border:1px solid #eadcff;display:flex;align-items:center;gap:12px;">
          <div style="flex:1;min-width:0;">
            <div style="font-weight:700;"><?= $e($inv['crush_name'] ?: $inv['crush_email'] ?: 'A secret crush') ?></div>
            <span class="iv-badge" style="display:inline-block;margin-top:4px;font-size:11px;font-weight:700;color:#fff;background:<?= $e($color) ?>;padding:2px 9px;border-radius:999px;"><?= $e($label) ?></span>
          </div>
          <?php if ($answered): ?>
            <a href="/invites/<?= $e($inv['public_token']) ?>/response"
               style="padding:9px 14px;border-radius:12px;background:#ff3d8b;color:#fff;font-weight:700;text-decoration:none;white-space:nowrap;">View</a>
          <?php else: ?>
            <button type="button" class="iv-copy" data-link="<?= $e(rtrim($appUrl, '/') . '/i/' . $inv['public_token']) ?>"
               style="padding:9px 14px;border-radius:12px;border:1px solid #e7d4ff;background:#fff;color:#5a2a52;font-weight:600;cursor:pointer;white-space:nowrap;">Copy link</button>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <script>
    document.querySelectorAll('.iv-copy').forEach(function(b){
      b.addEventListener('click', function(){
        var t = b.getAttribute('data-link');
        if (navigator.clipboard && t) navigator.clipboard.writeText(t).then(function(){
          var o = b.textContent; b.textContent = 'Copied!'; setTimeout(function(){ b.textContent = o; }, 1500);
        });
      });
    });
    </script>
  <?php endif;
  return ob_get_clean(); };
$body = $content();
include __DIR__ . '/../layout.php';
```

- [ ] **Step 4: Run the test, then the full suite (serially)** — Run: `vendor/bin/phpunit --filter DashboardTest` then `vendor/bin/phpunit`
Expected: all green.

- [ ] **Step 5: Commit**

```bash
git add templates/invite/dashboard.php tests/Invite/DashboardTest.php
git commit -m "feat(dashboard): card layout with status badges + copy/view actions"
```

---

### Task 4: Enriched invite detail (timeline + who + reshare)

**Files:**
- Modify: `app/Reveal/RevealController.php`, `templates/reveal/response.php`
- Test: `tests/Reveal/RevealDetailTest.php`

**Interfaces:** `RevealController::render` also passes `appUrl` (for the reshare link). `reveal/response.php` in the `reveal` state adds: a **status timeline** (Sent → Answered → Confirmed, derived from `invite['created_at']`, `response['created_at']`, and `invite['status']`), a **who** line (crush name/email + whether sent anonymously / revealed), the existing full answer with a **map link** for pickup, the calendar button, and a **reshare** (copy link) control.

- [ ] **Step 1: Write the failing test** — `tests/Reveal/RevealDetailTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Reveal;

use App\Core\View;
use PHPUnit\Framework\TestCase;

final class RevealDetailTest extends TestCase
{
    public function test_reveal_shows_timeline_who_and_reshare(): void
    {
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $html = $view->render('reveal/response', [
            'title' => 'Your crush', 'state' => 'reveal', 'appUrl' => 'https://crush.app',
            'invite' => [
                'crush_name' => 'Mia', 'crush_email' => 'mia@x.test', 'public_token' => 'tok9',
                'status' => 'confirmed', 'created_at' => '2026-06-01 10:00:00', 'is_anonymous' => 1,
            ],
            'response' => [
                'chosen_start' => '2026-06-30 08:08:00', 'meal_choice' => 'dinner',
                'pickup_name' => 'Tartine', 'pickup_address' => '1 Main St',
                'pickup_clean_url' => 'https://www.google.com/maps/search/?api=1&query=Tartine',
                'created_at' => '2026-06-02 09:00:00',
            ],
        ]);
        $this->assertStringContainsString('iv-timeline', $html);                 // timeline
        $this->assertStringContainsString('Mia', $html);                          // who
        $this->assertStringContainsString('/invites/tok9/calendar', $html);       // calendar
        $this->assertStringContainsString('iv-reshare', $html);                   // reshare control
        $this->assertStringContainsString('https://www.google.com/maps', $html);  // map link
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter RevealDetailTest`
Expected: FAIL — no timeline/who/reshare.

- [ ] **Step 3: Pass `appUrl` from `RevealController::render`** — add `'appUrl' => rtrim($this->appUrl, '/')` to the render data array. (`RevealController` already has `$this->appUrl`; if not, add it from the constructor — confirm and use the existing field. If there is genuinely no appUrl on the controller, pass `''` and the template still renders, but prefer the real one.)

- [ ] **Step 4: Enrich the `reveal` branch of `templates/reveal/response.php`** — replace the `else /* reveal */` block with:

```php
  <?php else: /* reveal */
    $appUrl = $appUrl ?? '';
    $anon = (int) ($invite['is_anonymous'] ?? 0) === 1;
    $place = trim((string) (($response['pickup_name'] ?? '') . ' ' . ($response['pickup_address'] ?? '')));
    $mapHref = $response['pickup_clean_url'] ?? '';
    $steps = [['Sent', $invite['created_at'] ?? null, true],
              ['Answered', $response['created_at'] ?? null, true],
              ['Confirmed', null, in_array($invite['status'] ?? '', ['confirmed', 'closed'], true)]]; ?>
    <h1 style="text-wrap:balance;">It's a date with <?= $e($crush) ?></h1>
    <p style="opacity:.8;font-size:13px;margin-top:-4px;">
      <?= $anon ? 'You sent this anonymously.' : 'Sent as yourself.' ?>
      <?= $e($invite['crush_email'] ?? '') ?>
    </p>
    <ol class="iv-timeline" style="list-style:none;padding:0;margin:14px 0;display:flex;gap:6px;">
      <?php foreach ($steps as [$lbl, $ts, $done]): ?>
        <li style="flex:1;text-align:center;font-size:11px;<?= $done ? 'color:#16a34a;font-weight:700;' : 'opacity:.4;' ?>">
          <div style="height:6px;border-radius:3px;background:<?= $done ? '#16a34a' : '#e7d4ff' ?>;margin-bottom:4px;"></div>
          <?= $e($lbl) ?>
        </li>
      <?php endforeach; ?>
    </ol>
    <ul style="list-style:none;padding:0;line-height:1.8;">
      <li><strong>When:</strong> <?= $e($response['chosen_start'] ?? '') ?></li>
      <?php if (!empty($response['meal_choice'])): ?><li><strong>Craving:</strong> <?= $e($response['meal_choice']) ?></li><?php endif; ?>
      <?php if (!empty($response['meal_wish'])): ?><li><strong>Wish:</strong> <?= $e($response['meal_wish']) ?></li><?php endif; ?>
      <?php if (!empty($response['crush_contact'])): ?><li><strong>Contact:</strong> <?= $e($response['crush_contact']) ?></li><?php endif; ?>
      <?php if ($place !== ''): ?>
        <li><strong>Pickup:</strong>
          <?php if (is_string($mapHref) && str_starts_with((string) $mapHref, 'http')): ?>
            <a href="<?= $e($mapHref) ?>" target="_blank" rel="noopener" style="color:#ff3d8b;"><?= $e($place) ?></a>
          <?php else: ?><?= $e($place) ?><?php endif; ?>
        </li>
      <?php endif; ?>
    </ul>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;">
      <a href="/invites/<?= $e($invite['public_token']) ?>/calendar" style="padding:12px 18px;border-radius:14px;background:#ff3d8b;color:#fff;font-weight:700;text-decoration:none;">Download calendar invite</a>
      <button type="button" class="iv-reshare" data-link="<?= $e(rtrim((string) $appUrl, '/') . '/i/' . $invite['public_token']) ?>"
              style="padding:12px 18px;border-radius:14px;border:1px solid #e7d4ff;background:#fff;color:#5a2a52;font-weight:600;cursor:pointer;">Copy link</button>
    </div>
    <script>
    document.querySelectorAll('.iv-reshare').forEach(function(b){
      b.addEventListener('click', function(){
        var t = b.getAttribute('data-link');
        if (navigator.clipboard && t) navigator.clipboard.writeText(t).then(function(){
          var o = b.textContent; b.textContent = 'Copied!'; setTimeout(function(){ b.textContent = o; }, 1500);
        });
      });
    });
    </script>
  <?php endif;
```

- [ ] **Step 5: Run the test, then the full suite (serially)** — Run: `vendor/bin/phpunit --filter RevealDetailTest` then `vendor/bin/phpunit`
Expected: all green (existing reveal tests still pass — the answer fields are unchanged, only additive markup).

- [ ] **Step 6: Commit**

```bash
git add app/Reveal/RevealController.php templates/reveal/response.php tests/Reveal/RevealDetailTest.php
git commit -m "feat(reveal): detail view with status timeline, who, map link, reshare"
```

---

## Self-Review

**1. Spec coverage:** "Copied!" feedback + animation (#3) — Task 1. Letter/envelope confirmation (#4) — Task 2. Dashboard nicer cards + click-through + no raw URL (#5) — Task 3. Detail view: full answer + status timeline + calendar/reshare + who (#5) — Task 4. Icons only; escaped — throughout.

**2. Placeholder scan:** No "TBD". Envelope is theme-independent (inline styles). Timeline derives from existing timestamps/status (no new query). Full code for every change.

**3. Type consistency:** Templates consume the same `invite`/`response`/`appUrl` shapes already passed by their controllers; `RevealController::render` adds `appUrl` (existing field). `dashboard.php` uses `invites` rows (`crush_name`/`crush_email`/`status`/`public_token`). No controller signature changes except the additive `appUrl` render key. Tests render templates directly via `View` (no DB needed for Tasks 1-3; Task 4 likewise renders the template with fixture arrays).
