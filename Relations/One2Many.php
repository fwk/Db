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
 * @subpackage Relations
 * @author     Julien Ballestracci <julien@nitronet.org>
 * @copyright  2011-2012 Julien Ballestracci <julien@nitronet.org>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link       http://www.phpfwk.com
 */
namespace Fwk\Db\Relations;

use Fwk\Db\Relation;
use Fwk\Db\Query;
use Fwk\Db\Accessor;
use Fwk\Db\EntityEvents;
use Fwk\Db\Registry;

class One2Many extends AbstractRelation implements Relation, \ArrayAccess, \Countable, \IteratorAggregate {

    /**
     * @var string
     */
    protected $reference;
    
    /**
     * @var array
     */
    protected $orderBy;

    /**
     *
     * @var array
     */
    protected $removed;
    
    /**
     * Constructor
     * 
     * @param string $local
     * @param string $foreign
     * @param string $table
     * @param string $entity
     */
    public function  __construct($local, $foreign, $table, $entity = null) {
        parent::__construct($local, $foreign, $table, $entity);
    }
    
    /**
     * Prepares a Query to fetch this relation
     *
     * @param Query $query
     * @return void
     */
    public function prepare(Query $query, $columnName) {
        if ($this->isLazy())
            return;

        $this->columnName   = $columnName;
        $join = array(
            'column' => $this->columnName,
            'relation' => $this,
            'skipped' => false,
            'reference' => $this->reference,
            'entity' => $this->entity
        );

        $query->join($this->tableName, $this->local, $this->foreign, Query::JOIN_LEFT, $join);
    }

    /**
     * Sets a column to use as a reference
     * 
     * @param string $column
     * @return One2Many
     */
    public function setReference($column) {
        $this->reference = $column;

        return $this;
    }

    public function setOrderBy($column, $direction = true) {
        $this->orderBy = array('column' => $column, 'direction' => $direction);

        return $this;
    }

    public function fetch() {
        if (!$this->fetched && $this->isActive()) {
            $query = new Query();
            $query->entity($this->entity);

            $query->select()
                    ->from($this->tableName, 'lazy')
                    ->where('lazy.' . $this->foreign . '=?');

            if (isset($this->orderBy)) {
                $query->orderBy($this->orderBy['column'], $this->orderBy['direction']);
            }

            $connect    = $this->getConnection();
            $res        = $connect->execute($query, array($this->parentRefs));
            $idKeys     = $connect->table($this->tableName)->getIdentifiersKeys();
            
            foreach ($res as $result) {
               $ids     = array();
               $access  = new Accessor($result);
               foreach($idKeys as $key) {
                   $ids[$key]   = $access->get($key);
               }
               $this->add($result, $ids);
            }

            $this->setFetched(true);
        }

        return $this;
    }

    /**
     * Adds an object to the collection
     *
     * @param mixed $object
     */
    public function add($object, array $identifiers = array()) {
        if($this->contains($object))
                return;
        
        $data   = array(
            'reference' => null
        );

        if(!empty ($this->reference)) {
            $access             = new Accessor($object);
            $reference          = $access->get($this->reference);
            $data['reference']  = $reference;
        }

        $this->getRegistry()->store($object, $identifiers, Registry::STATE_NEW, $data);
    }

    
    /**
     *
     * @param mixed $object
     * @param \Fwk\Events\EventDispatcher $evd
     */
    public function setParent($object, \Fwk\Events\Dispatcher $evd) {
        $return     = parent::setParent($object, $evd);
        if($return === true) {
            $evd->on(EntityEvents::AFTER_SAVE, array($this, 'onParentSave'));
            $evd->on(EntityEvents::AFTER_UPDATE, array($this, 'onParentSave'));
        }

        return $return;
    }

