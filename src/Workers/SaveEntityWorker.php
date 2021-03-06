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
 * @category   Database
 * @package    Fwk\Db
 * @subpackage Workers
 * @author     Julien Ballestracci <julien@nitronet.org>
 * @copyright  2011-2012 Julien Ballestracci <julien@nitronet.org>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link       http://www.phpfwk.com
 */
namespace Fwk\Db\Workers;

use Fwk\Db\Events\AfterSaveEvent;
use Fwk\Db\Events\AfterUpdateEvent;
use Fwk\Db\Events\BeforeSaveEvent;
use Fwk\Db\Events\BeforeUpdateEvent;
use Fwk\Db\Exceptions\UnregisteredEntityException;
use Fwk\Db\Query;
use Fwk\Db\Registry\RegistryState;
use Fwk\Db\WorkerInterface;
use Fwk\Db\Accessor;
use Fwk\Db\Connection;

/**
 * Save Entity WorkerInterface
 * 
 * This worker is used when an entity or relation have to be inserted or 
 * updated.
 * 
 * @category Workers
 * @package  Fwk\Db
 * @author   Julien Ballestracci <julien@nitronet.org>
 * @license  http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link     http://www.phpfwk.com
 */
class SaveEntityWorker extends AbstractWorker implements WorkerInterface
{
    /**
     * Executes the worker (SQL queries) and fire EntityEvents
     * 
     * @param Connection $connection Database connection
     * 
     * @return void
     */
    public function execute(Connection $connection)
    {
        $registry   = $this->getRegistry();
        $entry      = $registry->getEntry($this->entity);
        if (false === $entry) {
            throw new UnregisteredEntityException('Unregistered entity: '. get_class($this->entity));
        }

        $state      = $entry->getState();
        $table      = $connection->table($registry->getTableName());
        $query      = Query::factory();
        $queryParams = array();
        $access     = new Accessor($this->entity);
        $exec       = true;
        $tableRegistry = $connection->table($registry->getTableName())->getRegistry();

        if (in_array($this->entity, self::$working, true)) {
            return;
        }

        if ($tableRegistry !== $registry && $tableRegistry->contains($this->entity)) {
            $state = $tableRegistry->getState($this->entity);
        }

        switch ($state) {
        case RegistryState::UNKNOWN:
            throw new \LogicException(sprintf('Entity is in unknown state (%s)', get_class($this->entity)));

        case RegistryState::REGISTERED:
            array_push(self::$working, $this->entity);
            foreach ($access->getRelations() as $relation) {
                $relation->setParent(
                    $this->entity,
                    $this->getRegistry()->getEventDispatcher($this->entity)
                );
            }

            $registry->fireEvent(
                $this->entity,
                $event = new BeforeSaveEvent($connection, $table, $this->entity)
            );

            if ($event->isStopped()) {
                return;
            }

            $query->insert($table->getName());
            $values     = $access->toArray();
            $columns    = $table->getColumns();
            $setted     = 0;

            foreach ($columns as $columnObj) {
                $default = $columnObj->getDefault();
                $key = $columnObj->getName();
                $value  = (array_key_exists($key, $values) ? $values[$key] : -1);

                if (-1 === $value) {
                    if (!empty($default) || true === $columnObj->getAutoincrement()
                        || $columnObj->getNotnull() === true
                    ) {
                        continue;
                    }
                }

                $query->set($key, '?');
                $queryParams[] = $value;
                $setted++;
            }

            if (!$setted) {
                $exec = false;
            }

            $event = new AfterSaveEvent($connection, $table, $this->entity);
            break;

        case RegistryState::FRESH:
        case RegistryState::CHANGED:
            array_push(self::$working, $this->entity);

            $registry->fireEvent(
                $this->entity,
                new BeforeUpdateEvent($connection, $table, $this->entity)
            );

            $query->update($table->getName())->where('1 = 1');

            // reload changed values in case the event changed some...
            $changed    = $registry->getChangedValues($this->entity);
            $ids        = $entry->getIdentifiers();
            $idKeys     = $table->getIdentifiersKeys();

            if (!count($ids)) {
                static::removeFromWorking($this->entity);
                throw new \LogicException(
                    sprintf('Entity %s lacks identifiers and cannot be saved.', get_class($this->entity))
                );
            }

            $setted     = 0;
            foreach ($changed as $key => $value) {
                if ($table->hasColumn($key)) {
                    $query->set($key, '?');
                    $queryParams[] = $value;
                    $setted++;
                }
            }

            if (!$setted) {
                $exec   = false;
            }

            foreach ($idKeys as $key) {
                $query->andWhere(sprintf('`%s` = ?', $key));
                $value = $access->get($key);
                if (!$value) {
                    static::removeFromWorking($this->entity);
                    throw new \RuntimeException(
                        sprintf(
                            'Cannot save entity object (%s) because it lacks '.
                            'identifier (%s)',
                            get_class($this->entity),
                            $key
                        )
                    );
                }

                $queryParams[] = $value;
            }

            $event = new AfterUpdateEvent($connection, $table, $this->entity);
            break;
        }

        if ($exec) {
            $connection->execute($query, $queryParams);
        }
        $registry->defineInitialValues($this->entity);
        if (isset($event)) {
            $registry->fireEvent($this->entity, $event);
        }
        static::removeFromWorking($this->entity);
    }
}
