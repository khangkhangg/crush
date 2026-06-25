<?php
declare(strict_types=1);

namespace App\I18n;

final class Languages
{
    public const ALL = [
        'en' => 'English',
        'vi' => 'Tiếng Việt',
        'es' => 'Español',
        'zh' => '中文',
        'hi' => 'हिन्दी',
        'pt' => 'Português',
        'fr' => 'Français',
        'ko' => '한국어',
        'ja' => '日本語',
        'th' => 'ไทย',
    ];

    /** @return array<int,string> */
    public static function codes(): array
    {
        return array_keys(self::ALL);
    }

    public static function name(string $code): string
    {
        return self::ALL[$code] ?? $code;
    }
}
