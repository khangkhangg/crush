<?php
declare(strict_types=1);

namespace Tests\Admin;

use App\Core\Router;
use PHPUnit\Framework\TestCase;

final class AdminRoutingTest extends TestCase
{
    public function test_admin_trailing_slash_is_registered(): void
    {
        $router = new Router();
        $router->add('GET', '/admin', static fn() => 'dashboard');
        $router->add('GET', '/admin/', static fn() => 'dashboard');

        $this->assertNotNull($router->match('GET', '/admin'));
        $this->assertNotNull($router->match('GET', '/admin/'));
    }
}
