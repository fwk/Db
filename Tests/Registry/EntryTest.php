<?php

namespace Fwk\Db\Registry;
use MyProject\Proxies\__CG__\stdClass;


/**
 * Test class for Registry Entry.
 */
class EntryTest extends \PHPUnit_Framework_TestCase
{
    protected $object;

    protected function tearDown()
    {
        unset($this->object);
    }

    public function testEntryDataMethods()
    {
        $objArr = array(
            'id'    => 1,
            'test'  => 'testValue',
            'again' => 'anotherValue',
            'prop'  => 'propValue'
        );

        $obj = (object)$objArr;
        $this->object = new Entry($obj, array('id' => 1), RegistryState::REGISTERED, array(
           'constructorData'    => true
        ));

        $this->assertEquals(true, $this->object->data('constructorData'));
        $this->assertEquals(true, $this->object['constructorData']);
        $this->object->mergeData(array('constructorData' => 'overriden'));
        $this->assertEquals('overriden', $this->object->data('constructorData'));
        $this->assertFalse($this->object->data('myTest'));
        $this->object['myTest'] = 'testing';
        $this->assertEquals('testing', $this->object->data('myTest'));
        unset($this->object['myTest']);
        $this->assertFalse($this->object->data('myTest'));
        $this->assertNull($this->object->data('myTest', null));
    }

    public function testConstructorInvalidArgument()
    {
        $this->setExpectedException('InvalidArgumentException');
        $this->object = new Entry(array('id' => 1), array('id' => 1), RegistryState::REGISTERED, array(
            'constructorData'    => true
        ));
    }

    public function testStateChanges()
    {
        $objArr = array(
            'id'    => 1,
            'test'  => 'testValue',
            'again' => 'anotherValue',
            'prop'  => 'propValue'
        );

        $obj = (object)$objArr;
        $this->object = new Entry($obj, array('id' => 1));

        $this->assertTrue($this->object->isState(RegistryState::UNKNOWN));
        $this->object->fresh();
        $this->assertTrue($this->object->isState(RegistryState::FRESH));
    }

    public function testObjectHasChanges()
    {
        $objArr = array(
            'id'    => 1,
            'test'  => 'testValue',
            'again' => 'anotherValue',
            'prop'  => 'propValue'
        );

        $obj = (object)$objArr;
        $this->object = new Entry($obj, array('id' => 1), RegistryState::FRESH);

        $this->assertFalse($this->object->hasChanged());
        $obj->test = 'changedValue';
        $this->assertTrue($this->object->hasChanged());
        $this->assertEquals($obj, $this->object->getObject());
        $this->assertArrayHasKey('test', $this->object->getChangedValues());
    }

    public function testMatching()
    {
        $objArr = array(
            'id'    => 1,
            'test'  => 'testValue',
            'again' => 'anotherValue',
            'prop'  => 'propValue'
        );

        $obj = (object)$objArr;
        $this->object = new Entry($obj, array('id' => 1), RegistryState::FRESH);

        $this->assertFalse($this->object->match(array('id' => 2)));
        $this->assertFalse($this->object->match(array('id' => 1), "App\\Model\\Test")); // id = ok, class = ko
        $this->assertFalse($this->object->match(array('id' => 1, 'otherId' => 'nope'), "stdClass")); // id = ko, class = ok
        $this->assertTrue($this->object->match(array('id' => 1)));
        $this->assertTrue($this->object->match(array('id' => 1), 'stdClass'));
        $this->assertFalse($this->object->matchObject(new \stdClass()));
        $this->assertTrue($this->object->matchObject($obj));
    }
}