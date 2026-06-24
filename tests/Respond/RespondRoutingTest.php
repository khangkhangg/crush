<?php
declare(strict_types=1);

namespace Tests\Respond;

use App\Core\Router;
use PHPUnit\Framework\TestCase;

final class RespondRoutingTest extends TestCase
{
    public function test_respond_routes_registered(): void
    {
        $router = new Router();
        $router->add('GET', '/i/{token}', static fn() => 'open');
        $router->add('POST', '/i/{token}', static fn() => 'submit');

        $get = $router->match('GET', '/i/abc123');
        $this->assertSame(['token' => 'abc123'], $get['params']);
        $post = $router->match('POST', '/i/abc123');
        $this->assertNotNull($post);
    }
}
