<?php

namespace Fwk\Db;


class User extends \stdClass
{
    public $emails;
    
    public $metas;
    
    public $phone;
    
    public function __construct() {
        $this->emails = new Relations\Many2Many(
                'id', 
                'user_id', 
                'fwkdb_test_emails', 
                'fwkdb_test_users_emails', 
                'id', 
                'email_id', 
                'Fwk\Db\Email'
        );
        
        $this->metas = new Relations\One2Many(
                'id', 
                'user_id', 
                'fwkdb_test_users_metas'
        );
        $this->metas->setReference('name');
        
        $this->phone = new Relations\One2One('phone_id', 'id', 'fwkdb_test_phones');
    }
}

class Email extends \stdClass
{
}

/**
 * Test class for Accessor.
 * Generated by PHPUnit on 2012-05-27 at 17:46:42.
 */
class BasicEntityRelationsTest extends \PHPUnit_Framework_TestCase {

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
    }

    protected function tearDown()
    {
        \FwkDbTestUtil::dropTestDb($this->connection);
    }
    
    public function testM2MSave()
    {
        $u = new User;
        $u->username = "joebar";
        
        $e = new Email();
        $e->email = "joe@bar.com";
        $e->verified = 1;
        
        $this->assertEquals(0, count($this->connection->table('fwkdb_test_users')->finder()->all()));
        $this->assertEquals(0, count($this->connection->table('fwkdb_test_emails')->finder()->all()));
        
        $u->emails[] = $e;
        $this->connection->table('fwkdb_test_users')->save($u);
        
        $this->assertEquals(1, count($this->connection->table('fwkdb_test_users')->finder()->all()));
        $this->assertEquals(1, count($this->connection->table('fwkdb_test_emails')->finder()->all()));
        $this->assertEquals(1, count($this->connection->table('fwkdb_test_users_emails')->finder()->all()));
    }
    
    public function testM2MRemoveChild()
    {
        $u = new User;
        $u->username = "joebar";
        
        $e = new Email();
        $e->email = "joe@bar.com";
        $e->verified = 1;
        
        $u->emails[] = $e;
        $this->connection->table('fwkdb_test_users')->save($u);
        
        $user = $this->connection->table('fwkdb_test_users')->finder('Fwk\Db\User')->one(1);
        $this->assertNotNull($user);
        $this->assertInstanceOf('\Fwk\Db\Email', $user->emails[0]);
        
        unset($user->emails[0]);
        $this->connection->table('fwkdb_test_users')->save($user);
        
        $this->assertNull($user->emails[0]);
        $this->assertEquals(1, count($this->connection->table('fwkdb_test_emails')->finder()->all()));
        $this->assertEquals(0, count($this->connection->table('fwkdb_test_users_emails')->finder()->all()));
    }
    
    public function testO2MSave()
    {
        $u = new User;
        $u->username = "joebar";
        
        $m = new \stdClass;
        $m->name = "param";
        $m->value = "value";
        
        $u->metas[] = $m;
        
        $this->assertEquals($m, $u->metas['param']); // test the reference
        $this->assertEquals(0, count($this->connection->table('fwkdb_test_users')->finder()->all()));
        $this->assertEquals(0, count($this->connection->table('fwkdb_test_users_metas')->finder()->all()));
        
        $this->connection->table('fwkdb_test_users')->save($u);
        
        $this->assertEquals(1, count($this->connection->table('fwkdb_test_users')->finder()->all()));
        $this->assertEquals(1, count($this->connection->table('fwkdb_test_users_metas')->finder()->all()));
        $this->assertEquals($u->id, $u->metas['param']->user_id);
    }
    
    public function testO2MRemoveChild()
    {
        $u = new User;
        $u->username = "joebar";
        
        $m = new \stdClass;
        $m->name = "param";
        $m->value = "value";
        
        $u->metas[] = $m;
        $this->connection->table('fwkdb_test_users')->save($u);
        
        $user = $this->connection->table('fwkdb_test_users')->finder('Fwk\Db\User')->one(1);
        $this->assertNotNull($user);
        $this->assertInstanceOf('\stdClass', $user->metas['param']);
        
        unset($user->metas['param']);
        $this->connection->table('fwkdb_test_users')->save($user);
        
        $this->assertEquals(1, count($this->connection->table('fwkdb_test_users')->finder()->all()));
        $this->assertEquals(0, count($this->connection->table('fwkdb_test_users_metas')->finder()->all()));
        $this->assertNull($user->metas['param']);
    }
    
    public function testO2OSave()
    {
        $u = new User;
        $u->username = "joebar";
        
        $m = new \stdClass;
        $m->number = "0467010506";
        
        $u->phone->set($m);
        
        $this->assertEquals(0, count($this->connection->table('fwkdb_test_users')->finder()->all()));
        $this->assertEquals(0, count($this->connection->table('fwkdb_test_phones')->finder()->all()));
        
        $this->connection->table('fwkdb_test_users')->save($u);
        
        $this->assertEquals(1, count($this->connection->table('fwkdb_test_users')->finder()->all()));
        $this->assertEquals(1, count($this->connection->table('fwkdb_test_phones')->finder()->all()));
        $this->assertEquals($m->id, $u->phone_id);
    }
    
    public function testO2ORemoveChild()
    {
        $u = new User;
        $u->username = "joebar";
        
        $m = new \stdClass;
        $m->number = "0467010506";
        
        $u->phone->set($m);
        
        $this->connection->table('fwkdb_test_users')->save($u);
        
        $user = $this->connection->table('fwkdb_test_users')->finder('Fwk\Db\User')->one(1);
        $this->assertNotNull($user);
        $this->assertInstanceOf('\stdClass', $user->phone->get());
        
        $u->phone->set();
        $this->connection->table('fwkdb_test_users')->save($user);
        
        $this->assertEquals(1, count($this->connection->table('fwkdb_test_users')->finder()->all()));
        $this->assertEquals(1, count($this->connection->table('fwkdb_test_phones')->finder()->all()));
        $this->assertNull($user->phone_id);
    }
}