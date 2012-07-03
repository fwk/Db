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
    Fwk\Db\Registry;

class Many2Many extends AbstractManyRelation implements Relation
{
    protected $foreignTable;
    
    protected $foreignRefs;
    
    protected $foreignLink;

    public function __construct($local, $foreign, $table, $foreignTable, 
        $foreignRefs, $foreignLink, $entity = null)
    {
        parent::__construct($local, $foreign, $table, $entity);

        $this->foreignTable = $foreignTable;
        $this->foreignRefs = $foreignRefs;
        $this->foreignLink = $foreignLink;
    }

    /**
     * Prepares a Query to fetch this relation (only FETCH_EAGER)
     *
     * @param Query $query
     * @return void
     */
    public function prepare(Query $query, $columName)
    {
        if ($this->isLazy()) {
            return;
        }
        
        $join1 = array(
            'column' => 'skipped_join',
            'relation' => false,
            'skipped' => true,
            'reference' => null,
            'entity' => null
        );

        $this->columnName   = $columName;
        $join = array(
            'column' => $this->columnName,
            'relation' => $this,
            'skipped' => false,
            'reference' => $this->reference,
            'entity' => $this->entity
        );

        srand();
        $skAlias = 'skj' . rand(100, 999);
        $jAlias = 'j' . rand(100, 999);
        
        $query->join($this->foreignTable . ' ' . $skAlias, $this->local, $this->foreign, Query::JOIN_LEFT, $join1);
        $query->join($this->tableName . ' ' . $jAlias, $jAlias . '.' . $this->foreignRefs, $skAlias . '.' . $this->foreignLink, Query::JOIN_LEFT, $join);
    }

    /**
     *
     * @param mixed $object
     * @param \Fwk\Events\Dispatcher $evd
     */
    public function setParent($object, \Fwk\Events\Dispatcher $evd)
    {
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
    public function  onParentSave(\Fwk\Events\Event $event)
    {
        $connection     = $event->connection;
        $parent         = $event->object;
        $parentRegistry = $event->registry;
        $table          = $connection->table($this->getTableName());

        $registry       = $table->getRegistry();
        foreach($this->getWorkersQueue() as $worker) {
            $worker->setRegistry($registry);
            $entity     = $worker->getEntity();
            $exec       = true;
            
            if($worker instanceof \Fwk\Db\Entity\Workers\DeleteEntityWorker) {
                 if(!isset($this->parent))
                        throw new \RuntimeException (sprintf('No parent defined for entity %s', get_class($entity)));

                $access = new Accessor($this->parent);
                $data   = $parentRegistry->getData($this->parent);
                $ids    = $data['identifiers'];
                if(!count($ids))
                    throw new \RuntimeException (sprintf('Parent (%s) have no identifiers defined', get_class($this->parent)));

                if(!\array_key_exists($this->local, $ids))
                        throw new \RuntimeException (sprintf('Local key "%s" is not a valid identifier (primary key on %s)', $this->local, $registry->getTableName()));

                $value  = $access->get($this->local);
                if(empty($value))
                    throw new \RuntimeException (sprintf('Identifier not set on %s (column: %s)', get_class($this->parent), $this->local));

                $query  = Query::factory()
                        ->delete($this->foreignTable)
                        ->where(sprintf('%s = ?', $this->foreign))
                        ->andWhere(sprintf('%s = ?', $this->foreignLink));

                $params[]   = $value;

                $acc        = Accessor::factory($entity)->get($this->local);
                $params[]   = $acc;

                if(empty($acc))
                    throw new \RuntimeException (sprintf('Identifier not set on %s (column: %s)', get_class($entity), $this->local));
                
                $connection->execute($query, $params);
                $this->getRegistry()->remove($entity);
                continue;
                
                $exec = false;
            }

            if($worker instanceof \Fwk\Db\Entity\Workers\SaveEntityWorker) {
                if(!isset($this->parent))
                        throw new \RuntimeException (sprintf('No parent defined for entity %s', get_class($entity)));

                $access = new Accessor($this->parent);
                $data   = $parentRegistry->getData($this->parent);
                $ids    = $data['identifiers'];
                if(!count($ids))
                    throw new \RuntimeException (sprintf('Parent (%s) have no identifiers defined', get_class($this->parent)));

                if(!\array_key_exists($this->local, $ids))
                        throw new \RuntimeException (sprintf('Local key "%s" is not a valid identifier (primary key on %s)', $this->local, $registry->getTableName()));

                $value  = $access->get($this->local);
                Accessor::factory($entity)->set($this->foreign, $value);
                $registry->markForAction($entity, Registry::ACTION_SAVE);
            }

            if($exec) {
                $worker->execute($connection);
                
                if($worker instanceof \Fwk\Db\Entity\Workers\SaveEntityWorker && $this->getRegistry()->getState($entity) == Registry::STATE_NEW) {
                    $query  = Query::factory()
                        ->insert($this->foreignTable);

                    $val = Accessor::factory($entity)->get($this->foreignRefs);

                    $query->set($this->foreign, $access->get($this->foreignRefs));
                    $query->set($this->foreignLink, \Fwk\Types\Accessor::factory($entity)->get($this->local));

                    $connection->execute($query, array());
                    $this->getRegistry()->defineInitialValues($entity);
                }
            }
        }
    }
    
    public function fetch()
    {
        if (!$this->fetched && $this->isActive()) {
            $params     = array();
            $query      = new Query();
            
            $query->entity($this->entity);
            $query->select()
                    ->from($this->tableName, 'lazy')
                    ->where('1 = 1');

            if (isset($this->orderBy)) {
                $query->orderBy($this->orderBy['column'], $this->orderBy['direction']);
            }

             $join1 = array(
                    'column'    => 'skipped_join',
                    'skipped'   => true,
             );

            $query->join($this->foreignTable .' j', 'lazy.'. $this->foreignRefs, $this->foreignLink, Query::JOIN_LEFT, $join1);
            $query->where('j.'. $this->foreign .'=?');
            $params[]   = $this->parentRefs;
            
            $connect    = $this->getConnection();
            $idKeys     = $connect->table($this->tableName)->getIdentifiersKeys();

            $res = $connect->execute($query, $params);
            foreach ($res as $result) {
                   $ids     = array();
                   $access  = new \Fwk\Types\Accessor($result);
                   foreach($idKeys as $key) {
                       $ids[$key]   = $access->get($key);
                   }
                   $this->add($result, $ids);
            }
            
            $this->setFetched(true);
        }

        return $this;
    }

    public function getForeignTable()
    {

        return $this->foreignTable;
    }

    public function getForeignLink()
    {

        return $this->foreignLink;
    }

    public function getForeignReference()
    {

        return $this->foreignRefs;
    }

    /**
     *
     * @return \SplPriorityQueue
     */
    public function getWorkersQueue()
    {
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
}