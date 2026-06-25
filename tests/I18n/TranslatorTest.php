<?php
declare(strict_types=1);

namespace Tests\I18n;

use App\Core\Locale;
use App\Core\View;
use App\I18n\Languages;
use App\I18n\Translator;
use Tests\Support\DatabaseTestCase;

final class TranslatorTest extends DatabaseTestCase
{
    public function test_locale_supports_ten_languages(): void
    {
        foreach (['en', 'vi', 'es', 'zh', 'hi', 'pt', 'fr', 'ko', 'ja', 'th'] as $c) {
            $this->assertTrue(Locale::isSupported($c), $c);
        }
        $this->assertSame('Tiếng Việt', Languages::name('vi'));
    }

    public function test_translate_falls_back_to_english(): void
    {
        $this->pdo()->prepare('INSERT INTO ui_translations (lang, `key`, value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)')
            ->execute(['vi', 'Send a crush invite', 'Gửi lời mời hẹn hò']);
        $vi = new Translator($this->pdo(), 'vi');
        $this->assertSame('Gửi lời mời hẹn hò', $vi->t('Send a crush invite'));
        $this->assertSame('Untranslated here', $vi->t('Untranslated here'));   // fallback = English
        $en = new Translator($this->pdo(), 'en');
        $this->assertSame('Send a crush invite', $en->t('Send a crush invite')); // en = identity
    }

    public function test_view_injects_t_and_lang(): void
    {
        $this->pdo()->prepare('INSERT INTO ui_translations (lang, `key`, value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)')
            ->execute(['vi', 'Your invites', 'Lời mời của bạn']);
        $translator = new Translator($this->pdo(), 'vi');
        $this->assertSame('Lời mời của bạn', $translator->t('Your invites'));
        $this->assertSame('vi', $translator->lang());
        $view = new View(\dirname(__DIR__, 2) . '/templates', $translator);
        $html = $view->render('invite/dashboard', ['title' => 'x', 'invites' => [], 'appUrl' => '']);
        $this->assertStringContainsString('lang="vi"', $html);
    }
}
