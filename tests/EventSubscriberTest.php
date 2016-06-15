<?php
namespace Fwk\Db;

use Fwk\Db\Events\FreshEvent;
use Fwk\Db\Listeners\Timestampable;
use Fwk\Db\Listeners\Typable;

class TestListener
{
    public function onFresh(FreshEvent $event)
    {
        $event->getEntity()->property = "listener!";
    }
}

class User2 extends \stdClass implements EventSubscriberInterface
{
    public $emails;

    public $metas;

    public $phone;

    public $property = null;
    public $callableProperty = null;

    public function __construct()
    {
        $this->emails = new Relations\Many2Many(
            'id',
            'user_id',
            'fwkdb_test_emails',
            'fwkdb_test_users_emails',
            'id',
            'email_id',
            'Fwk\Db\Email2'
        );

        $this->metas = new Relations\One2Many(
            'id',
            'user_id',
            'fwkdb_test_users_metas'
        );
        $this->metas->setReference('name');

        $this->phone = new Relations\One2One('phone_id', 'id', 'fwkdb_test_phones');
    }

    /**
     * @return array
     */
    public function getListeners()
    {
        return array(
            new TestListener(),
            'fresh' => array($this, 'listenerMethod')
        );
    }

    public function listenerMethod(FreshEvent $event)
    {
        $this->callableProperty = 'callableCalled!';
    }
}

class Email2 extends \stdClass
{
}

/**
 * Test class for Accessor.
 * Generated by PHPUnit on 2012-05-27 at 17:46:42.
 */
class EventSubscriberTest extends \PHPUnit_Framework_TestCase
{
    /**
     *
     * @var \Fwk\Db\Connection
     */
    protected $connection;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->connection = new Connection(array(
            'memory'    => true,
            'driver'    => 'pdo_sqlite'
        ));

        \FwkDbTestUtil::createTestDb($this->connection);

        $obj = new \stdClass;
        $obj->username = "joeBar";

        $obj2 = new \stdClass;
        $obj2->username = "joeBar4";
        $obj2->created_at = date('Y-m-d H:i:s');
        $this->connection->table("fwkdb_test_users")->save(array($obj, $obj2));
    }

    protected function tearDown()
    {
        \FwkDbTestUtil::dropTestDb($this->connection);
    }

    public function testListenerIsTriggered()
    {
        $obj = $this->connection->table("fwkdb_test_users")->finder()->setEntity('Fwk\Db\User2')->all();
        $this->assertEquals(2, count($obj));
        $this->assertEquals("listener!", $obj[0]->property);
    }

    public function testCallableListenerIsTriggered()
    {
        $obj = $this->connection->table("fwkdb_test_users")->finder()->setEntity('Fwk\Db\User2')->all();
        $this->assertEquals(2, count($obj));
        $this->assertEquals("callableCalled!", $obj[0]->callableProperty);
    }

    public function testCustomTableListeners()
    {
        $table = $this->connection->table("fwkdb_test_users");
        $table->setDefaultEntityListeners(array(new Timestampable()));

        $obj = new \stdClass;
        $obj->username = "joeBar2";

        $this->assertFalse(isset($obj->created_at));
        $this->connection->table("fwkdb_test_users")->save($obj);

        $this->assertTrue(isset($obj->created_at));
        $this->assertNotNull($obj->created_at);
    }

    public function testFinderListeners()
    {
        $table = $this->connection->table("fwkdb_test_users");

        $obj = $table->finder(null, array(new Typable()))->one(2);

        $this->assertTrue($obj instanceof \stdClass);
        $this->assertTrue(isset($obj->created_at));
        $this->assertNotNull($obj->created_at);
        $this->assertTrue(isset($obj->created_at));
    }
}