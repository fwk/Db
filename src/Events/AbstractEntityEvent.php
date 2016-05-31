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
namespace Fwk\Db\Events;

use Fwk\Db\Table;
use Fwk\Events\Event;
use Fwk\Db\Connection;

/**
 * Abstract class for Entity-events.
 *
 * Entity-events are events catchable by an EventSubscriber entity.
 *
 * @category Events
 * @package  Fwk\Db
 * @author   Julien Ballestracci <julien@nitronet.org>
 * @license  http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link     http://www.nitronet.org/fwk
 */
abstract class AbstractEntityEvent extends Event
{
    const EVENT_NAME = 'dbEvent';

    /**
     * Constructor
     *
     * @param Connection $connection The Database connection
     * @param Table      $table      The entity's table
     * @param object     $entity     The entity
     *
     * @return void
     */
    public function __construct(Connection $connection, Table $table, $entity)
    {
        parent::__construct(
            static::EVENT_NAME, array(
                'connection' => $connection,
                'table'     => $table,
                'entity'    => $entity
            )
        );
    }

    /**
     * Returns the Database Connection
     *
     * @return Connection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Returns the entity's table instance
     *
     * @return Table
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Returns the entity
     *
     * @return object
     */
    public function getEntity()
    {
        return $this->entity;
    }
}