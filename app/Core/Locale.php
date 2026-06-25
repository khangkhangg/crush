<?php
declare(strict_types=1);

namespace App\Core;

final class Locale
{
    public const SUPPORTED = ['en', 'vi', 'es', 'zh', 'hi', 'pt', 'fr', 'ko', 'ja', 'th'];

    public static function detect(?string $acceptLanguage): string
    {
        if ($acceptLanguage === null || trim($acceptLanguage) === '') {
            return 'en';
        }
        $best = null;
        $bestQ = -1.0;
        foreach (explode(',', $acceptLanguage) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $bits = explode(';', $part);
            $tag = strtolower(trim($bits[0]));
            $q = 1.0;
            foreach (array_slice($bits, 1) as $param) {
                $param = trim($param);
                if (str_starts_with($param, 'q=')) {
                    $q = (float) substr($param, 2);
                }
            }
            $primary = explode('-', $tag)[0];
            if (self::isSupported($primary) && $q > $bestQ) {
                $best = $primary;
                $bestQ = $q;
            }
        }
        return $best ?? 'en';
    }

    public static function isSupported(string $lang): bool
    {
        return in_array($lang, self::SUPPORTED, true);
    }
}
