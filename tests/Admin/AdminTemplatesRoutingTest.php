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
