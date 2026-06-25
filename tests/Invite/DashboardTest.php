<?php
declare(strict_types=1);

namespace Tests\Invite;

use App\Core\View;
use PHPUnit\Framework\TestCase;

final class DashboardTest extends TestCase
{
    public function test_cards_show_status_badge_and_view_link(): void
    {
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $html = $view->render('invite/dashboard', [
            'title' => 'Your invites', 'appUrl' => 'https://crush.app',
            'invites' => [[
                'crush_name' => 'Mia', 'crush_email' => 'mia@x.test', 'status' => 'confirmed',
                'public_token' => 'tok123',
            ]],
        ]);
        $this->assertStringContainsString('Mia', $html);
        $this->assertStringContainsString('iv-badge', $html);                       // status badge
        $this->assertStringContainsString('class="iv-view"', $html);                // enhanced view trigger
        $this->assertStringContainsString('/invites/tok123/response', $html);       // real href (no-JS fallback)
        $this->assertStringContainsString('class="iv-detail"', $html);              // inline expand panel
        $this->assertStringNotContainsString('https://crush.app/i/tok123', $html);  // raw URL not dumped
    }
}
