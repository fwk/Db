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
namespace Fwk\Db\Listeners;

use Fwk\Db\Accessor;
use Fwk\Db\Events\AfterSaveEvent;
use Fwk\Db\Events\AfterUpdateEvent;
use Fwk\Db\Events\BeforeSaveEvent;
use Fwk\Db\Events\BeforeUpdateEvent;
use Fwk\Db\Events\FreshEvent;
use Fwk\Db\Relation;
use Fwk\Db\Table;

/**
 * Typable
 *
 * This listeners helps an Entity to expose real data types, instead of string values
 * Example: converts a date YYYY-MM-DD hh:mm:ss to \DateTime
 *
 * @category Listeners
 * @package  Fwk\Db
 * @author   Julien Ballestracci <julien@nitronet.org>
 * @license  http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link     http://www.nitronet.org/fwk
 */
class Typable
{
    /**
     * Columns to be skipped
     *
     * @var array
     */
    protected $skipColumns = array();

    /**
     * Constructor
     *
     * @param array $skipColumns Columns to be skipped
     *
     * @return void
     */
    public function __construct(array $skipColumns = array())
    {
        $this->skipColumns = $skipColumns;
    }

    /**
     * Listener triggered when an entity has just been fetched from the database
     *
     * @param FreshEvent $event The Fresh event
     *
     * @return void
     */
    public function onFresh(FreshEvent $event)
    {
        $this->fromDatabaseToTypes($event->getEntity(), $event->getTable());
    }

    /**
     * Listeneer triggered before a new entity is saved to database
     *
     * @param BeforeSaveEvent $event The BeforeSave event
     *
     * @return void
     */
    public function onBeforeSave(BeforeSaveEvent $event)
    {
        $this->fromTypesToDatabase($event->getEntity(), $event->getTable());
    }

    /**
     * Listeneer triggered before an existing entity is updated
     *
     * @param BeforeUpdateEvent $event The BeforeUpdate event
     *
     * @return void
     */
    public function onBeforeUpdate(BeforeUpdateEvent $event)
    {
        $this->fromTypesToDatabase($event->getEntity(), $event->getTable());
    }

    /**
     * Listeneer triggered after an entity creation in database
     *
     * @param AfterSaveEvent $event The AfterSave event
     *
     * @return void
     */
    public function onAfterSave(AfterSaveEvent $event)
    {
        $this->fromDatabaseToTypes($event->getEntity(), $event->getTable());
    }

    /**
     * Listeneer triggered after an update of an existing entity
     *
     * @param AfterUpdateEvent $event The AfterUpdate event
     *
     * @return void
     */
    public function onAfterUpdate(AfterUpdateEvent $event)
    {
        $this->fromDatabaseToTypes($event->getEntity(), $event->getTable());
    }

    /**
     * Convert database-types to real data types
     *
     * @param object $entity The entity
     * @param Table  $table  The entity's table
     *
     * @return void
     */
    protected function fromDatabaseToTypes($entity, Table $table)
    {
        $accessor   = Accessor::factory($entity);
        $array      = $accessor->toArray();
        $platform   = $table->getConnection()->getDriver()->getDatabasePlatform();
        foreach ($array as $key => $value) {
            if (in_array($key, $this->skipColumns) || $value instanceof Relation) {
                continue;
            }

            $accessor->set(
                $key,
                $table->getColumn($key)
                    ->getType()
                    ->convertToPHPValue($value, $platform)
            );
        }
    }

    /**
     * Convert real data types to database-types
     *
     * @param object $entity The entity
     * @param Table  $table  The entity's table
     *
     * @return void
     */
    protected function fromTypesToDatabase($entity, Table $table)
    {
        $accessor   = Accessor::factory($entity);
        $array      = $accessor->toArray();
        $platform   = $table->getConnection()->getDriver()->getDatabasePlatform();
        foreach ($array as $key => $value) {
            if (in_array($key, $this->skipColumns) || $value instanceof Relation) {
                continue;
            }

            $accessor->set(
                $key,
                $table->getColumn($key)
                    ->getType()
                    ->convertToDatabaseValue($value, $platform)
            );
        }
    }
}