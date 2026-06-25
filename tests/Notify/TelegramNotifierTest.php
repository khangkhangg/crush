<?php
declare(strict_types=1);
namespace Tests\Notify;
use App\Notify\TelegramNotifier;
use PHPUnit\Framework\TestCase;

final class TelegramNotifierTest extends TestCase
{
    public function test_unconfigured_when_token_or_chat_missing(): void
    {
        $this->assertFalse((new TelegramNotifier('', ''))->isConfigured());
        $this->assertFalse((new TelegramNotifier('tok', ''))->isConfigured());
        $this->assertFalse((new TelegramNotifier('', 'chat'))->isConfigured());
        $this->assertTrue((new TelegramNotifier('tok', 'chat'))->isConfigured());
    }

    public function test_notify_throws_when_unconfigured(): void
    {
        $this->expectException(\RuntimeException::class);
        (new TelegramNotifier('', ''))->notify('hi');
    }

    public function test_verify_throws_when_unconfigured(): void
    {
        $this->expectException(\RuntimeException::class);
        (new TelegramNotifier('', ''))->verify();
    }
}
