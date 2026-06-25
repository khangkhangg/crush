<?php
declare(strict_types=1);
namespace Tests\Mail;
use App\Mail\{Email, FailoverMailer, Mailer};
use PHPUnit\Framework\TestCase;

final class FailoverMailerTest extends TestCase
{
    private function email(): Email { return new Email('x@y.z', 's', '<p>h</p>'); }

    public function test_uses_primary_when_it_succeeds(): void
    {
        $primary = new class implements Mailer { public int $n = 0; public function send(Email $e): void { $this->n++; } public function verify(): void {} };
        $backup  = new class implements Mailer { public int $n = 0; public function send(Email $e): void { $this->n++; } public function verify(): void {} };
        (new FailoverMailer($primary, $backup))->send($this->email());
        $this->assertSame(1, $primary->n);
        $this->assertSame(0, $backup->n);
    }

    public function test_falls_back_when_primary_throws(): void
    {
        $primary = new class implements Mailer { public function send(Email $e): void { throw new \RuntimeException('boom'); } public function verify(): void {} };
        $backup  = new class implements Mailer { public int $n = 0; public function send(Email $e): void { $this->n++; } public function verify(): void {} };
        (new FailoverMailer($primary, $backup))->send($this->email());
        $this->assertSame(1, $backup->n);
    }

    public function test_calls_onFailure_and_rethrows_when_both_fail(): void
    {
        $primary = new class implements Mailer { public function send(Email $e): void { throw new \RuntimeException('p'); } public function verify(): void {} };
        $backup  = new class implements Mailer { public function send(Email $e): void { throw new \RuntimeException('b'); } public function verify(): void {} };
        $called = false;
        $f = new FailoverMailer($primary, $backup, function (\Throwable $t) use (&$called) { $called = true; });
        $this->expectException(\RuntimeException::class);
        try { $f->send($this->email()); } finally { $this->assertTrue($called); }
    }
}
