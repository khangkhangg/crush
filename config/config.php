<?php
declare(strict_types=1);

use App\Core\Config;

return Config::fromEnv(getenv() ?: []);
