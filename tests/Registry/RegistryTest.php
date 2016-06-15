<?php

namespace Fwk\Db\Registry;
use Fwk\Db\Connection;
use Fwk\Db\Events\FreshEvent;
use Fwk\Db\Table;

/**
 * Test class for Registry.
 * Generated by PHPUnit on 2011-05-25 at 10:33:22.
 */
class RegistryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Registry
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->object = new Registry('test');
    }

    /**
     */
    public function testStore()
    {
        $obj = new \stdClass();
        $this->assertEquals(null, $this->object->get(array('keyOne' => 1, 'keyTwo' => 2)));
        $this->object->store($obj, array('keyOne' => 1, 'keyTwo' => 2));
        $this->assertEquals($obj, $this->object->get(array('keyOne' => 1, 'keyTwo' => 2)));
    }

    public function testStoreWithListener()
    {
        $obj = new \stdClass();
        $obj->listen = false;
        $this->object->store($obj, array('keyOne' => 1, 'keyTwo' => 2), RegistryState::REGISTERED, array('listeners' => array('fresh' => function(FreshEvent $event) {
            $event->getEntity()->listen = true;
        })));
        $this->assertFalse($obj->listen);
        $this->object->getEventDispatcher($obj)->notify(new FreshEvent(new Connection(), new Table('test'), $obj));
        $this->assertTrue($obj->listen);
    }

    /**
     */
    public function testGet()
    {
        $obj   = new \stdClass();
        $this->object->store($obj, array('keyOne' => 1, 'keyTwo' => 2));
        $this->assertEquals($obj, $this->object->get(array('keyOne' => 1, 'keyTwo' => 2)));
    }

    /**
     */
    public function testRemove()
    {
        $obj   = new \stdClass();
        $this->object->store($obj, array('keyOne' => 1, 'keyTwo' => 2));
        $this->assertEquals($obj, $this->object->get(array('keyOne' => 1, 'keyTwo' => 2)));

        $this->object->remove($obj);
        $this->assertEquals(null, $this->object->get(array('keyOne' => 1, 'keyTwo' => 2)));
    }

    /**
     */
    public function testRemoveByIdentifiers()
    {
        $obj   = new \stdClass();
        $this->object->store($obj, array('keyOne' => 1, 'keyTwo' => 2));
        $this->assertEquals($obj, $this->object->get(array('keyOne' => 1, 'keyTwo' => 2)));

        $this->object->removeByIdentifiers(array('keyOne' => 1, 'keyTwo' => 2));
        $this->assertEquals(null, $this->object->get(array('keyOne' => 1, 'keyTwo' => 2)));
    }

    public function testCount()
    {
        $obj = new \stdClass();
        $this->assertEquals(0, $this->object->count());
        $this->object->store($obj, array('keyOne' => 1, 'keyTwo' => 2));
        $this->assertEquals(1, $this->object->count());
    }


    public function testRemoveUnknownEntity()
    {
        $this->setExpectedException('Fwk\Db\Exceptions\UnregisteredEntityException');
        $this->object->remove(new \stdClass());
    }

    public function testChangedValuesUnknownEntity()
    {
        $this->setExpectedException('Fwk\Db\Exceptions\UnregisteredEntityException');
        $this->object->getChangedValues(new \stdClass());
    }

    public function testGetTableName()
    {
        $this->assertEquals('test', $this->object->getTableName());
    }

    public function testGetState()
    {
        $obj   = new \stdClass();
        $this->assertEquals(RegistryState::UNREGISTERED, $this->object->getState($obj));
        $this->object->store($obj, array('keyOne' => 1, 'keyTwo' => 2));
        $this->assertEquals(RegistryState::UNKNOWN, $this->object->getState($obj));
    }

    public function testIsChanged()
    {
        $obj   = new \stdClass();
        $obj->coucou    = "pwet";
        $obj->test      = "pfff";

        $this->object->store($obj, array('keyOne' => 1, 'keyTwo' => 2));
        $this->object->defineInitialValues($obj, null, null);
        $this->assertEquals(RegistryState::FRESH, $this->object->getState($obj));

        $this->assertFalse($this->object->getEntry($obj)->hasChanged());

        $obj->coucou    = "coucou";
        $this->assertTrue($this->object->getEntry($obj)->hasChanged());
        $this->assertEquals(RegistryState::CHANGED, $this->object->getState($obj));
    }

    public function testToArray()
    {
        $obj   = new \stdClass();
        $this->object->store($obj, array('keyOne' => 1, 'keyTwo' => 2));
        $this->assertEquals(array($obj), $this->object->toArray());
    }

    public function testGetIterator()
    {
        $this->assertInstanceOf('\ArrayIterator', $this->object->getIterator());
    }

    public function testGetEventDispatcher()
    {
        $obj   = new \stdClass();
        $this->object->store($obj, array('keyOne' => 1, 'keyTwo' => 2));
        $this->assertInstanceOf('\Fwk\Events\Dispatcher', $this->object->getEventDispatcher($obj));
    }

    public function testGetEventDispatcherFail()
    {
        $obj   = new \stdClass();
        $this->setExpectedException('\Fwk\Db\Exceptions\UnregisteredEntityException');
        $this->object->getEventDispatcher($obj);
    }

    public function testUnknownAction()
    {
        $obj = new \stdClass;
        $this->object->store($obj);
        $this->object->markForAction($obj, "UnknownAction");

        $this->setExpectedException('\Fwk\Db\Exception');
        $this->object->getWorkersQueue();
    }

    public function testStoreAlreadyStored()
    {
        $obj = new \stdClass();
        $this->object->store($obj);
        $this->assertTrue($this->object->contains($obj));
        $this->object->store($obj);
    }
}
