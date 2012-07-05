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

use Fwk\Db\Relation,
    Fwk\Db\Query,
    Fwk\Db\Accessor,
    Fwk\Db\EntityEvents,
    Fwk\Db\Registry, 
    Fwk\Db\Workers\SaveEntityWorker, 
    Fwk\Db\Workers\DeleteEntityWorker;

class One2One extends AbstractRelation implements Relation
{
    /**
     * Prepares a Query to fetch this relation (only FETCH_EAGER)
     *
     * @param Query $query
     * 
     * @return void
     */
    public function prepare(Query $query, $columnName) {
        if ($this->isLazy())
            return;

        $this->columnName = $columnName;
        $join = array(
            'column' => $this->columnName,
            'relation' => $this,
            'skipped' => false,
            'reference' => null,
            'entity' => $this->entity
        );

        $query->join($this->tableName, $this->local, $this->foreign, Query::JOIN_LEFT, $join);
    }


    public function fetch()
    {
        if(!$this->fetched && $this->isActive()) {
            $query = new Query();
            $query->entity($this->entity);

            $query->select()
                  ->from($this->tableName, 'lazy')
                  ->where('lazy.'. $this->foreign .'=?');

            $connect    = $this->getConnection();
            $res        = $connect->execute($query, array($this->parentRefs));
            $idKeys     = $connect->table($this->tableName)->getIdentifiersKeys();

            if(count($res) >= 1) {
               $ids     = array();
               $access  = new Accessor($res[0]);
               foreach($idKeys as $key) {
                   $ids[$key]   = $access->get($key);
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
        
        foreach($this->getRegistry()->getStore() as $obj) {
            return $obj;
        }
        
        return null;
    }
    
    public function set($object = null)
    {
        if(null === $object) {
            foreach($this->getRegistry()->getStore() as $obj) {
                $this->remove($obj);
            }
            
            return;
        }
        
        $this->add($object);
    }
    
    public function __get($key) {
        $obj = $this->get();
        if(!\is_object($obj))
                throw new \RuntimeException (sprintf('Unable to retrieve "%s" parameter from relation (empty)', $key));
        
        return Accessor::factory($obj)->get($key);
    }

    public function __set($key, $value) {
        $obj    = $this->get();
        if(!\is_object($obj))
                throw new \RuntimeException (sprintf('Unable to set %s parameter on empty relation.', $key));

        return Accessor::factory($obj)->set($key, $value);
    }

    public function __isset($key) {
        $obj    = $this->fetch();
        if(!\is_object($obj))
                throw new \Exception (sprintf('Unable to retrieve %s parameter from relation (empty)', $key));

        $test       = Accessor::factory($obj)->get($key);
        return ($test != false ? true : false);
    }

    public function __call($name, $arguments) {
        $obj    = $this->get();
        if(!\is_object($obj))
                throw new \RuntimeException (sprintf('Unable to call function %s parameter on relation (empty)', $name));

        $access     = new Accessor($obj);
        try {
            $access->getReflector()->getMethod($name);

            return \call_user_func_array(array($obj, $name), $arguments);
        } catch(\ReflectionException $e) {
            throw new \RuntimeException(sprintf('Unable to call method %s::%s(): %s', get_class($obj), $name, $e->getMessage()));
        }
    }

    /**
     *
     * @param mixed $object
     * @param \Fwk\Events\Dispatcher $evd
     */
    public function setParent($object, \Fwk\Events\Dispatcher $evd) {
        $return     = parent::setParent($object, $evd);
        if($return === true) {
            $evd->on(EntityEvents::BEFORE_SAVE, array($this, 'onBeforeParentSave'));
            $evd->on(EntityEvents::BEFORE_UPDATE, array($this, 'onBeforeParentSave'));
        }

        return $return;
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
                $this->getRegistry()->getChangedValues($object);
                $data   = $this->getRegistry()->getData($object);
            }

            $ts     = ($data['ts_action'] == null ? \microtime(true) : $data['ts_action']);
            if(empty($action))
                continue;

            $priority   = $ts;
            switch($action) {
                case Registry::ACTION_DELETE:
                    $worker = new DeleteEntityWorker($object);
                    break;

                case Registry::ACTION_SAVE:
                    $worker = new SaveEntityWorker($object);
                    break;

                default:
                    throw new \InvalidArgumentException(sprintf("Unknown registry action '%s'", $action));

            }

            if(isset($worker))
                $queue->insert($worker, $priority);
        }

        return $queue;
    }


    /**
     * Listener executed when parent entity is saved
     *
     * @param \Fwk\Events\Event $event
     * @return void
     */
    public function  onBeforeParentSave(\Fwk\Events\Event $event) {
        $connection     = $event->connection;
        $parent         = $event->object;
        
        foreach($this->getWorkersQueue() as $worker) {
            $worker->setRegistry($this->registry);
            $entity     = $worker->getEntity();

            if($worker instanceof SaveEntityWorker) {
               $worker->execute($connection);
               
               $current = Accessor::factory($entity)->get($this->foreign);
               Accessor::factory($parent)->set($this->local, $current);
               $this->getRegistry()->defineInitialValues($entity);
            }

            if($worker instanceof DeleteEntityWorker) {
                Accessor::factory($parent)->set($this->local, null);
                parent::remove($entity);
            }
        }
    }
}

