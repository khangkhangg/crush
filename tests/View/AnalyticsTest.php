<?php
declare(strict_types=1);

namespace Tests\View;

use App\Core\View;
use PHPUnit\Framework\TestCase;

final class AnalyticsTest extends TestCase
{
    private function view(): View
    {
        return new View(\dirname(__DIR__, 2) . '/templates');
    }

    public function test_layout_pages_include_widget(): void
    {
        $html = $this->view()->render('invite/dashboard', ['title' => 'x', 'invites' => [], 'appUrl' => '']);
        $this->assertStringContainsString('user-statistic-widget.js', $html);
        $this->assertStringContainsString('pk_crushf9wlh77uytkd', $html);
    }

    public function test_standalone_crush_page_includes_widget(): void
    {
        $html = $this->view()->render('respond/confirmed', [
            'title' => 'Crush', 'theme' => 'bubblegum', 'when' => 'soon', 'reveal' => null, 'wasAnonymous' => false,
        ]);
        $this->assertStringContainsString('user-statistic-widget.js', $html);
    }
}
