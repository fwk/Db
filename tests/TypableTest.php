<?php
namespace Fwk\Db;


use Fwk\Db\Listeners\Typable;

class User4 extends \stdClass implements EventSubscriberInterface
{
    public $created_at;

    public $updated_at;

    /**
     * @return array
     */
    public function getListeners()
    {
        return array(
            new Typable()
        );
    }
}

/**
 * Test class for Accessor.
 * Generated by PHPUnit on 2012-05-27 at 17:46:42.
 */
class TypableTest extends \PHPUnit_Framework_TestCase
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
    }

    protected function tearDown()
    {
        \FwkDbTestUtil::dropTestDb($this->connection);
    }

    public function testTypedValues()
    {
        $obj = new User4();
        $obj->username = "joeBar";
        $obj->created_at = new \DateTime();

        $this->connection->table("fwkdb_test_users")->save($obj);

        $newInst = $this->connection->table("fwkdb_test_users")->finder('Fwk\Db\User4')->one(1);
        $this->assertNotEquals($obj, $newInst);
        $this->assertTrue(is_object($newInst));
        $this->assertTrue(($newInst->created_at instanceof \DateTime));
    }

    public function testUpdate()
    {
        $obj = new User4();
        $obj->username = "joeBarUpdated";
        $obj->created_at = new \DateTime();

        $this->connection->table("fwkdb_test_users")->save($obj);

        $obj->updated_at = new \DateTime();
        $this->connection->table("fwkdb_test_users")->save($obj);

        $newInst = $this->connection->table("fwkdb_test_users")->finder('Fwk\Db\User4')->one(1);
        $this->assertNotEquals($obj, $newInst);
        $this->assertTrue(is_object($newInst));
        $this->assertTrue(($newInst->updated_at instanceof \DateTime));
    }
}