# Crush v2 — Plan 6: Theme Rebuild Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the three themes as **structurally distinct designs** — Love Letter (a real envelope that opens into a letter), Bubblegum (a scrapbook page), Midnight (a dating-app match card) — each its own self-contained template, not a recolor of one shared layout.

**Architecture:** The crush invite page renders a per-theme template `templates/respond/themes/{key}.php`. The functional form (date, meal radios + place reveal, wish, contact, pickup, submit) lives in one shared partial `templates/respond/_form.php` (one tested behavior); each theme provides its own page chrome + self-contained CSS. `RespondController` renders the per-theme template via a single private helper used by both `open()` and the validation reshow.

**Tech Stack:** PHP 8.1+, PHPUnit 10, hand-written CSS. Apply make-interfaces-feel-better motion (staggered reveal, scale(.96) press, layered shadows, text-wrap:balance, 44px hit areas).

## Global Constraints

- PHP floor 8.1. PSR-4. No new Composer packages.
- **Icons only — never emojis.** All HTML output escaped via `App\Core\e()`. Inline reveal JS must use `textContent`, never `innerHTML`.
- Anonymity preserved: an anonymous invite shows "a secret admirer", never the sender's name/email.
- Each theme is a **genuinely different layout/structure**, not just different colors.
- Integration tests use MySQL `crush_test`. Local dev serves on **port 8888**.

## File Structure

- `templates/respond/_form.php` — shared invite form + place-reveal JS.
- `templates/respond/themes/love-letter.php` — envelope/letter.
- `templates/respond/themes/bubblegum.php` — scrapbook page.
- `templates/respond/themes/midnight.php` — dating-app card.
- `app/Respond/RespondController.php` (modify) — render per-theme template via a private helper.
- `tests/Respond/RespondThemeTest.php` — per-theme render tests.

---

### Task 1: Shared form partial + per-theme rendering + three theme templates

**Files:**
- Create: `templates/respond/_form.php`
- Create: `templates/respond/themes/love-letter.php`
- Create: `templates/respond/themes/bubblegum.php`
- Create: `templates/respond/themes/midnight.php`
- Modify: `app/Respond/RespondController.php`
- Test: `tests/Respond/RespondThemeTest.php`

**Interfaces:**
- `RespondController` gains a private `renderInvite(array $invite, string $theme, ?string $error = null, int $status = 200): Response` that renders `respond/themes/{theme}` (theme validated against `['love-letter','bubblegum','midnight']`, fallback `'bubblegum'`) with: `title`, `theme`, `csrf`, `token`, `senderLabel`, `message`, `dateMode`, `options`, `meals` (`MealOptions::CHOICES`), `places` (`InvitePlaceRepo::forInvite`), `error`. `open()` calls `renderInvite($invite, $theme)`; the submit validation reshow calls `renderInvite($invite, $theme, $error, $status)` (this also passes `places`, closing the v2-5 reshow gap).

- [ ] **Step 1: Write the failing test** — `tests/Respond/RespondThemeTest.php`

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

