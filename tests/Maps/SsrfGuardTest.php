<?php
declare(strict_types=1);

namespace Tests\Maps;

use App\Maps\SsrfGuard;
use PHPUnit\Framework\TestCase;

final class SsrfGuardTest extends TestCase
{
    public function test_scheme_allow_list(): void
    {
        $this->assertTrue(SsrfGuard::isAllowedScheme('https://maps.google.com/x'));
        $this->assertTrue(SsrfGuard::isAllowedScheme('http://goo.gl/x'));
        $this->assertFalse(SsrfGuard::isAllowedScheme('file:///etc/passwd'));
        $this->assertFalse(SsrfGuard::isAllowedScheme('gopher://x'));
    }

    public function test_host_allow_list(): void
    {
        $this->assertTrue(SsrfGuard::isAllowedHost('maps.google.com'));
        $this->assertTrue(SsrfGuard::isAllowedHost('www.google.com'));
        $this->assertTrue(SsrfGuard::isAllowedHost('maps.app.goo.gl'));
        $this->assertTrue(SsrfGuard::isAllowedHost('g.co'));
        $this->assertFalse(SsrfGuard::isAllowedHost('evil.com'));
        $this->assertFalse(SsrfGuard::isAllowedHost('google.com.evil.com'));
        $this->assertFalse(SsrfGuard::isAllowedHost('notgoogle.com'));
    }

    public function test_public_ip_detection(): void
    {
        $this->assertTrue(SsrfGuard::isPublicIp('142.250.72.78'));
        $this->assertFalse(SsrfGuard::isPublicIp('127.0.0.1'));
        $this->assertFalse(SsrfGuard::isPublicIp('10.0.0.5'));
        $this->assertFalse(SsrfGuard::isPublicIp('192.168.1.1'));
        $this->assertFalse(SsrfGuard::isPublicIp('169.254.1.1'));
        $this->assertFalse(SsrfGuard::isPublicIp('::1'));
        $this->assertFalse(SsrfGuard::isPublicIp('not-an-ip'));
    }

    public function test_is_allowed_url(): void
    {
        $this->assertTrue(SsrfGuard::isAllowedUrl('https://maps.app.goo.gl/abc'));
        $this->assertFalse(SsrfGuard::isAllowedUrl('https://evil.com/x'));
        $this->assertFalse(SsrfGuard::isAllowedUrl('http://169.254.169.254/latest/meta-data'));
    }
}
