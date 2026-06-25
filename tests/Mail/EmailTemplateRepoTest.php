<?php
declare(strict_types=1);

namespace Tests\Mail;

use App\Mail\EmailTemplateRepo;
use Tests\Support\DatabaseTestCase;

final class EmailTemplateRepoTest extends DatabaseTestCase
{
    public function test_get_exact_then_en_fallback(): void
    {
        $repo = new EmailTemplateRepo($this->pdo());
        $this->assertSame('vi', $this->langOf($repo->get('welcome', 'vi')));
        // Korean exists too
        $this->assertNotNull($repo->get('welcome', 'ko'));
        // unsupported lang (German not seeded) -> en fallback row
        $fb = $repo->get('welcome', 'de');
        $this->assertNotNull($fb);
        $this->assertSame('Welcome to Crush', $fb['subject']);
    }

    public function test_render_interpolates_and_escapes_body(): void
    {
        $repo = new EmailTemplateRepo($this->pdo());
        $out = $repo->render('invite', 'en', ['message' => '<b>Ann</b>', 'link' => 'https://crush.app/x', 'unsubscribe' => 'https://crush.app/u']);
        $this->assertStringContainsString('&lt;b&gt;Ann&lt;/b&gt;', $out['html']); // escaped in body
        $this->assertStringContainsString('https://crush.app/x', $out['html']);
        $this->assertStringNotContainsString('{{message}}', $out['html']);
    }

    public function test_render_subject_strips_newlines_not_escaped(): void
    {
        $repo = new EmailTemplateRepo($this->pdo());
        $out = $repo->render('result', 'en', ['crushName' => "Cee\r\nBcc: evil@x", 'when' => '', 'meal' => '', 'place' => '', 'mapHref' => '']);
        $this->assertStringNotContainsString("\n", $out['subject']);   // CR/LF stripped (header-injection safe)
        $this->assertStringContainsString('Cee', $out['subject']);
    }

    public function test_render_throws_when_missing(): void
    {
        $repo = new EmailTemplateRepo($this->pdo());
        $this->expectException(\RuntimeException::class);
        $repo->render('nope', 'en', []);
    }

    private function langOf(?array $row): ?string
    {
        return $row['lang'] ?? null;
    }
}
