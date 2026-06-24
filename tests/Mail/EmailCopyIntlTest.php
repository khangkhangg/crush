<?php
declare(strict_types=1);

namespace Tests\Mail;

use App\Mail\EmailTemplateRepo;
use Tests\Support\DatabaseTestCase;

final class EmailCopyIntlTest extends DatabaseTestCase
{
    public function test_vietnamese_welcome_is_accented(): void
    {
        $repo = new EmailTemplateRepo($this->pdo());
        $vi = $repo->getExact('welcome', 'vi');
        $this->assertStringContainsString('Chào mừng', $vi['subject']);   // accented
        // placeholders preserved
        $this->assertStringContainsString('{{name}}', $vi['body_html']);
        $this->assertStringContainsString('{{link}}', $vi['body_html']);
    }

    public function test_korean_invite_preserves_placeholders(): void
    {
        $repo = new EmailTemplateRepo($this->pdo());
        $ko = $repo->getExact('invite', 'ko');
        foreach (['{{senderLabel}}', '{{link}}', '{{unsubscribe}}'] as $p) {
            $this->assertStringContainsString($p, $ko['body_html']);
        }
    }
}
