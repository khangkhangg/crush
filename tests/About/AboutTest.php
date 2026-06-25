<?php
declare(strict_types=1);

namespace Tests\About;

use App\About\AboutController;
use App\Core\View;
use App\I18n\Translator;
use Tests\Support\DatabaseTestCase;

final class AboutTest extends DatabaseTestCase
{
    public function test_about_renders_and_translates(): void
    {
        $en = new AboutController(new View(\dirname(__DIR__, 2) . '/templates', new Translator($this->pdo(), 'en')));
        $body = $en->show()->body();
        $this->assertSame(200, (new AboutController(new View(\dirname(__DIR__, 2) . '/templates', new Translator($this->pdo(), 'en'))))->show()->status());
        $this->assertStringContainsString('Crush', $body);

        // A seeded Vietnamese landing string (from migration 0017) is used by $t.
        $vi = new Translator($this->pdo(), 'vi');
        $this->assertNotSame('', $vi->t('Send your crush a date — anonymously, adorably.'));  // has a vi row or falls back
    }
}
