<?php
declare(strict_types=1);

namespace App\Core;

interface RegeneratesId
{
    public function regenerateId(): void;
}
