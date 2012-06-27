<?php
$vendorDir = __DIR__ . '/vendor';

if (!@include($vendorDir . '/autoload.php')) {
    die("You must set up the project dependencies, run the following commands:
wget http://getcomposer.org/composer.phar
php composer.phar install
");
}


$db = new \Fwk\Db\Connection(array(
    'driver' => 'pdo_mysql',
    'dbname' => 'testugap',
    'user'      => 'root',
    'password'  => 'monique'
));

$schema = $db->getSchema();
$myTable = $schema->createTable("test_table");
$myTable->addColumn("id", "integer", array("unsigned" => true));
$myTable->addColumn("username", "string", array("length" => 32));
$myTable->setPrimaryKey(array("id"));
$myTable->addUniqueIndex(array("username"));


var_dump($db->table('etats')->getIdentifiersKeys());