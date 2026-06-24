<?php
declare(strict_types=1);

namespace Tests\Maps;

use App\Maps\LinkResolver;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeFetcher;

final class LinkResolverTest extends TestCase
{
    public function test_empty_input_is_all_null(): void
    {
        $r = (new LinkResolver(new FakeFetcher([])))->resolve('   ');
        $this->assertNull($r['name']);
        $this->assertNull($r['address']);
        $this->assertNull($r['clean_url']);
    }

    public function test_plain_address_builds_search_url(): void
    {
        $r = (new LinkResolver(new FakeFetcher([])))->resolve('1 Main St, Springfield');
        $this->assertNull($r['name']);
        $this->assertSame('1 Main St, Springfield', $r['address']);
        $this->assertStringContainsString('google.com/maps/search/', $r['clean_url']);
        $this->assertStringContainsString(rawurlencode('1 Main St, Springfield'), $r['clean_url']);
    }

    public function test_short_link_is_unfurled_and_parsed(): void
    {
        $fetcher = new FakeFetcher([
            'https://maps.app.goo.gl/abc' => [
                'finalUrl' => 'https://www.google.com/maps/place/Blue+Bottle+Coffee/@37.7,-122.4,17z',
                'body'     => '<html><head><meta property="og:title" content="Blue Bottle Coffee"><title>Blue Bottle Coffee - Google Maps</title></head></html>',
            ],
        ]);
        $r = (new LinkResolver($fetcher))->resolve('https://maps.app.goo.gl/abc');
        $this->assertSame('Blue Bottle Coffee', $r['name']);
        $this->assertSame('Blue Bottle Coffee', $r['address']);
        $this->assertStringContainsString('google.com/maps/search/', $r['clean_url']);
        $this->assertStringContainsString(rawurlencode('Blue Bottle Coffee'), $r['clean_url']);
    }

    public function test_generic_google_maps_title_is_dropped_so_place_name_wins(): void
    {
        // The goo.gl interstitial often has og:title "Google Maps"; the real place
        // comes from the /maps/place/ URL — the generic title must not win.
        $fetcher = new FakeFetcher([
            'https://maps.app.goo.gl/x' => [
                'finalUrl' => 'https://www.google.com/maps/place/Octo+Tapas+Restobar/@10.77,106.70,15z',
                'body'     => '<html><head><meta property="og:title" content="Google Maps"></head></html>',
            ],
        ]);
        $r = (new LinkResolver($fetcher))->resolve('https://maps.app.goo.gl/x');
        $this->assertNull($r['name']);                               // generic title dropped
        $this->assertSame('Octo Tapas Restobar', $r['address']);     // place name from the URL
    }

    public function test_disallowed_url_falls_back_to_raw(): void
    {
        $r = (new LinkResolver(new FakeFetcher([])))->resolve('https://evil.com/x');
        $this->assertNull($r['name']);
        $this->assertNull($r['address']);
        $this->assertSame('https://evil.com/x', $r['clean_url']);
    }

    public function test_fetch_failure_falls_back_to_raw_url(): void
    {
        // Allowed host, but FakeFetcher has no canned response -> it throws -> resolver swallows.
        $r = (new LinkResolver(new FakeFetcher([])))->resolve('https://maps.app.goo.gl/missing');
        $this->assertNull($r['name']);
        $this->assertSame('https://maps.app.goo.gl/missing', $r['clean_url']);
    }
}
