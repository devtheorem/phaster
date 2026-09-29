<?php

namespace DevTheorem\Phaster\Test;

use DevTheorem\PeachySQL\PeachySql;
use DevTheorem\Phaster\Test\src\App;
use PDO;
use PHPUnit\Framework\Attributes\Group;

#[Group('pgsql')]
class PgsqlDbTest extends DbTestCase
{
    private static ?PeachySql $db = null;

    public static function createConnection(): PDO
    {
        $c = App::$config;
        $dbName = getenv('POSTGRES_HOST') !== false ? 'postgres' : 'PeachySQL';

        return new PDO($c->getPgsqlDsn($dbName), $c->pgsqlUser, $c->pgsqlPassword, [
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public static function dbProvider(): PeachySql
    {
        if (!self::$db) {
            self::$db = new PeachySql(self::createConnection());
            self::createTestTable(self::$db);
            assert(self::$db !== null);
        }

        return self::$db;
    }

    protected function getIdentityColumnDefinition(): string
    {
        return 'SERIAL PRIMARY KEY';
    }

    private static function createTestTable(PeachySql $db): void
    {
        self::tearDownAfterClass();

        $sql = "
            CREATE TABLE Users (
                user_id SERIAL PRIMARY KEY,
                name VARCHAR(50) NOT NULL UNIQUE,
                dob DATE NOT NULL,
                weight REAL NOT NULL,
                is_disabled BOOLEAN NOT NULL
            )";

        $db->query($sql);

        $sql = "
            CREATE TABLE UserThings (
                thing_id SERIAL PRIMARY KEY,
                user_id INT NOT NULL,
                FOREIGN KEY (user_id) REFERENCES Users(user_id)
            )";

        $db->query($sql);
    }
}
