<?php
declare(strict_types=1);

namespace Tests\View;

use App\Core\View;
use PHPUnit\Framework\TestCase;

final class DesignSystemTest extends TestCase
{
    public function test_layout_defines_component_classes(): void
    {
        // invite/dashboard renders through templates/layout.php (where the component CSS lives).
        $html = (new View(\dirname(__DIR__, 2) . '/templates'))->render('invite/dashboard', ['title' => 'x', 'invites' => [], 'appUrl' => '']);
        foreach (['.seg', '.chip', '.field', 'input:checked', ':focus-visible'] as $needle) {
            $this->assertStringContainsString($needle, $html, $needle);
        }
    }
}
