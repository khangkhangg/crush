# Crush — Plan 1: Foundation & Core Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up the project skeleton and core building blocks (config, routing, request/response, CSRF, view rendering, DB + migrations) with a passing PHPUnit suite and a live health-check page.

**Architecture:** Lean vanilla PHP. A single front controller (`public/index.php`) builds a `Router`, matches the request, and invokes a handler that returns a `Response`. Core utilities live under `app/Core` as small, single-responsibility classes, each unit-tested. Database access is a thin PDO factory plus a file-based migration runner.

**Tech Stack:** PHP 8.1+, Composer (PSR-4 autoload), PHPUnit 10, PDO (MySQL in prod, SQLite in-memory for DB unit tests).

## Global Constraints

- PHP version floor: **8.1** (declared in `composer.json` `require.php`).
- Minimal dependencies: only add a Composer package when it clearly pays off. This plan adds **only** `phpunit/phpunit` (dev).
- **Icons only — never emojis** in any UI/template/email output.
- All HTML output escaped via the `e()` helper / `View`. All POSTs will carry CSRF (enforced in later plans; mechanism built here).
- PSR-4: `App\` → `app/`, `Tests\` → `tests/`.
- Secrets come from environment, never committed.

---

### Task 1: Composer project + PHPUnit harness

**Files:**
- Create: `composer.json`
- Create: `phpunit.xml`
- Create: `tests/SmokeTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: working `vendor/autoload.php`, `App\` + `Tests\` PSR-4 autoloading, runnable `vendor/bin/phpunit`.

- [ ] **Step 1: Write `composer.json`**

```json
{
  "name": "crush/app",
  "description": "Send a crush a date invite.",
  "type": "project",
  "require": {
    "php": ">=8.1"
  },
  "require-dev": {
    "phpunit/phpunit": "^10"
  },
  "autoload": {
    "psr-4": { "App\\": "app/" }
  },
  "autoload-dev": {
    "psr-4": { "Tests\\": "tests/" }
  },
  "config": { "sort-packages": true },
  "minimum-stability": "stable"
}
```

- [ ] **Step 2: Write `phpunit.xml`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         failOnWarning="true">
  <testsuites>
    <testsuite name="all">
      <directory>tests</directory>
    </testsuite>
  </testsuites>
</phpunit>
```

- [ ] **Step 3: Write the failing smoke test** — `tests/SmokeTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase
{
    public function test_autoloader_and_phpunit_work(): void
    {
        $this->assertTrue(true);
    }
}
```

- [ ] **Step 4: Install deps and run** — Run: `composer install && vendor/bin/phpunit`
Expected: PHPUnit runs, `1 passed`. (If `composer` is unavailable, install Composer first; if PHP < 8.1, upgrade.)

- [ ] **Step 5: Commit**

```bash
git add composer.json composer.lock phpunit.xml tests/SmokeTest.php
git commit -m "chore: composer + phpunit harness"
```

---

### Task 2: Core\Config

**Files:**
- Create: `app/Core/Config.php`
- Test: `tests/Core/ConfigTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `App\Core\Config` with `__construct(array $items)`, `get(string $key, mixed $default = null): mixed`, static `fromEnv(array $env): self`.

- [ ] **Step 1: Write the failing test** — `tests/Core/ConfigTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Core;

