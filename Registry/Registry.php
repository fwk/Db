<?php
namespace Fwk\Db\Registry;

use Fwk\Db\Accessor;
use Fwk\Db\Connection;
use Fwk\Db\Events\AbstractEntityEvent;
use Fwk\Db\Events\AfterSaveEvent;
use Fwk\Db\Events\FreshEvent;
use Fwk\Db\EventSubscriber;
use Fwk\Db\Exception;
use Fwk\Db\Exceptions\UnregisteredEntity;
use Fwk\Db\Table;
use Fwk\Db\Workers\DeleteEntityWorker;
use Fwk\Db\Workers\SaveEntityWorker;
use Fwk\Events\Dispatcher;
use Fwk\Events\Event;
use \SplObjectStorage;

class Registry implements \Countable, \IteratorAggregate
{
    const ACTION_SAVE           = 'save';
    const ACTION_DELETE         = 'delete';

    /**
     * Storage handler
     *
     * @var SplObjectStorage
     */
    protected $store;

    /**
     * Table name
     *
     * @var string
     */
    protected $tableName;

    /**
     * @var integer
     */
    protected $_priority    = \PHP_INT_MAX;

    /**
     * Constructor
     *
     * @param string $tableName
     *
     * @return void
     */
    public function __construct($tableName)
    {
        $this->tableName    = $tableName;
        $this->store        = new SplObjectStorage();
    }

    /**
     * Stores an object into registry
     *
     * @param mixed $object
     *
     * @return Entry
     */
    public function store($object, array $identifiers = array(), $state = RegistryState::UNKNOWN, array $data = array())
    {
        if ($this->contains($object)) {
            return $this;
        }

        $entry = Entry::factory($object, $identifiers, $state, $data);

        $dispatcher = $entry->data('dispatcher', new Dispatcher());
        $listeners  = $entry->data('listeners', array());

        /**
         * @todo Put this one elsewhere
         */
        $dispatcher->on(AfterSaveEvent::EVENT_NAME, array($this, 'getLastInsertId'));

        $dispatcher->addListener($object);
        if ($object instanceof EventSubscriber) {
            foreach ($object->getListeners() as $key => $listener) {
                if (is_object($listener) && !is_callable($listener)) {
                    $dispatcher->addListener($listener);
                } elseif (is_callable($listener)) {
                    $dispatcher->on($key, $listener);
                }
            }
        }

        foreach ($listeners as $key => $listener) {
            if (is_object($listener) && !is_callable($listener)) {
                $dispatcher->addListener($listener);
            } elseif (is_callable($listener)) {
                $dispatcher->on($key, $listener);
            }
        }

        $this->store->attach($entry);

        return $entry;
    }

    /**
     * Fetches an object
     *
     * @param  array $identifiers
     *
     * @return object|null
     */
    public function get(array $identifiers, $entityClass = null)
    {
        foreach ($this->store as $entry) {
            if ($entry->match($identifiers, $entityClass)) {
                return $entry->getObject();
            }
        }

        return null;
    }

    /**
     * Returns the table name
     *
     * @return string
     */
    public function getTableName()
    {
        return $this->tableName;
    }


    /**
     *
     * @param mixed $object
     *
     * @return Entry
     */
    public function getEntry($object)
    {
        foreach ($this->store as $entry) {
            if ($entry->matchObject($object)) {
                return $entry;
            }
        }

        return false;
    }

    /**
     *
     * @return Entry
     */
    protected function getEntryByIdentifiers(array $identifiers, $className = null)
    {
        foreach ($this->store as $entry) {
            if ($entry->match($identifiers, $className)) {
                return $entry;
            }
        }

        return false;
    }

    /**
     * Returns an Event Dispatcher attached to a stored object
     *
     * @return Dispatcher
     * @throws UnregisteredEntity if the $object is not registered
     */
    public function getEventDispatcher($object)
    {
        $entry = $this->getEntry($object);
        if ($entry === false) {
            throw new UnregisteredEntity(sprintf('Unregistered entity (%s)', get_class($object)));
        }

        return $entry->data('dispatcher', new Dispatcher());
    }

    /**
     *
     * @param mixed $object
     * @param \Fwk\Events\Event $event
     *
     * @return void
     */
    public function fireEvent($object, Event $event)
    {
        $this->getEventDispatcher($object)->notify($event);
    }

