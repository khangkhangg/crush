<?php
declare(strict_types=1);

namespace App\Notify;

use App\Settings\SettingsRepo;

final class TelegramNotifier
{
    public function __construct(
        private string $botToken,
        private string $chatId,
    ) {}

    public static function fromSettings(SettingsRepo $s): self
    {
        return new self(
            (string) $s->get('telegram_bot_token', ''),
            (string) $s->get('telegram_chat_id', ''),
        );
    }

    public function isConfigured(): bool
    {
        return $this->botToken !== '' && $this->chatId !== '';
    }

    public function verify(): void
    {
        if ($this->botToken === '') {
            throw new \RuntimeException('Telegram bot token is not set.');
        }
        $this->request('getMe', []);
    }

    public function notify(string $text): void
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Telegram is not configured.');
        }
        $this->request('sendMessage', ['chat_id' => $this->chatId, 'text' => $text]);
    }

    /** @param array<string,string> $params */
    private function request(string $method, array $params): void
    {
        $ch = curl_init('https://api.telegram.org/bot' . $this->botToken . '/' . $method);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_TIMEOUT        => 15,
        ]);
        $res    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err    = curl_error($ch);
        curl_close($ch);
        if ($res === false || $status < 200 || $status >= 300) {
            throw new \RuntimeException('Telegram ' . $method . ' failed (' . $status . '): ' . $err . ' ' . (string) $res);
        }
    }
}
