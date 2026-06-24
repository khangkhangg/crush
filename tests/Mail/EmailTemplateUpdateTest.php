<?php
declare(strict_types=1);

namespace Tests\Mail;

use App\Mail\EmailTemplateRepo;
use Tests\Support\DatabaseTestCase;

final class EmailTemplateUpdateTest extends DatabaseTestCase
{
    public function test_get_exact_has_no_fallback(): void
    {
        $repo = new EmailTemplateRepo($this->pdo());
        $this->assertNotNull($repo->getExact('welcome', 'vi'));
        $this->assertNull($repo->getExact('welcome', 'fr')); // no fallback, unlike get()
    }

    public function test_update_changes_subject_and_body(): void
    {
        $repo = new EmailTemplateRepo($this->pdo());
        $repo->update('welcome', 'en', 'New subject {{name}}', '<p>New body {{link}}</p>');
        $row = $repo->getExact('welcome', 'en');
        $this->assertSame('New subject {{name}}', $row['subject']);
        $this->assertSame('<p>New body {{link}}</p>', $row['body_html']);
    }
}
