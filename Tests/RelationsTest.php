<?php

namespace Fwk\Db;

use Fwk\Db\Relations\Many2Many;
use Fwk\Db\Relations\One2Many;
use Fwk\Db\Relations\One2One;

class Commit
{
    protected $id;
    protected $hash;
    protected $repositoryId;
    protected $pushId;

    protected $authorName;
    protected $authorDate;
    protected $authorEmail;

    protected $authorId;

    protected $committerName;
    protected $committerDate;
    protected $committerEmail;

    protected $committerId;

    protected $message;

    protected $indexDate;

    protected $repository;
    protected $author;
    protected $committer;
    protected $references;
    protected $push;

    public function __construct()
    {
        $this->push = new One2One(
            'pushId',
            'id',
            'pushes',
            'Fwk\Db\Push'
        );

        $this->references = new Many2Many(
            'id',
            'commitId',
            'refs',
            'commits_refs',
            'id',
            'refId',
            'Fwk\Db\Reference'
        );
    }

    public function getId() {
        return $this->id;
    }

    public function setId($id) {
        $this->id = $id;
    }

    public function getHash() {
        return $this->hash;
    }

    public function setHash($hash) {
        $this->hash = $hash;
    }

    public function getRepositoryId() {
        return $this->repositoryId;
    }

    public function setRepositoryId($repositoryId) {
        $this->repositoryId = $repositoryId;
    }

    public function getPushId() {
        return $this->pushId;
    }

    public function setPushId($pushId) {
        $this->pushId = $pushId;
    }

    public function getAuthorName() {
        return $this->authorName;
    }

    public function setAuthorName($authorName) {
        $this->authorName = $authorName;
    }

    public function getAuthorDate() {
        return $this->authorDate;
    }

    public function setAuthorDate($authorDate) {
        $this->authorDate = $authorDate;
    }

    public function getAuthorEmail() {
        return $this->authorEmail;
    }

    public function setAuthorEmail($authorEmail) {
        $this->authorEmail = $authorEmail;
    }

    public function getAuthorId() {
        return $this->authorId;
    }

    public function setAuthorId($authorId) {
        $this->authorId = $authorId;
    }

    public function getCommitterName() {
        return $this->committerName;
    }

    public function setCommitterName($committerName) {
        $this->committerName = $committerName;
    }

    public function getCommitterDate() {
        return $this->committerDate;
    }

    public function setCommitterDate($committerDate) {
        $this->committerDate = $committerDate;
    }

    public function getCommitterEmail() {
        return $this->committerEmail;
    }

    public function setCommitterEmail($committerEmail) {
        $this->committerEmail = $committerEmail;
    }

    public function getCommitterId() {
        return $this->committerId;
    }

    public function setCommitterId($committerId) {
        $this->committerId = $committerId;
    }

    public function getMessage() {
        return $this->message;
    }

    public function setMessage($message) {
        $this->message = $message;
    }

    public function getIndexDate() {
        return $this->indexDate;
    }

    public function setIndexDate($indexDate) {
        $this->indexDate = $indexDate;
    }

    /**
     *
     * @return One2One
     */
    public function getRepository()
    {
        return $this->repository;
    }

    /**
     *
     * @return One2One
     */
    public function getAuthor()
    {
        return $this->author;
    }

    /**
     *
     * @return One2One
     */
    public function getCommitter()
    {
        return $this->committer;
    }

    /**
     *
     * @return One2One
     */
    public function getPush()
    {
        return $this->push;
    }

    /**
     *
     * @return Many2Many
     */
    public function getReferences()
    {
        return $this->references;
    }

    public function getCommitterDateObj()
    {
        return new \DateTime($this->committerDate);
    }

    public function getAuthorDateObj()
    {
        return new \DateTime($this->authorDate);
    }

    public function getComputedCommitterName()
    {
        if (empty($this->committerId)) {
            return (empty($this->committerName) ? $this->committerEmail : $this->committerName);
        }

        return $this->getCommitter()->get()->getFullname();
    }
}


class Reference
{
    protected $id;
    protected $name;
    protected $fullname;
    protected $repositoryId;
    protected $pushId;
    protected $createdOn;
    protected $commitHash;
    protected $type;

    protected $repository;
    protected $commits;
    protected $push;

    public function __construct()
    {
        $this->commits = new Many2Many(
            'id',
            'refId',
            'refs',
            'commits_refs',
            'id',
            'commitId',
            'Fwk\Db\Commit'
        );

        $this->push = new One2One(
            'pushId',
            'id',
            'pushes',
            'Fwk\Db\Push'
        );
    }

    public function getId() {
        return $this->id;
    }

    public function setId($id) {
        $this->id = $id;
    }

    public function getName() {
        return $this->name;
    }

    public function setName($name) {
        $this->name = $name;
    }

