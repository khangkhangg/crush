<?php
declare(strict_types=1);

namespace Tests\Profile;

use App\Profile\Avatars;
use PHPUnit\Framework\TestCase;

final class AvatarsTest extends TestCase
{
    public function test_catalog_and_validation(): void
    {
        $this->assertNotEmpty(Avatars::keys());
        $this->assertTrue(Avatars::isValid(Avatars::keys()[0]));
        $this->assertFalse(Avatars::isValid('not-a-real-avatar'));
        $this->assertSame(Avatars::keys()[0], Avatars::default());
    }
}
