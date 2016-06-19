<?php

namespace Fwk\Db;
use Fwk\Db\Events\AfterQueryEvent;
use Fwk\Db\Events\BeforeQueryEvent;
use Fwk\Db\Events\ConnectEvent;
use Fwk\Db\Events\ConnectionErrorEvent;
use Fwk\Db\Events\ConnectionStateChangeEvent;
use Fwk\Db\Events\DisconnectEvent;
use Fwk\Db\Exceptions\ConnectionErrorException;


class TestConnectionListener
{
    public $disconnected = true;
    public $hasErrored = false;
    public $states = 0;
    public $currentState;

    public $currentQuery;
    public $currentQueryParams;
    public $currentQueryOpts;
    public $currentQueryResults;

    public $queries = 0;

    public function onConnect(ConnectEvent $event)
    {
        $this->currentState = $event->getConnection()->getState();
        $this->disconnected = false;
    }

    public function onDisconnect(DisconnectEvent $event)
    {
        $this->disconnected = true;
    }

    public function onConnectionError(ConnectionErrorEvent $event)
    {
        $this->hasErrored = $event->getException();
        if($event->getConnection()->isConnected()) {
            $event->getConnection()->disconnect();
        }
    }

    public function onConnectionStateChange(ConnectionStateChangeEvent $event)
    {
        if ($event->getNewState() != $event->getPreviousState()) {
            $this->states++;
        }
        $this->currentState = $event->getConnection()->getState();
    }

    public function onBeforeQuery(BeforeQueryEvent $event)
    {
        $opts = $event->getQueryOptions();
        $event->setQueryOptions($opts + array('testopt' => true));
        $results = $event->getResults();

        if (!isset($this->currentQueryResults)) {
            $this->currentQuery = $event->getQuery();
            $this->currentQueryParams = $event->getQueryParameters();
            $this->currentQueryOpts = $event->getQueryOptions();

            $event->setResults(666);
            $event->stop();
            $this->currentQueryResults = true;
        } else {
            $event->setQuery(Query::factory());
            $event->setQueryParameters(array('driver_opt' => 'one'));
            $event->setResults(null);
        }
    }

    public function onAfterQuery(AfterQueryEvent $event)
    {
        $this->queries++;
        $this->currentQueryResults = $event->getResults();
        $this->currentState = $event->getConnection()->getState();
    }
}
/**
 * Test class for EventDispatcher.
 */
class ConnectionEventsTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Connection
     */
    protected $object;
    protected $listener;
    protected $listener2;

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

        $this->object->addListener($this->listener = new TestConnectionListener());

        \FwkDbTestUtil::createTestDb($this->object);
    }

    protected function tearDown()
    {
        try {
            \FwkDbTestUtil::dropTestDb($this->object);
        } catch(ConnectionErrorException $exp) {
        }
    }

    public function testConnectAndDisconnectEvents()
    {
        $this->assertFalse($this->listener->disconnected);
    }

    public function testConnectionErrorEvent()
    {
        $this->object = new Connection(array(
            'driver'    => 'pdo_mysql',
            'host'  => 'inexistant.example.com-no',
            'user'  => 'testEUH',
            'autoConnect' => false
        ));

        $this->object->addListener($this->listener = new TestConnectionListener());

        $this->assertFalse($this->object->isConnected());
        $this->assertFalse($this->listener->hasErrored);

        $this->setExpectedException('\Fwk\Db\Exceptions\ConnectionErrorException');
        $this->object->connect();

        $this->assertTrue(($this->listener->hasErrored instanceof Exception));
    }

    public function testConnectionStateChangeEvent()
    {
        $connx = new Connection(array(
            'driver'    => 'pdo_mysql',
            'host'  => 'inexistant.example.com-no',
            'user'  => 'testEUH',
            'autoConnect' => false
        ));

        $connx->addListener($this->listener2 = new TestConnectionListener());

        $this->assertEquals(0, $this->listener2->states);
        $this->setExpectedException('\Fwk\Db\Exceptions\ConnectionErrorException');
        $connx->connect();

        $this->assertEquals(2 /* connect + error */, $this->listener2->states);
    }

    public function testBeforeAndAfterQueryEvents()
    {
        $query = Query::factory()
            ->insert('fwkdb_test_users')
            ->set('username', 'joeBar');

        $this->object->execute($query);
        $this->assertEquals(0, $this->listener->queries); // should have been stopped

        $query2 = Query::factory()
            ->insert('fwkdb_test_users')
            ->set('username', 'joeBar');

        $this->object->execute($query2);
        $this->assertEquals(1, $this->listener->queries);
    }
}
