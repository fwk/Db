<?php

namespace Fwk\Db;

class TestObj
{
    public $test;

    protected $protected;

    private $private;

    private $privWithoutGetter;

    private $privWithoutSetter;

    public function getProtected()
    {
        return $this->protected;
    }

    public function setProtected($protected)
    {
        $this->protected = $protected;
    }

    public function getPrivate()
    {
        return $this->private;
    }

    public function setPrivate($private)
    {
        $this->private = $private;
    }

    public function setPrivWithoutGetter($privWithoutGetter)
    {
        $this->privWithoutGetter = $privWithoutGetter;
    }
}

class AATestObj extends \ArrayObject
{
    public function __construct($array)
    {
        parent::__construct($array);
        $this->setFlags(\ArrayObject::ARRAY_AS_PROPS);
    }
}

/**
 * Test class for Accessor.
 * Generated by PHPUnit on 2012-05-27 at 17:46:42.
 */
class AccessorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Accessor
     */
    protected $object;

    /**
     * @var TestObj
     */
    protected $testable;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->testable = new TestObj();
        $this->testable->test = "testing";
        $this->testable->setProtected("protectedValue");
        $this->testable->setPrivate("privateValue");
        $this->testable->setPrivWithoutGetter("noGetter");

        $this->object = new Accessor($this->testable);
    }

    protected function tearDown()
    {
        unset($this->object);
    }

    /**
     */
    public function testGetAA()
    {
        $aaObj = new AATestObj(array(
            'set' => true,
            'test' => 'coucou'
        ));
        $this->object = new Accessor($aaObj);

        $this->assertEquals(true, $this->object->get('set'));
        $this->assertEquals("coucou", $this->object->get('test'));
    }

    /**
     */
    public function testSetAA()
    {
        $aaObj = new AATestObj(array(
            'set' => true,
            'test' => 'coucou'
        ));
        $this->object = new Accessor($aaObj);
        $this->assertTrue($this->object->set('testing', 'ok'));
    }

    /**
     */
    public function testGet()
    {
        $this->assertEquals("testing", $this->object->get('test'));
        $this->assertEquals("privateValue", $this->object->get('private'));
        $this->assertEquals("protectedValue", $this->object->get('protected'));

        $this->assertFalse($this->object->get('privWithoutGetter'));

        // test with override should fetch the value
        $this->object->overrideVisibility(true);
        $this->assertEquals("noGetter", $this->object->get('privWithoutGetter'));
    }

    /**
     */
    public function testSet()
    {
        $this->assertFalse($this->object->set('testing', 'nonExistant'));
        $this->assertTrue($this->object->set('test', "testeuh"));
        $this->assertTrue($this->object->set('private', 'priv'));
        $this->assertTrue($this->object->set('protected', 'prot'));
        $this->assertFalse($this->object->set('privWithoutSetter', 'shouldFail'));

        // test with override should set the value
        $this->object->overrideVisibility(true);
        $this->assertTrue($this->object->set('privWithoutSetter', "newValue"));
    }

    /**
     */
    public function testGetReflector()
    {
        $this->assertInstanceOf('\ReflectionObject', $this->object->getReflector());
    }

    /**
     */
    public function testToArrayAndSetValues()
    {
        $values = array(
            'test'  => "testeuh",
            "private"   => "priv",
            "protected" => "prot",
            "privWithoutGetter" => "coucou"
        );

        $this->object->setValues($values);

        $result = array(
            "test"  => "testeuh",
            "private" =>  "priv",
            "protected" => "prot",
            "privWithoutGetter" => false,
            "privWithoutSetter" => false
        );

        $this->assertEquals($result, $this->object->toArray());

        $this->object->overrideVisibility(true);
        $result['privWithoutGetter'] = "coucou";

        $this->assertEquals($result, $this->object->toArray());
    }

    /**
     */
    public function testHashCode()
    {
        $obj = new \stdClass;
        $obj->test = "pwet";

        $firstHash = $this->object->hashCode();
        $this->assertEquals($firstHash,$this->object->hashCode());
        $values = array(
            'test'  => "testeuh",
            "private"   => array('coucou' => 'pwet'),
            "protected" => "prot",
            "privWithoutGetter" => $obj
        );

        $this->object->setValues($values);
        $this->assertNotEquals($firstHash,$this->object->hashCode());
    }

    /**
     */
    public function testFactory()
    {
        $this->assertEquals($this->object, Accessor::factory($this->testable));
        $this->setExpectedException('\InvalidArgumentException');
        Accessor::factory('stringArgumentIsInvalid');
    }
}
