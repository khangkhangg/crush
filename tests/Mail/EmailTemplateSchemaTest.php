<?php
declare(strict_types=1);

namespace Tests\Mail;

use Tests\Support\DatabaseTestCase;

final class EmailTemplateSchemaTest extends DatabaseTestCase
{
    public function test_table_and_seeds(): void
    {
        $cols = array_column($this->pdo()->query('SHOW COLUMNS FROM email_templates')->fetchAll(), 'Field');
        foreach (['id', 'key', 'lang', 'subject', 'body_html'] as $c) {
            $this->assertContains($c, $cols, "email_templates.$c");
        }
        // 4 keys x 3 langs = 12 seeded rows
        $count = (int) $this->pdo()->query('SELECT COUNT(*) AS c FROM email_templates')->fetch()['c'];
        $this->assertSame(12, $count);
        foreach (['welcome', 'invite', 'result', 'magic'] as $key) {
            foreach (['en', 'vi', 'ko'] as $lang) {
                $stmt = $this->pdo()->prepare('SELECT subject, body_html FROM email_templates WHERE `key` = ? AND lang = ?');
                $stmt->execute([$key, $lang]);
                $row = $stmt->fetch();
                $this->assertNotFalse($row, "missing $key/$lang");
                $this->assertNotSame('', trim((string) $row['subject']));
                $this->assertNotSame('', trim((string) $row['body_html']));
            }
        }
    }
}
