<?php
declare(strict_types=1);

namespace Tests\Maps;

use App\Maps\LinkResolver;
use App\Maps\MapsController;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeFetcher;

final class MapsControllerTest extends TestCase
{
    private function controller(array $map = []): MapsController
    {
        return new MapsController(new LinkResolver(new FakeFetcher($map)));
    }

    public function test_requires_login(): void
    {
        $res = $this->controller()->preview(null, 'https://maps.app.goo.gl/x');
        $this->assertSame(401, $res->status());
    }

    public function test_resolves_place(): void
    {
        // FakeFetcher maps url => ['finalUrl'=>…, 'body'=>…]; resolver reads og:title + /maps/place/.
        $fetch = ['https://maps.app.goo.gl/x' => [
            'finalUrl' => 'https://www.google.com/maps/place/Octo+Tapas/@10,106,15z',
            'body' => '<meta property="og:title" content="Octo Tapas Restobar">',
        ]];
        $res = $this->controller($fetch)->preview(7, 'https://maps.app.goo.gl/x');
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('Octo Tapas', $res->body());
        $this->assertStringContainsString('application/json', implode(' ', $res->headers()));
    }

    public function test_blank_url_returns_empty(): void
    {
        $res = $this->controller()->preview(7, '');
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('null', $res->body());     // name/address null
    }
}
