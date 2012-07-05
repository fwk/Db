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
 * @subpackage Workers
 * @author     Julien Ballestracci <julien@nitronet.org>
 * @copyright  2011-2012 Julien Ballestracci <julien@nitronet.org>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link       http://www.phpfwk.com
 */
namespace Fwk\Db\Workers;

use Fwk\Db\Registry,
    Fwk\Db\Worker,
    Fwk\Events\Event,
    Fwk\Db\EntityEvents,
    Fwk\Db\Accessor;

class SaveEntityWorker extends AbstractWorker implements Worker
{
    public function execute(\Fwk\Db\Connection $connection)
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
                throw new \LogicException(sprintf('Entity is in unknown state (%s)', get_class($this->entity)));

            case Registry::STATE_NEW:
                $registry->fireEvent($this->entity, new Event(EntityEvents::BEFORE_SAVE, array('object'  => $this->entity, 'registry' => $registry, 'connection'   => $connection)));

                $query->insert($table->getName());
                $values     = $access->toArray();
                $columns    = $table->getColumns();
                $setted     = 0;

                foreach ($columns as $key => $columnObj) {
                    $default = $columnObj->getDefault();
                    $value  = (array_key_exists($key, $values) ? $values[$key] : -1);
                    if(-1 === $value && (!empty($default) || $columnObj->getAutoincrement() == true))
                            continue;

                    elseif(-1 === $value && $columnObj->getNotnull() === true)
                            throw new \LogicException (sprintf('Column %s (%s) does not allow null value', $key, $table->getName()));

                    $query->set($key, '?');
                    $queryParams[] = $value;

                    $setted++;
                }

                if(!$setted)
                    $exec = false;

                $event      = EntityEvents::AFTER_SAVE;
                break;

            case Registry::STATE_FRESH:
            case Registry::STATE_CHANGED:
                $changed    = $registry->getChangedValues($this->entity);
                $data       = $registry->getData($this->entity);
                $state      = $data['state'];

                $registry->fireEvent($this->entity, new Event(EntityEvents::BEFORE_UPDATE, array('object'  => $this->entity, 'registry' => $registry, 'connection'   => $connection)));

                // reload changed values in case the event changed some...
                $changed    = $registry->getChangedValues($this->entity);

                $query->update($table->getName())->where('1 = 1');
                $ids    = $data['identifiers'];
                $idKeys = $table->getIdentifiersKeys();

                if(!count($ids))
                    throw new \LogicException(sprintf('Entity %s lacks identifiers and cannot be saved.', get_class($this->entity)));

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
                    if(!$value)
                        throw new \RuntimeException(sprintf('Cannot save entity object (%s) because it lacks identifier (%s)', get_class($this->entity), $key));

                    $queryParams[] = $value;
                }

                $event      = EntityEvents::AFTER_UPDATE;
                break;
        }

        if ($exec) {
            $connection->execute($query, $queryParams);
        }
        $registry->defineInitialValues($this->entity);
        $registry->fireEvent($this->entity, new Event($event, array('object'  => $this->entity, 'registry' => $registry, 'connection'   => $connection)));
    }
}
