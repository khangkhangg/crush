<?php
declare(strict_types=1);

namespace Tests\Landing;

use App\Core\Router;
use PHPUnit\Framework\TestCase;

final class LandingRoutingTest extends TestCase
{
    public function test_root_routes_exist_for_get_and_post(): void
    {
        $router = new Router();
        $router->add('GET', '/', static fn() => 'home');
        $router->add('POST', '/', static fn() => 'start');
        $this->assertNotNull($router->match('GET', '/'));
        $this->assertNotNull($router->match('POST', '/'));
    }
}
