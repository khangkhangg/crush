<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Config;
use App\Core\DB;

$email = $argv[1] ?? null;
if ($email === null) {
    fwrite(STDERR, "Usage: php bin/make-admin.php <email>\n");
    exit(1);
}

/** @var Config $config */
$config = require dirname(__DIR__) . '/config/config.php';
$pdo = DB::connect($config);
$stmt = $pdo->prepare('UPDATE users SET is_admin = 1 WHERE email = ?');
$stmt->execute([$email]);

if ($stmt->rowCount() === 0) {
    fwrite(STDERR, "No user with email {$email}\n");
    exit(1);
}
echo "Granted admin to {$email}\n";
