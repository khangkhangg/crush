# Crush v9 — Plan 1: UI Internationalization Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the UI translatable into 10 languages with a casual, human "17-year-old" voice; detect the browser language, let users switch via a globe icon (top-right), ship an About page, and let admins edit translations at `/admin/languages`. This plan builds the full pipeline and proves it on the landing + About screens (other screens get wrapped in a follow-up).

**Architecture:** Gettext-style — the **English phrase is the key**, so `$t('Send a crush invite')` returns the active-language translation or the English fallback. A DB `ui_translations(lang, key, value)` store (admin-editable) backs `App\I18n\Translator`, which `View` injects as `$t` (+ the active `$lang`). The active language = `lang` cookie → `Accept-Language` → `en`.

**Tech Stack:** PHP 8.1+, PDO MySQL, PHPUnit 10. No new Composer dependencies.

## Global Constraints

- PHP floor 8.1. PSR-4. No new Composer packages. Prepared statements.
- **Icons only — never emojis.** All HTML `$e()`-escaped; translations are plain text (no HTML) and are escaped at output. POSTs validate CSRF; `/admin/languages` is admin-gated. Run the suite **serially** (FK/"Duplicate schema_migrations" bursts = concurrent-run corruption → reset `crush_test`).
- Languages (all LTR): `en` English, `vi` Tiếng Việt, `es` Español, `zh` 中文, `hi` हिन्दी, `pt` Português, `fr` Français, `ko` 한국어, `ja` 日本語, `th` ไทย. Casual tone. Production: `https://crush.didudi.com`.

## File Structure

- `migrations/0016_ui_translations.sql`, `migrations/0017_seed_landing_about.sql`.
- `app/I18n/Translator.php`, `app/I18n/Languages.php` (new); `app/Core/Locale.php`, `app/Core/View.php` (modify).
- `app/I18n/LangController.php`, `app/About/AboutController.php` (new); `config/routes.php`, `public/index.php` (modify).
- `templates/layout.php` + standalone templates (switcher); `templates/about.php`, `templates/landing/home.php` (wrap); `templates/admin/languages.php`, `templates/admin/language_edit.php` (admin).
- `app/Admin/AdminController.php` (modify) — language management.

---

### Task 1: Translation store + Translator + Languages + View `$t`

**Files:**
- Create: `migrations/0016_ui_translations.sql`, `app/I18n/Translator.php`, `app/I18n/Languages.php`
- Modify: `app/Core/Locale.php`, `app/Core/View.php`
- Test: `tests/I18n/TranslatorTest.php`

**Interfaces:**
- `ui_translations(id, lang VARCHAR(5), `key` VARCHAR(191), value TEXT, UNIQUE(lang,`key`))`.
- `App\I18n\Languages`: `const ALL = ['en'=>'English','vi'=>'Tiếng Việt','es'=>'Español','zh'=>'中文','hi'=>'हिन्दी','pt'=>'Português','fr'=>'Français','ko'=>'한국어','ja'=>'日本語','th'=>'ไทย']`; `static codes(): array`; `static name(string): string`.
- `App\I18n\Translator(\PDO $pdo, string $lang)`: `lang(): string`; `t(string $english): string` (returns the `(lang,english)` value, else `$english`); `all(string $lang): array` (key→value for the admin UI).
- `App\Core\Locale::SUPPORTED` = the 10 codes.
- `App\Core\View::__construct(string $templateDir, ?Translator $translator = null)`; `render` injects `$t` (calls `$translator?->t($s) ?? $s`) and `$lang` (`$translator?->lang() ?? 'en'`).

