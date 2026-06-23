<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase
{
    public function test_autoloader_and_phpunit_work(): void
    {
        $this->assertTrue(true);
    }
}
