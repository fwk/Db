<?php

namespace Fwk\Db;


/**
 * Test class for EventDispatcher.
 */
class ConnectionTest extends \PHPUnit_Framework_TestCase {

    /**
     * @var EventDispatcher
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp() {
        $schema = require __DIR__ .'/resources/testDatabaseSchema.php';
        
        $this->object = new Connection($schema, array(
            
        ));
    }


    public function testTableFail() 
    {
        $this->setExpectedException('\Fwk\Db\Exceptions\TableNotFound');
        $this->object->table('nonExistantTable');
    }

    public function testTableOk() 
    {
        $table = $this->object->table(TEST_TABLE_1);
        $this->assertTrue(($table instanceof Table));
    }
}
