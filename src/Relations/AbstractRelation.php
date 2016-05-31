<?php
/**
 * Fwk
 *
 * Copyright (c) 2011-2014, Julien Ballestracci <julien@nitronet.org>.
 * All rights reserved.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * PHP Version 5.3
 *
 * @category   Database
 * @package    Fwk
 * @subpackage Db
 * @author     Julien Ballestracci <julien@nitronet.org>
 * @copyright  2011-2014 Julien Ballestracci <julien@nitronet.org>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link       http://www.nitronet.org/fwk
 */
namespace Fwk\Db\Relations;

use Fwk\Db\Registry\RegistryState;
use Fwk\Db\Relation,
    Fwk\Db\Registry\Registry,
    Fwk\Db\Exception,
    Fwk\Db\Connection,
    Fwk\Events\Dispatcher,
    \IteratorAggregate;
use Fwk\Db\Workers\DeleteEntityWorker;
use Fwk\Db\Workers\SaveEntityWorker;

/**
 * Abstract utility class for Relations
 *
 * @category Relations
 * @package  Fwk\Db
 * @author   Julien Ballestracci <julien@nitronet.org>
 * @license  http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link     http://www.nitronet.org/fwk
 */
abstract class AbstractRelation implements IteratorAggregate
{
    /**
     * Local column name
     *
     * @var string
     */
    protected $local;

    /**
     * Foreign column name
     *
     * @var string
     */
    protected $foreign;

    /**
     * Fetch mode
     * {@see Relation::FETCH_LAZY} and {@see Relation::FETCH_EAGER}
     *
     * @var integer
     */
    protected $fetchMode = Relation::FETCH_LAZY;

    /**
     * Entity classname for this relation
     *
     * @var string
     */
    protected $entity;

    /**
     * Column name in parent entity for this relation
     *
     * @var string
     */
    protected $columnName;

    /**
     * Connection
     *
     * @var \Fwk\Db\Connection
     */
    protected $connection;

    /**
     * Is the relation fetched ?
     *
     * @var boolean
     */
    protected $fetched = false;

    /**
     * Parent references
     *
     * @var mixed
     */
    protected $parentRefs;

    /**
     * Parent entity (if any)
     *
     * @var mixed
     */
    protected $parent;

    /**
     * Relation's own registry
     *
     * @var Registry
     */
    protected $registry;

    /**
     * Referenced table name
     *
     * @var string
     */
    protected $tableName;

    /**
     * List of entity listeners
     *
     * @var array
     */
    protected $listeners = array();

    /**
     * Constructor
     *
     * @param string $local     The local column's name
     * @param string $foreign   The foreign column's name
     * @param string $table     The foreign table name
     * @param string $entity    The entity's class name
     * @param array  $listeners List of entity listeners
     *
     * @return void
     */
    public function __construct($local, $foreign, $table, $entity = null,
        array $listeners = array()
    ) {
        $this->tableName    = $table;
        $this->registry     = new Registry($table);
        $this->local        = $local;
        $this->foreign      = $foreign;
        $this->entity       = ($entity === null ? '\stdClass' : $entity);
        $this->listeners    = $listeners;
    }

    /**
     * FETCH_EAGER -or- FETCH_LAZY
     *
     * @param integer $mode The fetch mode (@see constants)
     *
     * @return Relation
     */
    public function setFetchMode($mode)
    {
        $this->fetchMode = $mode;

        return $this;
    }

    /**
     * Changes the fetched state of this relation
     *
     * @param boolean $bool Is the data fetched yet?
     *
     * @return Relation
     */
    public function setFetched($bool)
    {
        $this->fetched = (bool)$bool;
        if ($this->fetched === true) {
            $table  = $this->connection->table($this->tableName);
            $objs   = $this->getRegistry()->toArray();
            foreach ($objs as $object) {
                $this->getRegistry()->defineInitialValues(
                    $object,
                    $this->connection,
                    $table
                );
            }
        }

        return $this;
    }

     /**
     * Returns the connection defined for this relation
     *
     * @return Connection
     */
    public function getConnection()
    {
        if (!isset($this->connection)) {
            throw new Exception(
                sprintf(
                    'No connection defined for this relation (%s: %s<->%s)',
                    $this->tableName,
                    $this->local,
                    $this->foreign
                )
            );
        }

        return $this->connection;
    }

    /**
     * Sets a connection for this relation (used for lazy loading)
     *
     * @param Connection $connection The database connection instance
     *
     * @return Relation
     */
    public function setConnection(Connection $connection)
    {
        $this->connection   = $connection;

        return $this;
    }

