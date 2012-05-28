<?php

namespace Fwk\Db;

/**
 */
class QueryTest extends \PHPUnit_Framework_TestCase {

    /**
     * @var Query
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp() {
        $this->object = new Query;
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown() {
        unset($this->object);
    }

    /**
     */
    public function testSelect() {
        $this->object->select('column1, column2');
        $this->assertEquals('column1, column2', $this->object['select']);
        $this->assertEquals(Query::TYPE_SELECT, $this->object->getType());
    }

    /**
     */
    public function testFrom() {
        $this->object->from('table alias');
        $this->assertEquals('table alias', $this->object['from']);
        $this->object->from('table', 'alias');
        $this->assertEquals('table alias', $this->object['from']);
    }

    /**
     */
    public function testFetchMode() {
        $this->object->setFetchMode(\PDO::FETCH_ASSOC);
        $this->assertEquals(\PDO::FETCH_ASSOC, $this->object->getFetchMode());
    }

    /**
     */
    public function testDelete() {
        $this->object->delete('table');
        $this->assertEquals('table', $this->object['delete']);
        $this->assertEquals(Query::TYPE_DELETE, $this->object->getType());
    }

    /**
     */
    public function testInsert() {
        $this->object->insert('table');
        $this->assertEquals('table', $this->object['insert']);
        $this->assertEquals(Query::TYPE_INSERT, $this->object->getType());
    }

    /**
     */
    public function testUpdate() {
        $this->object->update('table');
        $this->assertEquals('table', $this->object['update']);
        $this->assertEquals(Query::TYPE_UPDATE, $this->object->getType());
    }

    /**
     */
    public function testWhere() {
        $this->object->where('condition = 1');
        $this->assertEquals('condition = 1', $this->object['where']);
    }

    /**
     */
    public function testAndWhere() {
        $this->assertEquals(null, $this->object['wheres']);
        $this->object->andWhere('condition = 1');
        $this->assertTrue(is_array($this->object['wheres']));
        $this->assertEquals(1, count($this->object['wheres']));
    }

    /**
     */
    public function testOrWhere() {
        $this->assertEquals(null, $this->object['wheres']);
        $this->object->orWhere('condition = 1');
        $this->assertTrue(is_array($this->object['wheres']));
        $this->assertEquals(1, count($this->object['wheres']));
    }

    /**
     */
    public function testLimit() {
        $this->object->limit(100);
        $this->assertEquals(100, $this->object['limit']);
    }

    /**
     */
    public function testGroupBy() {
        $this->object->groupBy('field');
        $this->assertEquals('field', $this->object['groupBy']);
    }

    /**
     */
    public function testOrderBy() {
        $this->object->orderBy('column', true);
        $this->assertTrue(is_array($this->object['orderBy']));
        $this->assertEquals(array(
            'column' => 'column',
            'order' => true
        ), $this->object['orderBy']);
    }

    /**
     */
    public function testFactory() {
        $this->assertInstanceOf('\Fwk\Db\Query', Query::factory());
    }

    /**
     */
    public function testSet() {
        $this->assertNull($this->object['values']);
        $this->object->set('key', 'value');
        $this->assertTrue(is_array($this->object['values']));
        $this->assertEquals(1, count($this->object['values']));
        $this->object->set('key2', 'value');
        $this->assertEquals(2, count($this->object['values']));
    }

    /**
     */
    public function testValues() {
        $this->assertNull($this->object['values']);
        $this->object->values(array('key' => 'value', 'key2' => 'value'));
        $this->assertTrue(is_array($this->object['values']));
        $this->assertEquals(2, count($this->object['values']));
        $this->object->set('key3', 'value');
        $this->assertEquals(3, count($this->object['values']));
    }

    /**
     */
    public function testJoin() {
        $this->assertNull($this->object['joins']);
        $this->object->join('joinTable', 'id');
        $this->assertTrue(is_array($this->object['joins']));
        $this->assertEquals(1, count($this->object['joins']));
    }

    /**
     */
    public function testEntity() {
        $this->object->entity('\stdClass');
        $this->assertEquals('\stdClass', $this->object['entity']);
    }
}