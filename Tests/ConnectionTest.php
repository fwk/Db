<?php

namespace Fwk\Db;
use Fwk\Db\Events\BeforeQueryEvent;

/**
 * Test class for EventDispatcher.
 */
class ConnectionTest extends \PHPUnit_Framework_TestCase
{
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
        $this->assertFalse($this->object->isError());
    }
    
    public function testConnectFail()
    {
        $this->setExpectedException('Fwk\Db\Exceptions\ConnectionError');
        $this->object = new Connection(array(
            'driver'    => 'pdo_mysql',
            'host'  => 'inexistant.example.com-no',
            'user'  => 'testEUH',
            'autoConnect' => true
        ));
    }
    
    public function testAutoConnect()
    {
        $this->object = new Connection(array(
            'memory'    => true,
            'driver'    => 'pdo_sqlite',
            'autoConnect' => true
        ));
        $this->assertTrue($this->object->isConnected());
        // coverage
        $this->assertEquals(Connection::STATE_CONNECTED, $this->object->getState());
    }
    
    public function testConnectFailErrorState()
    {
        try {
            $this->object = new Connection(array(
                'driver' => 'pdo_mysql',
                'host'  => 'inexistant.example.com-no',
                'user'  => 'testEUH'
            ));
            $this->object->connect();
        } catch(\Exception $e) { }
        
        $this->assertTrue($this->object->isError());
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
        $tbl = $this->object->table('nonExistant');
    }
 
    public function testTable()
    {
        $this->prepareTestTable();
        $tbl = $this->object->table('test_table');
        $this->assertInstanceOf('Fwk\Db\Table', $tbl);
    }

    public function testNewQueryBridge()
    {
        $this->assertInstanceOf('\Fwk\Db\QueryBridge', $this->object->newQueryBrige());
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
    
    public function testTransaction()
    {
        // coverage
        $this->assertInstanceOf('Fwk\Db\Connection', $this->object->beginTransaction());
        $this->assertInstanceOf('Fwk\Db\Connection', $this->object->commit());
        $this->assertInstanceOf('Fwk\Db\Connection', $this->object->beginTransaction());
        $this->assertInstanceOf('Fwk\Db\Connection', $this->object->rollBack());
    }
    
    public function testEventStopQuery()
    {
        $this->object->on(BeforeQueryEvent::EVENT_NAME, function(BeforeQueryEvent $e) {
            $e->stop();
            $e->results = "test";
        });
        
        $query = Query::factory()->select()->from('fwkdb_test_users');
        $res = $this->object->execute($query);
        
        $this->assertEquals($res, "test"); 
    }
}
