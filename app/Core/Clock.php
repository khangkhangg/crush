<?php
declare(strict_types=1);

namespace App\Core;

interface Clock
{
    public function now(): \DateTimeImmutable;
}
