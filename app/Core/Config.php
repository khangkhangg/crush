<?php
declare(strict_types=1);

namespace App\Core;

final class Config
{
    public function __construct(private array $items) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->items[$key] ?? $default;
    }

    public static function fromEnv(array $env): self
    {
        return new self([
            'app_env' => $env['APP_ENV'] ?? 'production',
            'app_url' => $env['APP_URL'] ?? 'http://localhost',
            'db_dsn'  => $env['DB_DSN']  ?? '',
            'db_user' => $env['DB_USER'] ?? '',
            'db_pass' => $env['DB_PASS'] ?? '',
        ]);
    }
}
