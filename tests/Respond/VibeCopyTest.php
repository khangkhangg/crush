<?php
declare(strict_types=1);

namespace Tests\Respond;

use App\Core\View;
use PHPUnit\Framework\TestCase;

final class VibeCopyTest extends TestCase
{
    public function test_recipient_picker_uses_broad_vibe_copy(): void
    {
        $html = (new View(\dirname(__DIR__, 2) . '/templates'))->render('respond/_form', [
            'token' => 'abc',
            'csrf' => 'x',
            'meals' => [['key' => 'hotel', 'label' => 'Hotel', 'icon' => 'ic-hotel']],
        ]);
        $this->assertStringContainsString('Pick a vibe', $html);
        $this->assertStringNotContainsString('What are you craving?', $html);
    }
}
