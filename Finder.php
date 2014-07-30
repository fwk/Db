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
 * @category  Database
 * @package   Fwk\Db
 * @author    Julien Ballestracci <julien@nitronet.org>
 * @copyright 2011-2012 Julien Ballestracci <julien@nitronet.org>
 * @license   http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link      http://www.phpfwk.com
 */
namespace Fwk\Db;

/**
 * Finder 
 * 
 * Utility class to "navigate" within a database table.
 * 
 * @category Library
 * @package  Fwk\Db
 * @author   Julien Ballestracci <julien@nitronet.org>
 * @license  http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link     http://www.phpfwk.com
 */
class Finder
{
    /**
     * Results
     *
     * @var array
     */
    protected $resultSet;

    /**
     * Table to navigate
     *
     * @var Table
     */
    protected $table;

    /**
     * Current Connection
     *
     * @var Connection
     */
    protected $connection;

    /**
     * Current query
     *
     * @var Query
     */
    protected $query;

    /**
     * Query parameters
     *
     * @var array
     */
    protected $params;

    /**
     * Entity class name
     *
     * @var string
     */
    protected $entity;

    /**
     * List of entity listeners
     *
     * @array
     */
    protected $listeners;

    /**
     * Constructor
     *
     * @param Table      $table      Main table to query
     * @param Connection $connection Actual Connection
     *
     * @return void
     */
    public function __construct(Table $table, Connection $connection = null)
    {
        $query = Query::factory()
                    ->select()
                    ->from(sprintf('%s %s', $table->getName(), 'f'));

        $this->table        = $table;

        if (null !== $connection) {
            $this->setConnection($connection);
        }

        $this->query        = $query;
        $this->params       = array();
        $this->setEntity($table->getDefaultEntity(), $table->getDefaultEntityListeners());
    }

    /**
     * Defines a connection
     *
     * @param Connection $connection Actual Connection
     *
     * @return Finder
     */
    public function setConnection(Connection $connection)
    {
        $this->connection   = $connection;

        return $this;
    }

    /**
     * Returns current connection
     *
     * @throws Exception  if no connection is defined
     * @return Connection
     */
    public function getConnection()
    {
        if (!isset($this->connection)) {
            throw new Exception('No connection is defined');
        }

        return $this->connection;
    }

    /**
     * Find one or more results according to the list of $identifiers. 
     * 
     * @param array $identifiers List of columns to search
     * 
     * @throws Exception if Query already done
     * @return array
     */
    public function find(array $identifiers)
    {
        if (isset($this->resultSet)) {
            throw new Exception('Finder already executed');
        }

        $this->query->where('1 = 1');
        $this->query->entity($this->getEntity());

        foreach ($identifiers as $key => $value) {
            $this->query->andWhere(sprintf('f.%s = ?', $key));
            $this->params[] = $value;
        }

        return $this->getResultSet();
    }

    /**
     * Finds one entry according to identifiers. 
     * 
     * @param mixed $identifiers List of/single identifiers
     * 
     * @throws Exception if Query already done
     * @return mixed
     */
    public function one($identifiers)
    {
        if (isset($this->resultSet)) {
            throw new Exception('Finder already executed');
        }

        $ids = $this->table->getIdentifiersKeys();
        if (!is_array($identifiers)) {
            if (count($ids) === 1) {
                $identifiers = array($ids[0] => $identifiers);
            } else {
                $identifiers = array($identifiers);
            }
        }

        $this->query->where('1 = 1');
        $this->query->entity($this->getEntity(), $this->listeners);

        foreach ($ids as $key) {
            if (!isset($identifiers[$key])) {
                 throw new Exceptions\MissingIdentifier(
                     sprintf('Missing required identifier "%s"', $key)
                 );
            }

            $this->query->andWhere(sprintf('f.%s = ?', $key));
            $this->params[] = $identifiers[$key];
        }

        $res = $this->getResultSet();
        if (count($res) >= 1) {
            return $res[0];
        }

        return null;
    }

    /**
     * Fetches all entries from the table
     * 
     * @return array
     */
    public function all()
    {
        $this->query->entity($this->getEntity());

        return $this->getResultSet();
    }

    /**
     * Executes the query (if required) and returns the result set.
     * 
     * @return array
     */
    public function getResultSet()
    {
        if (!isset($this->resultSet)) {
            $this->resultSet = $this->getConnection()->execute(
                $this->query, 
                $this->params
            );
        }

        return $this->resultSet;
    }

    /**
     * Entity class name
     * 
     * @return string 
     */
    public function getEntity()
    {
        return $this->entity;
    }

    /**
     * List of entity listeners
     *
     * @return array
     */
    public function getListeners()
    {
        return $this->listeners;
    }

    /**
     * Defines the entity that should be returned by this Finder
     * 
     * @param string $entity    The entity class name
     * @param array  $listeners List of listeners to be used with this entity (table defaults are overriden)
     * 
     * @return Finder
     */
    public function setEntity($entity, array $listeners = array())
    {
        $this->entity       = $entity;
        $this->listeners    = $listeners;

        return $this;
    }
}