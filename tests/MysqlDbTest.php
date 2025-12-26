<?php

namespace DevTheorem\Phaster\Test;

use DevTheorem\PeachySQL\PeachySql;
use DevTheorem\Phaster\Test\src\App;
use PDO;
use PHPUnit\Framework\Attributes\Group;

#[Group('mysql')]
class MysqlDbTest extends DbTestCase
{
    private static ?PeachySql $db = null;

    public static function dbProvider(): PeachySql
    {
        if (!self::$db) {
            $c = App::$config;

            $pdo = new PDO($c->mysqlDsn, $c->mysqlUser, $c->mysqlPassword, [
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            self::$db = new PeachySql($pdo);
            self::createTestTable(self::$db);
            assert(self::$db !== null);
        }

        return self::$db;
    }

    private static function createTestTable(PeachySql $db): void
    {
        self::tearDownAfterClass();

        $sql = "
            CREATE TABLE Users (
                user_id INT PRIMARY KEY AUTO_INCREMENT NOT NULL,
                name VARCHAR(50) NOT NULL UNIQUE,
                dob DATE NOT NULL,
                weight DOUBLE NOT NULL,
                is_disabled BOOLEAN NOT NULL
            )";

        $db->query($sql);

        $sql = "
            CREATE TABLE UserThings (
                thing_id INT PRIMARY KEY AUTO_INCREMENT NOT NULL,
                user_id INT NOT NULL,
                FOREIGN KEY (user_id) REFERENCES Users(user_id)
            )";

        $db->query($sql);
    }
}
