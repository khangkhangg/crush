<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Auth\UserRepo;
use App\Core\DB;
use App\Core\SystemClock;

$email    = $argv[1] ?? null;
$password = $argv[2] ?? null;
if ($email === null || $password === null || $password === '') {
    fwrite(STDERR, "Usage: php bin/set-password.php <email> <password>\n");
    exit(1);
}

/** @var App\Core\Config $config */
$config = require dirname(__DIR__) . '/config/config.php';
$pdo    = DB::connect($config);
$users  = new UserRepo($pdo, new SystemClock());

$user = $users->findByEmail($email);
if ($user === null) {
    fwrite(STDERR, "No user with email {$email}\n");
    exit(1);
}
$users->setPasswordHash((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
echo "Password set for {$email}\n";
