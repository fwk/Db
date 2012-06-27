<?php

namespace Fwk\Db;

/**
 * Test class for EventDispatcher.
 */
class ConnectionTest extends \PHPUnit_Framework_TestCase {

    /**
     * @var Connection
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->object = new Connection(array(
            'memory'    => true,
            'driver'    => 'pdo_sqlite'
        ));
    }

    
    public function testConnect()
    {
        $this->assertFalse($this->object->isConnected());
        $this->assertTrue($this->object->connect());
        $this->assertTrue($this->object->isConnected());
    }

    public function testOptions()
    {
        $this->assertEquals(array('memory' => true, 'driver' => 'pdo_sqlite'), $this->object->getOptions());
        $this->object->setOptions(array(
            'testOpt1' => "value1",
            'testOpt2' => "value2"
        ));
        
        $this->assertEquals(array(
            'memory'    => true,
            'driver'    => 'pdo_sqlite',
            'testOpt1' => "value1",
            'testOpt2' => "value2"
        ), $this->object->getOptions());
        
        $this->assertEquals("value2", $this->object->get('testOpt2'));
        $this->object->setOptions(array(
            'testOpt2' => "merged"
        ));
        $this->assertEquals("merged", $this->object->get('testOpt2'));
        $this->object->set('testOpt1', "override");
        $this->assertEquals("override", $this->object->get('testOpt1'));
        $this->assertEquals("default", $this->object->get('inexistant', "default"));
    }
    
    public function testDisconnect()
    {
        $this->assertTrue($this->object->disconnect());
        $this->assertFalse($this->object->isConnected());
        $this->assertTrue($this->object->connect());
        $this->assertTrue($this->object->isConnected());
        $this->assertTrue($this->object->disconnect());
        $this->assertFalse($this->object->isConnected());
    }
    
    public function testTableNotExists()
    {
        $this->setExpectedException('\Fwk\Db\Exceptions\TableNotFound');
        $this->object->table('nonExistant');
    }
    
    public function testTable()
    {
        $this->prepareTestTable();
        $tbl = $this->object->table('test_table');
        $this->assertInstanceOf('\Fwk\Db\Table', $tbl);
    }
    
    protected function prepareTestTable()
    {
        $schema = $this->object->getSchema();
        
        $myTable = $schema->createTable("test_table");
        $myTable->addColumn("id", "integer", array("unsigned" => true));
        $myTable->addColumn("username", "string", array("length" => 32));
        $myTable->setPrimaryKey(array("id"));
        $myTable->addUniqueIndex(array("username"));
    }
}
