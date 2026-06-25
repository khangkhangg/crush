<?php
declare(strict_types=1);

namespace Tests\I18n;

use App\I18n\LangController;
use PHPUnit\Framework\TestCase;

final class LangSwitchTest extends TestCase
{
    public function test_supported_sets_cookie_and_redirects_back(): void
    {
        $res = (new LangController())->set('vi', '/invites/new');
        $this->assertSame(302, $res->status());
        $this->assertSame('/invites/new', $res->headers()['Location']);
        $this->assertStringContainsString('lang=vi', implode(' ', $res->headers()));   // Set-Cookie
    }

    public function test_unsupported_redirects_home_no_cookie(): void
    {
        $res = (new LangController())->set('xx', '/invites');
        $this->assertSame('/', $res->headers()['Location']);
    }

    public function test_unsafe_referer_goes_home(): void
    {
        $res = (new LangController())->set('vi', 'https://evil.com');
        $this->assertSame('/', $res->headers()['Location']);
    }
}
