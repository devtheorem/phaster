<?php

namespace DevTheorem\Phaster\Test;

use DevTheorem\PeachySQL\PeachySql;
use DevTheorem\Phaster\Test\src\App;
use PDO;

/**
 * @group mssql
 */
class MssqlDbTest extends DbTestCase
{
    private static ?PeachySql $db = null;

    public static function dbProvider(): PeachySql
    {
        if (!self::$db) {
            $c = App::$config;
            $server = $c->mssqlServer;
            $username = $c->mssqlUsername;
            $password = $c->mssqlPassword;

            $pdo = new PDO("sqlsrv:Server=$server;Database=PeachySQL", $username, $password, [
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::SQLSRV_ATTR_FETCHES_NUMERIC_TYPE => true,
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
                user_id INT PRIMARY KEY IDENTITY NOT NULL,
                name NVARCHAR(50) NOT NULL UNIQUE,
                dob DATE NOT NULL,
                weight FLOAT NOT NULL,
                is_disabled BIT NOT NULL
            );
            CREATE TABLE UserThings (
                thing_id INT PRIMARY KEY IDENTITY NOT NULL,
                user_id INT NOT NULL,
                FOREIGN KEY (user_id) REFERENCES Users(user_id)
            )";

        $db->query($sql);
    }
}
