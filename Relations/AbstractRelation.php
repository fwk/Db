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
 * @subpackage Relations
 * @author     Julien Ballestracci <julien@nitronet.org>
 * @copyright  2011-2012 Julien Ballestracci <julien@nitronet.org>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link       http://www.phpfwk.com
 */
namespace Fwk\Db\Relations;

use Fwk\Db\EntityEvents,
    Fwk\Db\Relation,
    Fwk\Db\Registry,
    Fwk\Db\Exception, 
    Fwk\Db\Connection, 
    Fwk\Events\Dispatcher,
    \IteratorAggregate;

/**
 * Abstract utility class for Relations
 */
abstract class AbstractRelation implements IteratorAggregate
{
    /** 
     * Local column name
     * 
     * @var string 
     */
    protected $local;

    /** 
     * Foreign column name
     * 
     * @var string 
     */
    protected $foreign;

    /** 
     * Fetch mode 
     * {@see Relation::FETCH_LAZY} and {@see Relation::FETCH_EAGER}
     * 
     * @var integer
     */
    protected $fetchMode = Relation::FETCH_LAZY;

    /** 
     * Entity classname for this relation
     * 
     * @var string 
     */
    protected $entity;

    /** 
     * Column name in parent entity for this relation
     * 
     * @var string 
     */
    protected $columnName;

    /**
     * Connection
     *
     * @var \Fwk\Db\Connection
     */
    protected $connection;
    
    /**
     * Is the relation fetched ?
     * 
     * @var boolean
     */
    protected $fetched = false;

    /**
     * Parent references 
     * 
     * @var mixed
     */
    protected $parentRefs;

    /**
     * Parent entity (if any)
     * 
     * @var mixed 
     */
    protected $parent;
    
    /**
     * Relation's own registry
     * 
     * @var Registry
     */
    protected $registry;
    
    /**
     * Referenced table name
     * 
     * @var string 
     */
    protected $tableName;

    /**
     *
     * @param string $local
     * @param string $foreign
     * @param string $table
     * @param string $entity
     */
    public function __construct($local, $foreign, $table, $entity = null)
    {
        $this->tableName = $table;
        $this->registry = new Registry($table);
        $this->local    = $local;
        $this->foreign  = $foreign;
        $this->entity   = ($entity === null ? '\stdClass' : $entity);
    }

    /**
     * FETCH_EAGER -or- FETCH_LAZY
     * 
     * @param integer $mode
     * 
     * @return Relation
     */
    public function setFetchMode($mode)
    {
        $this->fetchMode = $mode;

        return $this;
    }

    /**
     *
     * @param boolean $bool
     * 
     * @return Relation
     */
    public function setFetched($bool)
    {
        $this->fetched = (bool)$bool;
        if($this->fetched) {
            foreach($this->getRegistry()->getStore() as $object) {
                $this->getRegistry()->defineInitialValues($object);
            }
        }
        
        return $this;
    }

     /**
     * Returns the connection defined for this relation
     *
     * @return Connection
     */
    public function getConnection()
    {
        if(!isset($this->connection)) {
            throw new Exception(
                sprintf(
                    'No connection defined for this relation (%s: %s<->%s)', 
                    $this->tableName, 
                    $this->local, 
                    $this->foreign
                )
            );
        }
        
        return $this->connection;
    }

    /**
     * Sets a connection for this relation (used for lazy loading)
     *
     * @param Connection $connection
     * 
     * @return Relation
     */
    public function setConnection(Connection $connection)
    {
        $this->connection   = $connection;

        return $this;
    }

    /**
     * Tells if an entity managed by this relation has changed 
     * 
     * @return boolean
     */
    public function hasChanged()
    {
        foreach($this->getRegistry()->getStore() as $obj) {
            $this->getRegistry()->getChangedValues($obj);
            $data   = $this->getRegistry()->getData($obj);
            if($data['state'] != Registry::STATE_FRESH || !empty($data['action']))
                return true;
        }

        return false;
    }
   
    /**
     * Tells if this relation is active (parents references have been defined)
     * 
     * @return boolean
     */
    public function isActive()
    {
        
        return isset($this->parentRefs);
    }

    /**
     * Tells if the specified object is in the relation
     * 
     * @param mixed $object
     * 
     * @return boolean 
     */
    public function has($object)
    {

        return $this->getRegistry()->contains($object);
    }

