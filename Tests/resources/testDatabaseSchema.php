<?php
namespace Fwk\Db;

if(!defined("TEST_TABLE_1")) {
    define("TEST_TABLE_1", "testTableOne");
}

return call_user_func(function() {
    $schema = new Testing\Schema();

    $testTable = new Table(TEST_TABLE_1);
    $testTable->addColumns(array(
        new Columns\NumericColumn('id', 'integer', 11, false, null, Column::INDEX_PRIMARY, true),
        new Columns\TextColumn('test', 'varchar', 255, false, null),
        new Columns\TextColumn('test_null', 'varchar', 50, true, null)
    ));

    $schema->addTable($testTable);

    return $schema;
});
