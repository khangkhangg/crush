<?php
declare(strict_types=1);

namespace App\Core;

interface Store
{
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value): void;
}
