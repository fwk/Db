<?php
/**
 * Fwk
 *
 * Copyright (c) 2011-2012, Julien Ballestracci <julien@nitronet.org>.
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
 * @package    Fwk
 * @subpackage Db
 * @author     Julien Ballestracci <julien@nitronet.org>
 * @copyright  2011-2012 Julien Ballestracci <julien@nitronet.org>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link       http://www.phpfwk.com
 */
namespace Fwk\Db;

use Fwk\Db\Events\AbstractEntityEvent;
use Fwk\Db\Events\AfterSaveEvent;
use Fwk\Db\Events\FreshEvent;
use Fwk\Events\Dispatcher;
use Fwk\Events\Event;
use Fwk\Db\Workers\DeleteEntityWorker;
use Fwk\Db\Workers\SaveEntityWorker;

/**
 * Entity registry object
 *
 */
class Registry implements \Countable, \IteratorAggregate
{
    const STATE_NEW             = 'new';
    const STATE_FRESH           = 'fresh';
    const STATE_CHANGED         = 'changed';
    const STATE_UNKNOWN         = 'unknown';
    const STATE_UNREGISTERED    = 'unregistered';

    const ACTION_SAVE           = 'save';
    const ACTION_DELETE         = 'delete';

    /**
     * Storage object
     *
     * @var array
     */
    protected $store = array();

    /**
     * Storage data
     *
     * @var array
     */
    protected $datas = array();

    /**
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
     * @return void
     */
    public function __construct($tableName)
    {
        $this->tableName    = $tableName;
    }

    /**
     * Stores an object into registry
     *
     * @param mixed $object
     *
     * @return Registry
     */
    public function store($object, array $identifiers = array(), $state = Registry::STATE_UNKNOWN, array $data = array())
    {
        if ($this->contains($object)) {
            return $this;
        }

        \ksort($identifiers);

        if (!count($identifiers)) {
            $identifiers = array('%hash%' => Accessor::factory($object)->hashCode());
        }

        $data       = array_merge_recursive(array(
            'className'     => get_class($object),
            'identifiers'          => $identifiers,
            'state'                => $state,
            'initial_values'       => array(),
            'ts_stored'     => \microtime(true),
            'ts_action'     => 0,
            'action'        => null,
            'dispatcher'    => (isset($data['dispatcher']) ? $data['dispatcher'] : new Dispatcher()),
            'listeners'     => array()
        ), $data);

        $dispatcher = $data['dispatcher'];
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

        foreach ($data['listeners'] as $key => $listener) {
            if (is_object($listener) && !is_callable($listener)) {
                $dispatcher->addListener($listener);
            } elseif (is_callable($listener)) {
                $dispatcher->on($key, $listener);
            }
        }

        $idx = count($this->store);
        $this->store[$idx] = $object;
        $this->datas[$idx] = $data;

        return $this;
    }

    protected function getObjectStorageIndex($object)
    {
        foreach ($this->store as $idx => $obj) {
            if ($obj === $object) {
                return $idx;
            }
        }

        return false;
    }

    /**
     * Fetches an object
     *
     * @param  array $identifiers
     *
     * @return object|null
     */
    public function get(array $identifiers)
    {
        \ksort($identifiers);

        foreach ($this->datas as $idx => $infos) {
            if($infos['identifiers'] == $identifiers) {
                return $this->store[$idx];
            }
        }

        return null;
    }

    /**
     *
     * @return string
     */
    public function getTableName()
    {
        return $this->tableName;
    }

    /**
     *
     * @param  mixed $object
     * @return array
     */
    public function getData($object)
    {
        $idx = $this->getObjectStorageIndex($object);
        if ($idx === false) {
            throw new Exceptions\UnregisteredEntity(
                sprintf('Unregistered entity (%s)', \get_class($object))
            );
        }

        return $this->datas[$idx];
    }

    /**
     *
     * @param mixed $object
     * @param array $data
     *
     * @return Registry
     */
    public function setData($object, array $data = array())
    {
        $idx = $this->getObjectStorageIndex($object);
        if (false === $idx) {
            throw new Exceptions\UnregisteredEntity(
                sprintf('Unregistered entity (%s)', \get_class($object))
            );
        }

        $this->datas[$idx] = array_merge($this->datas[$idx], $data);

        return $this;
    }

    /**
     *
     * @param mixed $object
     * @param \Fwk\Events\Event $event
     */
    public function fireEvent($object, Event $event)
    {
        return $this->getEventDispatcher($object)->notify($event);
    }

