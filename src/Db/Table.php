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

use Fwk\Db\Entity\Registry;

class Table {

    /**
     * Table name
     * 
     * @var string
     */
    protected $name;

    /**
     * Columns
     * 
     * @var array<DbColumn>
     */
    protected $columns;

    /**
     * Connection
     *
     * @var Connection
     */
    protected $connection;

    /**
     * Array of primary keys
     * 
     * @var array
     */
    protected $identifiersKey;

    /**
     * Entity registry
     * 
     * @var <type>
     */
    protected $registry;

    /**
     * Classname of the default entity for this table
     * 
     * @var string
     */
    protected $defaultEntity;

    /**
     * Constructor
     *
     * @param string $tableName
     */
    public function __construct($tableName) {
        $this->name     = $tableName;
    }

    /**
     * Adds a column to this table;
     *
     * @param Schema\DbColumn $column
     * @return Table
     */
    public function addColumn(Schema\DbColumn $column) {
        $this->columns[$column->getName()]      = $column;

        return $this;
    }

    /**
     * Adds multiple columns at once
     * 
     * @param array $columns
     * @return Table
     */
    public function addColumns(array $columns) {
        foreach($columns as $column) {
            $this->addColumn($column);
        }

        return $this;
    }

    /**
     * Returns all columns from this table
     * 
     * @return array
     */
    public function getColumns() {

        return $this->columns;
    }

    /**
     * Defines a connection for this table
     * 
     * @param Connection $connection
     * @return Table
     */
    public function setConnection(Connection $connection) {
        $this->connection   = $connection;

        return $this;
    }

    /**
     * Returns current connection for this table
     * 
     * @return Connection
     */
    public function getConnection() {
        if(!isset($this->connection))
                throw new \RuntimeException (sprintf('No connection defined for table "%s"', $this->name));

        return $this->connection;
    }

    /**
     * Tries to find one entry in the table matching submitted identifier.
     *
     * Identifier can be a single parameter (integer, string) using primary key
     * or an array
     *
     * @param mixed $identifier
     * @return mixed
     */
    public function findOne($identifier, $entityClass = null) {
        if(null === $entityClass)
            $entityClass    = $this->getDefaultEntity();

        $idents     = $this->getIdentifiersKeys();
        $query      = new Query();
        $query->entity($entityClass);
        $query->select()->from($this->getName(), 'f');

        $params     = array();

        if(!count($idents))
            throw new \RuntimeException (sprintf('table %s lacks primary key(s)', $this->name));
        
        if(!is_array($identifier) && count($idents) == 1) {
            $query->where('f.'. $idents[0] .' = ?');
            $params[]   = $identifier;
        }
        
        elseif(is_array($idents) && count($idents) == count($identifier)) {
            foreach($idents as $key) {
                if(!isset($identifier[$key]))
                    throw new \LogicException(sprintf('Missing required identifier %s', $key));

                $query->where('f.'. $key .' = ?');
                $params[]   = $identifier[$key];
            }
        }
        
        elseif(is_array($identifier)) {
            foreach($idents as $key  => $value) {
                if(!\is_int($key))
                    throw new \LogicException(sprintf('Array identifier should have named column names', $key));

                $query->where('f.'. $key .' = ?');
                $params[]   = $value;
            }
        }
        
        else
            throw new \LogicException (sprintf('Invalid identifier %s', $identifier));

        $results    = $this->getConnection()->execute($query, $params);
        if(count($results) == 1)
            return $results[0];

        return null;
    }

    public function find($identifiers, $entityClass = null, $maxCount = 0) {
        if(null === $entityClass)
            $entityClass    = $this->getDefaultEntity ();

        
    }

    public function count() {

    }

    public function findAll($entityClass = null) {
        if(null === $entityClass)
            $entityClass    = $this->getDefaultEntity ();

        $query  = Query::factory()->select()
                                  ->from($this->getName())
                                  ->entity($entityClass);

        return $this->getConnection()->execute($query);
    }

    public function getName() {
        
        return $this->name;
    }

    /**
     * Returns primary identifiers key for this table
     * 
     * @return array
     */
    public function getIdentifiersKeys() {
        if (!isset($this->identifiersKey)) {
            $columns = $this->columns;
            $final = array();
            foreach ($columns as $column) {
                if ($column->isPrimary()) {
                    array_push($final, $column->getName());
                }
            }

            $this->identifiersKey = $final;
        }
        return $this->identifiersKey;
    }

    /**
     * Returns the entity registry for this table
     *
     * @return Registry
     */
    public function getRegistry() {
        if(!isset($this->registry)) {
            $this->registry = new Registry($this->name);
        }
        
        return $this->registry;
    }

    /**
     * Returns an object representation of a given column
     *
     * @param string $columnName
     * @return Schema\DbColumn
     */
    public function getColumn($columnName) {
        if(!isset($this->columns[$columnName]))
                throw new \RuntimeException (sprintf('Unknown column "%s" on table %s', $columnName, $this->name));

        return $this->columns[$columnName];
    }

    public function isColumn($columnName) {

        return isset($this->columns[$columnName]);
    }
    
    public function setDefaultEntity($entityClass) {
        $this->defaultEntity    = $entityClass;

        return $this;
    }

    public function getDefaultEntity() {
        if(!isset($this->defaultEntity)) {
            $schema                 = $this->getConnection()->getSchema();
            $this->defaultEntity    = $schema->getDeclaredEntity($this->name);
        }
        return $this->defaultEntity;
    }

    public function save($entity) {
        if(is_null($entity))
            return;
        
        if(!is_array($entity) && !\is_object($entity))
            throw new \InvalidArgumentException (sprintf("You can only save an object or a list of objects"));

        if(!\is_array($entity))
            $entity = array($entity);

        foreach($entity as $object) {
            if(is_null($object))
                continue;

            $this->getRegistry()->markForAction($object, Registry::ACTION_SAVE);
        }
        
        $connection     = $this->getConnection();
        if(!$connection->isTransactionnal()) {
            $this->work();
        }
    }

    public function delete($entity) {
        if(is_null($entity))
            return;

        if(!is_array($entity) && !\is_object($entity))
            throw new \InvalidArgumentException (sprintf("You can only delete an object or a list of objects"));

        if(!\is_array($entity))
            $entity = array($entity);

        foreach($entity as $object) {
            if(is_null($object))
                continue;
            
            $this->getRegistry()->markForAction($object, Registry::ACTION_DELETE);
        }
        
        $connection     = $this->getConnection();
        if(!$connection->isTransactionnal()) {
            $this->work();
        }
    }

    protected function work() {
        $queue          = $this->getRegistry()->getWorkersQueue();
        $connection     = $this->getConnection();
        
        foreach($queue as $worker) {
           $worker->execute($connection);
        }
    }
    
    public function destruct() {
        $connection     = $this->getConnection();
        if($connection->isTransactionnal())
            $this->work();
    }
    
    public function deleteAll() {
        $query = Query::factory()->delete($this->getName());
        $res = $this->getConnection()->execute($query);
        
        return $this;
    }
}