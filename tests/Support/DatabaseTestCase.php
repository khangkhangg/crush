<?php
declare(strict_types=1);

namespace Tests\Support;

use App\Core\MigrationRunner;
use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    private static ?\PDO $sharedPdo = null;

    protected function pdo(): \PDO
    {
        if (self::$sharedPdo === null) {
            $dsn  = getenv('CRUSH_TEST_DSN') ?: 'mysql:host=127.0.0.1;dbname=crush_test;charset=utf8mb4';
            $user = getenv('CRUSH_TEST_USER') ?: 'root';
            $pass = getenv('CRUSH_TEST_PASS') ?: '';
            $pdo = new \PDO($dsn, $user, $pass);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
            $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
            self::$sharedPdo = $pdo;
        }
        return self::$sharedPdo;
    }

    protected function setUp(): void
    {
        $pdo = $this->pdo();
        // Drop every table for a clean slate, then re-apply all migrations.
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $tables = $pdo->query(
            'SELECT table_name AS t FROM information_schema.tables WHERE table_schema = DATABASE()'
        )->fetchAll();
        foreach ($tables as $row) {
            $pdo->exec('DROP TABLE IF EXISTS `' . $row['t'] . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

        $migrationsDir = \dirname(__DIR__, 2) . '/migrations';
        (new MigrationRunner($pdo, $migrationsDir))->run();
    }
}
