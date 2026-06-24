<?php
declare(strict_types=1);

namespace App\Core;

final class PhpSessionStore implements Store
{
    private function boot(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->boot();
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->boot();
        $_SESSION[$key] = $value;
    }
}