    public function getFullname()
    {
        return $this->fullname;
    }

    public function setFullname($fullname)
    {
        $this->fullname = $fullname;
    }

    public function getRepositoryId() {
        return $this->repositoryId;
    }

    public function setRepositoryId($repositoryId) {
        $this->repositoryId = $repositoryId;
    }

    public function getCreatedOn() {
        return $this->createdOn;
    }

    public function setCreatedOn($createdOn) {
        $this->createdOn = $createdOn;
    }

    public function getCommitHash() {
        return $this->commitHash;
    }

    public function setCommitHash($commitHash)
    {
        $this->commitHash = $commitHash;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    public function getPushId() {
        return $this->pushId;
    }

    public function setPushId($pushId) {
        $this->pushId = $pushId;
    }

    public function getRepository()
    {
        return $this->repository;
    }

    public function getCommits()
    {
        return $this->commits;
    }

    public function isBranch()
    {
        return $this->type === "branch";
    }

    /**
     * @return \Fwk\Db\Relations\One2One
     */
    public function getPush()
    {
        return $this->push;
    }
}


class Push
{
    protected $id;
    protected $userId;
    protected $username;
    protected $repositoryId;
    protected $createdOn;

    protected $repository;
    protected $author;
    protected $commits;
    protected $references;

    public function __construct()
    {
        $this->commits = new One2Many(
            'id',
            'pushId',
            'commits',
            'Fwk\Db\Commit'
        );

        $this->references = new One2Many(
            'id',
            'pushId',
            'refs',
            'Fwk\Db\Reference'
        );
    }

    public function getId() {
        return $this->id;
    }

    public function setId($id) {
        $this->id = $id;
    }

    public function getUserId() {
        return $this->userId;
    }

    public function setUserId($userId) {
        $this->userId = $userId;
    }

    public function getUsername() {
        return $this->username;
    }

    public function setUsername($username) {
        $this->username = $username;
    }

    public function getRepositoryId() {
        return $this->repositoryId;
    }

    public function setRepositoryId($repositoryId) {
        $this->repositoryId = $repositoryId;
    }

    public function getCreatedOn() {
        return $this->createdOn;
    }

    public function setCreatedOn($createdOn) {
        $this->createdOn = $createdOn;
    }

    /**
     *
     * @return One2One
     */
    public function getRepository()
    {
        return $this->repository;
    }

    /**
     *
     * @return One2One
     */
    public function getAuthor()
    {
        return $this->author;
    }

    /**
     *
     * @return One2Many
     */
    public function getCommits()
    {
        return $this->commits;
    }

    public function getReferences()
    {
        return $this->references;
    }
}

/**
 * Test class for Accessor.
 * Generated by PHPUnit on 2012-05-27 at 17:46:42.
 */
class RelationsTest extends \PHPUnit_Framework_TestCase
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

