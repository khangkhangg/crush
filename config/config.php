<?php
declare(strict_types=1);

use App\Core\Config;

// Merge a project-root .env file (production) over the process environment so
// both web (PHP-FPM) and CLI (bin/*) read the same config without pool env[].
$env = getenv() ?: [];
$envFile = dirname(__DIR__) . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $env[trim($key)] = trim($value);
    }
}

return Config::fromEnv($env);