- [ ] **Step 1: Write the failing test** — `tests/I18n/TranslatorTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\I18n;

use App\Core\Locale;
use App\Core\View;
use App\I18n\Languages;
use App\I18n\Translator;
use Tests\Support\DatabaseTestCase;

final class TranslatorTest extends DatabaseTestCase
{
    public function test_locale_supports_ten_languages(): void
    {
        foreach (['en', 'vi', 'es', 'zh', 'hi', 'pt', 'fr', 'ko', 'ja', 'th'] as $c) {
            $this->assertTrue(Locale::isSupported($c), $c);
        }
        $this->assertSame('Tiếng Việt', Languages::name('vi'));
    }

    public function test_translate_falls_back_to_english(): void
    {
        $this->pdo()->prepare('INSERT INTO ui_translations (lang, `key`, value) VALUES (?, ?, ?)')
            ->execute(['vi', 'Send a crush invite', 'Gửi lời mời hẹn hò']);
        $vi = new Translator($this->pdo(), 'vi');
        $this->assertSame('Gửi lời mời hẹn hò', $vi->t('Send a crush invite'));
        $this->assertSame('Untranslated here', $vi->t('Untranslated here'));   // fallback = English
        $en = new Translator($this->pdo(), 'en');
        $this->assertSame('Send a crush invite', $en->t('Send a crush invite')); // en = identity
    }

    public function test_view_injects_t_and_lang(): void
    {
        $this->pdo()->prepare('INSERT INTO ui_translations (lang, `key`, value) VALUES (?, ?, ?)')
            ->execute(['vi', 'Your invites', 'Lời mời của bạn']);
        $view = new View(\dirname(__DIR__, 2) . '/templates', new Translator($this->pdo(), 'vi'));
        $html = $view->render('invite/dashboard', ['title' => 'x', 'invites' => [], 'appUrl' => '']);
        $this->assertStringContainsString('lang="vi"', $html);   // layout sets <html lang>
    }
}
```

