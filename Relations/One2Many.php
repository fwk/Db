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

use Fwk\Db\Events\AbstractEntityEvent;
use Fwk\Db\Events\AfterSaveEvent;
use Fwk\Db\Events\AfterUpdateEvent;
use Fwk\Db\Relation;
use Fwk\Db\Query;
use Fwk\Db\Accessor;
use Fwk\Db\Registry;
use Fwk\Db\Workers\SaveEntityWorker;
use Fwk\Db\Workers\DeleteEntityWorker;
use Fwk\Events\Dispatcher;

/**
 * One to Many Relation
 *
 * Represents a 1-n relation in the database
 *
 * @category Relations
 * @package  Fwk\Db
 * @author   Julien Ballestracci <julien@nitronet.org>
 * @license  http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link     http://www.nitronet.org/fwk
 */
class One2Many extends AbstractManyRelation implements Relation
{
    /**
     * Prepares a Query to fetch this relation (if FETCH_EAGER)
     *
     * @param Query  $query      The running Query
     * @param string $columnName The column name (property) for this relation
     *
     * @return void
     */
    public function prepare(Query $query, $columnName)
    {
        if ($this->isLazy()) {
            return;
        }

        $this->columnName   = $columnName;
        $join = array(
            'column' => $this->columnName,
            'relation' => $this,
            'skipped' => false,
            'reference' => $this->reference,
            'entity' => $this->entity
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
     * Fetches data from the database an fills this relation
     *
     * @return Relation
     */
    public function fetch()
    {
        if (!$this->fetched && $this->isActive()) {
            $query = new Query();
            $query->entity($this->getEntity())
                ->select()
                ->from($this->getTableName(), 'lazy')
                ->where('lazy.' . $this->getForeign() . '=?');

            if (isset($this->orderBy)) {
                $query->orderBy(
                    $this->orderBy['column'],
                    $this->orderBy['direction']
                );
            }

            $connect   = $this->getConnection();
            $res    = $connect->execute($query, array($this->parentRefs));
            $idKeys = $connect->table($this->getTableName())->getIdentifiersKeys();
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

    /**
     * Defines a parent entity for this relation
     *
     * @param object     $object The parent entity
     * @param Dispatcher $evd    The related events dispatcher
     *
     * @return boolean
     */
    public function setParent($object, Dispatcher $evd)
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
     * @param AbstractEntityEvent $event The EntityEvent
     *
     * @return void
     */
    public function onParentSave(AbstractEntityEvent $event)
    {
        $connection     = $event->getConnection();
        $parentRegistry = $event->getTable()->getRegistry();
        $table          = $connection->table($this->getTableName());
        $registry       = $table->getRegistry();

        foreach ($this->getWorkersQueue() as $worker) {
            $worker->setRegistry($registry);
            $entity     = $worker->getEntity();

            if ($worker instanceof SaveEntityWorker) {
                if (!isset($this->parent)) {
                    throw new \RuntimeException(
                        sprintf(
                            'No parent defined for entity %s',
                            get_class($entity)
                        )
                    );
                }

                $access = new Accessor($this->parent);
                $data   = $parentRegistry->getData($this->parent);
                $ids    = $data['identifiers'];
                if (!count($ids)) {
                    throw new \RuntimeException(
                        sprintf(
                            'Parent (%s) have no identifiers defined',
                            get_class($this->parent)
                        )
                    );
                }

                if (count($ids) > 1 && !array_key_exists($this->local, $ids)) {
                    throw new \RuntimeException(
                        sprintf(
                            'Local key "%s" is not a valid identifier (primary key on %s)',
                            $this->local,
                            $registry->getTableName()
                        )
                    );
                }

                $value  = $access->get($this->local);
                Accessor::factory($entity)->set($this->foreign, $value);
                $registry->markForAction($entity, Registry::ACTION_SAVE);
            }

            $worker->execute($connection);

            if ($worker instanceof DeleteEntityWorker) {
                parent::getRegistry()->remove($entity);
            }
        }
    }
}
