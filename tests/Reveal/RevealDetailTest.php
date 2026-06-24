<?php
declare(strict_types=1);

namespace Tests\Reveal;

use App\Core\View;
use PHPUnit\Framework\TestCase;

final class RevealDetailTest extends TestCase
{
    public function test_reveal_shows_timeline_who_and_reshare(): void
    {
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $html = $view->render('reveal/response', [
            'title' => 'Your crush', 'state' => 'reveal', 'appUrl' => 'https://crush.app',
            'invite' => [
                'crush_name' => 'Mia', 'crush_email' => 'mia@x.test', 'public_token' => 'tok9',
                'status' => 'confirmed', 'created_at' => '2026-06-01 10:00:00', 'is_anonymous' => 1,
            ],
            'response' => [
                'chosen_start' => '2026-06-30 08:08:00', 'meal_choice' => 'dinner',
                'pickup_name' => 'Tartine', 'pickup_address' => '1 Main St',
                'pickup_clean_url' => 'https://www.google.com/maps/search/?api=1&query=Tartine',
                'created_at' => '2026-06-02 09:00:00',
            ],
        ]);
        $this->assertStringContainsString('iv-timeline', $html);                 // timeline
        $this->assertStringContainsString('Mia', $html);                          // who
        $this->assertStringContainsString('/invites/tok9/calendar', $html);       // calendar
        $this->assertStringContainsString('iv-reshare', $html);                   // reshare control
        $this->assertStringContainsString('data-token="tok9"', $html);            // reshare token (URL built client-side)
        $this->assertStringContainsString('https://www.google.com/maps', $html);  // map link
    }
}
