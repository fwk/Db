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

use Fwk\Db\Events\AfterDeleteEvent;
use Fwk\Db\Events\BeforeDeleteEvent;
use Fwk\Db\Registry;
use Fwk\Db\Worker;
use Fwk\Db\Accessor;
use Fwk\Db\Connection;

/**
 * Save Entity Worker
 * 
 * This worker is used when an entity or relation have to be deleted.
 * 
 * @category Workers
 * @package  Fwk\Db
 * @author   Julien Ballestracci <julien@nitronet.org>
 * @license  http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link     http://www.phpfwk.com
 */
class DeleteEntityWorker extends AbstractWorker implements Worker
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

        if (in_array($this->entity, static::$working, true)) {
            return;
        }

        switch ($state) {
        case Registry::STATE_UNKNOWN:
            throw new \LogicException(
                sprintf(
                    'Entity is in unknown state (%s)', 
                    get_class($this->entity)
                )
            );

        case Registry::STATE_NEW:
            return;

        case Registry::STATE_FRESH:
        case Registry::STATE_CHANGED:
            array_push(static::$working, $this->entity);
            $registry->fireEvent($this->entity, new BeforeDeleteEvent($connection, $table, $this->entity));

            $changed    = $registry->getChangedValues($this->entity);
            $data       = $registry->getData($this->entity);

            $query->delete($table->getName())->where('1 = 1');
            $ids    = $data['identifiers'];
            $idKeys = $table->getIdentifiersKeys();
            if (!count($ids)) {
                static::removeFromWorking($this->entity);
                throw new \LogicException(
                    sprintf(
                        'Entity %s lacks identifiers and cannot be deleted.', 
                        get_class($this->entity)
                    )
                );
            }
            
            foreach ($changed as $key => $value) {
                if (\array_key_exists($key, $ids)) {
                    static::removeFromWorking($this->entity);
                    throw new \LogicException(
                        sprintf(
                            'Unable to delete entity because identifiers (%s) '.
                            'have been modified', 
                            implode(', ', $ids)
                        )
                    );
                }
            }

            foreach ($idKeys as $key) {
                $query->andWhere(sprintf('`%s` = ?', $key));
                $value = $access->get($key);
                if (!$value) {
                    static::removeFromWorking($this->entity);
                    throw new \RuntimeException(
                        sprintf(
                            'Cannot delete entity object (%s) because it '. 
                            'lacks identifier (%s)', 
                            get_class($this->entity), 
                            $key
                        )
                    );
                }
                $queryParams[] = $value;
            }

            break;
        }

        $connection->execute($query, $queryParams);
        $registry->fireEvent($this->entity, new AfterDeleteEvent($connection, $table, $this->entity));
        $registry->remove($this->entity);
        static::removeFromWorking($this->entity);
    }
}