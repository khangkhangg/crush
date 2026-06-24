# Crush v3 — Plan 4: Admin Email-Template UI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an admin list and edit every email template (subject + HTML body, per language) from `/admin/templates`.

**Architecture:** `EmailTemplateRepo` gains `getExact` (no fallback) + `update`. `AdminController` gains `templates` (list), `editTemplate` (one row's form), and `saveTemplate` (persist), all `is_admin`-gated with CSRF, plus a placeholder hint per key. Two admin templates.

**Tech Stack:** PHP 8.1+, PDO MySQL, PHPUnit 10. No new Composer dependencies.

## Global Constraints

- PHP floor 8.1. PSR-4. No new Composer packages. Prepared statements.
- **Icons only — never emojis** in admin UI. All output `$e()`-escaped; admin POSTs validate CSRF; admin-gated (`requireAdmin`).
- Editing a specific `(key, lang)` must load that exact row (no `en` fallback when editing `vi`/`ko`).
- Integration tests use MySQL `crush_test`.

## File Structure

- `app/Mail/EmailTemplateRepo.php` (modify) — `getExact`, `update`.
- `app/Admin/AdminController.php` (modify) — `templates`, `editTemplate`, `saveTemplate` + `EmailTemplateRepo` dep + placeholder hints.
- `templates/admin/templates.php` (list), `templates/admin/template_edit.php` (edit form).
- `config/routes.php`, `public/index.php` (modify) — wire routes + inject the repo.

---

### Task 1: EmailTemplateRepo getExact/update + AdminController template methods

**Files:**
- Modify: `app/Mail/EmailTemplateRepo.php`
- Modify: `app/Admin/AdminController.php`
- Test: `tests/Mail/EmailTemplateUpdateTest.php`
- Test: `tests/Admin/AdminTemplatesTest.php`

**Interfaces:**
- Produces:
  - `EmailTemplateRepo::getExact(string $key, string $lang): ?array` — exact `(key,lang)`, **no fallback**.
  - `EmailTemplateRepo::update(string $key, string $lang, string $subject, string $bodyHtml): void` — updates the existing row (upsert via `INSERT ... ON DUPLICATE KEY UPDATE`).
  - `AdminController` gains a trailing `EmailTemplateRepo $emailTemplates` constructor param, a `const TEMPLATE_PLACEHOLDERS` map (key → hint string), and:
    - `templates(?int $userId): Response` — admin-gated; renders `admin/templates` with `EmailTemplateRepo::all()`.
    - `editTemplate(?int $userId, string $key, string $lang): Response` — admin-gated; loads the exact row (404-ish "unknown template" render if missing) and renders `admin/template_edit` with the row, the placeholder hint, and a CSRF token.
    - `saveTemplate(?int $userId, array $input, string $csrf): Response` — admin-gated; 400 on bad CSRF; `update(key, lang, subject, body_html)`; `302 /admin/templates`.

- [ ] **Step 1: Write the failing tests** — `tests/Mail/EmailTemplateUpdateTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Mail;

use App\Mail\EmailTemplateRepo;
use Tests\Support\DatabaseTestCase;

final class EmailTemplateUpdateTest extends DatabaseTestCase
{
    public function test_get_exact_has_no_fallback(): void
    {
        $repo = new EmailTemplateRepo($this->pdo());
        $this->assertNotNull($repo->getExact('welcome', 'vi'));
        $this->assertNull($repo->getExact('welcome', 'fr')); // no fallback, unlike get()
    }

    public function test_update_changes_subject_and_body(): void
    {
        $repo = new EmailTemplateRepo($this->pdo());
        $repo->update('welcome', 'en', 'New subject {{name}}', '<p>New body {{link}}</p>');
        $row = $repo->getExact('welcome', 'en');
        $this->assertSame('New subject {{name}}', $row['subject']);
        $this->assertSame('<p>New body {{link}}</p>', $row['body_html']);
    }
}
```

And `tests/Admin/AdminTemplatesTest.php`:

```php
<?php
declare(strict_types=1);

namespace Tests\Admin;

use App\Admin\AdminController;
use App\Auth\UserRepo;
use App\Core\ArrayStore;
use App\Core\Csrf;
use App\Core\View;
use App\Invite\InviteRepo;
use App\Mail\EmailTemplateRepo;
use App\Security\BlockRepo;
use App\Settings\SettingsRepo;
use App\Theme\AbEventRepo;
use App\Theme\ThemeRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class AdminTemplatesTest extends DatabaseTestCase
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
            'http://localhost', new EmailTemplateRepo($this->pdo())
        );
    }

    private function adminId(): int
    {
        $u = (new UserRepo($this->pdo(), $this->clock))->create('admin@x.test', 'Boss', 'magic');
        $this->pdo()->prepare('UPDATE users SET is_admin = 1 WHERE id = ?')->execute([$u['id']]);
        return $u['id'];
    }

    public function test_templates_list_requires_admin(): void
    {
        $this->assertSame(403, $this->controller(new Csrf(new ArrayStore()))->templates(null)->status());
    }

    public function test_templates_list_renders(): void
    {
        $res = $this->controller(new Csrf(new ArrayStore()))->templates($this->adminId());
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('welcome', $res->body());
    }

    public function test_edit_renders_exact_row(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf)->editTemplate($this->adminId(), 'welcome', 'vi');
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString($csrf->token(), $res->body());
        $this->assertStringContainsString('name="body_html"', $res->body());
    }

    public function test_save_updates_and_redirects(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $ctrl = $this->controller($csrf);
        $adminId = $this->adminId();
        $res = $ctrl->saveTemplate($adminId, [
            'key' => 'welcome', 'lang' => 'en', 'subject' => 'Hi {{name}}', 'body_html' => '<p>{{link}}</p>',
        ], $csrf->token());
        $this->assertSame(302, $res->status());
        $this->assertSame('Hi {{name}}', (new EmailTemplateRepo($this->pdo()))->getExact('welcome', 'en')['subject']);
    }

    public function test_save_rejects_bad_csrf(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf)->saveTemplate($this->adminId(), ['key' => 'welcome', 'lang' => 'en'], 'wrong');
        $this->assertSame(400, $res->status());
    }
}
```

- [ ] **Step 2: Run to verify they fail** — Run: `vendor/bin/phpunit --filter "EmailTemplateUpdateTest|AdminTemplatesTest"`
Expected: FAIL — undefined `getExact`/`update`; `AdminController::__construct` arity.

- [ ] **Step 3: Add `getExact` + `update` to `app/Mail/EmailTemplateRepo.php`**

```php
    public function getExact(string $key, string $lang): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM email_templates WHERE `key` = ? AND lang = ?');
        $stmt->execute([$key, $lang]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function update(string $key, string $lang, string $subject, string $bodyHtml): void
    {
        $this->pdo->prepare(
            'INSERT INTO email_templates (`key`, lang, subject, body_html) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE subject = VALUES(subject), body_html = VALUES(body_html)'
        )->execute([$key, $lang, $subject, $bodyHtml]);
    }
```

- [ ] **Step 4: Add the template methods to `app/Admin/AdminController.php`** — add `use App\Mail\EmailTemplateRepo;`, a trailing constructor param `private EmailTemplateRepo $emailTemplates,`, a placeholder hint map, and the three methods:

```php
    private const TEMPLATE_PLACEHOLDERS = [
        'welcome' => '{{name}} {{link}}',
        'invite'  => '{{senderLabel}} {{message}} {{link}} {{unsubscribe}}',
        'result'  => '{{crushName}} {{when}} {{meal}} {{place}} {{mapHref}}',
        'magic'   => '{{link}}',
    ];

    public function templates(?int $userId): Response
    {
        if ($this->requireAdmin($userId) === null) {
            return $this->forbidden();
        }
        return $this->render('admin/templates', [
            'title' => 'Email templates', 'templates' => $this->emailTemplates->all(),
        ]);
    }

    public function editTemplate(?int $userId, string $key, string $lang): Response
    {
        if ($this->requireAdmin($userId) === null) {
            return $this->forbidden();
        }
        $row = $this->emailTemplates->getExact($key, $lang);
        if ($row === null) {
            return $this->render('admin/templates', [
                'title' => 'Email templates', 'templates' => $this->emailTemplates->all(),
                'flash' => 'Unknown template.',
            ]);
        }
        return $this->render('admin/template_edit', [
            'title'       => 'Edit template',
            'csrf'        => $this->csrf->token(),
            'tpl'         => $row,
            'placeholders'=> self::TEMPLATE_PLACEHOLDERS[$key] ?? '',
        ]);
    }

    public function saveTemplate(?int $userId, array $input, string $csrf): Response
    {
        if ($this->requireAdmin($userId) === null) {
            return $this->forbidden();
        }
        if (!$this->csrf->validate($csrf)) {
            return $this->render('admin/templates', [
                'title' => 'Email templates', 'templates' => $this->emailTemplates->all(),
                'flash' => 'Session expired, please retry.',
            ])->withStatus(400);
        }
        $key = (string) ($input['key'] ?? '');
        $lang = (string) ($input['lang'] ?? '');
        $subject = (string) ($input['subject'] ?? '');
        $body = (string) ($input['body_html'] ?? '');
        if ($key !== '' && $lang !== '') {
            $this->emailTemplates->update($key, $lang, $subject, $body);
        }
        return (new Response('', 302))->withHeader('Location', '/admin/templates');
    }
```

> `requireAdmin`, `forbidden`, and `render` already exist on `AdminController` (from v1). Confirm `render` is the private helper that wraps `View::render` in a `Response` (it is).

- [ ] **Step 5: Run the tests** — Run: `vendor/bin/phpunit --filter "EmailTemplateUpdateTest|AdminTemplatesTest"`
Expected: FAIL still — the `admin/templates` + `admin/template_edit` views don't exist yet. Create minimal placeholders so these logic tests pass (Task 2 styles them):

`templates/admin/templates.php` (minimal):
```php
<?php $templates = $templates ?? []; $flash = $flash ?? null; ?>
<?php $content = function () use ($e, $templates, $flash) { ob_start(); ?>
  <div class="panel"><h1>Email templates</h1>
  <?php if ($flash): ?><div class="flash"><?= $e($flash) ?></div><?php endif; ?>
  <table><tr><th>Key</th><th>Lang</th><th></th></tr>
  <?php foreach ($templates as $t): ?>
    <tr><td><?= $e($t['key']) ?></td><td><?= $e($t['lang']) ?></td>
      <td><a href="/admin/templates/edit?key=<?= $e($t['key']) ?>&lang=<?= $e($t['lang']) ?>">Edit</a></td></tr>
  <?php endforeach; ?>
  </table></div>
<?php return ob_get_clean(); };
$body = $content();
include __DIR__ . '/layout.php';
```

`templates/admin/template_edit.php` (minimal):
```php
<?php $tpl = $tpl ?? []; $placeholders = $placeholders ?? ''; ?>
<?php $content = function () use ($e, $tpl, $placeholders, $csrf) { ob_start(); ?>
  <div class="panel"><h1>Edit <?= $e($tpl['key']) ?> / <?= $e($tpl['lang']) ?></h1>
  <p style="font-size:12px;opacity:.7">Placeholders: <?= $e($placeholders) ?></p>
  <form method="post" action="/admin/templates">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <input type="hidden" name="key" value="<?= $e($tpl['key']) ?>">
    <input type="hidden" name="lang" value="<?= $e($tpl['lang']) ?>">
    <label>Subject <input type="text" name="subject" value="<?= $e($tpl['subject']) ?>"></label>
    <label>Body (HTML) <textarea name="body_html" rows="12" style="width:100%;font-family:monospace"><?= $e($tpl['body_html']) ?></textarea></label>
    <button type="submit">Save template</button>
  </form></div>
<?php return ob_get_clean(); };
$body = $content();
include __DIR__ . '/layout.php';
```

- [ ] **Step 6: Run the tests again** — Run: `vendor/bin/phpunit --filter "EmailTemplateUpdateTest|AdminTemplatesTest"`
Expected: PASS.

- [ ] **Step 7: Update existing AdminController test constructions** — `tests/Admin/AdminAuthTest.php`, `AdminSettingsTest.php`, `AdminThemesTest.php`, `AdminModerationTest.php` build `AdminController`; add the trailing `new EmailTemplateRepo($this->pdo())` arg (import it). Run `vendor/bin/phpunit` until green.

- [ ] **Step 8: Commit**

```bash
git add app/Mail/EmailTemplateRepo.php app/Admin/AdminController.php templates/admin/templates.php templates/admin/template_edit.php tests/
git commit -m "feat(admin): email-template list/edit/save (repo getExact+update)"
```

---

### Task 2: Polish the admin template views + routes + wiring

**Files:**
- Modify: `templates/admin/templates.php`, `templates/admin/template_edit.php` (group by key, nav link)
- Modify: `templates/admin/layout.php` (add a "Templates" nav link)
- Modify: `config/routes.php`, `public/index.php`
- Test: `tests/Admin/AdminTemplatesRoutingTest.php`

**Interfaces:**
- Produces routes `GET /admin/templates`, `GET /admin/templates/edit`, `POST /admin/templates`, wired to `AdminController`.

- [ ] **Step 1: Write the routing test** — `tests/Admin/AdminTemplatesRoutingTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Admin;

use App\Core\Router;
use PHPUnit\Framework\TestCase;

final class AdminTemplatesRoutingTest extends TestCase
{
    public function test_template_routes_shapes(): void
    {
        $r = new Router();
        $r->add('GET', '/admin/templates', static fn() => 'list');
        $r->add('GET', '/admin/templates/edit', static fn() => 'edit');
        $r->add('POST', '/admin/templates', static fn() => 'save');
        $this->assertNotNull($r->match('GET', '/admin/templates'));
        $this->assertNotNull($r->match('GET', '/admin/templates/edit'));
        $this->assertNotNull($r->match('POST', '/admin/templates'));
    }
}
```

- [ ] **Step 2: Run it** — Run: `vendor/bin/phpunit --filter AdminTemplatesRoutingTest`
Expected: PASS.

- [ ] **Step 3: Improve `templates/admin/templates.php`** — group rows by key with the lang as a sub-list (still a simple table is fine); keep it `$e()`-escaped, no emojis. (If the minimal version from Task 1 is already acceptable, just ensure each row links to `/admin/templates/edit?key=…&lang=…`.)

- [ ] **Step 4: Add a "Templates" nav link to `templates/admin/layout.php`** — in the `<nav>`, add: `<a href="/admin/templates">Templates</a>` after the existing links.

- [ ] **Step 5: Register routes** — in `config/routes.php` (the factory already receives `AdminController $admin`):

```php
    $router->add('GET',  '/admin/templates',      static fn(): Response => $admin->templates($currentUserId()));
    $router->add('GET',  '/admin/templates/edit', static fn(): Response => $admin->editTemplate(
        $currentUserId(),
        (static fn($v) => is_string($v) ? $v : '')($_GET['key'] ?? ''),
        (static fn($v) => is_string($v) ? $v : '')($_GET['lang'] ?? '')
    ));
    $router->add('POST', '/admin/templates',      static fn(): Response => $admin->saveTemplate(
        $currentUserId(), $_POST, (static fn($v) => is_string($v) ? $v : '')($_POST['csrf'] ?? '')
    ));
```

- [ ] **Step 6: Wire in `public/index.php`** — change the `AdminController` construction to pass the existing `$emailTemplates` (built in Plan v3-3) as the trailing argument: `new AdminController($view, $csrf, $users, $settings, $themeRepo, $abEvents, $inviteRepo, $blockRepo, $appUrl, $emailTemplates)`.

- [ ] **Step 7: Run the full suite** — Run: `vendor/bin/phpunit`
Expected: all green.

- [ ] **Step 8: Manual check on port 8888** — confirm the templates list is admin-gated:

```bash
DB_DSN="mysql:host=127.0.0.1;dbname=crush_dev;charset=utf8mb4" DB_USER=root DB_PASS= APP_URL="http://127.0.0.1:8888" \
  php -S 127.0.0.1:8888 -t public >/dev/null 2>&1 &
sleep 1
curl -s -o /dev/null -w '/admin/templates (logged out): %{http_code}\n' 127.0.0.1:8888/admin/templates
kill %1
```
Expected: `403`.

- [ ] **Step 9: Commit**

```bash
git add templates/admin/ config/routes.php public/index.php tests/Admin/AdminTemplatesRoutingTest.php
git commit -m "feat(admin): email-template routes + nav + wiring"
```

---

## Self-Review

**1. Spec coverage:** Admin `/admin/templates` list + per-row edit of subject + body per language (spec §6) — Tasks 1,2. `getExact` (no fallback for editing) + `update` (§6) — Task 1. Placeholder hint per key (§6) — Task 1. Admin-gated + CSRF + escaped + no emojis — Tasks 1,2.

**2. Placeholder scan:** No "TBD". Minimal views in Task 1 are functional; Task 2 adds the nav link + grouping. Full code throughout.

**3. Type consistency:** `EmailTemplateRepo::getExact(string,string): ?array`, `update(string,string,string,string): void`; `AdminController::templates(?int): Response`, `editTemplate(?int,string,string): Response`, `saveTemplate(?int,array,string): Response`, consuming the existing `requireAdmin`/`forbidden`/`render` helpers. `AdminController` gains a trailing `EmailTemplateRepo`, matched in `public/index.php` (passing the `$emailTemplates` built in v3-3) and all four existing Admin test constructions.
