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

use Fwk\Events\Dispatcher;

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
    protected $options;
    /**
     * Driver object
     * 
     * @var Fwk\Db\Driver
     */
    protected $driver;

    /**
     * Database Schema
     * 
     * @var Schema\DbSchema
     */
    protected $schema;

    /**
     * Current state of the connection
     * 
     * @var integer
     */
    protected $state            = self::STATE_INITIALIZED;

    /**
     * Should this connection throw exceptions or fail silently ?
     *
     * @var boolean
     */
    protected $throwExceptions  = true;

    /**
     * Tables objects (cache)
     * 
     * @var array<Table>
     */
    protected $tables;
    
    /**
     * Constructor with generic configuration parameters (array)
     * 
     * options description:
     *      - autoConnect:  (boolean)   should connect on construct (defaults to false)
     *      - throwExceptions: (boolean)should we throw exceptions or silently fail ?
     *      - transactionnal: (boolean) should we save/delete entities ondemand or within a final transaction ?
     * 
     * @param Driver $driver
     * @param Schema $schema
     * @param array $options
     */
    public function __construct(Driver $driver, Schema $schema, array $options = array()) {
        $this->options      = $options;
        $this->schema       = $schema;
        $this->driver       = $driver;
        
        $this->throwExceptions((bool)$this->get('throwExceptions', true));
        
        if(true === $this->get('autoConnect', false)) {
            $this->connect();
        }
    }

    /**
     * Destructor
     *
     */
    public function __destruct() {
        /**
         * @todo EntityManager transaction ?
         */
    }
    
    /**
     * Establish connection with database
     * 
     * @return boolean
     */
    public function connect() {

        return $this->getDriver()->connect();
    }

    /**
     * Sets an option value
     *
     * @param string $option
     * @param mixed $value
     * @return Connection
     */
    public function set($option, $value) {
        $this->options[$option] = $value;

        return $this;
    }

    /**
     * Returns an option value or $default if not defined.
     * 
     * @param string $option
     * @param mixed $default
     * @return mixed
     */
    public function get($option, $default = null) {

        return (\array_key_exists($option, $this->options) ? $this->options[$option] : null);
    }

    /**
     * Returns all options values
     * 
     * @return array
     */
    public function getOptions() {
        
        return $this->options;
    }
    
    /**
     * Sets (merge) multiple options values
     *
     * @param array $options
     * @return Connection
     */
    public function setOptions(array $options = array()) {
        $this->options = \array_merge($this->options, $options);

        return $this;
    }

    /**
     * Returns the driver for this connection
     * 
     * @return Driver
     */
    public function getDriver() {
        
        return $this->driver;
        
        /**
         * 
        if(!isset($this->driver)) {
            $driverClassName    = $this->get('driver');
            if(empty($driverClassName))
                throw new Exception(sprintf('No driver defined'));
            
            $new                = new $driverClassName; 
            if(!$new instanceof Driver)
                throw new Exception (sprintf('%s does not implement Driver interface'));

            $new->setConnection($this);
            $this->driver       = $new;
            $evd                = $new->getEventDispatcher();

            // register events
            $connection         = $this;
            
            $evd->on(Driver::EVENT_CONNECT, function() use ($connection) {
                $connection->setState(Connection::STATE_CONNECTED);
            });

            $evd->on(Driver::EVENT_DISCONNECT, function() use ($connection) {
                $connection->setState(Connection::STATE_DISCONNECTED);
            });

            $evd->on(Driver::EVENT_ERROR, function($event) use ($connection) {
                $connection->setState(Connection::STATE_ERROR);

                if(isset($event->exception))
                        $connection->setErrorException($event->exception);
            });
        }
        */

        return $this->driver;
    }

    /**
     * Returns the defined database schema
     * 
     * @return Schema
     */
    public function getSchema() {

        return $this->schema;
    }
    /**
     * Tells if the connection is established
     * 
     * @return boolean
     */
    public function isConnected() {
        
        return ($this->state === self::STATE_CONNECTED);
    }

    /**
     * Executes a query and return results
     *
     * @param Query $query
     * @param array $params Query values (if any)
     * @return mixed
     */
    public function execute(Query $query, array $params = array(), array $options = array()) {
        return $this->getDriver()->query($query, $params, $options);
    }

    /**
     * Defines current state
     * 
     * @param integer $state
     * @return Connection
     */
    public function setState($state) {
        $this->state        = $state;

        return $this;
    }

    /**
     * Returns current connection state
     * 
     * @return integer
     */
    public function getState() {

        return $this->state;
    }

    /**
     * Should this connection throw exceptions or fail silently ?
     * 
     * @param boolean $boolean
     * @return Connection
     */
    public function throwExceptions($boolean) {
        $this->throwExceptions  = $boolean;

        return $this;
    }

    /**
     * Sets an error Exception and throws it
     *
     * @see throwExceptions
     * @param \Exception $e
     * @return void
     */
    public function setErrorException(\Exception $e) {
        $this->setState(self::STATE_ERROR);
        
        if($this->throwExceptions)
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
    public function table($tableName) {
        if(!isset($this->tables[$tableName])) {
            $table      = $this->getSchema()->getTable($tableName);
            if(!$table instanceof Table) {
                $this->setErrorException(
                    new Exceptions\TableNotFound(
                        sprintf('Inexistant table "%s"', $tableName)
                    )
                );
            }
            
            $table->setConnection($this);
            $this->tables[$tableName]   = $table;
        }

        return $this->tables[$tableName];
    }

    public function lastInsertId() {

        return $this->getDriver()->getLastInsertId();
    }
    
    /**
     * 
     * @return boolean
     */
    public function isTransactionnal() {
        
        return $this->get('transactionnal', false);
    }
    
    public function beginTransaction() {

        return $this->getDriver()->beginTransaction();
    }

    public function commit() {

        return $this->getDriver()->commit();	
    }

    public function rollBack() {

        return $this->getDriver()->rollBack();
    }

}