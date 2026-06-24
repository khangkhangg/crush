<?php
declare(strict_types=1);

namespace Tests\Maps;

use App\Maps\FetchResult;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeFetcher;

final class FakeFetcherTest extends TestCase
{
    public function test_returns_mapped_result(): void
    {
        $f = new FakeFetcher([
            'https://maps.app.goo.gl/abc' => ['finalUrl' => 'https://www.google.com/maps/place/Cafe', 'body' => '<title>Cafe</title>'],
        ]);
        $res = $f->fetch('https://maps.app.goo.gl/abc');
        $this->assertInstanceOf(FetchResult::class, $res);
        $this->assertSame('https://www.google.com/maps/place/Cafe', $res->finalUrl);
        $this->assertStringContainsString('Cafe', $res->body);
    }

    public function test_unknown_url_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        (new FakeFetcher([]))->fetch('https://maps.app.goo.gl/missing');
    }
}
