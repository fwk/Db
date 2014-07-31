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
 * @category  Database
 * @package   Fwk\Db
 * @author    Julien Ballestracci <julien@nitronet.org>
 * @copyright 2011-2012 Julien Ballestracci <julien@nitronet.org>
 * @license   http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link      http://www.phpfwk.com
 */
namespace Fwk\Db\Relations;

use Fwk\Db\Events\AbstractEntityEvent;
use Fwk\Db\Events\BeforeSaveEvent;
use Fwk\Db\Events\BeforeUpdateEvent;
use Fwk\Db\Relation;
use Fwk\Db\Query;
use Fwk\Db\Accessor;
use Fwk\Db\Registry;
use Fwk\Db\Workers\SaveEntityWorker;
use Fwk\Db\Workers\DeleteEntityWorker;
use Fwk\Events\Dispatcher;

/**
 * Represents a One --> One database relation.
 *
 * @category Relations
 * @package  Fwk\Db
 * @author   Julien Ballestracci <julien@nitronet.org>
 * @license  http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link     http://www.phpfwk.com
 */
class One2One extends AbstractRelation implements Relation
{
    /**
     * Prepares a Query to fetch this relation (only FETCH_EAGER)
     *
     * @param Query  $query      The query
     * @param string $columnName Column name on the parent entity
     *
     * @return void
     */
    public function prepare(Query $query, $columnName)
    {
        if ($this->isLazy()) {
            return; 
        }

        $this->columnName = $columnName;
        $join = array(
            'column'    => $this->columnName,
            'relation'  => $this,
            'skipped'   => false,
            'reference' => null,
            'entity'    => $this->entity
        );

        $query->join(
            $this->tableName, 
            $this->local, 
            $this->foreign, 
            Query::JOIN_LEFT, 
            $join
        );
    }

    /**
     * Fetches (if necessary) relation's entities
     * 
     * @return One2One
     */
    public function fetch()
    {
        if (!$this->fetched && $this->isActive()) {
            $query = new Query();
            $query->entity($this->entity);

            $query->select()
                ->from($this->tableName, 'lazy')
                ->where('lazy.'. $this->foreign .'=?');

            $connect    = $this->getConnection();
            $res        = $connect->execute($query, array($this->parentRefs));
            $idKeys     = $connect->table($this->tableName)
                ->getIdentifiersKeys();

            if (count($res) >= 1) {
                $ids     = array();
                $access  = new Accessor($res[0]);
                foreach ($idKeys as $key) {
                    $ids[$key] = $access->get($key);
                }
                $this->add($res[0], $ids);
            }

            $this->setFetched(true);
        }

        return $this;
    }

    /**
     * Fetches and return the entity (or null)
     *
     * @return mixed
     */
    public function get()
    {
        $this->fetch();

        foreach ($this->getRegistry()->getStore() as $obj) {
            return $obj;
        }

        return null;
    }

    /**
     * Defines the $object as the One.
     * 
     * If $object is null, the relation is canceled on the parent object
     * 
     * @param mixed $object Entity
     * 
     * @return void 
     */
    public function set($object = null)
    {
        if (null === $object) {
            foreach ($this->getRegistry()->getStore() as $obj) {
                $this->remove($obj);
            }

            return;
        }

        $this->add($object);
    }

    /**
     * Magic method to allow $parent->relation->propName;
     * Returns value from the linked object.
     * 
     * @param string $key Property/Column name
     * 
     * @throws \RuntimeException if relation is empty
     * @return mixed 
     */
    public function __get($key)
    {
        $obj = $this->get();
        if (!\is_object($obj)) {
            throw new \RuntimeException('Empty relation');
        }
        
        return Accessor::factory($obj)->get($key);
    }

    /**
     * Magic method to allow $parent->relation->propName = "value"
     * 
     * @param string $key   Property/Column name
     * @param mixed  $value The value
     * 
     * @return void 
     */
    public function __set($key, $value)
    {
        $obj    = $this->get();
        if (!\is_object($obj)) {
            throw new \RuntimeException('Empty relation');
        }
        
        Accessor::factory($obj)->set($key, $value);
    }