final class RespondThemeTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    /** @param \Closure(int):int $picker chooses theme index 0,1,2 */
    private function controller(\Closure $picker): RespondController
    {
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $invites = new InviteRepo($this->pdo(), $this->clock);
        $users = new UserRepo($this->pdo(), $this->clock);
        $postman = new Postman(new SpyMailer(), new IcsBuilder($this->clock), $view, 'http://localhost');
        $onboarder = new CrushOnboarder($users, new MagicLink($this->pdo(), $users, $this->clock, 900), $postman, 'http://localhost');
        return new RespondController(
            $view, new Csrf(new ArrayStore()), $invites, new ResponseRepo($this->pdo(), $this->clock), $users,
            new ABAssigner(new ThemeRepo($this->pdo()), $invites, $picker),
            new AbEventRepo($this->pdo(), $this->clock), $this->clock,
            new LinkResolver(new FakeFetcher([])), $postman, $onboarder, new InvitePlaceRepo($this->pdo())
        );
    }

    private function invite(bool $anon): array
    {
        $sender = (new UserRepo($this->pdo(), $this->clock))->create('sue@x.test', 'Sue', 'magic')['id'];
        return (new InviteRepo($this->pdo(), $this->clock))->create([
            'sender_id' => $sender, 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => $anon ? 1 : 0, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => 'hi there', 'expires_at' => '2030-01-01 00:00:00',
        ]);
    }

    public function test_each_theme_renders_its_own_template_with_the_form(): void
    {
        // active themes ordered: bubblegum(0), love-letter(1), midnight(2)
        $cases = [0 => 'theme-bubblegum', 1 => 'theme-love-letter', 2 => 'theme-midnight'];
        foreach ($cases as $idx => $marker) {
            $ctrl = $this->controller(fn(int $m) => $idx);
            $res = $ctrl->open($this->invite(false)['public_token']);
            $this->assertSame(200, $res->status());
            $this->assertStringContainsString($marker, $res->body(), "theme class for index $idx");
            $this->assertStringContainsString('name="chosen_start"', $res->body());
            $this->assertStringContainsString('name="meal_choice"', $res->body());
        }
    }

    public function test_anonymous_sender_hidden_in_theme(): void
    {
        $ctrl = $this->controller(fn(int $m) => 2); // midnight
        $res = $ctrl->open($this->invite(true)['public_token']);
        $this->assertStringContainsString('secret admirer', $res->body());
        $this->assertStringNotContainsString('sue@x.test', $res->body());
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter RespondThemeTest`
Expected: FAIL — theme template `respond/themes/...` not found (or marker missing).

- [ ] **Step 3: Write `templates/respond/_form.php`** (shared functional form; generic `rf-*` classes; `textContent` reveal; fixes the v2-5 "show label not key" nit via `data-label`)

```php
<?php $error = $error ?? null; $meals = $meals ?? []; $places = $places ?? []; ?>
<?php if ($error): ?><p class="rf-error" role="alert"><?= $e($error) ?></p><?php endif; ?>
<form method="post" action="/i/<?= $e($token) ?>" class="rf-form">
  <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
  <label class="rf-field">Pick a day &amp; time
    <input type="datetime-local" name="chosen_start" required>
  </label>
  <fieldset class="rf-meals">
    <legend>What are you craving?</legend>
    <div class="rf-chips">
      <?php foreach ($meals as $m): $p = $places[$m['key']] ?? null; $plabel = $p ? ($p['place_resolved_name'] ?: $p['place_name']) : ''; ?>
        <label class="rf-chip">
          <input type="radio" name="meal_choice" value="<?= $e($m['key']) ?>" data-place="<?= $e($plabel) ?>" data-label="<?= $e($m['label']) ?>">
          <svg class="rf-ic"><use href="#<?= $e($m['icon']) ?>"/></svg>
          <span><?= $e($m['label']) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
  </fieldset>
  <p class="rf-place" style="display:none"></p>
  <label class="rf-field">Any wish? (optional)
    <input type="text" name="meal_wish" placeholder="surprise me">
  </label>
  <label class="rf-field">Your contact (optional)
    <input type="text" name="crush_contact" placeholder="phone or @handle">
  </label>
  <label class="rf-field">Where should they pick you up? (optional)
    <input type="text" name="pickup_raw" placeholder="address or Google Maps link">
  </label>
  <button type="submit" class="rf-cta">Send my answer</button>
</form>
<script>
(function(){
  var p = document.querySelector('.rf-place');
  if (!p) return;
  document.querySelectorAll('input[name="meal_choice"]').forEach(function(r){
    r.addEventListener('change', function(){
      if (r.dataset.place) { p.textContent = r.dataset.label + ' at ' + r.dataset.place; p.style.display = 'block'; }
      else { p.style.display = 'none'; }
    });
  });
})();
</script>
```

- [ ] **Step 4: Write `templates/respond/themes/love-letter.php`** (envelope → letter; serif; aged paper; self-contained CSS)

```php
<?php $message = $message ?? null; ?>
<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $e($title ?? 'Crush') ?></title>
<style>
  *{box-sizing:border-box} body{margin:0;min-height:100svh;display:flex;align-items:center;justify-content:center;padding:20px;
    background:repeating-linear-gradient(45deg,#efe2c8,#efe2c8 14px,#ece0c4 14px,#ece0c4 28px);
    font-family:Georgia,"Times New Roman",serif;color:#5a3b2e;-webkit-font-smoothing:antialiased}
  .ll-letter{width:min(94vw,440px);background:#fbf3e0;border-radius:6px;padding:30px 30px 26px;position:relative;
    box-shadow:0 2px 4px rgba(90,59,46,.15),0 24px 50px rgba(90,59,46,.28);
    background-image:linear-gradient(#fbf3e0 95%,#e8d9b8);background-size:100% 34px;}
  .ll-seal{width:64px;height:64px;border-radius:50%;margin:-54px auto 6px;display:flex;align-items:center;justify-content:center;
    background:radial-gradient(circle at 35% 30%,#d8556a,#a51f37);color:#fff;box-shadow:inset 0 -4px 8px rgba(0,0,0,.3);}
  .ll-seal svg{width:30px;height:30px} .ll-kicker{text-align:center;font-style:italic;font-size:22px;text-wrap:balance;margin:8px 0}
  .ll-msg{text-align:center;line-height:1.6;opacity:.9} .rf-error{color:#a51f37;text-align:center}
  .rf-form{display:flex;flex-direction:column;gap:14px;margin-top:18px} .rf-field{display:flex;flex-direction:column;gap:4px;font-style:italic;font-size:14px}
  .rf-field input{padding:9px;border:0;border-bottom:1.5px solid #c8a96f;background:transparent;font-family:inherit;font-size:16px;color:#5a3b2e}
  .rf-meals{border:0;padding:0;margin:0} .rf-meals legend{font-style:italic;margin-bottom:8px}
  .rf-chips{display:flex;flex-wrap:wrap;gap:8px} .rf-chip{display:inline-flex;align-items:center;gap:6px;min-height:44px;padding:6px 12px;border:1.5px solid #c8a96f;border-radius:4px;cursor:pointer;transition:scale .12s}
  .rf-chip:active{scale:.96} .rf-chip input{position:absolute;opacity:0;width:0;height:0} .rf-chip:has(input:checked){background:#a51f37;color:#fff;border-color:#a51f37}
  .rf-ic{width:16px;height:16px} .rf-place{font-style:italic;color:#a51f37} .rf-cta{min-height:48px;border:0;border-radius:4px;background:#a51f37;color:#fbf3e0;font-family:inherit;font-size:17px;font-style:italic;cursor:pointer;box-shadow:0 4px 0 #6f1224;transition:scale .12s} .rf-cta:active{scale:.96;box-shadow:0 2px 0 #6f1224}
</style></head>
<body class="theme-love-letter">
<?php include __DIR__ . '/../../partials/icons.php'; ?>
<main class="ll-letter">
  <div class="ll-seal"><svg><use href="#ic-heart"/></svg></div>
  <p class="ll-kicker"><?= $e($senderLabel) ?> requests your company</p>
  <?php if ($message): ?><p class="ll-msg"><?= $e($message) ?></p><?php endif; ?>
  <?php include __DIR__ . '/../_form.php'; ?>
</main>
</body></html>
```

- [ ] **Step 5: Write `templates/respond/themes/bubblegum.php`** (scrapbook page; tilted frames, washi tape, sticker chips; self-contained CSS)

```php
<?php $message = $message ?? null; ?>
<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $e($title ?? 'Crush') ?></title>
<style>
  *{box-sizing:border-box} body{margin:0;min-height:100svh;display:flex;align-items:center;justify-content:center;padding:20px;
    background:#ffe9f4 radial-gradient(circle at 20% 20%,#fff0fa,#ffd9ec 60%,#e7d4ff);
    font-family:"Trebuchet MS","Segoe UI",sans-serif;color:#7a2e6b;-webkit-font-smoothing:antialiased}
  .bg-page{width:min(94vw,440px);background:#fff;border-radius:10px;padding:26px 22px 24px;position:relative;transform:rotate(-1.2deg);
    box-shadow:0 2px 4px rgba(122,46,107,.12),0 18px 40px rgba(157,123,255,.3)}
  .bg-page:before{content:"";position:absolute;top:-12px;left:30px;width:90px;height:26px;background:rgba(255,209,102,.7);transform:rotate(-6deg);box-shadow:0 2px 4px rgba(0,0,0,.08)}
  .bg-page:after{content:"";position:absolute;top:-10px;right:26px;width:80px;height:24px;background:rgba(157,123,255,.55);transform:rotate(5deg)}
  .bg-title{font-size:26px;font-weight:900;color:#ff3d8b;text-shadow:1px 1px 0 #fff;text-wrap:balance;margin:6px 0;transform:rotate(.6deg)}
  .bg-msg{background:#fff7fb;border:1px dashed #ff9ccb;border-radius:8px;padding:10px;transform:rotate(-.8deg);line-height:1.5}
  .rf-error{color:#c81e68} .rf-form{display:flex;flex-direction:column;gap:14px;margin-top:16px} .rf-field{display:flex;flex-direction:column;gap:5px;font-size:13px;font-weight:700;color:#9a6a90}
  .rf-field input{padding:11px;border:2px solid #ffd0e8;border-radius:12px;font-family:inherit;font-size:16px}
  .rf-meals{border:0;padding:0;margin:0} .rf-meals legend{font-weight:800;color:#ff3d8b;margin-bottom:6px}
  .rf-chips{display:flex;flex-wrap:wrap;gap:10px} .rf-chip{display:inline-flex;align-items:center;gap:6px;min-height:44px;padding:8px 12px;background:#fff;border-radius:14px;cursor:pointer;box-shadow:0 2px 6px rgba(255,61,139,.25);transform:rotate(-2deg);transition:scale .12s,transform .12s}
  .rf-chip:nth-child(even){transform:rotate(2deg)} .rf-chip:active{scale:.96} .rf-chip input{position:absolute;opacity:0;width:0;height:0} .rf-chip:has(input:checked){box-shadow:0 0 0 3px #ff3d8b inset}
  .rf-ic{width:16px;height:16px;color:#ff3d8b} .rf-place{font-weight:800;color:#ff3d8b;transform:rotate(-1deg)}
  .rf-cta{min-height:50px;border:0;border-radius:999px;background:#ff3d8b;color:#fff;font-family:inherit;font-weight:800;font-size:17px;cursor:pointer;box-shadow:0 5px 0 #c81e68;transition:scale .12s} .rf-cta:active{scale:.96;box-shadow:0 2px 0 #c81e68}
</style></head>
<body class="theme-bubblegum">
<?php include __DIR__ . '/../../partials/icons.php'; ?>
<main class="bg-page">
  <h1 class="bg-title"><?= $e($senderLabel) ?> has a crush on u</h1>
  <?php if ($message): ?><p class="bg-msg"><?= $e($message) ?></p><?php endif; ?>
  <?php include __DIR__ . '/../_form.php'; ?>
</main>
</body></html>
```

- [ ] **Step 6: Write `templates/respond/themes/midnight.php`** (dating-app match card; dark hero, glass card, pill carousel; self-contained CSS)

```php
<?php $message = $message ?? null; ?>
<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $e($title ?? 'Crush') ?></title>
<style>
  *{box-sizing:border-box} body{margin:0;min-height:100svh;display:flex;align-items:flex-end;justify-content:center;
    background:radial-gradient(130% 80% at 70% 0%,#3a1a5e,#160c2e 55%,#0c0720);font-family:"Segoe UI",system-ui,sans-serif;color:#ede6ff;-webkit-font-smoothing:antialiased}
  .mn-star{position:fixed;width:3px;height:3px;background:#fff;border-radius:50%;opacity:.7}
  .mn-card{width:min(100vw,460px);margin:0 8px;background:rgba(255,255,255,.06);backdrop-filter:blur(12px);
    border:1px solid rgba(255,143,199,.25);border-radius:28px 28px 0 0;padding:26px 22px 28px;
    box-shadow:0 -10px 50px rgba(157,123,255,.4)}
  .mn-hero{height:96px;border-radius:20px;margin-bottom:14px;display:flex;align-items:center;justify-content:center;
    background:linear-gradient(120deg,#ff5fa2,#9d7bff);box-shadow:0 0 30px rgba(157,123,255,.6)}
  .mn-hero svg{width:44px;height:44px;color:#fff;filter:drop-shadow(0 0 10px rgba(255,255,255,.6))}
  .mn-title{font-size:24px;font-weight:800;text-wrap:balance;margin:4px 0;background:linear-gradient(90deg,#ff8fc7,#9d7bff);-webkit-background-clip:text;background-clip:text;color:transparent}
  .mn-msg{opacity:.85;line-height:1.5} .rf-error{color:#ff8fb0}
  .rf-form{display:flex;flex-direction:column;gap:14px;margin-top:14px} .rf-field{display:flex;flex-direction:column;gap:5px;font-size:13px;font-weight:600;color:#b8a8e0}
  .rf-field input{padding:12px;border:1px solid rgba(255,143,199,.35);border-radius:14px;background:rgba(255,255,255,.06);color:#ede6ff;font-size:16px;font-family:inherit}
  .rf-meals{border:0;padding:0;margin:0} .rf-meals legend{font-weight:600;color:#b8a8e0;margin-bottom:8px}
  .rf-chips{display:flex;gap:10px;overflow-x:auto;padding-bottom:6px} .rf-chip{flex:0 0 auto;display:inline-flex;align-items:center;gap:6px;min-height:44px;padding:9px 14px;border-radius:999px;background:rgba(255,255,255,.08);color:#ffb3da;border:1px solid rgba(255,143,199,.4);cursor:pointer;white-space:nowrap;transition:scale .12s}
  .rf-chip:active{scale:.96} .rf-chip input{position:absolute;opacity:0;width:0;height:0} .rf-chip:has(input:checked){box-shadow:0 0 0 2px #ff5fa2 inset;background:rgba(255,95,162,.18)}
  .rf-ic{width:15px;height:15px} .rf-place{font-weight:700;color:#ff8fc7}
  .rf-cta{min-height:50px;border:0;border-radius:16px;background:linear-gradient(90deg,#ff5fa2,#9d7bff);color:#fff;font-weight:800;font-size:17px;font-family:inherit;cursor:pointer;box-shadow:0 0 24px rgba(157,123,255,.6);transition:scale .12s} .rf-cta:active{scale:.96}
</style></head>
<body class="theme-midnight">
<?php include __DIR__ . '/../../partials/icons.php'; ?>
<span class="mn-star" style="top:8%;left:18%"></span><span class="mn-star" style="top:14%;left:72%"></span>
<span class="mn-star" style="top:22%;left:44%"></span><span class="mn-star" style="top:6%;left:60%"></span>
<main class="mn-card">
  <div class="mn-hero"><svg><use href="#ic-moon"/></svg></div>
  <h1 class="mn-title"><?= $e($senderLabel) ?> has a crush on you</h1>
  <?php if ($message): ?><p class="mn-msg"><?= $e($message) ?></p><?php endif; ?>
  <?php include __DIR__ . '/../_form.php'; ?>
</main>
</body></html>
```

- [ ] **Step 7: Refactor `RespondController` to render per-theme templates** — add the private helper and route `open()` + the submit reshow through it.

Add:
```php
    private const THEME_TEMPLATES = ['love-letter', 'bubblegum', 'midnight'];

    private function renderInvite(array $invite, string $theme, ?string $error = null, int $status = 200): Response
    {
        $key = in_array($theme, self::THEME_TEMPLATES, true) ? $theme : 'bubblegum';
        return Response::html($this->view->render('respond/themes/' . $key, [
            'title'       => 'You have an invite',
            'theme'       => $key,
            'csrf'        => $this->csrf->token(),
            'token'       => $invite['public_token'],
            'senderLabel' => $this->senderLabel($invite),
            'message'     => $invite['message'],
            'dateMode'    => $invite['date_mode'],
            'options'     => $this->invites->dateOptions((int) $invite['id']),
            'meals'       => MealOptions::CHOICES,
            'places'      => $this->places->forInvite((int) $invite['id']),
            'error'       => $error,
        ]), $status);
    }
```

In `open()`, replace the final `return Response::html($this->view->render('respond/show', [...]));` with `return $this->renderInvite($invite, $theme);`.

In `submit()`, replace the `reshow(...)` helper body (the validation re-render path) so it calls `return $this->renderInvite($invite, $theme, $error, $status);` (drop the old `respond/show` render). Keep the `reshow` method signature/callers unchanged.

> `MealOptions` and `InvitePlaceRepo` are already used by `RespondController` (v2-5). `templates/respond/show.php` may be left in place (no longer referenced) or deleted; leaving it is harmless.

- [ ] **Step 8: Run the theme test** — Run: `vendor/bin/phpunit --filter RespondThemeTest`
Expected: PASS (2 tests).

- [ ] **Step 9: Run the full suite** — Run: `vendor/bin/phpunit`
Expected: all green. (Existing `RespondOpenTest`/`RespondSubmitTest`/`RespondPlaceTest` assert on body content like "secret admirer", "dinner", "name=…" which the new templates still emit via `_form.php` + `senderLabel`. Fix any assertion that referenced markup unique to the old `show.php` by pointing it at the equivalent in the new templates.)

- [ ] **Step 10: Manual check on port 8888** — seed an invite per theme and confirm each renders its own structure:

```bash
DB_DSN="mysql:host=127.0.0.1;dbname=crush_dev;charset=utf8mb4" DB_USER=root DB_PASS= APP_URL="http://127.0.0.1:8888" \
  php -S 127.0.0.1:8888 -t public >/dev/null 2>&1 &
sleep 1
for T in love-letter bubblegum midnight; do
  TOK=$(php -r '$p=new PDO("mysql:host=127.0.0.1;dbname=crush_dev;charset=utf8mb4","root","");$p->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$p->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);require "vendor/autoload.php";$c=new App\Core\SystemClock();$u=new App\Auth\UserRepo($p,$c);$s=$u->findByEmail("seed@x.test")?:$u->create("seed@x.test","Seed","magic");$i=new App\Invite\InviteRepo($p,$c);$inv=$i->create(["sender_id"=>$s["id"],"crush_email"=>"c@x.test","crush_name"=>"You","is_anonymous"=>1,"reveal_on_response"=>1,"date_mode"=>"instant","message"=>"hi","theme_key"=>$argv[1],"expires_at"=>"2030-01-01 00:00:00"]);echo $inv["public_token"];' "$T")
  echo "$T => $(curl -s "127.0.0.1:8888/i/$TOK" | grep -o "theme-$T" | head -1)"
done
kill %1
```
Expected: each line prints its matching `theme-<key>` class.

- [ ] **Step 11: Commit**

```bash
git add templates/respond/_form.php templates/respond/themes/ app/Respond/RespondController.php tests/Respond/RespondThemeTest.php
git commit -m "feat(themes): rebuild 3 themes as structurally distinct templates"
```

---

## Self-Review

**1. Spec coverage:** Three structurally distinct themes — envelope/letter, scrapbook, dating-app card — each its own template (spec §7) — Task 1. Per-theme template rendering with fallback, A/B + funnel unchanged (§7,§11) — Task 1 `renderInvite`. Shared functional form keeps behavior tested once; place reveal carried over (§6) — `_form.php` (also fixes v2-5 reshow-places gap + label-not-key nit). Anonymity preserved (§: privacy) — `senderLabel`, tested. Icons only, `textContent` reveal, escaped output, motion (scale/press, text-wrap:balance, 44px hit areas). Port 8888 manual check.

**2. Placeholder scan:** No "TBD". All three theme templates are complete, self-contained pages with real distinct CSS. The controller helper is fully shown.

**3. Type consistency:** `RespondController::renderInvite(array,string,?string,int): Response` (private); consumes `MealOptions::CHOICES`, `InvitePlaceRepo::forInvite`, `InviteRepo::dateOptions`, `senderLabel()` as already defined in v1/v2. Templates consume `senderLabel`/`message`/`csrf`/`token`/`meals`/`places`/`error` provided by `renderInvite`; `_form.php` is included within theme scope so those vars + `$e` are in scope.