    /**
     *
     * @return \Fwk\Events\Dispatcher
     */
    public function getEventDispatcher($object)
    {
        $idx = $this->getObjectStorageIndex($object);
        if ($idx === false) {
            throw new Exceptions\UnregisteredEntity(
                sprintf('Unregistered entity (%s)', \get_class($object))
            );
        }

        return $this->datas[$idx]['dispatcher'];
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

        foreach ($table->getColumns() as $column) {
            if(!$column->getAutoincrement())
                    continue;

            $columnName         = $column->getName();
            $access         = Accessor::factory($obj);

            $test           = $access->get($columnName);
            if(!empty($test))
                continue;

            $lastInsertId   = $connx->lastInsertId();
            $access->set($columnName, $lastInsertId);

            $data = $this->getData($obj);
            $data['identifiers'][$columnName] = $lastInsertId;
            $this->setData($obj, $data);
            $this->defineInitialValues($obj, $event->getConnection(), $table);
        }
    }

    /**
     * Removes an object from the registry
     *
     * @param  mixed    $object
     * @return Registry
     */
    public function remove($object)
    {
        $idx = $this->getObjectStorageIndex($object);
        if ($idx === false) {
            throw new Exceptions\UnregisteredEntity(
                sprintf('Unregistered entity (%s)', \get_class($object))
            );
        }

        unset($this->store[$idx]);
        unset($this->datas[$idx]);

        return $this;
    }

    /**
     * Removes an object from its identifiers
     *
     * @param array $identifiers
     * @return Registry
     */
    public function removeByIdentifiers(array $identifiers)
    {
        $obj = $this->get($identifiers);
        if (null !== $obj) {
            $this->remove($obj);
        }

        return $this;
    }

    /**
     * Tells if the registry contains an instance of the object
     *
     * @param mixed $object
     */
    public function contains($object)
    {
        return false !== $this->getObjectStorageIndex($object);
    }

    /**
     *
     * @param object $object
     *
     * @return string
     */
    public function getState($object)
    {
        $idx = $this->getObjectStorageIndex($object);
        if (false !== $idx) {
            return $this->datas[$idx]['state'];
        }

        return self::STATE_UNREGISTERED;
    }

    /**
     * Mark current object values (Accessor) as initial values
     *
     * @param <type> $object
     */
    public function defineInitialValues($object, Connection $connection = null, Table $table = null)
    {
        $accessor   = new Accessor($object);
        $data       = $this->getData($object);
        $values     = $accessor->toArray(array($accessor, 'everythingAsArrayModifier'));

        $data['initial_values'] = $values;
        $data['state']          = Registry::STATE_FRESH;
        $this->setData($object, $data);

        if ($connection !== null && $table !== null) {
            $data['dispatcher']->notify(new FreshEvent($connection, $table, $object));
        }
    }

    /**
     *
     * @param  <type> $object
     * @return <type>
     */
    public function getChangedValues($object)
    {
        if(!$this->contains($object)) {
            throw new Exceptions\UnregisteredEntity(
                sprintf('Unregistered entity (%s)', \get_class($object))
            );
        }

        $accessor   = new Accessor($object);
        $data       = $this->getData($object);
        $values     = $accessor->toArray(array($accessor, 'everythingAsArrayModifier'));

        $diff       = array();
        foreach ($values as $key => $val) {
            if(!isset($data['initial_values'][$key]) || $data['initial_values'][$key] !== $val) {
                $diff[$key] = $val;
            }
        }

        if (count($diff) && $data['state'] == self::STATE_FRESH) {
            $data['state'] = self::STATE_CHANGED;
            $this->setData($object, $data);
        }

        return $diff;
    }

    /**
     * Tells if an object has changed since "defineInitialValues" was called
     *
     * @param mixed $object
     */
    public function isChanged($object)
    {
        $this->getChangedValues($object);

        return ($this->getState($object) == self::STATE_CHANGED);
    }

    /**
     *
     * @param mixed $object
     */
    public function markForAction($object, $action, array $listeners = array())
    {
        $state  = $this->getState($object);
        if ($state == self::STATE_UNREGISTERED) {
            $this->store($object, array(), self::STATE_NEW, array('listeners' => $listeners));
        }

        $data   = $this->getData($object);
        $data['action']     = $action;
        $data['ts_action']  = $this->_priority;
        $this->_priority--;
        $this->setData($object, $data);
    }

    /**
     *
     * @return \SplPriorityQueue
     */
    public function getWorkersQueue()
    {
        $queue  = new \SplPriorityQueue();

        foreach ($this->store as $idx => $object) {
            $data   = $this->datas[$idx];
            $action = $data['action'];
            $ts     = $data['ts_action'];

            if(empty($action) || null === $ts) {
                continue;
            }

            $chg        = $this->getChangedValues($object);
            $access     = new Accessor($object);
            $relations  = $access->getRelations();
            foreach (array_keys($chg) as $key) {
                if (!\array_key_exists($key, $relations)) {
                    continue;
                }

                $relation   = $relations[$key];
                $relation->setParent($object, $this->getEventDispatcher($object));
            }

            $priority   = $ts;
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
            $queue->insert($worker, $priority);
        }

        return $queue;
    }

    public function toArray()
    {
        return $this->store;
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

    public function getStore()
    {
        return $this->store;
    }
}
