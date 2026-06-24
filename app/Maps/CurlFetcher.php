<?php
declare(strict_types=1);

namespace App\Maps;

final class CurlFetcher implements Fetcher
{
    public function __construct(private int $maxRedirects = 5, private int $timeoutSeconds = 8) {}

    public function fetch(string $url): FetchResult
    {
        $current = $url;
        for ($hop = 0; $hop <= $this->maxRedirects; $hop++) {
            $this->assertSafe($current);

            $ch = curl_init($current);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,   // we follow manually to re-check each hop
                CURLOPT_TIMEOUT        => $this->timeoutSeconds,
                CURLOPT_CONNECTTIMEOUT => $this->timeoutSeconds,
                CURLOPT_HEADER         => true,
                CURLOPT_USERAGENT      => 'CrushBot/1.0',
            ]);
            $raw = curl_exec($ch);
            if ($raw === false) {
                $err = curl_error($ch);
                curl_close($ch);
                throw new \RuntimeException('Maps fetch failed: ' . $err);
            }
            $status     = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);

            $headers = substr((string) $raw, 0, $headerSize);
            $body    = substr((string) $raw, $headerSize);

            if ($status >= 300 && $status < 400 && preg_match('/^location:\s*(.+)$/mi', $headers, $m)) {
                $next = trim($m[1]);
                if (!preg_match('#^https?://#i', $next)) {
                    // Relative redirect: resolve against current host.
                    $base = parse_url($current);
                    $next = ($base['scheme'] ?? 'https') . '://' . ($base['host'] ?? '') . '/' . ltrim($next, '/');
                }
                $current = $next;
                continue;
            }

            return new FetchResult($current, (string) $body);
        }
        throw new \RuntimeException('Maps fetch: too many redirects.');
    }

    private function assertSafe(string $url): void
    {
        if (!SsrfGuard::isAllowedUrl($url)) {
            throw new \RuntimeException('Maps fetch blocked: disallowed URL.');
        }
        $host = (string) parse_url($url, PHP_URL_HOST);
        $ip = gethostbyname($host);
        if ($ip === $host || !SsrfGuard::isPublicIp($ip)) {
            throw new \RuntimeException('Maps fetch blocked: host resolves to a non-public address.');
        }
    }
}
