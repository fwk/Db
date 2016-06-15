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

use Doctrine\DBAL\Driver\PDOStatement;
use Fwk\Db\Events\AfterQueryEvent;
use Fwk\Db\Events\BeforeQueryEvent;
use Fwk\Db\Events\ConnectEvent;
use Fwk\Db\Events\ConnectionErrorEvent;
use Fwk\Db\Events\ConnectionStateChangeEvent;
use Fwk\Db\Events\DisconnectEvent;
use Fwk\Events\Dispatcher;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\DriverManager;

/**
 * Represents a Connection to a database
 * 
 * @category Library
 * @package  Fwk\Db
 * @author   Julien Ballestracci <julien@nitronet.org>
 * @license  http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link     http://www.phpfwk.com
 */
class Connection extends Dispatcher
{
    /**
     * State when initialized
     */
    const STATE_INITIALIZED     = 0;
    
    /**
     * State when connected to SGDB
     */
    const STATE_CONNECTED       = 1;
    
    /**
     * State when disconnected from SGDB
     */
    const STATE_DISCONNECTED    = 2;
    
    /**
     * State when an exception has been thrown
     */
    const STATE_ERROR           = 3;

    /**
     * Connection options
     *
     * @var array
     */
    protected $options = array();

    /**
     * DBAL Connection object
     *
     * @var DbalConnection
     */
    protected $driver;

    /**
     * Schema object
     *
     * @var \Doctrine\DBAL\Schema\Schema
     */
    protected $schema;

    /**
     * Current state of the connection
     *
     * @var integer
     */
    protected $state = self::STATE_INITIALIZED;

    /**
     * Tables objects (cache)
     *
     * @var array
     */
    protected $tables;

    /**
     * Constructor with generic configuration parameters (array)
     * Options are used by Doctrine\DBAL\Connection, please refer to
     * documentation:
     * 
     * http://docs.doctrine-project.org/projects/doctrine-dbal/en/latest
     * 
     * other options:
     *      - autoConnect:  (boolean) should connect on init (defaults to false)
     *
     * @param array $options Configuration options
     *
     * @return void
     */
    public function __construct(array $options = array())
    {
        $this->options      = $options;

        if (true === $this->get('autoConnect', false)) {
            $this->connect();
        }
    }

    /**
     * Establish connection to database
     *
     * @throws Exceptions\ConnectionErrorException when failing to connect
     * @return boolean
     */
    public function connect()
    {
        if (!$this->isConnected()) {
            try {
                $dbal = $this->getDriver();
                $dbal->connect();
            } catch (\Doctrine\DBAL\DBALException $e) {
            } catch (\PDOException $e) {
                throw $this->setErrorException(
                    new Exceptions\ConnectionErrorException($e->getMessage())
                );
            }
            
            $this->setState(self::STATE_CONNECTED);
            $this->notify(new ConnectEvent($this));
        }

        return true;
    }

    /**
     * End connection to database
     *
     * @throws Exceptions\ConnectionErrorException when failing to disconnect (?)
     * @return boolean
     */
    public function disconnect()
    {
        if (!$this->isConnected()) {
            return true;
        }

        $dbal = $this->getDriver();
        $dbal->close();

        $this->setState(self::STATE_DISCONNECTED);
        $this->notify(new DisconnectEvent($this));
        
        return true;
    }

    /**
     * Sets an option value
     *
     * @param string $option Option's key
     * @param mixed  $value  Option value
     *
     * @return Connection
     */
    public function set($option, $value)
    {
        $this->options[$option] = $value;

        return $this;
    }

    /**
     * Returns an option value or $default if option is not defined.
     *
     * @param string $option  Option key
     * @param mixed  $default Option value
     *
     * @return mixed
     */
    public function get($option, $default = null)
    {
        return array_key_exists($option, $this->options) ?
                $this->options[$option] :
                $default;
    }

    /**
     * Returns all options
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Sets (merge) multiple options values
     *
     * @param array $options List of options (keys->values)
     * 
     * @return Connection
     */
    public function setOptions(array $options = array())
    {
        $this->options = array_merge($this->options, $options);

        return $this;
    }

    /**
     * Returns the DBAL instance for this connection
     *
     * @return DbalConnection
     */
    public function getDriver()
    {
        if (!isset($this->driver)) {
            $this->setDriver(DriverManager::getConnection($this->options));
        }

        return $this->driver;
    }

    /**
     * Defines a driver
     *
     * @param DbalConnection $driver The DBAL Connection object
     * 
     * @return Connection
     */
    public function setDriver(DbalConnection $driver)
    {
        $this->driver = $driver;
        
        return $this;
    }

