<?php
declare(strict_types=1);

namespace Tests\I18n;

use App\Core\View;
use App\I18n\Translator;
use Tests\Support\DatabaseTestCase;

final class SenderFlowI18nTest extends DatabaseTestCase
{
    private function viewVi(): View
    {
        $ins = $this->pdo()->prepare('INSERT INTO ui_translations (lang, `key`, value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)');
        $ins->execute(['vi', 'Your invites', 'Lời mời của bạn']);
        $ins->execute(['vi', 'Your invite is ready', 'Lời mời của bạn đã sẵn sàng']);
        return new View(\dirname(__DIR__, 2) . '/templates', new Translator($this->pdo(), 'vi'));
    }

    public function test_dashboard_is_translated(): void
    {
        $html = $this->viewVi()->render('invite/dashboard', ['title' => 'x', 'invites' => [], 'appUrl' => '']);
        $this->assertStringContainsString('Lời mời của bạn', $html);          // "Your invites" wrapped
        $this->assertStringNotContainsString('>Your invites<', $html);
    }

    public function test_created_is_translated(): void
    {
        $html = $this->viewVi()->render('invite/created', ['title' => 'x', 'link' => 'https://c.app/i/t', 'invite' => ['crush_name' => 'A', 'crush_email' => 'a@x.test'], 'shareLinks' => []]);
        $this->assertStringContainsString('Lời mời của bạn đã sẵn sàng', $html); // "Your invite is ready" wrapped
    }
}
