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
        $tbl->setPrimaryKey(array("id"));
        $tbl->addUniqueIndex(array("username"));
        
        $queries = $schema->toSql($connection->getDriver()->getDatabasePlatform());
        foreach($queries as $query) {
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
        foreach($queries as $query) {
            $connection->getDriver()->exec($query);
        }
        
        self::$created = false;
    }
}