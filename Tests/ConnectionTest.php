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
    protected function setUp()
    {
        $schema = require __DIR__ .'/resources/testDatabaseSchema.php';
        $driver = new Testing\Driver();

        $this->object = new Connection($driver, $schema, array(

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

    public function testConnect()
    {
        $this->assertFalse($this->object->isConnected());
        $this->assertTrue($this->object->connect());
        $this->assertTrue($this->object->isConnected());
    }

    public function testOptions()
    {
        $this->assertEquals(array(), $this->object->getOptions());
        $this->object->setOptions(array(
            'testOpt1' => "value1",
            'testOpt2' => "value2"
        ));
        $this->assertEquals(array(
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
}
