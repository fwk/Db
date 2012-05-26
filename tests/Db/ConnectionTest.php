<?php

namespace Fwk\Db;

require_once __DIR__ .'/../../src/Db/Connection.php';
require_once __DIR__ .'/../../src/Db/Driver.php';
/**
 * Test class for EventDispatcher.
 */
class DispatcherTest extends \PHPUnit_Framework_TestCase {

    /**
     * @var EventDispatcher
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp() {
        $this->object = new Connection(array(
            ''
        ));
    }


    /**
     */
    public function testOn() {
        $this->assertTrue($this->object->on('test.event', array($this, 'eventFunction')));
    }

    // test function for event callback
    public function eventFunction($event) {
        $GLOBALS['testEvent'] = true;
    }
    
    /**
     */
    public function testRemoveListener() {
        $this->assertTrue($this->object->on('test.event', array($this, 'eventFunction')));
        
        $this->assertFalse($this->object->removeListener('test.event', array($this, 'nonExistantListener')));
        $this->assertTrue($this->object->removeListener('test.event', array($this, 'eventFunction')));
    }

    /**
     */
    public function testRemoveAllListeners() {
        $this->assertTrue($this->object->on('test.event', array($this, 'eventFunction')));

        $this->assertFalse($this->object->removeAllListeners('non.existent.event'));
        $this->assertTrue($this->object->removeAllListeners('test.event'));
    }

    /**
     */
    public function testNotify() {
        $this->assertFalse(isset($GLOBALS['testEvent']));
        $this->object->on('test.event', array($this, 'eventFunction'));
        
        $this->object->notify($event = new Event('test.event'));
        $this->assertTrue(isset($GLOBALS['testEvent']));
        $this->assertTrue($event->isProcessed());
    }

}
