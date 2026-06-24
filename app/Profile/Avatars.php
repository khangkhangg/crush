<?php
declare(strict_types=1);

namespace App\Profile;

final class Avatars
{
    public const KEYS = ['fox', 'bunny', 'cat', 'bear', 'frog', 'duck', 'ghost', 'star'];

    /** @return string[] */
    public static function keys(): array
    {
        return self::KEYS;
    }

    public static function isValid(string $key): bool
    {
        return in_array($key, self::KEYS, true);
    }

    public static function default(): string
    {
        return self::KEYS[0];
    }
}
