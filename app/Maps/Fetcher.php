<?php
declare(strict_types=1);

namespace App\Maps;

interface Fetcher
{
    /** @throws \RuntimeException when the URL is disallowed or unreachable. */
    public function fetch(string $url): FetchResult;
}
