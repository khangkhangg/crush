<?php
declare(strict_types=1);

// Apply pending SQL migrations against the configured database.
// Usage: php bin/migrate.php   (reads config from env / project-root .env)

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\DB;
use App\Core\MigrationRunner;

/** @var App\Core\Config $config */
$config = require dirname(__DIR__) . '/config/config.php';

$pdo = DB::connect($config);
$applied = (new MigrationRunner($pdo, dirname(__DIR__) . '/migrations'))->run();

echo $applied === []
    ? "No new migrations.\n"
    : ('Applied: ' . implode(', ', $applied) . "\n");
