<?php
declare(strict_types=1);

namespace Tests\Core;

use App\Core\Locale;
use PHPUnit\Framework\TestCase;

final class LocaleTest extends TestCase
{
    public function test_detects_supported_primary_subtag(): void
    {
        $this->assertSame('vi', Locale::detect('vi-VN,vi;q=0.9,en;q=0.8'));
        $this->assertSame('ko', Locale::detect('ko'));
        $this->assertSame('en', Locale::detect('en-US,en;q=0.9'));
    }

    public function test_falls_back_to_en(): void
    {
        $this->assertSame('en', Locale::detect('fr-FR,fr;q=0.9'));
        $this->assertSame('en', Locale::detect(''));
        $this->assertSame('en', Locale::detect(null));
    }

    public function test_highest_q_wins(): void
    {
        $this->assertSame('vi', Locale::detect('en;q=0.5,vi;q=0.9'));
        $this->assertSame('ko', Locale::detect('en;q=0.3,fr;q=0.8,ko;q=0.95'));
    }

    public function test_is_supported(): void
    {
        $this->assertTrue(Locale::isSupported('vi'));
        $this->assertTrue(Locale::isSupported('en'));
        $this->assertFalse(Locale::isSupported('fr'));
    }
}