    /**
     * Defines parent references
     * 
     * @param mixed $refs
     * 
     * @return Relation
     */
    public function setParentRefs($refs)
    {
        $this->parentRefs   = $refs;

        return $this;
    }

    /**
     *
     * @param mixed $object
     * @param Dispatcher $evd
     * 
     * @return boolean true if parent has been changed/defined
     */
    public function setParent($object)
    {
        if($this->parent === $object) {
            return false;
        }

        $this->parent   = $object;
        return true;
    }

    /**
     * Tells if this relation has been fetched
     * 
     * @return boolean
     */
    public function isFetched()
    {

        return $this->fetched;
    }

    /**
     * Tells if this relation is in LAZY fetch mode
     * 
     * @return boolean
     */
    public function isLazy()
    {
        
        return ($this->fetchMode === Relation::FETCH_LAZY);
    }

    /**
     * Tells if this relation is in EAGER fetch mode
     * 
     * @return boolean
     */
    public function isEager()
    {
        
        return ($this->fetchMode === Relation::FETCH_EAGER);
    }

    /**
     * Return the defined entity for this relation
     * 
     * @return string
     */
    public function getEntity()
    {
        
        return $this->entity;
    }

    /**
     * Return the foreign column for this relation
     * 
     * @return string 
     */
    public function getForeign()
    {
        
        return $this->foreign;
    }

    /**
     * Return the local column for this relation
     * 
     * @return string
     */
    public function getLocal()
    {
        
        return $this->local;
    }

    /**
     * Defines the column name for this relation on the parent entity
     * 
     * @param string $columnName
     * 
     * @return Relation 
     */
    public function setColumnName($columnName)
    {
        $this->columnName   = $columnName;
        
        return $this;
    }

    /**
     * Removes all objects
     * 
     * @return Relation
     */
    public function clear()
    {
        foreach($this->getRegistry()->getStore() as $object) {
            $this->getRegistry()->remove($object);
        }
        $this->fetched = false;
        
        return $this;
    }

    /**
     * Returns an array of all entities in this relation
     * 
     * @return array 
     */
     public function toArray()
    {
         $this->fetch();
        $final= array();
        foreach($this->getRegistry()->getStore() as $object) {
                $data   = $this->getRegistry()->getData($object);
                if($data['action'] == 'delete')
                    continue;

                if(empty($this->reference)) {
                    $final[] = $object;
                    continue;
                }

                $ref    = (isset($data['reference']) ? $data['reference'] : null);
                $final[$ref]  = $object;
        }

        return $final;
    }
    
    /**
     * Returns this relation's registry
     * 
     * @return Registry
     */
    public function getRegistry()
    {
        
        return $this->registry;
    }

    /**
     * Defines a registry for this relation
     * 
     * @param Registry $registry
     * 
     * @return Relation 
     */
    public function setRegistry(Registry $registry)
    {
        $this->registry = $registry;
        
        return $this;
    }
    
    /**
     * Return this relation's table name
     * 
     * @return string 
     */
    public function getTableName()
    {
        return $this->tableName;
    }

    /**
     * Defines this relation's table name
     * 
     * @param string $tableName 
     * 
     * @return Relation
     */
    public function setTableName($tableName)
    {
        $this->tableName = $tableName;
        
        return $this;
    }
    
    /**
     *
     * @param string $entityClass 
     * 
     * @return Relation
     */
    public function setEntity($entityClass)
    {
        $this->entity   = $entityClass;
        
        return $this;
    }
    
    /**
     * Add an entity to this relation
     * 
     * @param mixed $object
     * @param array $identifiers
     * 
     * @return Relation 
     */
    public function add($object, array $identifiers = array())
    {
        if($this->has($object)) {
            return;
        }

        $this->getRegistry()->store($object, $identifiers, Registry::STATE_NEW);
        
        return $this;
    }

    /**
     * Removes an entity from this relation
     * 
     * @param mixed $object
     * 
     * @return Relation 
     */
    public function remove($object)
    {
        if($this->has($object)) {
            $this->getRegistry()->markForAction($object, Registry::ACTION_DELETE);
        }
        
        return $this;
    }
    
    /**
     * Return this relation data within an Iterator (foreach ...)
     * {@see \Traversable}
     * 
     * @return \ArrayIterator 
     */
    public function getIterator() {
        $this->fetch();
        
        return new \ArrayIterator($this->toArray());
    }
}