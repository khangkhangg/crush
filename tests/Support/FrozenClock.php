<?php
declare(strict_types=1);

namespace Tests\Support;

use App\Core\Clock;

final class FrozenClock implements Clock
{
    public function __construct(private \DateTimeImmutable $at) {}

    public function now(): \DateTimeImmutable
    {
        return $this->at;
    }

    public function advance(int $seconds): void
    {
        $this->at = $this->at->modify("+{$seconds} seconds");
    }
}