    /**
     * Tells if an entity managed by this relation has changed
     *
     * @return boolean
     */
    public function hasChanged()
    {
        foreach ($this->getRegistry()->getStore() as $entry) {
            // trigger changedValues to ensure we have latest state
            $entry->getChangedValues();

            if (!$entry->isState(RegistryState::FRESH) || $entry->hasAction()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tells if this relation is active (parents references have been defined)
     *
     * @return boolean
     */
    public function isActive()
    {
        return isset($this->parentRefs);
    }

    /**
     * Tells if the specified object is in the relation
     *
     * @param object $object The object to test
     *
     * @return boolean
     */
    public function has($object)
    {
        return $this->getRegistry()->contains($object);
    }

    /**
     * Defines parent references
     *
     * @param array $refs Defines parent's references (eg. Primary Keys)
     *
     * @return Relation
     */
    public function setParentRefs($refs)
    {
        $this->parentRefs   = $refs;

        return $this;
    }

    /**
     * Sets the parent entity of this relation
     *
     * @param object     $object The parent entity
     * @param Dispatcher $evd    The Event Dispatcher for the parent entity.
     *
     * @return boolean true if parent has been changed/defined
     */
    public function setParent($object, Dispatcher $evd)
    {
        if ($this->parent === $object) {
            return false;
        }

        $this->parent = $object;

        return true;
    }

    /**
     * Tells if this relation has been fetched
     *
     * @return boolean
     */
    public function isFetched()
    {
        return $this->fetched;
    }

    /**
     * Tells if this relation is in LAZY fetch mode
     *
     * @return boolean
     */
    public function isLazy()
    {
        return ($this->fetchMode === Relation::FETCH_LAZY);
    }

    /**
     * Tells if this relation is in EAGER fetch mode
     *
     * @return boolean
     */
    public function isEager()
    {
        return ($this->fetchMode === Relation::FETCH_EAGER);
    }

    /**
     * Return the defined entity for this relation
     *
     * @return string
     */
    public function getEntity()
    {
        return $this->entity;
    }

    /**
     * Return the foreign column for this relation
     *
     * @return string
     */
    public function getForeign()
    {
        return $this->foreign;
    }

    /**
     * Return the local column for this relation
     *
     * @return string
     */
    public function getLocal()
    {
        return $this->local;
    }

    /**
     * Removes all objects
     *
     * @return Relation
     */
    public function clear()
    {
        $this->getRegistry()->clear();
        $this->fetched = false;

        return $this;
    }

    /**
     * Returns this relation's registry
     *
     * @return Registry
     */
    public function getRegistry()
    {
        return $this->registry;
    }

    /**
     * Defines a Registry for this relation
     *
     * @param Registry $registry The registry
     *
     * @return Relation
     */
    public function setRegistry(Registry $registry)
    {
        $this->registry = $registry;

        return $this;
    }

    /**
     * Return this relation's table name
     *
     * @return string
     */
    public function getTableName()
    {
        return $this->tableName;
    }

    /**
     * Add an entity to this relation
     *
     * @param object $object      The entity to add
     * @param array  $identifiers Identifiers (PK) of this entity if any
     *
     * @return Relation
     */
    public function add($object, array $identifiers = array())
    {
        if ($this->has($object)) {
            return $this;
        }

        $this->getRegistry()->store($object, $identifiers, RegistryState::REGISTERED);

        return $this;
    }

    /**
     * Removes an entity from this relation
     *
     * @param object $object The entity to be removed
     *
     * @return Relation
     */
    public function remove($object)
    {
        if ($this->has($object)) {
            $this->getRegistry()->markForAction($object, Registry::ACTION_DELETE);
        }

        return $this;
    }

    /**
     * Fetches data from database
     *
     * @return Relation
     */
    abstract public function fetch();

    /**
     * Returns a list of all entities in this relations.
     * Triggers a fetch() when fetchMode = FETCH_LAZY
     *
     * @return array
     */
    abstract public function toArray();

    /**
     * Return this relation data within an Iterator (foreach ...)
     * {@see \Traversable}
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        $this->fetch();

        return new \ArrayIterator($this->toArray());
    }

    /**
     * Returns all entity-listeners for this relation
     *
     * @return array
     */
    public function getListeners()
    {
        return $this->listeners;
    }

    /**
     * Returns to-be-executed workers queue
     *
     * @return \SplPriorityQueue
     */
    protected function getWorkersQueue()
    {
        $queue  = new \SplPriorityQueue();

        foreach ($this->getRegistry()->getStore() as $entry) {
            $entry->getChangedValues();
            $action = $entry->getAction();
            $state  = $entry->getState();

            if ($state === RegistryState::REGISTERED
                || ($state === RegistryState::CHANGED && $action !== Registry::ACTION_DELETE)
            ) {
                $action = Registry::ACTION_SAVE;
            }

            if ($action === Registry::ACTION_DELETE) {
                $queue->insert(new DeleteEntityWorker($entry->getObject()), $entry->getActionPriority());
            } elseif ($action === Registry::ACTION_SAVE) {
                $queue->insert(new SaveEntityWorker($entry->getObject()), $entry->getActionPriority());
            }
        }

        return $queue;
    }
}