use App\Core\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function test_get_returns_value_or_default(): void
    {
        $c = new Config(['app_env' => 'testing']);
        $this->assertSame('testing', $c->get('app_env'));
        $this->assertSame('fallback', $c->get('missing', 'fallback'));
        $this->assertNull($c->get('missing'));
    }

    public function test_from_env_maps_known_keys(): void
    {
        $c = Config::fromEnv(['APP_ENV' => 'prod', 'APP_URL' => 'https://x.test']);
        $this->assertSame('prod', $c->get('app_env'));
        $this->assertSame('https://x.test', $c->get('app_url'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails** — Run: `vendor/bin/phpunit --filter ConfigTest`
Expected: FAIL — `Class "App\Core\Config" not found`.

- [ ] **Step 3: Write minimal implementation** — `app/Core/Config.php`

```php
<?php
declare(strict_types=1);

namespace App\Core;

final class Config
{
    public function __construct(private array $items) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->items[$key] ?? $default;
    }

    public static function fromEnv(array $env): self
    {
        return new self([
            'app_env' => $env['APP_ENV'] ?? 'production',
            'app_url' => $env['APP_URL'] ?? 'http://localhost',
            'db_dsn'  => $env['DB_DSN']  ?? '',
            'db_user' => $env['DB_USER'] ?? '',
            'db_pass' => $env['DB_PASS'] ?? '',
        ]);
    }
}
```

- [ ] **Step 4: Run test to verify it passes** — Run: `vendor/bin/phpunit --filter ConfigTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Core/Config.php tests/Core/ConfigTest.php
git commit -m "feat(core): Config with env mapping"
```

---

### Task 3: Core\Router

**Files:**
- Create: `app/Core/Router.php`
- Test: `tests/Core/RouterTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `App\Core\Router` with `add(string $method, string $pattern, callable $handler): void` and `match(string $method, string $path): ?array` returning `['handler' => callable, 'params' => array<string,string>]` or `null`. Patterns support `{name}` segments.

- [ ] **Step 1: Write the failing test** — `tests/Core/RouterTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Core;

use App\Core\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function test_matches_static_route(): void
    {
        $r = new Router();
        $r->add('GET', '/health', fn() => 'ok');
        $m = $r->match('GET', '/health');
        $this->assertNotNull($m);
        $this->assertSame('ok', ($m['handler'])());
        $this->assertSame([], $m['params']);
    }

    public function test_extracts_named_params(): void
    {
        $r = new Router();
        $r->add('GET', '/i/{token}', fn() => null);
        $m = $r->match('GET', '/i/abc123');
        $this->assertSame(['token' => 'abc123'], $m['params']);
    }

    public function test_returns_null_on_no_match_or_wrong_method(): void
    {
        $r = new Router();
        $r->add('GET', '/health', fn() => 'ok');
        $this->assertNull($r->match('GET', '/nope'));
        $this->assertNull($r->match('POST', '/health'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails** — Run: `vendor/bin/phpunit --filter RouterTest`
Expected: FAIL — `Class "App\Core\Router" not found`.

- [ ] **Step 3: Write minimal implementation** — `app/Core/Router.php`

```php
<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<int,array{method:string,regex:string,names:string[],handler:callable}> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $names = [];
        $regex = preg_replace_callback('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', function ($m) use (&$names) {
            $names[] = $m[1];
            return '([^/]+)';
        }, $pattern);

        $this->routes[] = [
            'method'  => strtoupper($method),
            'regex'   => '#^' . $regex . '$#',
            'names'   => $names,
            'handler' => $handler,
        ];
    }

    /** @return array{handler:callable,params:array<string,string>}|null */
    public function match(string $method, string $path): ?array
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($method)) {
                continue;
            }
            if (preg_match($route['regex'], $path, $matches)) {
                array_shift($matches);
                return [
                    'handler' => $route['handler'],
                    'params'  => array_combine($route['names'], $matches) ?: [],
                ];
            }
        }
        return null;
    }
}
```

- [ ] **Step 4: Run test to verify it passes** — Run: `vendor/bin/phpunit --filter RouterTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Core/Router.php tests/Core/RouterTest.php
git commit -m "feat(core): Router with named params"
```

---

### Task 4: Core\Response

**Files:**
- Create: `app/Core/Response.php`
- Test: `tests/Core/ResponseTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `App\Core\Response` with `__construct(string $body = '', int $status = 200, array $headers = [])`, `withStatus(int): self`, `withHeader(string,string): self`, getters `status(): int`, `body(): string`, `headers(): array`, and static `html(string $body, int $status = 200): self`.

- [ ] **Step 1: Write the failing test** — `tests/Core/ResponseTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Core;

use App\Core\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function test_html_helper_sets_body_and_content_type(): void
    {
        $r = Response::html('<h1>hi</h1>', 201);
        $this->assertSame(201, $r->status());
        $this->assertSame('<h1>hi</h1>', $r->body());
        $this->assertSame('text/html; charset=utf-8', $r->headers()['Content-Type']);
    }

    public function test_with_methods_are_immutable_copies(): void
    {
        $a = new Response('x');
        $b = $a->withStatus(404)->withHeader('X-Test', '1');
        $this->assertSame(200, $a->status());
        $this->assertSame(404, $b->status());
        $this->assertSame('1', $b->headers()['X-Test']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails** — Run: `vendor/bin/phpunit --filter ResponseTest`
Expected: FAIL — `Class "App\Core\Response" not found`.

- [ ] **Step 3: Write minimal implementation** — `app/Core/Response.php`

```php
<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    public function __construct(
        private string $body = '',
        private int $status = 200,
        private array $headers = [],
    ) {}

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public function withStatus(int $status): self
    {
        return new self($this->body, $status, $this->headers);
    }

    public function withHeader(string $name, string $value): self
    {
        $headers = $this->headers;
        $headers[$name] = $value;
        return new self($this->body, $this->status, $headers);
    }

    public function status(): int { return $this->status; }
    public function body(): string { return $this->body; }
    public function headers(): array { return $this->headers; }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }
}
```

- [ ] **Step 4: Run test to verify it passes** — Run: `vendor/bin/phpunit --filter ResponseTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Core/Response.php tests/Core/ResponseTest.php
git commit -m "feat(core): immutable Response"
```

---

### Task 5: Core\Store + Core\Csrf

**Files:**
- Create: `app/Core/Store.php`
- Create: `app/Core/ArrayStore.php`
- Create: `app/Core/Csrf.php`
- Test: `tests/Core/CsrfTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `App\Core\Store` interface: `get(string $key, mixed $default = null): mixed`, `set(string $key, mixed $value): void`.
  - `App\Core\ArrayStore` implementing `Store` (in-memory; used in tests and wrappable around `$_SESSION` later).
  - `App\Core\Csrf` with `__construct(Store $store)`, `token(): string` (generates + persists once per session), `validate(?string $candidate): bool` (constant-time compare).

- [ ] **Step 1: Write the failing test** — `tests/Core/CsrfTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Core;

use App\Core\ArrayStore;
use App\Core\Csrf;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    public function test_token_is_stable_within_session(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $t1 = $csrf->token();
        $t2 = $csrf->token();
        $this->assertSame($t1, $t2);
        $this->assertGreaterThanOrEqual(32, strlen($t1));
    }

    public function test_validate_accepts_correct_and_rejects_wrong(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $token = $csrf->token();
        $this->assertTrue($csrf->validate($token));
        $this->assertFalse($csrf->validate('wrong'));
        $this->assertFalse($csrf->validate(null));
    }
}
```

- [ ] **Step 2: Run test to verify it fails** — Run: `vendor/bin/phpunit --filter CsrfTest`
Expected: FAIL — `Class "App\Core\ArrayStore" not found`.

- [ ] **Step 3: Write minimal implementations**

`app/Core/Store.php`
```php
<?php
declare(strict_types=1);

namespace App\Core;

interface Store
{
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value): void;
}
```

`app/Core/ArrayStore.php`
```php
<?php
declare(strict_types=1);

namespace App\Core;

final class ArrayStore implements Store
{
    public function __construct(private array $data = []) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }
}
```

`app/Core/Csrf.php`
```php
<?php
declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    private const KEY = '_csrf';

    public function __construct(private Store $store) {}

    public function token(): string
    {
        $token = $this->store->get(self::KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->store->set(self::KEY, $token);
        }
        return $token;
    }

    public function validate(?string $candidate): bool
    {
        $token = $this->store->get(self::KEY);
        if (!is_string($token) || !is_string($candidate)) {
            return false;
        }
        return hash_equals($token, $candidate);
    }
}
```

- [ ] **Step 4: Run test to verify it passes** — Run: `vendor/bin/phpunit --filter CsrfTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Core/Store.php app/Core/ArrayStore.php app/Core/Csrf.php tests/Core/CsrfTest.php
git commit -m "feat(core): Store interface + CSRF tokens"
```

---

### Task 6: Core\View (template render + escaping)

**Files:**
- Create: `app/Core/View.php`
- Create: `templates/_test_fixture.php` (used only by the test)
- Test: `tests/Core/ViewTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - Global helper `App\Core\e(mixed $value): string` (htmlspecialchars wrapper) — defined in `app/Core/View.php`.
  - `App\Core\View` with `__construct(string $templateDir)` and `render(string $name, array $data = []): string`. Templates are PHP files; `$data` keys become local variables; `e()` is available for escaping.

- [ ] **Step 1: Write the fixture template** — `templates/_test_fixture.php`

```php
<h1>Hello, <?= e($name) ?></h1>
```

- [ ] **Step 2: Write the failing test** — `tests/Core/ViewTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Core;

use App\Core\View;
use PHPUnit\Framework\TestCase;

final class ViewTest extends TestCase
{
    public function test_renders_template_with_escaped_data(): void
    {
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $html = $view->render('_test_fixture', ['name' => '<script>x</script>']);
        $this->assertStringContainsString('&lt;script&gt;x&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>x</script>', $html);
    }

    public function test_missing_template_throws(): void
    {
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $this->expectException(\RuntimeException::class);
        $view->render('does_not_exist');
    }
}
```

- [ ] **Step 3: Run test to verify it fails** — Run: `vendor/bin/phpunit --filter ViewTest`
Expected: FAIL — `Class "App\Core\View" not found`.

- [ ] **Step 4: Write minimal implementation** — `app/Core/View.php`

```php
<?php
declare(strict_types=1);

namespace App\Core;

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

final class View
{
    public function __construct(private string $templateDir) {}

    public function render(string $name, array $data = []): string
    {
        $path = $this->templateDir . '/' . $name . '.php';
        if (!is_file($path)) {
            throw new \RuntimeException("Template not found: {$name}");
        }
        $render = static function (string $__path, array $__data): string {
            // Local alias so templates can call e() unqualified.
            $e = static fn(mixed $v): string => \App\Core\e($v);
            extract($__data, EXTR_SKIP);
            ob_start();
            include $__path;
            return (string) ob_get_clean();
        };
        return $render($path, $data);
    }
}
```

> Note: the fixture calls `e($name)` — make the fixture use the local `$e` alias if you keep templates namespace-agnostic. Update `templates/_test_fixture.php` to `<h1>Hello, <?= $e($name) ?></h1>` so it resolves the closure alias.

- [ ] **Step 5: Adjust the fixture to use the `$e` alias** — `templates/_test_fixture.php`

```php
<h1>Hello, <?= $e($name) ?></h1>
```

- [ ] **Step 6: Run test to verify it passes** — Run: `vendor/bin/phpunit --filter ViewTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Core/View.php templates/_test_fixture.php tests/Core/ViewTest.php
git commit -m "feat(core): View renderer with escaping helper"
```

---

### Task 7: Core\DB + MigrationRunner + front controller health route

**Files:**
- Create: `app/Core/DB.php`
- Create: `app/Core/MigrationRunner.php`
- Create: `migrations/0001_init.sql`
- Create: `public/index.php`
- Create: `config/routes.php`
- Test: `tests/Core/MigrationRunnerTest.php`

**Interfaces:**
- Consumes: `App\Core\Config` (Task 2), `App\Core\Router` (Task 3), `App\Core\Response` (Task 4).
- Produces:
  - `App\Core\DB` with static `connect(Config $config): \PDO` (sets `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, native prepares off) and static `sqliteMemory(): \PDO` for tests.
  - `App\Core\MigrationRunner` with `__construct(\PDO $pdo, string $migrationsDir)` and `run(): array` returning the list of newly-applied filenames; idempotent via a `schema_migrations` table.
  - `public/index.php` front controller wiring routes from `config/routes.php`; a `GET /health` route returns `Response::html('ok')`.

- [ ] **Step 1: Write the failing test** — `tests/Core/MigrationRunnerTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Core;

use App\Core\DB;
use App\Core\MigrationRunner;
use PHPUnit\Framework\TestCase;

final class MigrationRunnerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/crush_mig_' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        file_put_contents($this->dir . '/0001_init.sql',
            "CREATE TABLE widgets (id INTEGER PRIMARY KEY, name TEXT NOT NULL);");
        file_put_contents($this->dir . '/0002_more.sql',
            "INSERT INTO widgets (name) VALUES ('a');");
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*'));
        rmdir($this->dir);
    }

    public function test_applies_all_migrations_then_is_idempotent(): void
    {
        $pdo = DB::sqliteMemory();
        $runner = new MigrationRunner($pdo, $this->dir);

        $applied = $runner->run();
        $this->assertSame(['0001_init.sql', '0002_more.sql'], $applied);

        $count = (int) $pdo->query('SELECT COUNT(*) AS c FROM widgets')->fetch()['c'];
        $this->assertSame(1, $count);

        // Second run applies nothing.
        $this->assertSame([], $runner->run());
    }
}
```

- [ ] **Step 2: Run test to verify it fails** — Run: `vendor/bin/phpunit --filter MigrationRunnerTest`
Expected: FAIL — `Class "App\Core\DB" not found`.

- [ ] **Step 3: Write `app/Core/DB.php`**

```php
<?php
declare(strict_types=1);

namespace App\Core;

final class DB
{
    public static function connect(Config $config): \PDO
    {
        $pdo = new \PDO(
            (string) $config->get('db_dsn'),
            (string) $config->get('db_user'),
            (string) $config->get('db_pass'),
        );
        self::tune($pdo);
        return $pdo;
    }

    public static function sqliteMemory(): \PDO
    {
        $pdo = new \PDO('sqlite::memory:');
        self::tune($pdo);
        return $pdo;
    }

    private static function tune(\PDO $pdo): void
    {
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
    }
}
```

- [ ] **Step 4: Write `app/Core/MigrationRunner.php`**

```php
<?php
declare(strict_types=1);

namespace App\Core;

final class MigrationRunner
{
    public function __construct(private \PDO $pdo, private string $migrationsDir) {}

    /** @return string[] filenames applied this run */
    public function run(): array
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (filename TEXT PRIMARY KEY, applied_at TEXT NOT NULL)'
        );

        $done = [];
        foreach ($this->pdo->query('SELECT filename FROM schema_migrations') as $row) {
            $done[$row['filename']] = true;
        }

        $files = glob($this->migrationsDir . '/*.sql') ?: [];
        sort($files);

        $applied = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (isset($done[$name])) {
                continue;
            }
            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new \RuntimeException("Cannot read migration: {$name}");
            }
            $this->pdo->exec($sql);
            $stmt = $this->pdo->prepare(
                'INSERT INTO schema_migrations (filename, applied_at) VALUES (?, ?)'
            );
            $stmt->execute([$name, gmdate('c')]);
            $applied[] = $name;
        }
        return $applied;
    }
}
```

- [ ] **Step 5: Run test to verify it passes** — Run: `vendor/bin/phpunit --filter MigrationRunnerTest`
Expected: PASS.

- [ ] **Step 6: Write the first real migration** — `migrations/0001_init.sql`

```sql
CREATE TABLE IF NOT EXISTS settings (
  `key`   VARCHAR(191) NOT NULL PRIMARY KEY,
  `value` TEXT NOT NULL
);
```

> This is intentionally tiny — Plan 2+ adds users, invites, etc. It exists so `MigrationRunner` has a real file to apply against MySQL in dev.

- [ ] **Step 7: Write the routes file** — `config/routes.php`

```php
<?php
declare(strict_types=1);

