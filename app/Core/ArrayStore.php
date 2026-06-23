<?php
declare(strict_types=1);

namespace App\Core;

final class ArrayStore implements Store
{
    public function __construct(private array $data = []) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }
}