    /**
     * Listener to fetch last insert ID on auto-increment columns
     *
     * @param AbstractEntityEvent $event
     *
     * @return void
     */
    public function getLastInsertId(AbstractEntityEvent $event)
    {
        $connx  = $event->getConnection();
        $table  = $connx->table($this->getTableName());
        $obj    = $event->getEntity();
        $entry  = $this->getEntry($obj);

        if (false === $entry) {
            return;
        }

        foreach ($table->getColumns() as $column) {
            if (!$column->getAutoincrement()) {
                continue;
            }

            $columnName     = $column->getName();
            $access         = Accessor::factory($obj);
            $test           = $access->get($columnName);

            if (!empty($test)) {
                continue;
            }

            $lastInsertId   = $connx->lastInsertId();
            $access->set($columnName, $lastInsertId);
            $ids = $entry->getIdentifiers();
            $ids[$columnName] = $lastInsertId;
            $entry->setIdentifiers($ids)->fresh();
        }
    }

    /**
     * Removes an object from the registry
     *
     * @param  mixed $object
     *
     * @return Registry
     * @throws UnregisteredEntity if the $object is not registered
     */
    public function remove($object)
    {
        $entry = $this->getEntry($object);
        if ($entry === false) {
            throw new UnregisteredEntity(sprintf('Unregistered entity (%s)', get_class($object)));
        }

        $this->store->detach($entry);
        unset($entry);

        return $this;
    }

    /**
     * Removes an object from its identifiers
     *
     * @param array $identifiers
     * @param string $className
     *
     * @return Registry
     */
    public function removeByIdentifiers(array $identifiers, $className = null)
    {
        $entry = $this->getEntryByIdentifiers($identifiers, $className);
        if (false !== $entry) {
            $this->store->detach($entry);
            unset($entry);
        }

        return $this;
    }

    /**
     * Tells if the registry contains an instance of the object
     *
     * @param mixed $object
     *
     * @return boolean
     */
    public function contains($object)
    {
        return false !== $this->getEntry($object);
    }

    /**
     *
     * @param object $object
     *
     * @return integer
     * @throws UnregisteredEntity
     */
    public function getState($object)
    {
        $entry = $this->getEntry($object);
        if ($entry === false) {
            return RegistryState::UNREGISTERED;
        }

        return $entry->getState();
    }

    /**
     *
     * @return array
     */
    public function toArray()
    {
        $arr = array();
        foreach ($this->store as $entry) {
            $arr[] = $entry->getObject();
        }

        return $arr;
    }

    /**
     *
     * @return integer
     */
    public function count()
    {
        return count($this->store);
    }

    /**
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->toArray());
    }


    public function markForAction($object, $action, array $listeners = array())
    {
        $entry  = $this->getEntry($object);
        if (false === $entry) {
            $entry = $this->store($object, array(), RegistryState::REGISTERED, array('listeners' => $listeners));
        }

        $entry->setAction($action, $this->_priority);
        $this->_priority--;
    }

    /**
     *
     * @return \SplPriorityQueue
     */
    public function getWorkersQueue()
    {
        $queue  = new \SplPriorityQueue();

        foreach ($this->store as $entry) {
            if (!$entry->hasAction()) {
                continue;
            }

            $action     = $entry->getAction();
            $object     = $entry->getObject();

            $access     = new Accessor($object);
            $relations  = $access->getRelations();
            foreach ($relations as $key => $relation) {
                $relation->setParent($object, $this->getEventDispatcher($object));
            }

            switch ($action) {
                case self::ACTION_DELETE:
                    $worker     = new DeleteEntityWorker($object);
                    break;

                case self::ACTION_SAVE:
                    $worker     = new SaveEntityWorker($object);
                    break;

                default:
                    throw new Exception(sprintf("Unknown registry action '%s'", $action));
            }

            $worker->setRegistry($this);
            $queue->insert($worker, $entry->getActionPriority());
        }

        return $queue;
    }

    /**
     * Mark current object values (Accessor) as initial values
     *
     * @param mixed $object
     *
     * @return void
     */
    public function defineInitialValues($object, Connection $connection = null, Table $table = null)
    {
        $entry = $this->getEntry($object);
        if (false === $entry) {
            throw new UnregisteredEntity(sprintf('Unregistered entity (%s)', get_class($object)));
        }

        $entry->fresh();

        if ($connection !== null && $table !== null) {
            $entry->data('dispatcher')->notify(new FreshEvent($connection, $table, $object));
        }
    }

    /**
     *
     * @param  mixed $object
     *
     * @return array
     */
    public function getChangedValues($object)
    {
        $entry = $this->getEntry($object);
        if (false === $entry) {
            throw new UnregisteredEntity(sprintf('Unregistered entity (%s)', get_class($object)));
        }

        return $entry->getChangedValues();
    }

    /**
     *
     */
    public function clear()
    {
        unset($this->store);
        $this->store        = new SplObjectStorage();
        $this->_priority    = \PHP_INT_MAX;

        return $this;
    }

    /**
     * @return SplObjectStorage
     */
    public function getStore()
    {
        return $this->store;
    }
}