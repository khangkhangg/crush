<?php
declare(strict_types=1);

namespace App\Core;

final class DB
{
    public static function connect(Config $config): \PDO
    {
        $pdo = new \PDO(
            (string) $config->get('db_dsn'),
            (string) $config->get('db_user'),
            (string) $config->get('db_pass'),
        );
        self::tune($pdo);
        return $pdo;
    }

    public static function sqliteMemory(): \PDO
    {
        $pdo = new \PDO('sqlite::memory:');
        self::tune($pdo);
        return $pdo;
    }

    private static function tune(\PDO $pdo): void
    {
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
    }
}