> The `lang="vi"` assertion depends on Task 2 wiring `<html lang="<?= $e($lang) ?>">` in `layout.php`. If you implement Task 1 strictly first, assert instead that `View` exposes `$t`/`$lang` by rendering a tiny template, or move this assertion to Task 2. Keep the first two assertions in Task 1.

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter TranslatorTest`
Expected: FAIL.

- [ ] **Step 3: Write `migrations/0016_ui_translations.sql`**

```sql
CREATE TABLE IF NOT EXISTS ui_translations (
  id    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  lang  VARCHAR(5)   NOT NULL,
  `key` VARCHAR(191) NOT NULL,
  value TEXT         NOT NULL,
  UNIQUE KEY uq_tr_lang_key (lang, `key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- [ ] **Step 4: Write `app/I18n/Languages.php` + `app/I18n/Translator.php`** — `Languages` per the interface above. `Translator`:

```php
<?php
declare(strict_types=1);

namespace App\I18n;

final class Translator
{
    /** @var array<string,string>|null */
    private ?array $map = null;

    public function __construct(private \PDO $pdo, private string $lang) {}

    public function lang(): string
    {
        return $this->lang;
    }

    public function t(string $english): string
    {
        if ($this->lang === 'en') {
            return $english;
        }
        if ($this->map === null) {
            $this->map = $this->all($this->lang);
        }
        return $this->map[$english] ?? $english;
    }

    /** @return array<string,string> */
    public function all(string $lang): array
    {
        $stmt = $this->pdo->prepare('SELECT `key`, value FROM ui_translations WHERE lang = ?');
        $stmt->execute([$lang]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(string) $row['key']] = (string) $row['value'];
        }
        return $out;
    }
}
```

- [ ] **Step 5: Extend `Locale::SUPPORTED`** to the 10 codes (`['en','vi','es','zh','hi','pt','fr','ko','ja','th']`). The q-weighted `detect` is unchanged (now matches more primary subtags).

- [ ] **Step 6: Inject `$t` + `$lang` in `View::render`** — add the optional ctor param +, in the `$render` closure, define `$t` and `$lang`:

```php
        $translator = $this->translator;
        $render = static function (string $__path, array $__data) use ($translator): string {
            $e = static fn(mixed $v): string => \App\Core\e($v);
            $t = static fn(string $s): string => $translator !== null ? $translator->t($s) : $s;
            $lang = $translator !== null ? $translator->lang() : 'en';
            extract($__data, EXTR_SKIP);
            ob_start();
            include $__path;
            return (string) ob_get_clean();
        };
```

(`extract(..., EXTR_SKIP)` won't overwrite `$t`/`$lang`/`$e`.)

- [ ] **Step 7: Run the tests (keep Task 1's two non-layout assertions green), then the full suite (serially)** — Run: `vendor/bin/phpunit --filter TranslatorTest` then `vendor/bin/phpunit`
Expected: green (existing `View`/render callers pass `null` translator → English identity; `Locale` detect tests still pass).

- [ ] **Step 8: Commit**

```bash
git add migrations/0016_ui_translations.sql app/I18n/ app/Core/Locale.php app/Core/View.php tests/I18n/TranslatorTest.php
git commit -m "feat(i18n): ui_translations store + Translator + 10 languages + View \$t"
```

---

### Task 2: Language resolution + globe switcher + /lang route

**Files:**
- Modify: `public/index.php`, `config/routes.php`, `templates/layout.php`, `templates/landing/home.php`, `templates/respond/confirmed.php`, `templates/respond/themes/{bubblegum,love-letter,midnight}.php`
- Create: `app/I18n/LangController.php`, `templates/partials/lang_switcher.php`
- Test: `tests/I18n/LangSwitchTest.php`

**Interfaces:**
- `public/index.php` resolves `$lang` = `$_COOKIE['lang']` if `Locale::isSupported`, else `Locale::detect($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')`; builds `new Translator($pdo, $lang)`; passes it to `new View(..., $translator)`.
- `App\I18n\LangController::set(string $code, ?string $referer): Response` — if `Locale::isSupported($code)`, set a 1-year `lang` cookie + `302` back to a safe `$referer` (must start `/`, else `/`); else `302 /`. Route `GET /lang/{code}`.
- `partials/lang_switcher.php` renders a top-right globe `<details>` menu of `Languages::ALL` linking to `/lang/{code}`; included by every full-HTML template; the active `$lang` is marked.
- `layout.php` sets `<html lang="<?= $e($lang ?? 'en') ?>">` and includes the switcher.

- [ ] **Step 1: Write the failing test** — `tests/I18n/LangSwitchTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\I18n;

use App\I18n\LangController;
use PHPUnit\Framework\TestCase;

final class LangSwitchTest extends TestCase
{
    public function test_supported_sets_cookie_and_redirects_back(): void
    {
        $res = (new LangController())->set('vi', '/invites/new');
        $this->assertSame(302, $res->status());
        $this->assertSame('/invites/new', $res->headers()['Location']);
        $this->assertStringContainsString('lang=vi', implode(' ', $res->headers()));   // Set-Cookie
    }

    public function test_unsupported_redirects_home_no_cookie(): void
    {
        $res = (new LangController())->set('xx', '/invites');
        $this->assertSame('/', $res->headers()['Location']);
    }

    public function test_unsafe_referer_goes_home(): void
    {
        $res = (new LangController())->set('vi', 'https://evil.com');
        $this->assertSame('/', $res->headers()['Location']);
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter LangSwitchTest`
Expected: FAIL.

- [ ] **Step 3: Write `app/I18n/LangController.php`**

```php
<?php
declare(strict_types=1);

namespace App\I18n;

use App\Core\Locale;
use App\Core\Response;

final class LangController
{
    public function set(string $code, ?string $referer): Response
    {
        $dest = (is_string($referer) && str_starts_with($referer, '/') && !str_starts_with($referer, '//')) ? $referer : '/';
        if (!Locale::isSupported($code)) {
            return (new Response('', 302))->withHeader('Location', '/');
        }
        $cookie = 'lang=' . $code . '; Path=/; Max-Age=31536000; SameSite=Lax';
        return (new Response('', 302))->withHeader('Location', $dest)->withHeader('Set-Cookie', $cookie);
    }
}
```

- [ ] **Step 4: Write `templates/partials/lang_switcher.php`** (globe `<details>`, top-right)

```php
<?php $lang = $lang ?? 'en'; ?>
<details class="lang-switch" style="position:fixed;top:10px;right:10px;z-index:40;">
  <summary style="list-style:none;cursor:pointer;width:38px;height:38px;border-radius:50%;background:rgba(255,255,255,.85);box-shadow:0 2px 8px rgba(90,42,82,.18);display:flex;align-items:center;justify-content:center;">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#5a2a52" stroke-width="1.7"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg>
  </summary>
  <div style="position:absolute;right:0;margin-top:6px;background:#fff;border-radius:12px;box-shadow:0 8px 24px rgba(90,42,82,.2);padding:6px;min-width:150px;max-height:60vh;overflow:auto;">
    <?php foreach (\App\I18n\Languages::ALL as $code => $name): ?>
      <a href="/lang/<?= $e($code) ?>" style="display:block;padding:8px 12px;border-radius:8px;text-decoration:none;color:#5a2a52;font-weight:<?= $lang === $code ? '800' : '500' ?>;background:<?= $lang === $code ? '#faf2ff' : 'transparent' ?>;"><?= $e($name) ?></a>
    <?php endforeach; ?>
  </div>
</details>
```

- [ ] **Step 5: Wire the switcher + `<html lang>` into the templates** — in `templates/layout.php`: change `<html lang="en">` to `<html lang="<?= $e($lang ?? 'en') ?>">` and add `<?php include __DIR__ . '/partials/lang_switcher.php'; ?>` right after `<body>`. Add the same include (path adjusted) + `<html lang>` to the standalone full-HTML templates: `landing/home.php`, `respond/confirmed.php`, `respond/themes/{bubblegum,love-letter,midnight}.php`. (Use `__DIR__ . '/../partials/lang_switcher.php'` from `landing`/`respond`, `__DIR__ . '/../../partials/lang_switcher.php'` from `respond/themes`.)

- [ ] **Step 6: Resolve the language + wire in `public/index.php`** — after `$pdo` is available and before `new View(...)`:

```php
$reqLang = is_string($_COOKIE['lang'] ?? null) && \App\Core\Locale::isSupported($_COOKIE['lang'])
    ? $_COOKIE['lang']
    : \App\Core\Locale::detect((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
$translator = new \App\I18n\Translator($pdo, $reqLang);
$view = new View(dirname(__DIR__) . '/templates', $translator);
```

(Replace the existing `$view = new View(...)`.) Pass `$lang`/switcher needs no extra wiring — `View` injects `$lang`. Add the route in `config/routes.php`:

```php
    $router->add('GET', '/lang/{code}', static fn(string $code): Response => (new \App\I18n\LangController())->set(
        $code, is_string($_SERVER['HTTP_REFERER'] ?? null) ? parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) : null
    ));
```

- [ ] **Step 7: Run the tests, then the full suite (serially)** — Run: `vendor/bin/phpunit --filter "LangSwitchTest|TranslatorTest"` then `vendor/bin/phpunit`
Expected: all green (existing template-render tests still pass — the switcher is additive markup; `<html lang>` defaults to `en`).

- [ ] **Step 8: Commit**

```bash
git add public/index.php config/routes.php app/I18n/LangController.php templates/partials/lang_switcher.php templates/layout.php templates/landing/home.php templates/respond/ tests/I18n/LangSwitchTest.php
git commit -m "feat(i18n): browser-detect + cookie language + globe switcher + /lang route"
```

---

### Task 3: About page + wrap landing & About + seed 10-language translations

**Files:**
- Create: `app/About/AboutController.php`, `templates/about.php`, `migrations/0017_seed_landing_about.sql`
- Modify: `templates/landing/home.php`, `config/routes.php`, `public/index.php`
- Test: `tests/About/AboutTest.php`

**Interfaces:**
- `App\About\AboutController::__construct(View)`; `show(): Response` renders `about` (translatable copy + a link back). Route `GET /about`.
- `landing/home.php` and `about.php` wrap their user-visible strings in `$t('…')`.
- `0017_seed_landing_about.sql` seeds `ui_translations` for the landing + about strings in the 9 non-English languages (casual tone).

- [ ] **Step 1: Write the failing test** — `tests/About/AboutTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\About;

use App\About\AboutController;
use App\Core\View;
use App\I18n\Translator;
use Tests\Support\DatabaseTestCase;

final class AboutTest extends DatabaseTestCase
{
    public function test_about_renders_and_translates(): void
    {
        $en = new AboutController(new View(\dirname(__DIR__, 2) . '/templates', new Translator($this->pdo(), 'en')));
        $body = $en->show()->body();
        $this->assertSame(200, (new AboutController(new View(\dirname(__DIR__, 2) . '/templates', new Translator($this->pdo(), 'en'))))->show()->status());
        $this->assertStringContainsString('Crush', $body);

        // A seeded Vietnamese landing string (from migration 0017) is used by $t.
        $vi = new Translator($this->pdo(), 'vi');
        $this->assertNotSame('', $vi->t('Send your crush a date — anonymously, adorably.'));  // has a vi row or falls back
    }
}
```

> Keep this assertion lenient (fallback-safe). The real proof is the seeded rows existing — add a direct count assertion if you prefer: `SELECT COUNT(*) FROM ui_translations WHERE lang='vi'` > 0.

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter AboutTest`
Expected: FAIL — controller/template missing.

- [ ] **Step 3: Write `app/About/AboutController.php`**

```php
<?php
declare(strict_types=1);

namespace App\About;

use App\Core\Response;
use App\Core\View;

final class AboutController
{
    public function __construct(private View $view) {}

    public function show(): Response
    {
        return Response::html($this->view->render('about', ['title' => 'About Crush']));
    }
}
```

- [ ] **Step 4: Write `templates/about.php`** (warm, casual copy, every string `$t()`-wrapped + `$e()`-escaped)

```php
<?php $content = function () use ($e, $t) { ob_start(); ?>
  <h1 style="text-wrap:balance;"><?= $e($t('Real life, but make it a date')) ?></h1>
  <p style="opacity:.9;line-height:1.6;"><?= $e($t('Crush is a tiny app for a big feeling: telling someone you like them. No followers, no swiping — just a sweet little invite to spend real time together.')) ?></p>
  <p style="opacity:.9;line-height:1.6;"><?= $e($t('We built it so asking someone out feels easy, not scary. Send it as a secret admirer, or as you. They pick the day, the vibe, the spot. You just show up.')) ?></p>
  <p style="opacity:.9;line-height:1.6;"><?= $e($t('Whether it is a first hello or reconnecting with someone you miss, this is your nudge to put down the phone and actually hang out. Connect like a human again.')) ?></p>
  <p style="margin-top:18px;"><a href="/" style="color:#ff3d8b;font-weight:700;text-decoration:none;"><?= $e($t('Send a crush invite')) ?></a></p>
<?php return ob_get_clean(); };
$body = $content();
include __DIR__ . '/layout.php';
```

- [ ] **Step 5: Wrap `templates/landing/home.php` strings** — wrap the user-visible text in `$t()`: the tagline `Send your crush a date — anonymously, adorably.`, the name/email placeholders (`your name`, `you@email.com`), `Start`, `Pick a password — you'll use it to sign back in.`, and add an About link (`<a href="/about"><?= $e($t('What is Crush?')) ?></a>`) in the `.fine` area. (`$t` is injected into every template by `View`.)

- [ ] **Step 6: Write `migrations/0017_seed_landing_about.sql`** — seed the landing + about strings for the 9 non-English languages. Pattern (one row per lang×key); fill natural, casual translations:

```sql
INSERT INTO ui_translations (lang, `key`, value) VALUES
-- tagline
('vi','Send your crush a date — anonymously, adorably.','Rủ người bạn thầm thương đi hẹn — ẩn danh, dễ thương cực.'),
('es','Send your crush a date — anonymously, adorably.','Invita a tu crush a una cita — en secreto y con mucho estilo.'),
('zh','Send your crush a date — anonymously, adorably.','偷偷约你喜欢的人出来——匿名又可爱。'),
('hi','Send your crush a date — anonymously, adorably.','अपने crush को डेट पर बुलाओ — गुमनाम और प्यारे अंदाज़ में।'),
('pt','Send your crush a date — anonymously, adorably.','Chame seu crush pra um date — anônimo e fofo.'),
('fr','Send your crush a date — anonymously, adorably.','Propose un rendez-vous à ton crush — en secret et tout mignon.'),
('ko','Send your crush a date — anonymously, adorably.','좋아하는 사람에게 데이트 신청 — 익명으로, 귀엽게.'),
('ja','Send your crush a date — anonymously, adorably.','好きな人をデートに誘おう — 匿名で、かわいく。'),
('th','Send your crush a date — anonymously, adorably.','ชวนคนที่แอบชอบไปเดต — แบบลับ ๆ และน่ารัก'),
-- Start button
('vi','Start','Bắt đầu'),('es','Start','Empezar'),('zh','Start','开始'),('hi','Start','शुरू करें'),('pt','Start','Começar'),('fr','Start','Commencer'),('ko','Start','시작'),('ja','Start','はじめる'),('th','Start','เริ่ม'),
-- About link
('vi','What is Crush?','Crush là gì?'),('es','What is Crush?','¿Qué es Crush?'),('zh','What is Crush?','Crush 是什么？'),('hi','What is Crush?','Crush क्या है?'),('pt','What is Crush?','O que é o Crush?'),('fr','What is Crush?','C''est quoi Crush ?'),('ko','What is Crush?','Crush가 뭐야?'),('ja','What is Crush?','Crushって何？'),('th','What is Crush?','Crush คืออะไร?'),
-- About hero
('vi','Real life, but make it a date','Đời thực, nhưng biến nó thành một buổi hẹn'),('es','Real life, but make it a date','La vida real, pero en plan cita'),('zh','Real life, but make it a date','真实生活，但来场约会吧'),('hi','Real life, but make it a date','असली ज़िंदगी, पर एक डेट जैसी'),('pt','Real life, but make it a date','A vida real, só que vira um date'),('fr','Real life, but make it a date','La vraie vie, version rendez-vous'),('ko','Real life, but make it a date','현실인데, 데이트로 만들어봐'),('ja','Real life, but make it a date','リアルな日常を、デートにしよう'),('th','Real life, but make it a date','ชีวิตจริง แต่ทำให้เป็นเดต')
ON DUPLICATE KEY UPDATE value = VALUES(value);
```

> Seed at minimum the four keys above for all 9 languages. Also seed the placeholders (`your name`, `you@email.com`) and `Pick a password — you'll use it to sign back in.` and the three About body paragraphs if time permits; untranslated keys safely fall back to English. Keep apostrophes SQL-escaped (`''`).

- [ ] **Step 7: Register `/about` + wire `AboutController`** — `public/index.php`: `$aboutCtrl = new AboutController($view);` + pass into the routes factory; `config/routes.php`: `AboutController $about` param + `$router->add('GET', '/about', static fn(): Response => $about->show());`.

- [ ] **Step 8: Run the tests, then the full suite (serially)** — Run: `vendor/bin/phpunit --filter "AboutTest|TranslatorTest"` then `vendor/bin/phpunit`
Expected: all green.

- [ ] **Step 9: Commit**

```bash
git add app/About/ templates/about.php templates/landing/home.php migrations/0017_seed_landing_about.sql config/routes.php public/index.php tests/About/AboutTest.php
git commit -m "feat(i18n): About page + translated landing (10 languages)"
```

---

### Task 4: Admin — manage translations at /admin/languages

**Files:**
- Modify: `app/Admin/AdminController.php`, `config/routes.php`, `public/index.php`
- Create: `templates/admin/languages.php`, `templates/admin/language_edit.php`
- Test: `tests/Admin/AdminLanguagesTest.php`

**Interfaces:**
- `AdminController` gains a trailing `Translator $translator` dep (used only for `all(lang)`; the admin always renders English chrome). Methods (admin-gated, CSRF on save):
  - `languages(?int $userId): Response` — lists `Languages::ALL` (each links to its editor).
  - `editLanguage(?int $userId, string $lang): Response` — shows every **already-translated key** for `$lang` plus an "add new" row; renders `admin/language_edit`.
  - `saveLanguage(?int $userId, array $input, string $csrf): Response` — `upsert` each submitted `keys[]`/`values[]` pair into `ui_translations` for `$input['lang']` (skip blank keys); `302 /admin/languages/edit?lang=…`.
- A small `Translator::upsert(string $lang, string $key, string $value): void` (or do the upsert inline in the controller with a prepared `INSERT … ON DUPLICATE KEY UPDATE`). Routes `GET /admin/languages`, `GET /admin/languages/edit`, `POST /admin/languages`.

- [ ] **Step 1: Write the failing test** — `tests/Admin/AdminLanguagesTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Admin;

use App\Admin\AdminController;
use App\Auth\UserRepo;
use App\Core\ArrayStore;
use App\Core\Csrf;
use App\Core\View;
use App\I18n\Translator;
use App\Invite\InviteRepo;
use App\Mail\EmailTemplateRepo;
use App\Security\BlockRepo;
use App\Settings\SettingsRepo;
use App\Share\ShareTargetRepo;
use App\Theme\AbEventRepo;
use App\Theme\ThemeRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class AdminLanguagesTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    private function controller(Csrf $csrf): AdminController
    {
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        return new AdminController(
            $view, $csrf, new UserRepo($this->pdo(), $this->clock), new SettingsRepo($this->pdo()),
            new ThemeRepo($this->pdo()), new AbEventRepo($this->pdo(), $this->clock),
            new InviteRepo($this->pdo(), $this->clock), new BlockRepo($this->pdo(), $this->clock),
            'http://localhost', new EmailTemplateRepo($this->pdo()), new ShareTargetRepo($this->pdo()),
            new Translator($this->pdo(), 'en')
        );
    }

    private function adminId(): int
    {
        $u = (new UserRepo($this->pdo(), $this->clock))->create('a@x.test', 'B', 'magic');
        $this->pdo()->prepare('UPDATE users SET is_admin = 1 WHERE id = ?')->execute([$u['id']]);
        return $u['id'];
    }

    public function test_list_requires_admin(): void
    {
        $this->assertSame(403, $this->controller(new Csrf(new ArrayStore()))->languages(null)->status());
    }

    public function test_list_shows_languages(): void
    {
        $res = $this->controller(new Csrf(new ArrayStore()))->languages($this->adminId());
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('Tiếng Việt', $res->body());
    }

    public function test_save_upserts_translation(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf)->saveLanguage($this->adminId(), [
            'lang' => 'vi', 'keys' => ['Start'], 'values' => ['Bắt đầu'],
        ], $csrf->token());
        $this->assertSame(302, $res->status());
        $this->assertSame('Bắt đầu', (new Translator($this->pdo(), 'vi'))->t('Start'));
    }

    public function test_save_rejects_bad_csrf(): void
    {
        $this->assertSame(400, $this->controller(new Csrf(new ArrayStore()))->saveLanguage($this->adminId(), ['lang' => 'vi'], 'wrong')->status());
    }
}
```

- [ ] **Step 2: Run to verify it fails** — Run: `vendor/bin/phpunit --filter AdminLanguagesTest`
Expected: FAIL — ctor arity / methods undefined.

- [ ] **Step 3: Add `Translator::upsert`**

```php
    public function upsert(string $lang, string $key, string $value): void
    {
        $this->pdo->prepare(
            'INSERT INTO ui_translations (lang, `key`, value) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)'
        )->execute([$lang, $key, $value]);
    }
```

- [ ] **Step 4: Add the three methods to `AdminController`** — `use App\I18n\Languages; use App\I18n\Translator;`, a trailing ctor param `private Translator $translator,`, and:

```php
    public function languages(?int $userId): Response
    {
        if ($this->requireAdmin($userId) === null) { return $this->forbidden(); }
        return $this->render('admin/languages', ['title' => 'Languages', 'languages' => Languages::ALL]);
    }

    public function editLanguage(?int $userId, string $lang): Response
    {
        if ($this->requireAdmin($userId) === null) { return $this->forbidden(); }
        if (!\App\Core\Locale::isSupported($lang)) { $lang = 'en'; }
        return $this->render('admin/language_edit', [
            'title' => 'Edit ' . Languages::name($lang), 'csrf' => $this->csrf->token(),
            'lang' => $lang, 'rows' => $this->translator->all($lang),
        ]);
    }

    public function saveLanguage(?int $userId, array $input, string $csrf): Response
    {
        if ($this->requireAdmin($userId) === null) { return $this->forbidden(); }
        if (!$this->csrf->validate($csrf)) {
            return $this->render('admin/languages', ['title' => 'Languages', 'languages' => Languages::ALL, 'flash' => 'Session expired, please retry.'])->withStatus(400);
        }
        $lang = (string) ($input['lang'] ?? '');
        if (\App\Core\Locale::isSupported($lang)) {
            $keys = (array) ($input['keys'] ?? []);
            $values = (array) ($input['values'] ?? []);
            foreach ($keys as $i => $k) {
                $k = trim((string) $k);
                $v = trim((string) ($values[$i] ?? ''));
                if ($k !== '' && $v !== '') { $this->translator->upsert($lang, $k, $v); }
            }
        }
        return (new Response('', 302))->withHeader('Location', '/admin/languages/edit?lang=' . urlencode($lang));
    }
```

- [ ] **Step 5: Write `templates/admin/languages.php` + `templates/admin/language_edit.php`** — list links to the editor; the editor is a form posting `lang` + parallel `keys[]`/`values[]` inputs (one row per existing key) + a blank "add" row. Mirror the email-templates admin style; `$e()`-escaped; admin-gated by the controller. Add a "Languages" link to `templates/admin/layout.php`'s nav.

```php
<?php /* templates/admin/languages.php */ $languages = $languages ?? []; $flash = $flash ?? null; ?>
<?php $content = function () use ($e, $languages, $flash) { ob_start(); ?>
  <div class="panel"><h1>Languages</h1>
  <?php if ($flash): ?><div class="flash"><?= $e($flash) ?></div><?php endif; ?>
  <ul style="list-style:none;padding:0;">
    <?php foreach ($languages as $code => $name): ?>
      <li style="padding:6px 0;"><a href="/admin/languages/edit?lang=<?= $e($code) ?>"><?= $e($name) ?> (<?= $e($code) ?>)</a></li>
    <?php endforeach; ?>
  </ul></div>
<?php return ob_get_clean(); };
$body = $content();
include __DIR__ . '/layout.php';
```

```php
<?php /* templates/admin/language_edit.php */ $lang = $lang ?? 'en'; $rows = $rows ?? []; $csrf = $csrf ?? ''; ?>
<?php $content = function () use ($e, $lang, $rows, $csrf) { ob_start(); ?>
  <div class="panel"><h1>Edit <?= $e($lang) ?></h1>
  <form method="post" action="/admin/languages">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <input type="hidden" name="lang" value="<?= $e($lang) ?>">
    <table style="width:100%"><tr><th>English</th><th>Translation</th></tr>
    <?php foreach ($rows as $key => $value): ?>
      <tr><td><input type="text" name="keys[]" value="<?= $e($key) ?>" readonly style="width:100%"></td>
          <td><input type="text" name="values[]" value="<?= $e($value) ?>" style="width:100%"></td></tr>
    <?php endforeach; ?>
      <tr><td><input type="text" name="keys[]" placeholder="English phrase" style="width:100%"></td>
          <td><input type="text" name="values[]" placeholder="translation" style="width:100%"></td></tr>
    </table>
    <button type="submit">Save</button>
  </form></div>
<?php return ob_get_clean(); };
$body = $content();
include __DIR__ . '/layout.php';
```

- [ ] **Step 6: Routes + wiring** — `config/routes.php`: `GET /admin/languages` → `languages`, `GET /admin/languages/edit` → `editLanguage($currentUserId(), $_GET['lang'] string-guarded)`, `POST /admin/languages` → `saveLanguage`. `public/index.php`: pass the existing `$translator` as the trailing `AdminController` arg. **Update all existing AdminController test ctors** to pass `new Translator($this->pdo(), 'en')`.

- [ ] **Step 7: Run the tests, then the full suite (serially)** — Run: `vendor/bin/phpunit --filter AdminLanguagesTest` then `vendor/bin/phpunit`
Expected: all green.

- [ ] **Step 8: Commit**

```bash
git add app/Admin/AdminController.php app/I18n/Translator.php config/routes.php public/index.php templates/admin/ tests/Admin/AdminLanguagesTest.php
git commit -m "feat(admin): manage translations at /admin/languages"
```

---

## Self-Review

**1. Spec coverage:** 10 languages incl. Vietnamese (item) — Task 1. Browser-detect + display accordingly + globe switcher top-right (item) — Task 2. About page about connecting/quality-time/confidence (item) — Task 3. Admin-managed translations (item) — Task 4. Casual translations seeded — Task 3. Icons only; escaped; CSRF; admin-gated — throughout.

**2. Placeholder scan:** No "TBD". Gettext-style (English = key) so untranslated strings safely fall back to English; only landing + About are wrapped/seeded in this plan (other screens follow). The switcher cookie is `SameSite=Lax`, 1-year; `/lang` referer is `/`-prefixed-only. Full code throughout.

**3. Type consistency:** `Translator(\PDO,string)`: `lang()/t(string)/all(string)/upsert(string,string,string)`. `Languages::ALL/codes()/name(string)`. `Locale::SUPPORTED` = 10. `View::__construct(string,?Translator)` injects `$t`/`$lang` (existing `new View(dir)` callers in tests still work — translator defaults null → English). `LangController::set(string,?string): Response`. `AboutController::show(): Response`. `AdminController` gains a trailing `Translator` (matched in `public/index.php` + all admin test ctors). Routes: `/lang/{code}`, `/about`, `/admin/languages[/edit]`.
