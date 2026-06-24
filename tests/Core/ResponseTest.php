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
