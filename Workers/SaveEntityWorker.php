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
use Fwk\Db\Registry,
    Fwk\Db\Worker,
    Fwk\Db\Accessor,
    Fwk\Db\Connection;

/**
 * Save Entity Worker
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
class SaveEntityWorker extends AbstractWorker implements Worker
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
        $data       = $registry->getData($this->entity);
        $state      = $data['state'];
        $table      = $connection->table($registry->getTableName());

        $query      = \Fwk\Db\Query::factory();
        $queryParams = array();
        $access     = new Accessor($this->entity);
        $exec       = true;

        switch ($state) {
        case Registry::STATE_UNKNOWN:
            throw new \LogicException(
                sprintf(
                    'Entity is in unknown state (%s)', 
                    get_class($this->entity)
                )
            );
            
        case Registry::STATE_NEW:
            $registry->fireEvent($this->entity, new BeforeSaveEvent($connection, $table, $this->entity));
            
            $query->insert($table->getName());
            $values     = $access->toArray();
            $columns    = $table->getColumns();
            $setted     = 0;

            foreach ($columns as $key => $columnObj) {
                $default = $columnObj->getDefault();
                $key = $columnObj->getName();
                $value  = (array_key_exists($key, $values) ? $values[$key] : -1);
                
                if (-1 === $value) {
                    if (!empty($default) || 
                        true === $columnObj->getAutoincrement()
                    ) {
                        $value = $default;
                        continue;
                    } elseif ($columnObj->getNotnull() === true) {
                        throw new \LogicException(
                            sprintf(
                                'Column %s (%s) does not allow null value', 
                                $key, 
                                $table->getName()
                            )
                        );
                    }
                }
                
                $query->set($key, '?');
                $queryParams[] = $value;

                $setted++;
            }

            if (!$setted) {
                $exec = false;
            }
            
            $event = $event = new AfterSaveEvent($connection, $table, $this->entity);;
            break;

        case Registry::STATE_FRESH:
        case Registry::STATE_CHANGED:
            $changed    = $registry->getChangedValues($this->entity);
            $data       = $registry->getData($this->entity);
            $state      = $data['state'];

            $registry->fireEvent($this->entity, new BeforeUpdateEvent($connection, $table, $this->entity));
            
            // reload changed values in case the event changed some...
            $changed    = $registry->getChangedValues($this->entity);

            $query->update($table->getName())->where('1 = 1');
            $ids    = $data['identifiers'];
            $idKeys = $table->getIdentifiersKeys();

            if (!count($ids)) {
                throw new \LogicException(
                    sprintf(
                        'Entity %s lacks identifiers and cannot be saved.', 
                        get_class($this->entity)
                    )
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
    }
}
