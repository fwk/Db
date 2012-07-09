<?php

namespace Fwk\Db;

/**
 * Test class for ResultSet.
 * Generated by PHPUnit on 2012-06-28 at 10:55:45.
 */
class ResultSetTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ResultSet
     */
    protected $object;

    /**
     * @todo Implement testFilter().
     */
    public function testFilter()
    {
        $std = new \stdClass;
        $std->test = "test";
        $std2 = clone $std;
        $std2->test = "coucou";

        $this->object = new ResultSet();
        $this->object[] = $std;
        $this->object[] = $std2;

        $res = $this->object->filter(function($o) { return ($o->test === "coucou"); });
        $this->assertInstanceOf(get_class($this->object), $res);
        $this->assertEquals(1, $res->count());
        $this->assertEquals($std2, $res[0]);
    }

    public function testOffsetExists()
    {
        $std = new \stdClass;
        $std->test = "test";
        $std2 = clone $std;
        $std2->test = "coucou";

        $this->object = new ResultSet();
        $this->object[1] = $std;
        $this->object['test'] = $std2;

        $this->assertTrue($this->object->offsetExists(1));
        $this->assertFalse($this->object->offsetExists('pwet'));
    }

    public function testConstructor()
    {
        $this->object = new ResultSet(array(
            'test' => new \stdClass,
            'newTest' => array('coucou' => 'test', 'prop' => 'value')
        ));
        
        $this->assertInstanceof('\stdClass', $this->object->offsetGet('newTest'));
        $val = $this->object->filter(function($elem) { return true; });
        $this->assertArrayHasKey('newTest', $val->toArray());
    }
    
    /**
     * @todo Implement testOffsetGet().
     */
    public function testOffsetGet()
    {
        $std = new \stdClass;
        $std->test = "test";

        $this->object = new ResultSet(array(
            'test' => $std
        ));

        $this->assertEquals($std, $this->object->offsetGet('test'));
        $this->assertNull($this->object->offsetGet('test-not'));
    }

    /**
     */
    public function testOffsetSet()
    {
        $std = new \stdClass;
        $std->test = "test";

        $this->object = new ResultSet();
        $this->object['test'] = $std;
        
        $new = clone $std;

        $this->assertEquals($std, $this->object->offsetGet('test'));
        $this->object->offsetSet('test', $new);
        $this->assertEquals($new, $this->object->offsetGet('test'));
    }

    /**
     * @todo Implement testOffsetUnset().
     */
    public function testOffsetUnset()
    {
        $std = new \stdClass;
        $std->test = "test";

        $this->object = new ResultSet();
        $this->object[] = $std;

        $this->assertEquals($std, $this->object->offsetGet(0));

        unset($this->object[0]);
        $this->assertNull($this->object->offsetGet(0));
    }

    /**
     * @todo Implement testToArray().
     */
    public function testToArray()
    {
        $std = new \stdClass;
        $std->test = "test";
        $std2 = clone $std;

        $this->object = new ResultSet();
        $this->object[] = $std;
        $this->object[] = $std2;

        $this->assertEquals(array(0 => $std, 1 => $std2), $this->object->toArray());
    }

    /**
     * @todo Implement testCount().
     */
    public function testCount()
    {
        $std = new \stdClass;
        $std->test = "test";
        $std2 = clone $std;

        $this->object = new ResultSet();

        $this->assertEquals(0, $this->object->count());
        $this->object[] = $std;
        $this->assertEquals(1, $this->object->count());
        $this->object[] = $std2;
        $this->assertEquals(2, $this->object->count());
    }

    /**
     * @todo Implement testGetIterator().
     */
    public function testHas()
    {
        $std = new \stdClass;
        $std->test = "test";
        $std2 = clone $std;

        $this->object = new ResultSet();

        $this->object[] = $std;
        $this->assertTrue($this->object->has($std));
        $this->assertFalse($this->object->has($std2));
    }

}
