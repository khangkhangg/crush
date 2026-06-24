<?php
declare(strict_types=1);

namespace Tests\Respond;

use App\Core\View;
use PHPUnit\Framework\TestCase;

final class ConfirmedEnvelopeTest extends TestCase
{
    public function test_envelope_markup_and_content(): void
    {
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $html = $view->render('respond/confirmed', [
            'title' => 'Crush', 'theme' => 'bubblegum', 'when' => 'Tue, Jun 30 at 8:08 AM',
            'reveal' => 'khang', 'wasAnonymous' => true,
        ]);
        $this->assertStringContainsString('class="envelope"', $html);   // envelope illustration
        $this->assertStringContainsString('Tue, Jun 30 at 8:08 AM', $html);
        $this->assertStringContainsString('khang', $html);              // reveal preserved
    }
}
