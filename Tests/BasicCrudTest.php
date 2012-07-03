<?php

namespace Fwk\Db;


/**
 * Test class for Accessor.
 * Generated by PHPUnit on 2012-05-27 at 17:46:42.
 */
class BasicCrudTest extends \PHPUnit_Framework_TestCase {

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
    }

    protected function tearDown()
    {
        \FwkDbTestUtil::dropTestDb($this->connection);
    }

    /**
     */
    public function testInsert()
    {
        $this->assertEquals(0, count($this->connection->table('fwkdb_test_users')->finder()->all()));
        $query = Query::factory()
                    ->insert('fwkdb_test_users')
                    ->set('username', 'joeBar');
        
        $this->connection->execute($query);
        $this->assertEquals(1, count($this->connection->table('fwkdb_test_users')->finder()->all()));
        
        $query = Query::factory()
                    ->insert('fwkdb_test_users')
                    ->set('username', 'joeBar2');
        $this->connection->execute($query);
        $this->assertEquals(2, count($this->connection->table('fwkdb_test_users')->finder()->all()));
    }
    
    /**
     */
    public function testUpdate()
    {
        $query = Query::factory()
                    ->insert('fwkdb_test_users')
                    ->set('username', 'joeBar');
        
        $this->connection->execute($query);
        
        $one = $this->connection->table('fwkdb_test_users')->finder()->one(1);
        $this->assertNotNull($one);
        
        $query = Query::factory()
                ->update('fwkdb_test_users')
                ->set('username', '?')
                ->where('id = ?');
        
        $this->connection->execute($query, array('joeBarUPDATED', 1));
        
        $one = $this->connection->table('fwkdb_test_users')->finder()->one(1);
        $this->assertNotNull($one);
        $this->assertEquals("joeBarUPDATED", $one->username);
    }
    
    /**
     */
    public function testDelete()
    {
        $query = Query::factory()
                    ->insert('fwkdb_test_users')
                    ->set('username', 'joeBar');
        
        $this->connection->execute($query);
        
        $one = $this->connection->table('fwkdb_test_users')->finder()->one(1);
        $this->assertNotNull($one);
        
        $query = Query::factory()
                ->delete('fwkdb_test_users')
                ->where('id = ?');
        
        $this->connection->execute($query, array(1));
        
        $one = $this->connection->table('fwkdb_test_users')->finder()->one(1);
        $this->assertNull($one);
    }
}