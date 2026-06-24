<?php
declare(strict_types=1);

namespace App\Maps;

final class LinkResolver
{
    public function __construct(private Fetcher $fetcher) {}

    /** @return array{name:?string,address:?string,clean_url:?string} */
    public function resolve(string $input): array
    {
        $input = trim($input);
        if ($input === '') {
            return ['name' => null, 'address' => null, 'clean_url' => null];
        }

        if (!$this->looksLikeUrl($input)) {
            return ['name' => null, 'address' => $input, 'clean_url' => $this->searchUrl($input)];
        }

        if (!SsrfGuard::isAllowedUrl($input)) {
            return ['name' => null, 'address' => null, 'clean_url' => $input];
        }

        try {
            $res = $this->fetcher->fetch($input);
        } catch (\Throwable) {
            return ['name' => null, 'address' => null, 'clean_url' => $input];
        }

        $name = $this->parseName($res->body);
        $address = $this->parseAddress($res->finalUrl) ?? $name;
        $query = trim((string) ($name ?? '') . ' ' . (string) ($address ?? ''));
        $clean = $query !== '' ? $this->searchUrl($query) : $res->finalUrl;

        return ['name' => $name, 'address' => $address, 'clean_url' => $clean];
    }

    private function looksLikeUrl(string $s): bool
    {
        return (bool) preg_match('#^https?://#i', $s);
    }

    private function searchUrl(string $query): string
    {
        return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($query);
    }

    private function parseName(string $html): ?string
    {
        if (preg_match('#<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']#i', $html, $m)) {
            return html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
        }
        if (preg_match('#<title>([^<]+)</title>#i', $html, $m)) {
            $title = trim($m[1]);
            // Strip a trailing " - Google Maps" suffix.
            $title = preg_replace('/\s*[-–]\s*Google Maps\s*$/i', '', $title);
            return $title !== '' ? html_entity_decode($title, ENT_QUOTES, 'UTF-8') : null;
        }
        return null;
    }

    private function parseAddress(string $finalUrl): ?string
    {
        if (preg_match('#/maps/place/([^/@]+)#', $finalUrl, $m)) {
            return trim(str_replace('+', ' ', rawurldecode($m[1]))) ?: null;
        }
        parse_str((string) parse_url($finalUrl, PHP_URL_QUERY), $q);
        foreach (['query', 'q'] as $key) {
            if (!empty($q[$key]) && is_string($q[$key])) {
                return trim($q[$key]);
            }
        }
        return null;
    }
}
