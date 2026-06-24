<?php
declare(strict_types=1);

namespace Tests\Support;

use App\Maps\Fetcher;
use App\Maps\FetchResult;

final class FakeFetcher implements Fetcher
{
    /** @param array<string,array{finalUrl:string,body:string}> $map */
    public function __construct(private array $map) {}

    public function fetch(string $url): FetchResult
    {
        if (!isset($this->map[$url])) {
            throw new \RuntimeException("FakeFetcher: no canned response for {$url}");
        }
        return new FetchResult($this->map[$url]['finalUrl'], $this->map[$url]['body']);
    }
}
