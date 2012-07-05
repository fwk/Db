<?php
$vendorDir = __DIR__ . '/../vendor';

if (!@include($vendorDir . '/autoload.php')) {
    die("You must set up the project dependencies, run the following commands:
wget http://getcomposer.org/composer.phar
php composer.phar install
");
}

class FwkDbTestUtil
{
    private static $created = false;

    public static function createTestDb(\Fwk\Db\Connection $connection)
    {
        if(self::$created == true)

            return;

        $schema = $connection->getSchema();
        $tbl = $schema->createTable('fwkdb_test_users');
        $tbl->addColumn("id", "integer", array("unsigned" => true, "autoincrement" => true));
        $tbl->addColumn("username", "string", array("length" => 32));
        $tbl->addColumn("phone_id", "integer", array("unsigned" => true, "notnull" => false));
        $tbl->setPrimaryKey(array("id"));
        $tbl->addUniqueIndex(array("username"));

        $tbl = $schema->createTable('fwkdb_test_emails');
        $tbl->addColumn("id", "integer", array("unsigned" => true, "autoincrement" => true));
        $tbl->addColumn("email", "string", array("length" => 255));
        $tbl->addColumn("verified", "integer", array("length" => 1, "unsigned" => true));
        $tbl->setPrimaryKey(array("id"));
        $tbl->addUniqueIndex(array("email"));

        $tbl = $schema->createTable('fwkdb_test_users_emails');
        $tbl->addColumn("user_id", "integer", array("unsigned" => true));
        $tbl->addColumn("email_id", "integer", array("unsigned" => true));
        $tbl->setPrimaryKey(array("user_id", "email_id"));

        $tbl = $schema->createTable('fwkdb_test_users_metas');
        $tbl->addColumn("id", "integer", array("unsigned" => true, "autoincrement" => true));
        $tbl->addColumn("user_id", "integer", array("unsigned" => true));
        $tbl->addColumn("name", "string", array("length" => 50));
        $tbl->addColumn("value", "string");
        $tbl->addIndex(array('user_id'));
        $tbl->setPrimaryKey(array("id"));

        $tbl = $schema->createTable('fwkdb_test_phones');
        $tbl->addColumn("id", "integer", array("unsigned" => true, "autoincrement" => true));
        $tbl->addColumn("number", "string", array("length" => 50));
        $tbl->setPrimaryKey(array("id"));

        $queries = $schema->toSql($connection->getDriver()->getDatabasePlatform());
        foreach ($queries as $query) {
            $connection->getDriver()->exec($query);
        }

        self::$created = true;
    }

    public static function dropTestDb(\Fwk\Db\Connection $connection)
    {
        if(self::$created == false)

            return;

        $schema = $connection->getSchema();
        $queries = $schema->toDropSql($connection->getDriver()->getDatabasePlatform());
        foreach ($queries as $query) {
            $connection->getDriver()->exec($query);
        }

        self::$created = false;
    }
}