        $this->createGitDatabase($this->connection);
    }

    protected function tearDown()
    {
        \FwkDbTestUtil::dropTestDb($this->connection);
    }


    protected function createGitDatabase(Connection $connection)
    {
        $schema = $connection->getSchema();
        $tbl = $schema->createTable('pushes');
        $tbl->addColumn("id", "integer", array("unsigned" => true, "autoincrement" => true));
        $tbl->addColumn("userId", "integer", array("unsigned" => true, "notnull" => false));
        $tbl->addColumn("username", "string", array("length" => 255, "notnull" => false));
        $tbl->addColumn("repositoryId", "integer", array("unsigned" => true));
        $tbl->addColumn('createdOn', 'datetime', array('notnull' => false));
        $tbl->setPrimaryKey(array("id"));
        $tbl->addIndex(array("username"));
        $tbl->addIndex(array("repositoryId"));

        $tbl = $schema->createTable('refs');
        $tbl->addColumn("id", "integer", array("unsigned" => true, "autoincrement" => true));
        $tbl->addColumn("name", "string", array("length" => 255));
        $tbl->addColumn("type", "string", array("length" => 10));
        $tbl->addColumn("fullname", "string", array("length" => 255));
        $tbl->addColumn("repositoryId", "integer", array("unsigned" => true));
        $tbl->addColumn("pushId", "integer", array("unsigned" => true));
        $tbl->addColumn('createdOn', 'datetime', array('notnull' => false));
        $tbl->addColumn("commitHash", "string", array("length" => 40));
        $tbl->setPrimaryKey(array("id"));
        $tbl->addIndex(array("pushId"));
        $tbl->addIndex(array("repositoryId"));
        $tbl->addForeignKeyConstraint('pushes', array("pushId"), array("id"));

        $tbl = $schema->createTable('commits');
        $tbl->addColumn("id", "integer", array("unsigned" => true, "autoincrement" => true));
        $tbl->addColumn("hash", "string", array("length" => 40));
        $tbl->addColumn("repositoryId", "integer", array("unsigned" => true));
        $tbl->addColumn("pushId", "integer", array("unsigned" => true));
        $tbl->addColumn("authorName", "string", array("length" => 255, "notnull" => false));
        $tbl->addColumn("authorEmail", "string", array("length" => 255));
        $tbl->addColumn("authorDate", "datetime", array("length" => 255));
        $tbl->addColumn("authorId", "integer", array("unsigned" => true, "notnull" => false));
        $tbl->addColumn("committerName", "string", array("length" => 255, "notnull" => false));
        $tbl->addColumn("committerEmail", "string", array("length" => 255));
        $tbl->addColumn("committerDate", "datetime", array());
        $tbl->addColumn("committerId", "integer", array("unsigned" => true, "notnull" => false));
        $tbl->addColumn("message", "string", array());
        $tbl->addColumn("indexDate", "datetime");
        $tbl->setPrimaryKey(array("id"));
        $tbl->addUniqueIndex(array("hash", "repositoryId"));
        $tbl->addIndex(array("pushId"));
        $tbl->addIndex(array("repositoryId"));
        $tbl->addIndex(array("authorId"));
        $tbl->addIndex(array("committerId"));
        $tbl->addForeignKeyConstraint('pushes', array("pushId"), array("id"));

        $tbl = $schema->createTable('commits_refs');
        $tbl->addColumn("commitId", "integer", array("unsigned" => true));
        $tbl->addColumn("refId", "integer", array("unsigned" => true));
        $tbl->setPrimaryKey(array("commitId", "refId"));
        $tbl->addIndex(array("commitId"));
        $tbl->addIndex(array("refId"));
        $tbl->addForeignKeyConstraint('commits', array("commitId"), array("id"));
        $tbl->addForeignKeyConstraint('refs', array("refId"), array("id"));

        $connection->connect();
        $queries = $schema->toSql($connection->getDriver()->getDatabasePlatform());
        foreach ($queries as $query) {
            $connection->getDriver()->exec($query);
        }
    }

    public function testCreatePushWithCommits()
    {
        $this->assertEquals(0, count($this->connection->table('pushes')->finder()->all()));
        $this->assertEquals(0, count($this->connection->table('commits')->finder()->all()));
        $this->assertEquals(0, count($this->connection->table('refs')->finder()->all()));

        $push = new Push();
        $push->setCreatedOn(date('Y-m-d H:i:s'));
        $push->setRepositoryId(1);
        $push->setUserId(25);
        $push->setUsername("Luimeme");

        $ref = new Reference();
        $ref->setRepositoryId(1);
        $ref->setCreatedOn(date('Y-m-d H:i:s'));
        $ref->setName('master');
        $ref->setFullname('refs/origin/master');
        $ref->setType("branch");
        $ref->setCommitHash(sha1("testCommit1"));

        $push->getReferences()->add($ref);

        $commit = new Commit();
        $commit->setRepositoryId(1);
        $commit->setAuthorName("ducon");
        $commit->setAuthorDate(date('Y-m-d H:i:s'));
        $commit->setAuthorEmail("dummy@nitronet.dev");
        $commit->setAuthorId(null);
        $commit->setCommitterDate(date('Y-m-d H:i:s'));
        $commit->setCommitterName("Dutrou");
        $commit->setCommitterEmail("committer@nitronet.dev");
        $commit->setCommitterId(null);
        $commit->setMessage("test commit");
        $commit->setIndexDate(date('Y-m-d H:i:s'));
        $commit->getPush()->add($push);
        $commit->setHash(sha1("testCommit1"));

        $commit2 = new Commit();
        $commit2->setRepositoryId(1);
        $commit2->setAuthorName("ducon");
        $commit2->setAuthorDate(date('Y-m-d H:i:s'));
        $commit2->setAuthorEmail("dummy@nitronet.dev");
        $commit2->setAuthorId(null);
        $commit2->setCommitterDate(date('Y-m-d H:i:s'));
        $commit2->setCommitterName("Dutrou");
        $commit2->setCommitterEmail("committer@nitronet.dev");
        $commit2->setCommitterId(null);
        $commit2->setMessage("test commit 2");
        $commit2->setIndexDate(date('Y-m-d H:i:s'));
        $commit2->getPush()->add($push);
        $commit2->setHash(sha1("testCommit2"));

        $ref->getCommits()->add($commit);
        $ref->getCommits()->add($commit2);

        $this->connection->table('pushes')->save($push);

        $this->assertEquals(1, count($this->connection->table('pushes')->finder()->all()));
        $this->assertEquals(1, count($this->connection->table('refs')->finder()->all()));
        $this->assertEquals(2, count($this->connection->table('commits')->finder()->all()));
    }
}