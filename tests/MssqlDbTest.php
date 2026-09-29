<?php

namespace DevTheorem\Phaster\Test;

use DevTheorem\PeachySQL\PeachySql;
use DevTheorem\Phaster\Test\src\App;
use PDO;
use PHPUnit\Framework\Attributes\Group;

#[Group('mssql')]
class MssqlDbTest extends DbTestCase
{
    private static ?PeachySql $db = null;

    public static function createConnection(): PDO
    {
        // set when running tests with GitHub Actions
        $server = getenv('SQLCMDSERVER');
        $username = getenv('SQLCMDUSER');
        $password = getenv('SQLCMDPASSWORD');

        if ($server === false || $username === false || $password === false) {
            $c = App::$config;
            $server = $c->mssqlServer;
            $username = $c->mssqlUsername;
            $password = $c->mssqlPassword;
        }

        // ODBC Driver 18 encrypts connections by default, and test servers generally use a self-signed certificate
        $dsn = "sqlsrv:Server=$server;Database=PeachySQL;TrustServerCertificate=1";

        return new PDO($dsn, $username, $password, [
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::SQLSRV_ATTR_FETCHES_NUMERIC_TYPE => true,
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
        return 'INT PRIMARY KEY IDENTITY NOT NULL';
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
