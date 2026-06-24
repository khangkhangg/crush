<?php
declare(strict_types=1);

namespace Tests\View;

use App\Core\View;
use PHPUnit\Framework\TestCase;

final class LayoutTest extends TestCase
{
    private function view(): View
    {
        return new View(\dirname(__DIR__, 2) . '/templates');
    }

    public function test_layout_has_box_sizing_reset_and_wide_card_rule(): void
    {
        // landing/home renders through layout.php
        $html = $this->view()->render('landing/home', ['title' => 'Crush', 'csrf' => 'x']);
        $this->assertStringContainsString('box-sizing:border-box', $html);
        $this->assertStringContainsString('.card--wide', $html);
    }
}