use App\Core\Response;
use App\Core\Router;

return static function (Router $router): void {
    $router->add('GET', '/health', static fn(): Response => Response::html('ok'));
};
```

- [ ] **Step 8: Write the front controller** — `public/index.php`

```php
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Response;
use App\Core\Router;

$router = new Router();
(require dirname(__DIR__) . '/config/routes.php')($router);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

$match = $router->match($method, $path);
$response = $match
    ? ($match['handler'])(...array_values($match['params']))
    : Response::html('<h1>Not found</h1>', 404);

if (!$response instanceof Response) {
    $response = Response::html((string) $response);
}
$response->send();
```

- [ ] **Step 9: Manually verify the app boots** — Run: `php -S 127.0.0.1:8080 -t public` then in another shell `curl -s 127.0.0.1:8080/health`
Expected: prints `ok`. Also `curl -s -o /dev/null -w '%{http_code}' 127.0.0.1:8080/nope` → `404`.

- [ ] **Step 10: Commit**

```bash
git add app/Core/DB.php app/Core/MigrationRunner.php migrations/0001_init.sql \
        public/index.php config/routes.php tests/Core/MigrationRunnerTest.php
git commit -m "feat(core): DB + migration runner + front controller health route"
```

---

## Self-Review

**1. Spec coverage (Plan 1 portion):** The foundation maps to spec §3 (architecture skeleton: `app/Core`, `public`, `templates`, `config`, `migrations`), and seeds the mechanisms later plans need — Router (all flows), View+`e()` (escaping per §14), Csrf (§14), DB+migrations (§4). Auth, invites, themes, maps, mail, admin are explicitly deferred to Plans 2–7. No Plan-1 requirement is unaddressed.

**2. Placeholder scan:** No "TBD/TODO/handle edge cases" — every code step shows complete code and exact commands with expected output. The only forward-reference notes ("Plan 2+ adds…") are scope markers, not placeholders.

**3. Type consistency:** `Config::get`, `Router::match` return shape `{handler,params}`, `Response::html/withStatus/withHeader`, `Store::get/set`, `Csrf::token/validate`, `View::render`, `DB::sqliteMemory/connect`, `MigrationRunner::run(): string[]` are used consistently across tasks and the front controller. `e()` is defined once in `View.php` and aliased as `$e` inside templates.

**Note on DB testing:** Core DB logic is unit-tested against SQLite in-memory (fast, no service needed). MySQL-specific schema is exercised in dev via `php -S` + a real MySQL `DB_DSN`; later plans that add repositories will document a `crush_test` MySQL database for integration tests where MySQL-only behavior matters.