    /**
     * Listener executed when parent entity is saved
     *
     * @param \Fwk\Events\Event $event
     * @return void
     */
    public function  onParentSave(\Fwk\Events\Event $event) {
        $connection     = $event->connection;
        $parent         = $event->object;
        $parentRegistry = $event->registry;
        $table          = $connection->table($this->getTableName());
        
        $registry       = $table->getRegistry();
        foreach($this->getWorkersQueue() as $worker) {
            $worker->setRegistry($registry);
            $entity     = $worker->getEntity();
            
            if($worker instanceof \Fwk\Db\Entity\Workers\SaveEntityWorker) {
                if(!isset($this->parent))
                        throw new \RuntimeException (sprintf('No parent defined for entity %s', get_class($entity)));

                $access = new Accessor($this->parent);
                $data   = $parentRegistry->getData($this->parent);

                $ids    = $data['identifiers'];
                if(!count($ids))
                    throw new \RuntimeException (sprintf('Parent (%s) have no identifiers defined', get_class($this->parent)));

                if(count($ids) > 1 && !\array_key_exists($this->local, $ids))
                        throw new \RuntimeException (sprintf('Local key "%s" is not a valid identifier (primary key on %s)', $this->local, $registry->getTableName()));

                $value  = $access->get($this->local);
                Accessor::factory($entity)->set($this->foreign, $value);
                $registry->markForAction($entity, Registry::ACTION_SAVE);
            }

            $worker->execute($connection);

            if($worker instanceof \Fwk\Db\Entity\Workers\DeleteEntityWorker) {
                parent::getRegistry()->remove($entity);
            }
        }
    }
    
    /**
     * Removes an object from the collection
     * 
     * @param mixed $object
     */
    public function remove($object) {
        if($this->contains($object)) {
            $this->getRegistry()->markForAction($object, Registry::ACTION_DELETE);
        }
    }

    public function getReference() {
        return $this->reference;
    }

    public function getOrderBy() {
        return $this->orderBy;
    }

    public function offsetExists($offset) {
        $this->fetch();
        $array  = $this->toArray();
        return \array_key_exists($offset, $array);
    }

    public function offsetGet($offset) {
        $this->fetch();
        $array  = $this->toArray();
        return (\array_key_exists($offset, $array) ? $array[$offset] : null);
    }

    public function offsetSet($offset, $value) {
        $this->fetch();
        return $this->add($value);
    }

    public function offsetUnset($offset) {
        $this->fetch();
        
        $obj    = $this->offsetGet($offset);
        if(null === $obj)
            return;

        return $this->remove($obj);
    }

    /**
     *
     * @return \SplPriorityQueue
     */
    public function getWorkersQueue() {
        $queue  = new \SplPriorityQueue();

        foreach($this->getRegistry()->getStore() as $object) {
            $data   = $this->getRegistry()->getData($object);
            $action = (($data['state'] == Registry::STATE_NEW || ($data['state'] == Registry::STATE_CHANGED && $data['action'] != Registry::ACTION_DELETE)) ? Registry::ACTION_SAVE : $data['action']);
            if(empty($data['action'])) {
                $chg    = $this->getRegistry()->getChangedValues($object);
                $data   = $this->getRegistry()->getData($object);
            }
            
            $ts     = ($data['ts_action'] == null ? \microtime(true) : $data['ts_action']);

            if(empty($action))
                continue;

            $priority   = $ts;
            switch($action) {
                case Registry::ACTION_DELETE:
                    $worker = new \Fwk\Db\Entity\Workers\DeleteEntityWorker($object);
                    break;

                case Registry::ACTION_SAVE:
                    $worker = new \Fwk\Db\Entity\Workers\SaveEntityWorker($object);
                    break;

                default:
                    throw new \InvalidArgumentException(sprintf("Unknown registry action '%s'", $action));

            }

            if(isset($worker))
                $queue->insert($worker, $priority);
        }

        return $queue;
    }

    public function count() {
        $this->fetch();

        return count($this->getRegistry()->getStore());
    }
    
    public function getIterator() {
        $this->fetch();
        
        return new \ArrayIterator($this->toArray());
    }
}

