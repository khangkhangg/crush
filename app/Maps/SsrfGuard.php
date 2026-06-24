<?php
declare(strict_types=1);

namespace App\Maps;

final class SsrfGuard
{
    private const ALLOWED_DOMAINS = ['google.com', 'goo.gl', 'g.co'];

    public static function isAllowedScheme(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return $scheme === 'http' || $scheme === 'https';
    }

    public static function isAllowedHost(string $host): bool
    {
        $host = strtolower(rtrim($host, '.'));
        foreach (self::ALLOWED_DOMAINS as $domain) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return true;
            }
        }
        return false;
    }

    public static function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    public static function isAllowedUrl(string $url): bool
    {
        if (!self::isAllowedScheme($url)) {
            return false;
        }
        $host = (string) parse_url($url, PHP_URL_HOST);
        return $host !== '' && self::isAllowedHost($host);
    }
}
