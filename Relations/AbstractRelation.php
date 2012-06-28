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

use Fwk\Db\EntityEvents;
use Fwk\Db\Relation;
use Fwk\Db\Registry;

abstract class AbstractRelation {

    /** @var string */
    protected $local;

    /** @var string */
    protected $foreign;

    /** @var integer */
    protected $fetchMode = Relation::FETCH_LAZY;

    /** @var string */
    protected $entity;

    /** @var string */
    protected $columnName;

    /**
     * Connection
     *
     * @var \Fwk\Db\Connection
     */
    protected $connection;
    
    /**
     * @var boolean
     */
    protected $fetched = false;

    protected $parentRefs;

    protected $parent;
    
    protected $registry;
    
    protected $tableName;

    /**
     *
     * @param string $local
     * @param string $foreign
     * @param string $table
     * @param string $entity
     */
    public function __construct($local, $foreign, $table, $entity = null) {
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
     * @return Generic
     */
    public function setFetchMode($mode) {
        $this->fetchMode = $mode;

        return $this;
    }

    /**
     *
     * @param boolean $bool
     * @return Relation
     */
    public function setFetched($bool) {
        $this->fetched = (bool)$bool;
        if($this->fetched) {
            foreach($this->getRegistry()->getStore() as $object) {
                $this->getRegistry()->defineInitialValues($object);
            }
        }
        return $this;
    }

     /**
     * Returns the connection defined for this relation (Lazy only)
     *
     * @return \Fwk\Db\Connection
     */
    public function getConnection() {
        if(!isset($this->connection))
                throw new \RuntimeException (sprintf('No connection defined for this relation (%s: %s<->%s)', $this->tableName, $this->local, $this->foreign));

        return $this->connection;
    }

    /**
     * Sets a connection for this relation (used for lazy loading)
     *
     * @param \Fwk\Db\Connection $connection
     * @return Many2Many
     */
    public function setConnection(\Fwk\Db\Connection $connection) {
        $this->connection   = $connection;

        return $this;
    }

    /**
     *
     * @return boolean
     */
    public function hasChanged() {
        foreach($this->getRegistry()->getStore() as $obj) {
            $cgh    = $this->getRegistry()->getChangedValues($obj);
            $data   = $this->getRegistry()->getData($obj);
            if($data['state'] != Registry::STATE_FRESH || !empty($data['action']))
                return true;
        }

        return false;
    }
   
    /**
     *
     * @return boolean
     */
    public function isActive() {
        
        return isset($this->parentRefs);
    }

    public function has($object) {

        return $this->getRegistry()->contains($object);
    }

    /**
     *
     * @return array
     */
    public function get() {
        $this->fetch();
        return $this->toArray();
    }
    
    /**
     *
     * @param mixed $refs
     * @return AbstractRelation
     */
    public function setParentRefs($refs) {
        $this->parentRefs   = $refs;

        return $this;
    }

    /**
     *
     * @param mixed $object
     * @param \Fwk\Events\Dispatcher $evd
     * @return boolean true if parent has been changed/defined
     */
    public function setParent($object, \Fwk\Events\Dispatcher $evd) {
        if($this->parent === $object) {
            return false;
        }

        $this->parent   = $object;
        return true;
    }

    /**
     *
     * @return boolean
     */
    public function isFetched() {

        return $this->fetched;
    }

    /**
     *
     * @return boolean
     */
    public function isLazy() {
        return ($this->fetchMode === Relation::FETCH_LAZY);
    }

    /**
     *
     * @return boolean
     */
    public function isEager() {
        return ($this->fetchMode === Relation::FETCH_EAGER);
    }

    /**
     *
     * @return string
     */
    public function getEntityClass() {
        return $this->entity;
    }

    public function getForeign() {
        return $this->foreign;
    }

    public function getLocal() {
        return $this->local;
    }

    public function setColumnName($columnName) {
        $this->columnName   = $columnName;
    }

    public function clear() {
        foreach($this->getRegistry()->getStore() as $object) {
            $this->getRegistry()->remove($object);
        }
    }

     public function toArray() {
        $final= array();
        foreach($this->getRegistry()->getStore() as $object) {
                $data   = $this->getRegistry()->getData($object);
                if($data['action'] == 'delete')
                    continue;

                if(empty($this->reference)) {
                    $final[] = $object;
                    continue;
                }

                $ref    = $data['reference'];
                $final[$ref]  = $object;
        }

        return $final;
    }
    
    /**
     *
     * @return Registry
     */
    public function getRegistry() {
        
        return $this->registry;
    }

    public function setRegistry($registry) {
        
        $this->registry = $registry;
    }
    
    public function contains($obj) {
        return $this->getRegistry()->contains($obj);
    }
    
    public function getTableName() {
        return $this->tableName;
    }

    public function setTableName($tableName) {
        $this->tableName = $tableName;
    }
    
    public function setEntityClass($entityClass) {
        $this->entity   = $entityClass;
    }
}