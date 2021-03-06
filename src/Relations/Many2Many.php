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

use Fwk\Db\Events\AbstractEntityEvent;
use Fwk\Db\Events\AfterSaveEvent;
use Fwk\Db\Events\AfterUpdateEvent;
use Fwk\Db\RelationInterface;
use Fwk\Db\Query;
use Fwk\Db\Accessor;
use Fwk\Db\Registry\Registry;
use Fwk\Db\Registry\RegistryState;
use Fwk\Db\Workers\SaveEntityWorker;
use Fwk\Db\Workers\DeleteEntityWorker;

class Many2Many extends AbstractManyRelation implements RelationInterface
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
     * @param  Query $query
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
     * @param mixed                  $object
     * @param \Fwk\Events\Dispatcher $evd
     */
    public function setParent($object, \Fwk\Events\Dispatcher $evd)
    {
        $return     = parent::setParent($object, $evd);
        if ($return === true) {
            $evd->on(AfterSaveEvent::EVENT_NAME, array($this, 'onParentSave'));
            $evd->on(AfterUpdateEvent::EVENT_NAME, array($this, 'onParentSave'));
        }

        return $return;
    }

    /**
     * Listener executed when parent entity is saved
     *
     * @param AbstractEntityEvent $event
     * @return void
     */
    public function  onParentSave(AbstractEntityEvent $event)
    {
        $connection     = $event->getConnection();
        $parentRegistry = $event->getTable()->getRegistry();
        $table          = $connection->table($this->getTableName());
        $registry       = $table->getRegistry();

        foreach ($this->getWorkersQueue() as $worker) {
            $worker->setRegistry($registry);
            $entity     = $worker->getEntity();
            $access     = new Accessor($this->parent);

            $entry      = $parentRegistry->getEntry($this->parent);
            $ids        = $entry->getIdentifiers();

            if (!count($ids)) {
                throw new \RuntimeException (sprintf('Parent (%s) have no identifiers defined', get_class($this->parent)));
            }

            if (!array_key_exists($this->local, $ids)) {
                throw new \RuntimeException (sprintf('Local key "%s" is not a valid identifier (primary key on %s)', $this->local, $registry->getTableName()));
            }

            if ($worker instanceof DeleteEntityWorker) {
                $value  = $access->get($this->local);
                if (empty($value)) {
                    throw new \RuntimeException (sprintf('Identifier not set on %s (column: %s)', get_class($this->parent), $this->local));
                }

                $params[]   = $value;
                $acc        = Accessor::factory($entity)->get($this->local);
                $params[]   = $acc;

                if (empty($acc)) {
                    throw new \RuntimeException (sprintf('Identifier not set on %s (column: %s)', get_class($entity), $this->local));
                }

                $connection->execute(
                    Query::factory()
                        ->delete($this->foreignTable)
                        ->where(sprintf('%s = ?', $this->foreign))
                        ->andWhere(sprintf('%s = ?', $this->foreignLink)),
                    $params
                );

                $this->getRegistry()->remove($entity);
                continue;
            }

            if ($worker instanceof SaveEntityWorker) {
                $value  = $access->get($this->local);
                Accessor::factory($entity)->set($this->foreign, $value);
                $registry->markForAction($entity, Registry::ACTION_SAVE);
            }

            $worker->execute($connection);

            if ($worker instanceof SaveEntityWorker && $this->getRegistry()->getState($entity) == RegistryState::REGISTERED) {
                $connection->execute(
                    Query::factory()
                        ->insert($this->foreignTable)
                        ->set($this->foreign, '?')
                        ->set($this->foreignLink, '?'),
                    array($access->get($this->local), Accessor::factory($entity)->get($this->foreignRefs))
                );
                $this->getRegistry()->defineInitialValues($entity, $connection, $table);
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

            $query->join($this->getForeignTable() .' j', 'lazy.'. $this->getForeignReference(), $this->getForeignLink(), Query::JOIN_LEFT, $join1);
            $query->where('j.'. $this->foreign .'=?');
            $params[]   = $this->parentRefs;

            $connect    = $this->getConnection();
            $idKeys     = $connect->table($this->tableName)->getIdentifiersKeys();

            $res = $connect->execute($query, $params);
            foreach ($res as $result) {
                   $ids     = array();
                   $access  = new Accessor($result);
                   foreach ($idKeys as $key) {
                       $ids[$key]   = $access->get($key);
                   }
                   parent::add($result, $ids);
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
}