    /**
     * Returns current database schema
     * 
     * @return \Doctrine\DBAL\Schema\Schema
     */
    public function getSchema()
    {
        if (!isset($this->schema)) {
            $this->connect();
            $this->schema = $this->getDriver()
                ->getSchemaManager()
                ->createSchema();
        }

        return $this->schema;
    }

    /**
     * Tells if the connection is established
     *
     * @return boolean
     */
    public function isConnected()
    {

        return ($this->state === self::STATE_CONNECTED);
    }

    /**
     * Tells if the connection is in error state
     *
     * @return boolean
     */
    public function isError()
    {

        return ($this->state === self::STATE_ERROR);
    }

    /**
     * Executes a query and return results
     *
     * @param Query $query   The Query object
     * @param array $params  Query values (if any)
     * @param array $options Extras query options
     *
     * @return mixed
     */
    public function execute(Query $query, array $params = array(),
        array $options = array()
    ) {
        $bridge = $this->newQueryBrige();
        $event  = new BeforeQueryEvent($this, $query, $params, $options);
        $event->setQueryBridge($bridge);

        $this->notify($event);

        if ($event->isStopped()) {
            return $event->getResults();
        }

        $stmt = $bridge->execute($query, $params, $options);

        if ($query->getType() == Query::TYPE_SELECT) {
            $stmt->execute($params);

            if (!$stmt instanceof PDOStatement) {
                return false; // never happend
            }

            $tmp = $stmt->fetchAll(
                ($query->getFetchMode() != Query::FETCH_SPECIAL ?
                    $query->getFetchMode() :
                    \PDO::FETCH_ASSOC
                )
            );

            if ($query->getFetchMode() === Query::FETCH_SPECIAL) {
                $hyd = new Hydrator($query, $this, $bridge->getColumnsAliases());
                $results = $hyd->hydrate($tmp);
            } else {
                $results = $tmp;
            }
        } else {
            $results = $stmt;
        }

        $aevent = new AfterQueryEvent($this, $query, $params, $options, $results);
        $this->notify($aevent);

        return $aevent->getResults();
    }

    /**
     * Returns a new instance of a QueryBridge
     * 
     * @return QueryBridge
     */
    public function newQueryBrige()
    {
        return new QueryBridge($this);
    }

    /**
     * Defines current state and trigger a STATE_CHANGE event
     *
     * @param integer $state New connection's state
     *
     * @return Connection
     */
    public function setState($state)
    {
        $newState       = (int)$state;
        if ($newState != $this->state) {
            $this->notify(
                new ConnectionStateChangeEvent($this, $this->state, $newState)
            );
            $this->state = $newState;
        }

        return $this;
    }

    /**
     * Returns current connection state
     *
     * @return integer
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * Sets an error Exception and toggle error state
     *
     * @param \Exception $exception The exception to be thrown
     *
     * @return \Exception
     */
    public function setErrorException(\Exception $exception)
    {
        $this->setState(self::STATE_ERROR);
        $this->notify(new ConnectionErrorEvent($this, $exception));

        return $exception;
    }

    /**
     * Returns a table object representing a database table
     *
     * @param string $tableName Table name
     *
     * @throws Exceptions\TableNotFoundException if table is not found
     * @return Table
     */
    public function table($tableName)
    {
        if (isset($this->tables[$tableName])) {
            return $this->tables[$tableName];
        }
        
        if ($this->getSchema()->hasTable($tableName)) {
            $table = new Table($tableName);
            $table->setConnection($this);
            $this->tables[$tableName] = $table;
            
            return $table;
        }

        throw $this->setErrorException(
            new Exceptions\TableNotFoundException(
                sprintf(
                    'Inexistant table "%s"', 
                    $tableName
                )
            )
        );
    }

    /**
     * Returns the last inserted ID in the database (if driver supports it)
     * 
     * @return integer 
     */
    public function lastInsertId()
    {

        return $this->getDriver()->lastInsertId();
    }

    /**
     * Starts a new transaction
     *
     * @return Connection
     */
    public function beginTransaction()
    {
        $this->getDriver()->beginTransaction();

        return $this;
    }

    /**
     * Commits the current transaction
     *
     * @return Connection
     */
    public function commit()
    {
        $this->getDriver()->commit();

        return $this;
    }

    /**
     * Cancels the current transaction
     *
     * @return Connection
     */
    public function rollBack()
    {
        $this->getDriver()->rollBack();

        return $this;
    }
}