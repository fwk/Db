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
 * @author     Julien Ballestracci <julien@nitronet.org>
 * @copyright  2011-2012 Julien Ballestracci <julien@nitronet.org>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link       http://www.phpfwk.com
 */
namespace Fwk\Db;

use Fwk\Events\Dispatcher,
    Fwk\Events\Event, 
    Doctrine\DBAL\Connection as DbalConnection,
    Doctrine\DBAL\DriverManager;

/**
 * Represents a Connection to a database
 *
 */
class Connection extends Dispatcher
{
    const STATE_INITIALIZED     = 0;
    const STATE_CONNECTED       = 1;
    const STATE_DISCONNECTED    = 2;
    const STATE_ERROR           = 3;

    /**
     * Connection options
     *
     * @var array
     */
    protected $options = array();
    
    /**
     * Driver object
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
    protected $state            = self::STATE_INITIALIZED;

    /**
     * Tables objects (cache)
     *
     * @var array<Table>
     */
    protected $tables;

    /**
     * Constructor with generic configuration parameters (array)
     *
     * options:
     *      - autoConnect:  (boolean) should connect on construct (defaults to false)
     *
     * @param array  $options
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
     * Establish connection with database
     *
     * @throws Fwk\Db\Exceptions\ConnectionError
     * 
     * @return boolean
     */
    public function connect()
    {
        if(!$this->isConnected()) {
            try {
                $dbal = $this->getDriver();
                $res  = $dbal->connect();
            } catch(\Doctrine\DBAL\DBALException $e) {
            } catch(\PDOException $e) {
                $this->setErrorException(new Exceptions\ConnectionError($e->getMessage()));
                return false;
            }
            
            if(!$res) {
                $this->setErrorException(new Exceptions\ConnectionError());
                return false;
            }
            
            $this->setState(self::STATE_CONNECTED);
            $event = new Event(ConnectionEvents::CONNECT, array(
                'connection'     => $this,
            ));

            $this->notify($event);
        }

        return true;
    }

    /**
     * Ends connection to database
     *
     * @return boolean
     */
    public function disconnect()
    {
        if (!$this->isConnected()) {
            return true;
        }

        try {
            $dbal = $this->getDriver();
            $dbal->close();
        } catch(\Doctrine\DBAL\DBALException $e) {
        } catch(\PDOException $e) {
            $this->setErrorException(new Exceptions\ConnectionError($e->getMessage()));
        }

        $this->setState(self::STATE_DISCONNECTED);
        $event = new Event(ConnectionEvents::DISCONNECT, array(
            'connection'     => $this,
        ));

        $this->notify($event);

        return true;
    }

    /**
     * Sets an option value
     *
     * @param string $option
     * @param mixed  $value
     *
     * @return Connection
     */
    public function set($option, $value)
    {
        $this->options[$option] = $value;

        return $this;
    }

    /**
     * Returns an option value or $default if not defined.
     *
     * @param string $option
     * @param mixed  $default
     *
     * @return mixed
     */
    public function get($option, $default = null)
    {

        return (\array_key_exists($option, $this->options) ?
                $this->options[$option] :
                $default);
    }

    /**
     * Returns all options values
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
     * @param  array      $options
     * @return Connection
     */
    public function setOptions(array $options = array())
    {
        $this->options = \array_merge($this->options, $options);

        return $this;
    }

    /**
     * Returns the DBAL instance for this connection
     *
     * @return DbalConnection
     */
    public function getDriver()
    {
        if(!isset($this->driver)) {
            $this->driver = DriverManager::getConnection($this->options);
        }
        
        return $this->driver;
    }

    /**
     * Defines a driver
     * 
     * @param DbalConnection $driver 
     */
    public function setDriver(DbalConnection $driver)
    {
        $this->driver = $driver;
    }
    
    /**
     *
     * @return \Doctrine\DBAL\Schema\Schema
     */
    public function getSchema()
    {
        if(!isset($this->schema)) {
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
     * @param Query $query
     * @param array $params Query values (if any)
     *
     * @return mixed
     */
    public function execute(Query $query, array $params = array(),
        array $options = array())
    {
        $event = new Event(ConnectionEvents::BEFORE_QUERY, array(
            'query'     => $query,
            'results'   => null
        ));

        $this->notify($event);

        if ($event->isStopped()) {

            return $event->results;
        }
        
        $bridge = $this->newQueryBrige();
        $stmt = $bridge->execute($query, $params, $options);
        $results = $stmt->execute($params);
        
        if($query->getType() == Query::TYPE_SELECT) {
            var_dump($stmt->fetchAll());
        }
        
        $results = array(); /** @todo */
        $event->results = $results;
        $afterEvent = new Event(ConnectionEvents::AFTER_QUERY, $event->getData());
        $this->notify($afterEvent);

        return $afterEvent->results;
    }

    
    /**
     * 
     * @return QueryBridge 
     */
    public function newQueryBrige()
    {
        return new QueryBridge($this);
    }
    
    /**
     * Defines current state
     *
     * @param integer $state
     *
     * @return Connection
     */
    public function setState($state)
    {
        $newState       = (int) $state;

        if ($newState != $this->state) {
            $this->notify(new Event(ConnectionEvents::STATE_CHANGE, array(
                'beforeState' => $this->state,
                'state'  => $state
            )));
        }

        $this->state    = $newState;

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
     * Sets an error Exception and throws it
     *
     * @param \Exception $e
     *
     * @return void
     */
    public function setErrorException(\Exception $e)
    {
        $this->setState(self::STATE_ERROR);

        $this->notify(new Event(ConnectionEvents::ERROREXCEPTION, array(
            'exception' => $e
        )));

        throw $e;
    }

    /**
     * Returns a table object representing a database table
     *
     * @param string $tableName
     *
     * @throws Exceptions\TableNotFound if inexistant table
     *
     * @return Table
     */
    public function table($tableName)
    {
        if (!isset($this->tables[$tableName])) {
            if(!$this->getSchema()->hasTable($tableName)) {
                $this->setErrorException(
                    new Exceptions\TableNotFound(
                        sprintf('Inexistant table "%s"', $tableName)
                    )
                );

                return false;
            }
            
            $table = new Table($tableName);
            $table->setConnection($this);
            
            $this->tables[$tableName]   = $table;
        }

        return $this->tables[$tableName];
    }

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
