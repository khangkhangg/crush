<?php
declare(strict_types=1);

namespace App\Maps;

final class FetchResult
{
    public function __construct(public string $finalUrl, public string $body) {}
}
