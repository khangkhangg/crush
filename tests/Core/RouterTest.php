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
