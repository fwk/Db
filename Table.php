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

class Table
{
    /**
     * Table name
     *
     * @var string
     */
    protected $name;

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
    public function __construct($tableName)
    {
        $this->name     = $tableName;
    }

    /**
     * Returns all columns from this table
     *
     * @return array
     */
    public function getColumns()
    {
        return $this
                ->getConnection()
                ->getSchema()
                ->getTable($this->name)
                ->getColumns();
    }

    /**
     * Defines a connection for this table
     *
     * @param  Connection $connection
     * @return Table
     */
    public function setConnection(Connection $connection)
    {
        $this->connection   = $connection;

        return $this;
    }

    /**
     * Returns current connection for this table
     *
     * @return Connection
     */
    public function getConnection()
    {
        if (!isset($this->connection)) {
            throw new Exception(sprintf('No connection defined for table "%s"', $this->name));
        }

        return $this->connection;
    }

    /**
     * Returns a Finder instance to navigate into this table
     *
     * @return Finder
     */
    public function finder($entity = null)
    {

        $finder = new Finder($this, $this->connection);
        if(null !== $entity) {
            $finder->setEntity($entity);
        }
        
        return $finder;
    }

    /**
     * Returns this table's name
     *
     * @return string
     */
    public function getName()
    {

        return $this->name;
    }

    /**
     * Returns primary identifiers key for this table
     *
     * @throws Exceptions\TableLacksIdentifiers
     *
     * @return array
     */
    public function getIdentifiersKeys()
    {
        if (!isset($this->identifiersKey)) {
            $idx = $this
                    ->getConnection()
                    ->getSchema()
                    ->getTable($this->name)
                    ->getIndexes();
            
            if(!count($idx)) {
                throw new Exceptions\TableLacksIdentifiers(
                    sprintf('"%s" has no indexes', $this->name)
                );
            }
            
            $final = array();
            foreach($idx as $index) {
                if($index->isPrimary()) {
                    $final += $index->getColumns();
                }
            }
            
            $final = array_unique($final);
            if (!count($final)) {
                throw new Exceptions\TableLacksIdentifiers(
                    sprintf('"%s" has no identifiers key(s)', $this->name)
                );
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
    public function getRegistry()
    {
        if (!isset($this->registry)) {
            $this->registry = new Registry($this->name);
        }

        return $this->registry;
    }

    /**
     * Returns an object representation of a given column
     *
     * @param  string $columnName
     * @return \Doctrine\DBAL\Schema\Column
     */
    public function getColumn($columnName)
    {
        
        if (!$this->hasColumn($columnName)) {
            throw new Exceptions\TableColumnNotFound(
                sprintf('Unknown Column "%s" on table %s',
                    $columnName,
                    $this->name
                )
            );
        }

        return $this->getConnection()
                    ->getSchema()
                    ->getTable($this->name)
                    ->getColumn($columnName);
    }

    public function hasColumn($columnName)
    {
        $tbl = $this->getConnection()
                    ->getSchema()
                    ->getTable($this->name);
        
        return $tbl->hasColumn($columnName);
    }

    public function setDefaultEntity($entityClass)
    {
        $this->defaultEntity    = $entityClass;

        return $this;
    }

    public function getDefaultEntity()
    {
        if (!isset($this->defaultEntity)) {
            $this->defaultEntity = '\stdClass';
        }

        return $this->defaultEntity;
    }

    public function save($entity)
    {
        if(is_null($entity))

            return;

        if(!is_array($entity) && !\is_object($entity))
            throw new \InvalidArgumentException (sprintf("You can only save an object or a list of objects"));

        if(!\is_array($entity))
            $entity = array($entity);

        foreach ($entity as $object) {
            if(is_null($object))
                continue;

            $this->getRegistry()->markForAction($object, Registry::ACTION_SAVE);
        }

        $this->work();
    }

    public function delete($entity)
    {
        if(is_null($entity))

            return;

        if(!is_array($entity) && !\is_object($entity))
            throw new \InvalidArgumentException (sprintf("You can only delete an object or a list of objects"));

        if(!\is_array($entity))
            $entity = array($entity);

        foreach ($entity as $object) {
            if(is_null($object))
                continue;

            $this->getRegistry()->markForAction($object, Registry::ACTION_DELETE);
        }

        $this->work();
    }

    protected function work()
    {
        $queue          = $this->getRegistry()->getWorkersQueue();
        $connection     = $this->getConnection();

        foreach ($queue as $worker) {
           $worker->execute($connection);
        }
    }

    public function deleteAll()
    {
        $query = Query::factory()->delete($this->getName());
        $this->getConnection()->execute($query);

        return $this;
    }
}