    /**
     * Magic method to allow isset($parent->relation->propName)
     * 
     * @param string $key Property/Column name
     * 
     * @return boolean 
     */
    public function __isset($key)
    {
        $obj    = $this->fetch();
        if (!\is_object($obj)) {
            throw new \RuntimeException('Empty relation');
        }
        
        $test = Accessor::factory($obj)->get($key);

        return ($test != false ? true : false);
    }

    /**
     * Magic method to allow calling methods like $parent->relation->call()
     * 
     * @param string $name      Method name
     * @param array  $arguments Call arguments
     * 
     * @return mixed 
     */
    public function __call($name, $arguments)
    {
        $obj = $this->get();
        if (!\is_object($obj)) {
            throw new \RuntimeException('Empty relation');
        }
        
        $access = new Accessor($obj);
        try {
            $access->getReflector()->getMethod($name);

            return \call_user_func_array(array($obj, $name), $arguments);
        } catch (\ReflectionException $e) {
            throw new \RuntimeException(
                sprintf(
                    'Unable to call method %s::%s(): %s', 
                    get_class($obj), 
                    $name, 
                    $e->getMessage()
                )
            );
        }
    }

    /**
     * Defines a parent object (the other One) 
     * 
     * @param mixed      $object The parent object
     * @param Dispatcher $evd    The related Events Dispatcher
     * 
     * @return boolean
     */
    public function setParent($object, Dispatcher $evd)
    {
        $return = parent::setParent($object, $evd);
        if ($return === true) {
            $evd->on(BeforeSaveEvent::EVENT_NAME, array($this, 'onBeforeParentSave'));
            $evd->on(BeforeUpdateEvent::EVENT_NAME, array($this, 'onBeforeParentSave'));
        }

        return $return;
    }

    /**
     * Returns to-be-executed workers queue
     * 
     * @return \SplPriorityQueue
     */
    public function getWorkersQueue()
    {
        $queue  = new \SplPriorityQueue();

        foreach ($this->getRegistry()->getStore() as $object) {
            $data   = $this->getRegistry()->getData($object);
            $action = (($data['state'] == Registry::STATE_NEW || ($data['state'] == Registry::STATE_CHANGED && $data['action'] != Registry::ACTION_DELETE)) ? Registry::ACTION_SAVE : $data['action']);
            if (empty($data['action'])) {
                $this->getRegistry()->getChangedValues($object);
                $data = $this->getRegistry()->getData($object);
            }

            $ts = ($data['ts_action'] == null ? 
                    \microtime(true) : 
                    $data['ts_action']
                  );
            
            if (empty($action)) {
                continue;
            }

            $priority   = $ts;
            switch ($action) {
            case Registry::ACTION_DELETE:
                $worker = new DeleteEntityWorker($object);
                break;

            case Registry::ACTION_SAVE:
                $worker = new SaveEntityWorker($object);
                break;

            default:
                throw new \InvalidArgumentException(
                    sprintf("Unknown registry action '%s'", $action)
                );
            }

            if (isset($worker)) {
                $queue->insert($worker, $priority);
            }
        }

        return $queue;
    }

    /**
     * Listener executed when parent entity is saved
     *
     * @param AbstractEntityEvent $event Dispatched event
     * 
     * @return void
     */
    public function  onBeforeParentSave(AbstractEntityEvent $event)
    {
        $connection     = $event->getConnection();
        $parent         = $event->getEntity();

        foreach ($this->getWorkersQueue() as $worker) {
            $worker->setRegistry($this->registry);
            $entity     = $worker->getEntity();

            if ($worker instanceof SaveEntityWorker) {
                $worker->execute($connection);
                $current = Accessor::factory($entity)->get($this->foreign);
                Accessor::factory($parent)->set($this->local, $current);
                $this->getRegistry()->defineInitialValues($entity, $connection, $connection->table($this->tableName));
            }

            if ($worker instanceof DeleteEntityWorker) {
                Accessor::factory($parent)->set($this->local, null);
                parent::remove($entity);
            }
        }
    }

    /**
     * Returns an array of all entities in this relation
     *
     * @return array
     */
    public function toArray()
    {
        $this->fetch();

        $final = array();
        $list = $this->getRegistry()->getStore();
        foreach ($list as $object) {
            $data = $this->getRegistry()->getData($object);
            if($data['action'] == 'delete') {
                continue;
            }

            $final[] = $object;
        }

        return $final;
    }
}
